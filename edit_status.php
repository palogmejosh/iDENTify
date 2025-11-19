<?php
require_once 'config.php';
requireAuth();

$user = getCurrentUser();
if (!$user || $user['role'] !== 'Clinical Instructor') {
    die('Unauthorized access.');
}

$patientId = (int)($_GET['id'] ?? 0);
if (!$patientId) die('Invalid patient ID');
$message = '';

/* fetch patient */
$stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
$stmt->execute([$patientId]);
$patient = $stmt->fetch();
if (!$patient) die('Patient not found.');

/* MODIFIED: handle status change */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newStatus = $_POST['status'] ?? 'Pending';
    $ciId = $user['id']; // The ID of the currently logged-in Clinical Instructor

    // Use a transaction to ensure both database writes succeed or fail together.
    $pdo->beginTransaction();

    try {
        // Step 1: Update the patient's status in the 'patients' table.
        $stmt1 = $pdo->prepare("UPDATE patients SET status = ? WHERE id = ?");
        $stmt1->execute([$newStatus, $patientId]);

        // Step 2: If the status is 'Approved', always stamp the CI's ID.
        // This is the key change: It runs every time 'Approved' is saved,
        // allowing a "redo" that fixes records with a blank "Checked by" field.
        if ($newStatus === 'Approved') {
            $stmt2 = $pdo->prepare(
                "INSERT INTO dental_examination (patient_id, checked_by_ci) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE checked_by_ci = VALUES(checked_by_ci)"
            );
            $stmt2->execute([$patientId, $ciId]);
        }

        // If both queries succeed, commit the changes.
        $pdo->commit();
        header("Location: patients.php?status_updated=1");
        exit;

    } catch (Exception $e) {
        // If anything goes wrong, roll back the changes.
        $pdo->rollBack();
        $message = "Error updating status: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Patient Status</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="dark-mode-override.css">
</head>
<body class="bg-gray-50 min-h-screen p-8">
    <div class="max-w-md mx-auto bg-white shadow p-6 rounded">
        <h2 class="text-xl font-semibold mb-4">
            Edit Status for: <?= htmlspecialchars($patient['first_name'].' '.$patient['last_name']) ?>
        </h2>

        <?php if ($message): ?>
            <div class="bg-red-100 text-red-700 p-3 rounded mb-3"><?= $message ?></div>
        <?php endif; ?>

        <form method="POST">
            <label class="block mb-2 text-sm font-medium">Select Status</label>
            <select name="status" class="form-select w-full mb-4 p-2 border rounded">
                <option value="Pending" <?= $patient['status']==='Pending' ? 'selected' : '' ?>>Pending</option>
                <option value="Approved" <?= $patient['status']==='Approved' ? 'selected' : '' ?>>Approved</option>
                <option value="Disapproved" <?= $patient['status']==='Disapproved' ? 'selected' : '' ?>>Declined</option>
            </select>
            <div class="flex justify-end gap-2">
                <a href="patients.php" class="px-4 py-2 bg-gray-200 rounded text-sm">Cancel</a>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded text-sm">Save</button>
            </div>
        </form>
    </div>
</body>
</html>