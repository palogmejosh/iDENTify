<?php
require_once 'config.php';
requireAuth();

$user = getCurrentUser();
$role = $user['role'] ?? '';
$userId = $user['id'] ?? 0;

$patientId = (int)($_POST['patient_id'] ?? 0);
if (!$patientId) { 
    http_response_code(400); 
    exit('Missing patient ID'); 
}

// Role-based access control
if ($role === 'Clinical Instructor') {
    // Clinical Instructors can only save data for patients assigned to them
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
} elseif (!in_array($role, ['Admin', 'Clinician', 'COD'])) {
    http_response_code(403);
    exit('Access denied: Insufficient permissions.');
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ---------- 1. Helper to save Base64 Signature ---------- */
function saveBase64(string $base64Key, int $patientId): string
{
    if (empty($_POST[$base64Key])) {
        return '';
    }
    
    $raw = $_POST[$base64Key];
    if (!str_starts_with($raw, 'data:image/png;base64,')) {
        return '';
    }

    $folder = __DIR__ . '/uploads/signature';
    if (!is_dir($folder)) {
        if (!mkdir($folder, 0755, true)) {
            http_response_code(500);
            exit('Failed to create signature directory.');
        }
    }

    $bin = base64_decode(substr($raw, 22), true);
    if ($bin === false) {
        return '';
    }

    // Use the consistent, shared filename for the data privacy signature
    $filename = $patientId . '_data_privacy_signature_path.png';
    $filepath = $folder . '/' . $filename;
    file_put_contents($filepath, $bin);

    return 'uploads/signature/' . $filename;
}

/* ---------- 2. Handle Shared Signature Synchronization ---------- */
// Save the new signature if one was drawn on this form.
$newSignaturePath = saveBase64('data_privacy_signature_path_base64', $patientId);
// Decide which path to save: the new one, or the old one if no new one was drawn.
$finalSignaturePath = $newSignaturePath ?: ($_POST['old_data_privacy_signature_path'] ?? '');

// If there's a signature path, update the informed_consent table to sync it.
if (!empty($finalSignaturePath)) {
    $consentSql = "INSERT INTO informed_consent (patient_id, data_privacy_signature_path) VALUES (?, ?)
                   ON DUPLICATE KEY UPDATE data_privacy_signature_path = VALUES(data_privacy_signature_path)";
    $consentStmt = $pdo->prepare($consentSql);
    $consentStmt->execute([$patientId, $finalSignaturePath]);
}

/* ---------- 3. Process and Save Progress Notes ---------- */
$rows = json_decode($_POST['notes_json'] ?? '[]', true);
if (!is_array($rows)) { 
    http_response_code(400); 
    exit('Invalid JSON data for progress notes.'); 
}

// The printed name is now a single field for the entire form
$printedName = $_POST['patient_signature'] ?? null;

try {
    $pdo->beginTransaction();
    
    // Clear existing notes for this patient
    $pdo->prepare("DELETE FROM progress_notes WHERE patient_id = ?")->execute([$patientId]);

    // Prepare a single statement to insert all new rows
    $stmt = $pdo->prepare("INSERT INTO progress_notes
      (patient_id, date, tooth, progress, clinician, ci, remarks, patient_signature)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

    // Loop through the submitted rows and insert them into the database
    foreach ($rows as $r) {
        // Skip empty rows
        if (empty(array_filter($r))) {
            continue;
        }
        
        $stmt->execute([
            $patientId,
            $r['date'] ?: null,
            $r['tooth'] ?: null,
            $r['progress'] ?: null,
            $r['clinician'] ?: null,
            $r['ci'] ?: null,
            $r['remarks'] ?: null,
            $printedName // Use the single printed name for all rows
        ]);
    }
    
    $pdo->commit();
    echo 'OK';

} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    error_log("Save Step 5 Failed: " . $e->getMessage());
    exit('A database error occurred while saving progress notes.');
}
