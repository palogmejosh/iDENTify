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

echo "<!DOCTYPE html>\n<html>\n<head>\n<title>CI Dashboard Counts Test</title>\n";
echo "<style>body{font-family:Arial;margin:40px;} .success{color:green;} .error{color:red;} .info{color:blue;} table{border-collapse:collapse;width:100%;margin:20px 0;} th,td{border:1px solid #ddd;padding:8px;text-align:left;} th{background:#f2f2f2;}</style>\n</head>\n<body>";
echo "<h1>🧪 Clinical Instructor Dashboard Counts Test</h1>";
echo "<p><strong>Current User:</strong> " . htmlspecialchars($currentUser['full_name'] . ' (' . $role . ')') . "</p>";

if ($role === 'Clinical Instructor') {
    $ciId = $currentUser['id'];
    
    echo "<h2>📊 Assignment Status Breakdown</h2>";
    try {
        // Test 1: Get all assignments for this Clinical Instructor
        $stmt = $pdo->prepare("
            SELECT 
                pa.assignment_status,
                COUNT(*) as count
            FROM patient_assignments pa 
            WHERE pa.clinical_instructor_id = ?
            GROUP BY pa.assignment_status
        ");
        $stmt->execute([$ciId]);
        $assignmentCounts = $stmt->fetchAll();
        
        echo "<table><tr><th>Assignment Status</th><th>Count</th></tr>";
        $totalAssignments = 0;
        foreach ($assignmentCounts as $assignment) {
            $status = $assignment['assignment_status'] ?? 'NULL';
            $count = $assignment['count'];
            $totalAssignments += $count;
            echo "<tr><td>{$status}</td><td>{$count}</td></tr>";
        }
        echo "<tr style='font-weight:bold;'><td>Total Assignments</td><td>{$totalAssignments}</td></tr>";
        echo "</table>";
        
    } catch (PDOException $e) {
        echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
    }
    
    echo "<h2>📈 Patient Status Distribution (Current vs Fixed Query)</h2>";
    
    try {
        // Test 2: Compare old query (accepted only) vs new query (accepted + completed)
        echo "<table><tr><th>Query Type</th><th>Approved</th><th>Pending</th><th>Declined</th><th>Total</th></tr>";
        
        // OLD QUERY (accepted only) - This was the bug
        $oldStmt = $pdo->prepare("
            SELECT p.status, COUNT(*) as count
            FROM patients p
            INNER JOIN patient_assignments pa ON p.id = pa.patient_id
            WHERE pa.clinical_instructor_id = ? AND pa.assignment_status = 'accepted'
            GROUP BY p.status
        ");
        $oldStmt->execute([$ciId]);
        $oldCounts = $oldStmt->fetchAll();
        
        $oldApproved = 0; $oldPending = 0; $oldDisapproved = 0;
        foreach ($oldCounts as $row) {
            switch ($row['status']) {
                case 'Approved': $oldApproved = $row['count']; break;
                case 'Pending': $oldPending = $row['count']; break;
                case 'Disapproved': $oldDisapproved = $row['count']; break;
            }
        }
        $oldTotal = $oldApproved + $oldPending + $oldDisapproved;
        
        // NEW QUERY (accepted + completed) - This is the fix
        $newStmt = $pdo->prepare("
            SELECT p.status, COUNT(*) as count
            FROM patients p
            INNER JOIN patient_assignments pa ON p.id = pa.patient_id
            WHERE pa.clinical_instructor_id = ? AND pa.assignment_status IN ('accepted', 'completed')
            GROUP BY p.status
        ");
        $newStmt->execute([$ciId]);
        $newCounts = $newStmt->fetchAll();
        
        $newApproved = 0; $newPending = 0; $newDisapproved = 0;
        foreach ($newCounts as $row) {
            switch ($row['status']) {
                case 'Approved': $newApproved = $row['count']; break;
                case 'Pending': $newPending = $row['count']; break;
                case 'Disapproved': $newDisapproved = $row['count']; break;
            }
        }
        $newTotal = $newApproved + $newPending + $newDisapproved;
        
        // Display comparison
        echo "<tr><td>OLD (accepted only)</td><td>{$oldApproved}</td><td>{$oldPending}</td><td>{$oldDisapproved}</td><td>{$oldTotal}</td></tr>";
        echo "<tr style='background:#e8f5e8;'><td><strong>NEW (accepted + completed)</strong></td><td><strong>{$newApproved}</strong></td><td><strong>{$newPending}</strong></td><td><strong>{$newDisapproved}</strong></td><td><strong>{$newTotal}</strong></td></tr>";
        
        if ($newTotal > $oldTotal) {
            echo "<tr><td colspan='5' class='success'>✅ FIXED: New query shows {$newTotal} patients vs old query's {$oldTotal} patients</td></tr>";
        } elseif ($newTotal == $oldTotal) {
            echo "<tr><td colspan='5' class='info'>ℹ️ Same count: No completed assignments yet, or all patients are still 'accepted' status</td></tr>";
        } else {
            echo "<tr><td colspan='5' class='error'>❌ Unexpected: New query shows fewer patients</td></tr>";
        }
        
        echo "</table>";
        
    } catch (PDOException $e) {
        echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
    }
    
    echo "<h2>🔍 Detailed Patient List</h2>";
    try {
        $stmt = $pdo->prepare("
            SELECT 
                p.id,
                p.first_name,
                p.last_name,
                p.status as patient_status,
                pa.assignment_status,
                pa.assigned_at,
                pa.updated_at as assignment_updated
            FROM patients p
            INNER JOIN patient_assignments pa ON p.id = pa.patient_id
            WHERE pa.clinical_instructor_id = ?
            ORDER BY pa.assigned_at DESC
        ");
        $stmt->execute([$ciId]);
        $patients = $stmt->fetchAll();
        
        if (empty($patients)) {
            echo "<p class='info'>ℹ️ No patients assigned to you yet.</p>";
        } else {
            echo "<table>";
            echo "<tr><th>ID</th><th>Patient Name</th><th>Patient Status</th><th>Assignment Status</th><th>Assigned Date</th><th>Last Updated</th></tr>";
            foreach ($patients as $patient) {
                $statusColor = '';
                switch ($patient['patient_status']) {
                    case 'Approved': $statusColor = 'style="color:green;font-weight:bold;"'; break;
                    case 'Disapproved': $statusColor = 'style="color:red;font-weight:bold;"'; break;
                    case 'Pending': $statusColor = 'style="color:orange;font-weight:bold;"'; break;
                }
                
                $assignmentColor = '';
                if ($patient['assignment_status'] === 'completed') {
                    $assignmentColor = 'style="background:#e8f5e8;font-weight:bold;"';
                }
                
                echo "<tr>";
                echo "<td>{$patient['id']}</td>";
                echo "<td>{$patient['first_name']} {$patient['last_name']}</td>";
                echo "<td {$statusColor}>{$patient['patient_status']}</td>";
                echo "<td {$assignmentColor}>{$patient['assignment_status']}</td>";
                echo "<td>" . date('M d, Y', strtotime($patient['assigned_at'])) . "</td>";
                echo "<td>" . ($patient['assignment_updated'] ? date('M d, Y H:i', strtotime($patient['assignment_updated'])) : 'N/A') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
    } catch (PDOException $e) {
        echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
    }
    
} else {
    echo "<p class='info'>ℹ️ This test is designed for Clinical Instructors. As an Admin, you can view this to understand the data structure.</p>";
}

echo "<h2>🎯 Test Instructions</h2>";
echo "<ol>";
echo "<li><strong>Before Fix:</strong> Dashboard showed only patients with 'accepted' assignment status</li>";
echo "<li><strong>After Status Update:</strong> When CI updates patient status, assignment becomes 'completed'</li>";
echo "<li><strong>Problem:</strong> Dashboard query excluded 'completed' assignments, causing count to decrease</li>";
echo "<li><strong>Solution:</strong> Updated all dashboard queries to include both 'accepted' AND 'completed' assignments</li>";
echo "<li><strong>Result:</strong> Dashboard now shows all assigned patients regardless of assignment completion status</li>";
echo "</ol>";

echo "<p><a href='dashboard.php'>→ Go to Dashboard</a> | ";
echo "<a href='patients.php'>→ Patients Tab</a></p>";

echo "</body></html>";
?>