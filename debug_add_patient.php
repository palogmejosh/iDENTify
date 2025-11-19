<?php
require_once 'config.php';

// Simulate the patient addition process to debug the error
echo "<h1>Debug Patient Addition</h1>\n";

// Test database connection
echo "<h2>1. Testing database connection</h2>\n";
if ($pdo) {
    echo "✅ Database connected successfully<br>\n";
} else {
    echo "❌ Database connection failed<br>\n";
    exit;
}

// Test getCurrentUser function
echo "<h2>2. Testing getCurrentUser function</h2>\n";
session_start();
// Create a test session (if none exists)
if (!isset($_SESSION['user_id'])) {
    echo "No session found, creating test session...<br>\n";
    $_SESSION['user_id'] = 1; // Assuming user ID 1 exists
}

$user = getCurrentUser();
if ($user) {
    echo "✅ Current user: " . htmlspecialchars($user['username'] ?? 'Unknown') . " (" . htmlspecialchars($user['role'] ?? 'Unknown') . ")<br>\n";
    $role = $user['role'] ?? '';
    $userId = $user['id'] ?? null;
} else {
    echo "❌ getCurrentUser failed<br>\n";
    exit;
}

// Test the getPatients function which is causing the error
echo "<h2>3. Testing getPatients function (this is where the error likely occurs)</h2>\n";
try {
    echo "Calling getPatients with parameters: search='', statusFilter='all', dateFrom='', dateTo='', role='$role', userId='$userId'<br>\n";
    $patients = getPatients('', 'all', '', '', $role, $userId);
    echo "✅ getPatients executed successfully. Found " . count($patients) . " patients<br>\n";
    
    // Show first patient if any
    if (!empty($patients)) {
        echo "Sample patient data:<br>\n";
        echo "<pre>" . htmlspecialchars(print_r($patients[0], true)) . "</pre>\n";
    }
} catch (Exception $e) {
    echo "❌ getPatients failed with error: " . htmlspecialchars($e->getMessage()) . "<br>\n";
    echo "Error details:<br>\n";
    echo "Error code: " . $e->getCode() . "<br>\n";
    echo "File: " . $e->getFile() . "<br>\n";
    echo "Line: " . $e->getLine() . "<br>\n";
}

// Check if created_by column exists
echo "<h2>4. Testing created_by column check</h2>\n";
$hasCreatedBy = checkCreatedByColumnExists();
echo "Created by column exists: " . ($hasCreatedBy ? 'Yes' : 'No') . "<br>\n";

// Test a simple patient insertion to see if that works
echo "<h2>5. Testing direct patient insertion</h2>\n";
try {
    // Check if a test patient already exists to avoid duplicates
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM patients WHERE email = 'debug@test.com'");
    $stmt->execute();
    $exists = $stmt->fetchColumn();
    
    if ($exists == 0) {
        echo "Inserting test patient...<br>\n";
        
        // Prepare sample data
        $patientData = [
            'first_name' => 'Debug',
            'last_name' => 'Test',
            'age' => 25,
            'phone' => '555-0123',
            'email' => 'debug@test.com',
            'status' => 'Pending',
            'created_by' => $userId
        ];
        
        // Build dynamic SQL query (same as in patients.php)
        $columns = array_keys($patientData);
        $placeholders = ':' . implode(', :', $columns);
        $columnsList = implode(', ', $columns);
        
        $sql = "INSERT INTO patients ({$columnsList}) VALUES ({$placeholders})";
        echo "SQL: $sql<br>\n";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($patientData);
        
        if ($result) {
            echo "✅ Test patient inserted successfully<br>\n";
            $insertedId = $pdo->lastInsertId();
            echo "Inserted patient ID: $insertedId<br>\n";
            
            // Now test getPatients again to see if error occurs
            echo "Testing getPatients after insertion...<br>\n";
            $patients = getPatients('', 'all', '', '', $role, $userId);
            echo "✅ getPatients after insertion executed successfully. Found " . count($patients) . " patients<br>\n";
        } else {
            echo "❌ Failed to insert test patient<br>\n";
        }
    } else {
        echo "Test patient already exists, skipping insertion.<br>\n";
        
        // Just test getPatients
        echo "Testing getPatients...<br>\n";
        $patients = getPatients('', 'all', '', '', $role, $userId);
        echo "✅ getPatients executed successfully. Found " . count($patients) . " patients<br>\n";
    }
} catch (Exception $e) {
    echo "❌ Patient insertion/testing failed with error: " . htmlspecialchars($e->getMessage()) . "<br>\n";
    echo "Error details:<br>\n";
    echo "Error code: " . $e->getCode() . "<br>\n";
    echo "File: " . $e->getFile() . "<br>\n";
    echo "Line: " . $e->getLine() . "<br>\n";
}

echo "<h2>6. Database schema verification</h2>\n";
try {
    // Check users table schema
    $stmt = $pdo->query("DESCRIBE users");
    $userColumns = $stmt->fetchAll();
    echo "Users table columns:<br>\n";
    foreach ($userColumns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")<br>\n";
    }
    
    // Check if users table has 'status' column
    $hasStatusColumn = false;
    foreach ($userColumns as $column) {
        if ($column['Field'] === 'status') {
            $hasStatusColumn = true;
            break;
        }
    }
    echo "<br>Users table has 'status' column: " . ($hasStatusColumn ? 'Yes' : 'No') . "<br>\n";
    echo "Users table has 'account_status' column: " . (array_search('account_status', array_column($userColumns, 'Field')) !== false ? 'Yes' : 'No') . "<br>\n";
    
} catch (Exception $e) {
    echo "❌ Schema check failed: " . htmlspecialchars($e->getMessage()) . "<br>\n";
}

echo "<h2>Complete</h2>\n";
echo "Debug script finished. Check the results above for any issues.<br>\n";
?>