<?php
require_once 'config.php';
requireAuth();

$user = getCurrentUser();
$role = $user['role'] ?? '';
$userId = $user['id'] ?? 0;

// Only Clinical Instructors can use this endpoint
if ($role !== 'Clinical Instructor') {
    http_response_code(403);
    exit('Access denied: This page is only for Clinical Instructors.');
}

$patientId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$patientId) {
    http_response_code(400);
    exit('Invalid patient ID');
}

// Verify CI has access to this patient
$accessCheck = $pdo->prepare(
    "SELECT pa.id FROM patient_assignments pa 
     WHERE pa.patient_id = ? 
     AND pa.clinical_instructor_id = ? 
     AND pa.assignment_status IN ('accepted', 'completed')"
);
$accessCheck->execute([$patientId, $userId]);
if (!$accessCheck->fetch()) {
    http_response_code(403);
    exit('Access denied: You do not have permission to edit this patient.');
}

/* ---------- Fetch existing progress notes ---------- */
$stmt = $pdo->prepare("SELECT * FROM progress_notes WHERE patient_id = ? ORDER BY id ASC");
$stmt->execute([$patientId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Map procedure_log_id => treatment plan text from procedure_logs
$tpMap = [];
$logIds = array_filter(array_unique(array_map(function($r){ return $r['procedure_log_id'] ?? null; }, $rows)));
if (!empty($logIds)) {
    // Use a simpler approach - fetch one by one to avoid parameter binding issues
    foreach ($logIds as $logId) {
        $singleStmt = $pdo->prepare("SELECT id, procedure_selected FROM procedure_logs WHERE id = ?");
        $singleStmt->execute([$logId]);
        $logData = $singleStmt->fetch(PDO::FETCH_ASSOC);
        if ($logData) {
            $tpMap[$logData['id']] = $logData['procedure_selected'];
        }
    }
}

// Mark auto-generated notes and attach treatment plan text
foreach ($rows as &$row) {
    $row['treatment_plan'] = '';
    if (!empty($row['procedure_log_id']) && isset($tpMap[$row['procedure_log_id']])) {
        $row['treatment_plan'] = $tpMap[$row['procedure_log_id']];
    }

    if (!empty($row['auto_generated']) && (int)$row['auto_generated'] === 1) {
        $prefix = '[AUTO] ';
        if (strpos((string)$row['progress'], 'Procedure Logged:') !== false) {
            if (strpos((string)$row['progress'], '[AUTO]') === false) {
                $row['progress'] = $prefix . $row['progress'];
            }
        } else {
            $row['progress'] = $prefix . 'Procedure Logged: ' . ($row['progress'] ?? '');
        }
        $row['is_auto_generated'] = true;
    } else {
        $row['is_auto_generated'] = false;
    }
}

// Provide a default empty row if no notes exist
if (!$rows) {
    $rows = [[
        'id' => null,
        'date' => '',
        'tooth' => '',
        'progress' => '',
        'clinician' => '',
        'ci' => '',
        'remarks' => '',
        'patient_signature' => '',
        'treatment_plan' => '',
        'is_auto_generated' => false
    ]];
}

/* ---------- Fetch patient header info ---------- */
$pt = $pdo->prepare("SELECT last_name, first_name, middle_initial, age, gender, status FROM patients WHERE id = ?");
$pt->execute([$patientId]);
$patient = $pt->fetch(PDO::FETCH_ASSOC);
$patientFullName = trim(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? ''));

/* ---------- Fetch the shared signature path ---------- */
$consentStmt = $pdo->prepare("SELECT data_privacy_signature_path FROM informed_consent WHERE patient_id = ?");
$consentStmt->execute([$patientId]);
$consentData = $consentStmt->fetch(PDO::FETCH_ASSOC);
$sharedSignaturePath = $consentData['data_privacy_signature_path'] ?? null;

/* ---------- Get current user info for clinician name sync ---------- */
$currentUserName = $user['full_name'] ?? '';

// Return JSON response
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'patient' => $patient,
    'patientFullName' => $patientFullName,
    'progressNotes' => $rows,
    'sharedSignaturePath' => $sharedSignaturePath,
    'currentUserName' => $currentUserName
]);
