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
$stmt = $pdo->prepare("SELECT * FROM progress_notes WHERE patient_id = ? ORDER BY id");
$stmt->execute([$patientId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
// Provide a default empty row if no notes exist
if (!$rows) $rows = [['id'=>null,'date'=>'','tooth'=>'','progress'=>'','clinician'=>'','ci'=>'','remarks'=>'','patient_signature'=>'']];

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
