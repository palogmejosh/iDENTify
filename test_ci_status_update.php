<?php
require_once 'config.php';
requireAuth();

$currentUser = getCurrentUser();
$role = $currentUser['role'] ?? '';

// Only Clinical Instructors and Admins can run this test
if (!in_array($role, ['Clinical Instructor', 'Admin'])) {
    header('Location: dashboard.php');
    exit;
}

$testResults = [];

echo "<!DOCTYPE html>\n<html>\n<head>\n<title>Clinical Instructor Status Update Test</title>\n";
echo "<style>body{font-family:Arial;margin:40px;} .success{color:green;} .error{color:red;} .info{color:blue;}</style>\n</head>\n<body>";
echo "<h1>🧪 Clinical Instructor Status Update Test</h1>";
echo "<p><strong>Current User:</strong> " . htmlspecialchars($currentUser['full_name'] . ' (' . $role . ')') . "</p>";

// Test 1: Check if function exists
echo "<h2>Test 1: Function Availability</h2>";
if (function_exists('updatePatientStatusByClinicalInstructor')) {
    echo "<p class='success'>✅ updatePatientStatusByClinicalInstructor function exists</p>";
} else {
    echo "<p class='error'>❌ updatePatientStatusByClinicalInstructor function NOT found</p>";
}

// Test 2: Check for assigned patients
echo "<h2>Test 2: Assigned Patients Check</h2>";
try {
    $stmt = $pdo->prepare("
        SELECT p.id, p.first_name, p.last_name, p.status, pa.id as assignment_id
        FROM patients p
        INNER JOIN patient_assignments pa ON p.id = pa.patient_id
        WHERE pa.clinical_instructor_id = ? AND pa.assignment_status = 'accepted'
        LIMIT 5
    ");
    $stmt->execute([$currentUser['id']]);
    $assignedPatients = $stmt->fetchAll();
    
    if (empty($assignedPatients)) {
        echo "<p class='info'>ℹ️ No patients currently assigned to you</p>";
        echo "<p><em>Note: You need assigned patients to test status updates</em></p>";
    } else {
        echo "<p class='success'>✅ Found " . count($assignedPatients) . " assigned patients:</p>";
        echo "<ul>";
        foreach ($assignedPatients as $patient) {
            echo "<li>ID {$patient['id']}: {$patient['first_name']} {$patient['last_name']} - Status: {$patient['status']}</li>";
        }
        echo "</ul>";
    }
} catch (PDOException $e) {
    echo "<p class='error'>❌ Database error: " . $e->getMessage() . "</p>";
}

// Test 3: Test the function (only if we have patients and user is CI)
if ($role === 'Clinical Instructor' && !empty($assignedPatients)) {
    echo "<h2>Test 3: Status Update Function Test</h2>";
    
    $testPatient = $assignedPatients[0];
    $originalStatus = $testPatient['status'];
    $testStatus = ($originalStatus === 'Pending') ? 'Approved' : 'Pending';
    
    echo "<p class='info'>🔄 Testing status change for patient: {$testPatient['first_name']} {$testPatient['last_name']}</p>";
    echo "<p>Original Status: <strong>{$originalStatus}</strong></p>";
    echo "<p>Testing change to: <strong>{$testStatus}</strong></p>";
    
    // Test the update
    $result = updatePatientStatusByClinicalInstructor($testPatient['id'], $currentUser['id'], $testStatus);
    
    if ($result) {
        echo "<p class='success'>✅ Status update successful!</p>";
        
        // Verify the change
        $verifyStmt = $pdo->prepare("SELECT status FROM patients WHERE id = ?");
        $verifyStmt->execute([$testPatient['id']]);
        $newStatus = $verifyStmt->fetch()['status'];
        
        if ($newStatus === $testStatus) {
            echo "<p class='success'>✅ Verification passed: Status is now {$newStatus}</p>";
        } else {
            echo "<p class='error'>❌ Verification failed: Expected {$testStatus}, got {$newStatus}</p>";
        }
        
        // Revert back to original status
        $revertResult = updatePatientStatusByClinicalInstructor($testPatient['id'], $currentUser['id'], $originalStatus);
        if ($revertResult) {
            echo "<p class='success'>✅ Successfully reverted to original status: {$originalStatus}</p>";
        } else {
            echo "<p class='error'>❌ Failed to revert to original status</p>";
        }
        
    } else {
        echo "<p class='error'>❌ Status update failed!</p>";
    }
}

// Test 4: Check database structure
echo "<h2>Test 4: Database Structure Check</h2>";
try {
    // Check if patient_assignments table exists and has proper structure
    $stmt = $pdo->query("DESCRIBE patient_assignments");
    $columns = $stmt->fetchAll();
    $requiredColumns = ['clinical_instructor_id', 'assignment_status', 'patient_id'];
    $foundColumns = array_column($columns, 'Field');
    
    $allColumnsExist = true;
    foreach ($requiredColumns as $col) {
        if (in_array($col, $foundColumns)) {
            echo "<p class='success'>✅ Column '{$col}' exists in patient_assignments</p>";
        } else {
            echo "<p class='error'>❌ Column '{$col}' missing in patient_assignments</p>";
            $allColumnsExist = false;
        }
    }
    
    if ($allColumnsExist) {
        echo "<p class='success'>✅ All required database columns are present</p>";
    }
    
} catch (PDOException $e) {
    echo "<p class='error'>❌ Database structure error: " . $e->getMessage() . "</p>";
}

echo "<h2>🎯 Testing Instructions</h2>";
echo "<ol>";
echo "<li>Log in as a Clinical Instructor</li>";
echo "<li>Ensure you have patients assigned to you (via COD)</li>";
echo "<li>Go to the Patients tab</li>";
echo "<li>Click the edit status button (yellow icon) next to any patient</li>";
echo "<li>Change the status and submit</li>";
echo "<li>Verify the status changes in the patient list</li>";
echo "</ol>";

echo "<p><a href='patients.php'>→ Go to Patients Tab</a> | ";
echo "<a href='dashboard.php'>→ Dashboard</a></p>";

echo "</body></html>";
?>