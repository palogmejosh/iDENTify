<?php
require_once 'config.php';
requireAuth();

// Validate Patient ID
$patientId = (int)($_POST['patient_id'] ?? 0);
if (!$patientId) {
    http_response_code(400);
    exit('Missing patient ID');
}

/* 1. Create upload directory if it doesn't exist */
$folder = __DIR__ . '/uploads/signature';
if (!is_dir($folder)) {
    if (!mkdir($folder, 0755, true)) {
        http_response_code(500);
        exit('Failed to create signature directory.');
    }
}

/* 2. Helper function to convert a base-64 string to a PNG file */
function saveBase64(string $base64Key): string
{
    global $patientId, $folder;

    $raw = $_POST[$base64Key] ?? '';
    if (!str_starts_with($raw, 'data:image/png;base64,')) {
        return '';
    }

    $bin = base64_decode(substr($raw, 22), true);
    if ($bin === false) {
        return '';
    }

    $namePart = str_replace('_base64', '', $base64Key);
    $file = $folder . '/' . $patientId . '_' . $namePart . '.png';
    file_put_contents($file, $bin);

    return 'uploads/signature/' . basename($file);
}

/* 3. Save new signatures and determine final file paths */
$patientSigPath = saveBase64('patient_signature_base64') ?: ($_POST['old_patient_signature'] ?? '');
$dataPrivacySigPath = saveBase64('data_privacy_signature_path_base64') ?: ($_POST['old_data_privacy_signature_path'] ?? '');


/* 4. Prepare all form data for SQL execution */
$fields = [
    'consent_treatment', 'consent_drugs', 'consent_changes', 'consent_radiographs',
    'consent_removal_teeth', 'consent_crowns', 'consent_dentures', 'consent_fillings',
    'consent_guarantee', 'consent_date', 'clinician_signature', 'clinician_date',
    'data_privacy_signature', // This is the printed name text
    'data_privacy_patient_sign',
    'witness_signature',
    'data_privacy_date',
    'consent_endodontics',
    'consent_periodontal'
];

$params = ['patient_id' => $patientId];
foreach ($fields as $f) {
    if (str_ends_with($f, '_date') && empty($_POST[$f])) {
        $params[$f] = null;
    } else {
        $params[$f] = $_POST[$f] ?? '';
    }
}

// Add the signature file paths to the parameters array
$params['patient_signature'] = $patientSigPath;
$params['data_privacy_signature_path'] = $dataPrivacySigPath; // Path for the new signature


/* 5. Build and execute the SQL query to insert or update the record */
$updateFields = [];
foreach (array_keys($params) as $key) {
    if ($key !== 'patient_id') {
        $updateFields[] = "$key = VALUES($key)";
    }
}

// The dynamic query will now include the new `data_privacy_signature_path` field
$sql = "INSERT INTO informed_consent (" . implode(', ', array_keys($params)) . ")
        VALUES (:" . implode(', :', array_keys($params)) . ")
        ON DUPLICATE KEY UPDATE " . implode(', ', $updateFields);

try {
    $pdo->prepare($sql)->execute($params);
    echo 'OK';
} catch (PDOException $e) {
    http_response_code(500);
    error_log('Informed consent save failed: ' . $e->getMessage());
    exit('Database error occurred while saving.');
}
