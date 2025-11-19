<?php
require_once 'config.php';
requireAuth();

$patientId = (int)($_POST['patient_id'] ?? 0);
if (!$patientId) {
    http_response_code(400);
    exit('Patient ID is required.');
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

/* ---------- 3. Handle Tooth Chart Photo and Drawing (existing logic) ---------- */
// This part remains the same as your original file
$photoBlob = null;
if (isset($_FILES['tooth_chart_photo']) && $_FILES['tooth_chart_photo']['error'] === UPLOAD_ERR_OK) {
    $photoBlob = file_get_contents($_FILES['tooth_chart_photo']['tmp_name']);
}

$drawingPath = null;
if (!empty($_POST['tooth_chart_drawing'])) {
    $base64 = $_POST['tooth_chart_drawing'];
    if (preg_match('/^data:image\/png;base64,(.+)$/', $base64, $m)) {
        $binary = base64_decode($m[1]);
        if ($binary !== false) {
            $dir = __DIR__ . '/uploads/tooth_chart';
            if (!is_dir($dir)) mkdir($dir, 0777, true);

            $fileName = $patientId . '_drawing.png';
            $filePath = $dir . '/' . $fileName;
            file_put_contents($filePath, $binary);
            $drawingPath = 'uploads/tooth_chart/' . $fileName;
        }
    }
}

/* ---------- 4. Collect all params for dental_examination table ---------- */
$ciId = (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Clinical Instructor') ? (int)$_SESSION['user_id'] : null;

$params = [
  'date_examined'               => $_POST['date_examined']          ?? null,
  'clinician'                   => $_POST['clinician']              ?? null,
  'diagnostic_tests'            => $_POST['diagnostic_tests']       ?? null,
  'other_notes'                 => $_POST['other_notes']            ?? null,
  // Keep the old assessment_plan_json for backward compatibility, but it won't be used
  'assessment_plan_json'        => $_POST['assessment_plan_json']   ?? null,
  'patient_signature'           => $_POST['patient_signature']      ?? null, // This is the printed name
  'history_performed_by'        => $_POST['history_performed_by']   ?? null,
  'history_performed_date'      => $_POST['history_performed_date'] ?? null,
  'history_checked_by'          => $_POST['history_checked_by']     ?? null,
  'history_checked_date'        => $_POST['history_checked_date']   ?? null,
  'checked_by_ci'               => $ciId
];

// Conditionally add media fields to avoid overwriting with NULLs
if ($photoBlob) {
    $params['tooth_chart_photo'] = $photoBlob;
}
if ($drawingPath) {
    $params['tooth_chart_drawing_path'] = $drawingPath;
}

/* ---------- 6. UPDATE dental_examination table ---------- */
$setParts = [];
foreach (array_keys($params) as $key) {
    $setParts[] = "$key = :$key";
}
$sql = "UPDATE dental_examination SET " . implode(', ', $setParts) . " WHERE patient_id = :patient_id";
$stmt = $pdo->prepare($sql);
$params['patient_id'] = $patientId; // Add patient_id for the WHERE clause
$stmt->execute($params);

// Update patient status if a CI is checking the record
if ($ciId) {
    $pdo->prepare("UPDATE patients SET status = 'Approved' WHERE id = ?")->execute([$patientId]);
}

echo 'OK';
