<?php
require_once 'config.php';
requireAuth();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$user = getCurrentUser();
$role = isset($user['role']) ? $user['role'] : '';
$userId = isset($user['id']) ? $user['id'] : 0;

$patientId = isset($_POST['patient_id']) ? (int)$_POST['patient_id'] : 0;
if (!$patientId) { 
    http_response_code(400); 
    exit('Missing patient ID'); 
}

// Role-based access control
if ($role === 'Clinical Instructor') {
    $accessCheck = $pdo->prepare(
        "SELECT pa.id FROM patient_assignments pa 
         WHERE pa.patient_id = ? 
         AND pa.clinical_instructor_id = ? 
         AND pa.assignment_status IN ('accepted', 'completed')"
    );
    $accessCheck->execute(array($patientId, $userId));
    if (!$accessCheck->fetch()) {
        http_response_code(403);
        exit('Access denied: You do not have permission to edit this patient.');
    }
} elseif (!in_array($role, array('Admin', 'Clinician', 'COD'))) {
    http_response_code(403);
    exit('Access denied: Insufficient permissions.');
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ---------- 1. Helper to save Base64 Signature ---------- */
function saveBase64($base64Key, $patientId)
{
    if (empty($_POST[$base64Key])) {
        return '';
    }
    
    $raw = $_POST[$base64Key];
    // Check if string starts with 'data:image/png;base64,'
    if (strpos($raw, 'data:image/png;base64,') !== 0) {
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

    $filename = $patientId . '_data_privacy_signature_path.png';
    $filepath = $folder . '/' . $filename;
    file_put_contents($filepath, $bin);

    return 'uploads/signature/' . $filename;
}

/* ---------- 2. Handle Shared Signature Synchronization ---------- */
$newSignaturePath = saveBase64('data_privacy_signature_path_base64', $patientId);
$finalSignaturePath = $newSignaturePath;
if (empty($finalSignaturePath)) {
    $finalSignaturePath = isset($_POST['old_data_privacy_signature_path']) ? $_POST['old_data_privacy_signature_path'] : '';
}

if (!empty($finalSignaturePath)) {
    $consentSql = "INSERT INTO informed_consent (patient_id, data_privacy_signature_path) VALUES (?, ?)
                   ON DUPLICATE KEY UPDATE data_privacy_signature_path = VALUES(data_privacy_signature_path)";
    $consentStmt = $pdo->prepare($consentSql);
    $consentStmt->execute(array($patientId, $finalSignaturePath));
}

/* ---------- 3. Process and Save Progress Notes ---------- */
$notesJson = isset($_POST['notes_json']) ? $_POST['notes_json'] : '[]';
error_log("Processing progress notes JSON: " . $notesJson);

$rows = json_decode($notesJson, true);
if (!is_array($rows)) { 
    http_response_code(400); 
    error_log("Invalid JSON data for progress notes: " . $notesJson);
    exit('Invalid JSON data for progress notes.'); 
}

$printedName = isset($_POST['patient_signature']) ? $_POST['patient_signature'] : null;
error_log("Processing " . count($rows) . " progress note rows for patient " . $patientId);

try {
    $pdo->beginTransaction();
    
    // Get existing row IDs from database
    $existingStmt = $pdo->prepare("SELECT id FROM progress_notes WHERE patient_id = ?");
    $existingStmt->execute(array($patientId));
    $existingRows = $existingStmt->fetchAll(PDO::FETCH_ASSOC);
    $existingIds = array();
    foreach ($existingRows as $row) {
        $existingIds[] = $row['id'];
    }
    
    // Track which IDs are being updated
    $processedIds = array();
    
    // Process each row
    foreach ($rows as $index => $r) {
        $rowId = !empty($r['id']) ? (int)$r['id'] : null;
        error_log("Processing row " . ($index + 1) . ": ID=" . ($rowId ?: 'new') . ", data=" . json_encode($r));
        
        // Skip completely empty rows
        if (empty($r['date']) && empty($r['tooth']) && empty($r['progress']) && 
            empty($r['clinician']) && empty($r['ci']) && empty($r['remarks'])) {
            error_log("Skipping empty row " . ($index + 1));
            continue;
        }
        
        if ($rowId && in_array($rowId, $existingIds)) {
            // Update existing row
            error_log("Updating existing row with ID " . $rowId);
            $updateStmt = $pdo->prepare("
                UPDATE progress_notes SET
                date = ?,
                tooth = ?,
                progress = ?,
                clinician = ?,
                ci = ?,
                remarks = ?,
                patient_signature = ?
                WHERE id = ? AND patient_id = ?
            ");
            
            $updateStmt->execute(array(
                !empty($r['date']) ? $r['date'] : null,
                !empty($r['tooth']) ? $r['tooth'] : null,
                !empty($r['progress']) ? $r['progress'] : null,
                !empty($r['clinician']) ? $r['clinician'] : null,
                !empty($r['ci']) ? $r['ci'] : null,
                !empty($r['remarks']) ? $r['remarks'] : null,
                $printedName,
                $rowId,
                $patientId
            ));
            
            $processedIds[] = $rowId;
        } else {
            // Insert new row
            error_log("Inserting new row for patient " . $patientId);
            $insertStmt = $pdo->prepare("
                INSERT INTO progress_notes
                (patient_id, date, tooth, progress, clinician, ci, remarks, patient_signature, auto_generated, procedure_log_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, NULL)
            ");
            
            $insertStmt->execute(array(
                $patientId,
                !empty($r['date']) ? $r['date'] : null,
                !empty($r['tooth']) ? $r['tooth'] : null,
                !empty($r['progress']) ? $r['progress'] : null,
                !empty($r['clinician']) ? $r['clinician'] : null,
                !empty($r['ci']) ? $r['ci'] : null,
                !empty($r['remarks']) ? $r['remarks'] : null,
                $printedName
            ));
        }
    }
    
    $pdo->commit();
    echo 'OK';

} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    error_log("Save Step 5 Failed: " . $e->getMessage());
    exit('Database error: ' . $e->getMessage());
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    error_log("Save Step 5 Failed: " . $e->getMessage());
    exit('Error: ' . $e->getMessage());
}