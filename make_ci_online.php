<?php
require_once 'config.php';
requireAuth();

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Get the current user
$user = getCurrentUser();
$userId = $user['id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Set as online
    $stmt = $pdo->prepare("
        UPDATE users 
        SET connection_status = 'online', 
            last_activity = NOW() 
        WHERE role = 'Clinical Instructor' AND account_status = 'active'
    ");
    $result = $stmt->execute();
    
    if ($result) {
        echo "<p style='color: green;'>Successfully set all Clinical Instructors as online!</p>";
    } else {
        echo "<p style='color: red;'>Failed to update Clinical Instructors.</p>";
    }
}

// Check current status of Clinical Instructors
$stmt = $pdo->query("
    SELECT id, full_name, connection_status, last_activity, 
           TIMESTAMPDIFF(MINUTE, last_activity, NOW()) as minutes_since_activity
    FROM users 
    WHERE role = 'Clinical Instructor' AND account_status = 'active'
");

echo "<h2>Clinical Instructor Status</h2>";
echo "<table border='1' cellspacing='0' cellpadding='4'>";
echo "<tr><th>ID</th><th>Name</th><th>Status</th><th>Last Activity</th><th>Minutes Since</th></tr>";

while ($row = $stmt->fetch()) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . htmlspecialchars($row['full_name']) . "</td>";
    echo "<td>" . htmlspecialchars($row['connection_status']) . "</td>";
    echo "<td>" . $row['last_activity'] . "</td>";
    echo "<td>" . $row['minutes_since_activity'] . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<form method='post'>";
echo "<button type='submit'>Set All Clinical Instructors as Online</button>";
echo "</form>";

echo "<p><a href='test_log_procedure.php'>Test Procedure Logging</a></p>";
?>
<!DOCTYPE html>
<html>
<head>
    <title>Clinical Instructor Status</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; }
        th { background-color: #f2f2f2; }
        button { padding: 5px 15px; }
    </style>
</head>
<body>