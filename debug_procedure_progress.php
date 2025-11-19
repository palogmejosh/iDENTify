<?php
require_once 'config.php';
requireAuth();

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$patientId = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

header('Content-Type: text/html; charset=utf-8');
echo '<h2>Debug: Procedure Logs -> Auto Progress Notes</h2>';

echo '<h3>Latest procedure_logs</h3>';
$stmt = $pdo->query("SELECT id, patient_id, procedure_selected, clinician_name, remarks, logged_at FROM procedure_logs ORDER BY id DESC LIMIT 20");
echo '<table border="1" cellspacing="0" cellpadding="4">';
echo '<tr><th>ID</th><th>Patient</th><th>Procedure</th><th>Clinician</th><th>Remarks</th><th>Logged At</th></tr>';
foreach ($stmt as $row) {
  echo '<tr>'; 
  echo '<td>' . (int)$row['id'] . '</td>'; 
  echo '<td>' . (int)$row['patient_id'] . '</td>'; 
  echo '<td>' . htmlspecialchars($row['procedure_selected']) . '</td>'; 
  echo '<td>' . htmlspecialchars($row['clinician_name']) . '</td>'; 
  echo '<td>' . htmlspecialchars($row['remarks']) . '</td>'; 
  echo '<td>' . htmlspecialchars($row['logged_at']) . '</td>'; 
  echo '</tr>';
}
echo '</table>';

if ($patientId) {
  echo '<h3>Progress notes for patient ' . $patientId . '</h3>';
  $st = $pdo->prepare("SELECT id, date, tooth, progress, clinician, ci, remarks, auto_generated, procedure_log_id FROM progress_notes WHERE patient_id = ? ORDER BY id DESC");
  $st->execute([$patientId]);
  echo '<table border="1" cellspacing="0" cellpadding="4">';
  echo '<tr><th>ID</th><th>Date</th><th>Tooth</th><th>Progress</th><th>Clinician</th><th>CI</th><th>Remarks</th><th>AUTO</th><th>Log ID</th></tr>';
  foreach ($st as $r) {
    echo '<tr>';
    echo '<td>' . (int)$r['id'] . '</td>';
    echo '<td>' . htmlspecialchars($r['date']) . '</td>';
    echo '<td>' . htmlspecialchars($r['tooth']) . '</td>';
    echo '<td>' . htmlspecialchars($r['progress']) . '</td>';
    echo '<td>' . htmlspecialchars($r['clinician']) . '</td>';
    echo '<td>' . htmlspecialchars($r['ci']) . '</td>';
    echo '<td>' . htmlspecialchars($r['remarks']) . '</td>';
    echo '<td>' . ((int)$r['auto_generated'] ? '1' : '0') . '</td>';
    echo '<td>' . htmlspecialchars($r['procedure_log_id']) . '</td>';
    echo '</tr>';
  }
  echo '</table>';
}

// Check assignment for last log
$st2 = $pdo->query("SELECT pl.id, pa.clinical_instructor_id, u.full_name AS ci_name
                    FROM procedure_logs pl
                    LEFT JOIN procedure_assignments pa ON pa.procedure_log_id = pl.id
                    LEFT JOIN users u ON u.id = pa.clinical_instructor_id
                    ORDER BY pl.id DESC LIMIT 10");
echo '<h3>Assignments</h3>';
echo '<table border="1" cellspacing="0" cellpadding="4">';
echo '<tr><th>Log ID</th><th>CI ID</th><th>CI Name</th></tr>';
foreach ($st2 as $r2) {
  echo '<tr><td>' . (int)$r2['id'] . '</td><td>' . htmlspecialchars($r2['clinical_instructor_id']) . '</td><td>' . htmlspecialchars($r2['ci_name']) . '</td></tr>';
}
echo '</table>';
?>