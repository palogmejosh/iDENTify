<?php
require_once 'config.php';
requireAuth();

// Test the getAssignedClinicalInstructor function
echo "<h2>Testing getAssignedClinicalInstructor Function</h2>";

// Get some test data
$stmt = $pdo->prepare("
    SELECT p.id, p.first_name, p.last_name, 
           pa.assignment_status,
           u_ci.full_name as assigned_ci_name
    FROM patients p 
    LEFT JOIN patient_assignments pa ON p.id = pa.patient_id
    LEFT JOIN users u_ci ON pa.clinical_instructor_id = u_ci.id
    WHERE pa.assignment_status IN ('accepted', 'completed')
    ORDER BY p.id 
    LIMIT 5
");
$stmt->execute();
$testPatients = $stmt->fetchAll();

if (empty($testPatients)) {
    echo "<p style='color: red;'>No patients with assigned Clinical Instructors found for testing.</p>";
    echo "<p>Make sure you have:</p>";
    echo "<ul>";
    echo "<li>Patients created by Clinicians</li>";
    echo "<li>Patients assigned to Clinical Instructors by COD</li>";
    echo "<li>Assignment status is 'accepted' or 'completed'</li>";
    echo "</ul>";
} else {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>Patient ID</th>";
    echo "<th>Patient Name</th>";
    echo "<th>Assignment Status</th>";
    echo "<th>Direct DB Query (Expected)</th>";
    echo "<th>getAssignedClinicalInstructor() (Actual)</th>";
    echo "<th>Match?</th>";
    echo "</tr>";

    foreach ($testPatients as $patient) {
        $expectedCI = $patient['assigned_ci_name'] ?? 'None';
        $actualCI = getAssignedClinicalInstructor($patient['id']) ?? 'None';
        $match = ($expectedCI === $actualCI) ? '✅ Yes' : '❌ No';
        $matchStyle = ($expectedCI === $actualCI) ? 'color: green;' : 'color: red;';
        
        echo "<tr>";
        echo "<td>" . htmlspecialchars($patient['id']) . "</td>";
        echo "<td>" . htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) . "</td>";
        echo "<td>" . htmlspecialchars($patient['assignment_status']) . "</td>";
        echo "<td>" . htmlspecialchars($expectedCI) . "</td>";
        echo "<td>" . htmlspecialchars($actualCI) . "</td>";
        echo "<td style='{$matchStyle}'><strong>{$match}</strong></td>";
        echo "</tr>";
    }
    echo "</table>";

    // Test with a non-existent patient
    echo "<h3>Test with Non-existent Patient (ID: 99999)</h3>";
    $nonExistentResult = getAssignedClinicalInstructor(99999);
    echo "<p>Result: " . ($nonExistentResult === null ? "null (correct)" : htmlspecialchars($nonExistentResult)) . "</p>";
}

echo "<hr>";
echo "<h3>Database Structure Check</h3>";

// Check if patient_assignments table exists and has the expected structure
try {
    $stmt = $pdo->query("DESCRIBE patient_assignments");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<p><strong>patient_assignments table columns:</strong></p>";
    echo "<ul>";
    foreach ($columns as $column) {
        echo "<li>" . htmlspecialchars($column) . "</li>";
    }
    echo "</ul>";
    
    $requiredColumns = ['patient_id', 'clinical_instructor_id', 'assignment_status', 'assigned_at'];
    $missingColumns = array_diff($requiredColumns, $columns);
    
    if (empty($missingColumns)) {
        echo "<p style='color: green;'>✅ All required columns are present.</p>";
    } else {
        echo "<p style='color: red;'>❌ Missing columns: " . implode(', ', $missingColumns) . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error accessing patient_assignments table: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<p><a href='patients.php'>← Back to Patients</a></p>";
?>