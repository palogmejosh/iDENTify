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

echo "<!DOCTYPE html>\n<html>\n<head>\n<title>CI Multiple Status Updates Test</title>\n";
echo "<style>body{font-family:Arial;margin:40px;} .success{color:green;} .error{color:red;} .info{color:blue;} .warning{color:orange;} table{border-collapse:collapse;width:100%;margin:20px 0;} th,td{border:1px solid #ddd;padding:8px;text-align:left;} th{background:#f2f2f2;} .test-step{background:#f9f9f9;padding:15px;margin:10px 0;border-left:4px solid #2196F3;}</style>\n</head>\n<body>";
echo "<h1>🧪 Clinical Instructor Multiple Status Updates Test</h1>";
echo "<p><strong>Current User:</strong> " . htmlspecialchars($currentUser['full_name'] . ' (' . $role . ')') . "</p>";

if ($role === 'Clinical Instructor') {
    $ciId = $currentUser['id'];
    
    echo "<h2>🎯 Test: Multiple Status Updates on Same Patient</h2>";
    echo "<p class='info'>This test verifies that Clinical Instructors can update patient status multiple times, even after marking as 'Approved' or 'Declined'.</p>";
    
    try {
        // Get a patient assigned to this Clinical Instructor
        $stmt = $pdo->prepare("
            SELECT 
                p.id,
                p.first_name,
                p.last_name,
                p.status as current_status,
                pa.assignment_status,
                pa.id as assignment_id
            FROM patients p
            INNER JOIN patient_assignments pa ON p.id = pa.patient_id
            WHERE pa.clinical_instructor_id = ? 
            AND pa.assignment_status IN ('accepted', 'completed')
            ORDER BY pa.assigned_at DESC
            LIMIT 1
        ");
        $stmt->execute([$ciId]);
        $testPatient = $stmt->fetch();
        
        if (!$testPatient) {
            echo "<p class='warning'>⚠️ No patients assigned to you. Please ask COD to assign patients first.</p>";
            echo "<p><a href='dashboard.php'>→ Go to Dashboard</a></p>";
            echo "</body></html>";
            exit;
        }
        
        $patientId = $testPatient['id'];
        $patientName = $testPatient['first_name'] . ' ' . $testPatient['last_name'];
        $originalStatus = $testPatient['current_status'];
        $originalAssignmentStatus = $testPatient['assignment_status'];
        
        echo "<div class='test-step'>";
        echo "<h3>📋 Test Patient Information</h3>";
        echo "<p><strong>Patient:</strong> {$patientName} (ID: {$patientId})</p>";
        echo "<p><strong>Original Status:</strong> {$originalStatus}</p>";
        echo "<p><strong>Original Assignment Status:</strong> {$originalAssignmentStatus}</p>";
        echo "</div>";
        
        // Test sequence: Original → Approved → Pending → Disapproved → Back to Original
        $testSequence = [];
        if ($originalStatus === 'Pending') {
            $testSequence = ['Approved', 'Pending', 'Disapproved', 'Pending'];
        } elseif ($originalStatus === 'Approved') {
            $testSequence = ['Pending', 'Disapproved', 'Approved'];
        } else { // Disapproved
            $testSequence = ['Pending', 'Approved', 'Disapproved'];
        }
        
        echo "<div class='test-step'>";
        echo "<h3>🔄 Test Sequence: " . implode(' → ', $testSequence) . "</h3>";
        echo "</div>";
        
        $stepNumber = 1;
        $allTestsPassed = true;
        
        foreach ($testSequence as $targetStatus) {
            echo "<div class='test-step'>";
            echo "<h4>Step {$stepNumber}: Update to '{$targetStatus}'</h4>";
            
            // Attempt the update
            $result = updatePatientStatusByClinicalInstructor($patientId, $ciId, $targetStatus);
            
            if ($result) {
                echo "<p class='success'>✅ Update function returned success</p>";
                
                // Verify the update in database
                $verifyStmt = $pdo->prepare("
                    SELECT 
                        p.status, 
                        pa.assignment_status,
                        pa.updated_at
                    FROM patients p
                    INNER JOIN patient_assignments pa ON p.id = pa.patient_id
                    WHERE p.id = ? AND pa.clinical_instructor_id = ?
                ");
                $verifyStmt->execute([$patientId, $ciId]);
                $verification = $verifyStmt->fetch();
                
                if ($verification && $verification['status'] === $targetStatus) {
                    echo "<p class='success'>✅ Database verification passed: Status is '{$verification['status']}'</p>";
                    echo "<p class='info'>ℹ️ Assignment Status: {$verification['assignment_status']}</p>";
                    echo "<p class='info'>ℹ️ Last Updated: " . date('Y-m-d H:i:s', strtotime($verification['updated_at'])) . "</p>";
                } else {
                    echo "<p class='error'>❌ Database verification failed</p>";
                    echo "<p class='error'>Expected: '{$targetStatus}', Got: '{$verification['status']}'</p>";
                    $allTestsPassed = false;
                }
                
            } else {
                echo "<p class='error'>❌ Update function failed</p>";
                $allTestsPassed = false;
            }
            
            echo "</div>";
            $stepNumber++;
            
            // Small delay between updates
            usleep(100000); // 0.1 second
        }
        
        // Final verification
        echo "<div class='test-step'>";
        echo "<h3>📊 Final Test Results</h3>";
        if ($allTestsPassed) {
            echo "<p class='success'>🎉 ALL TESTS PASSED! Clinical Instructors can now update patient status multiple times.</p>";
        } else {
            echo "<p class='error'>❌ Some tests failed. Please check the error messages above.</p>";
        }
        echo "</div>";
        
        // Show current assignment status breakdown
        echo "<h2>📈 Current Assignment Status Breakdown</h2>";
        $statusStmt = $pdo->prepare("
            SELECT 
                pa.assignment_status,
                COUNT(*) as count,
                GROUP_CONCAT(CONCAT(p.first_name, ' ', p.last_name, ' (', p.status, ')') SEPARATOR ', ') as patients
            FROM patient_assignments pa
            INNER JOIN patients p ON pa.patient_id = p.id
            WHERE pa.clinical_instructor_id = ?
            GROUP BY pa.assignment_status
        ");
        $statusStmt->execute([$ciId]);
        $statusBreakdown = $statusStmt->fetchAll();
        
        echo "<table>";
        echo "<tr><th>Assignment Status</th><th>Count</th><th>Patients</th></tr>";
        foreach ($statusBreakdown as $status) {
            echo "<tr>";
            echo "<td><strong>{$status['assignment_status']}</strong></td>";
            echo "<td>{$status['count']}</td>";
            echo "<td style='font-size:0.9em;'>{$status['patients']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
    } catch (Exception $e) {
        echo "<p class='error'>❌ Test Error: " . $e->getMessage() . "</p>";
    }
    
} else {
    echo "<p class='info'>ℹ️ This test is designed for Clinical Instructors. As an Admin, you can view the data structure.</p>";
    
    // Show all Clinical Instructors and their patients for admin view
    try {
        $stmt = $pdo->query("
            SELECT 
                u.full_name as ci_name,
                COUNT(pa.id) as total_assignments,
                SUM(CASE WHEN pa.assignment_status = 'accepted' THEN 1 ELSE 0 END) as accepted,
                SUM(CASE WHEN pa.assignment_status = 'completed' THEN 1 ELSE 0 END) as completed
            FROM users u
            LEFT JOIN patient_assignments pa ON u.id = pa.clinical_instructor_id
            WHERE u.role = 'Clinical Instructor'
            GROUP BY u.id, u.full_name
            ORDER BY u.full_name
        ");
        $ciStats = $stmt->fetchAll();
        
        echo "<h2>👩‍⚕️ Clinical Instructors Overview</h2>";
        echo "<table>";
        echo "<tr><th>Clinical Instructor</th><th>Total Assignments</th><th>Accepted</th><th>Completed</th></tr>";
        foreach ($ciStats as $ci) {
            echo "<tr>";
            echo "<td>{$ci['ci_name']}</td>";
            echo "<td>{$ci['total_assignments']}</td>";
            echo "<td>{$ci['accepted']}</td>";
            echo "<td style='background:#e8f5e8;font-weight:bold;'>{$ci['completed']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
    } catch (Exception $e) {
        echo "<p class='error'>Error: " . $e->getMessage() . "</p>";
    }
}

echo "<h2>🔧 What Was Fixed</h2>";
echo "<div class='test-step'>";
echo "<h3>Problem:</h3>";
echo "<p>Clinical Instructors could not update patient status after it was changed to 'Approved' or 'Disapproved' because:</p>";
echo "<ul>";
echo "<li>When status becomes 'Approved'/'Disapproved', assignment status changes to 'completed'</li>";
echo "<li>The update function only allowed updates on 'accepted' assignments</li>";
echo "<li>This blocked any further status changes</li>";
echo "</ul>";

echo "<h3>Solution:</h3>";
echo "<ul>";
echo "<li>✅ Modified authorization check to include both 'accepted' AND 'completed' assignments</li>";
echo "<li>✅ Added logic to revert assignment status back to 'accepted' when changing to 'Pending'</li>";
echo "<li>✅ Maintains proper assignment status tracking throughout all updates</li>";
echo "</ul>";
echo "</div>";

echo "<h2>🎯 Testing Instructions</h2>";
echo "<ol>";
echo "<li>Login as Clinical Instructor</li>";
echo "<li>Go to Patients tab</li>";
echo "<li>Update a patient status from Pending → Approved</li>";
echo "<li>Try updating the same patient from Approved → Pending (should now work!)</li>";
echo "<li>Try updating again from Pending → Disapproved (should work!)</li>";
echo "<li>Continue testing different status changes</li>";
echo "</ol>";

echo "<p><a href='patients.php'>→ Go to Patients Tab</a> | ";
echo "<a href='dashboard.php'>→ Dashboard</a></p>";

echo "</body></html>";
?>