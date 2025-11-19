<?php
require_once 'config.php';
requireAuth();

$user = getCurrentUser();
$role = $user['role'] ?? '';
if (!in_array($role, ['Admin', 'Clinician'])) {
    http_response_code(403);
    exit('Forbidden');
}

$patientId = $_POST['patient_id'] ?? null;
if (!$patientId) {
    http_response_code(400);
    exit('Patient ID missing');
}
$patientId = (int)$patientId;

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


/* ---------- 3. Build the data array for the patient_health table ---------- */
$cb = fn($k) => isset($_POST[$k]) ? 1 : 0;
$data = [
    'patient_id'                   => $patientId,
    'last_medical_physical'        => $_POST['last_medical_physical'] ?? '',
    'physician_name_addr'          => $_POST['physician_name_addr'] ?? '',
    'under_physician_care_note'    => $_POST['under_physician_care_note'] ?? '',
    'serious_illness_operation_note' => $_POST['serious_illness_operation_note'] ?? '',
    'hospitalized_note'            => $_POST['hospitalized_note'] ?? '',
    'childhood_diseases_note'      => $_POST['childhood_diseases_note'] ?? '',
    'taking_drugs'                 => $_POST['taking_drugs'] ?? '',
    'previous_dental_trouble'      => $_POST['previous_dental_trouble'] ?? '',
    'other_problem'                => $_POST['other_problem'] ?? '',
    'xray_exposure'                => $_POST['xray_exposure'] ?? '',
    'eyeglasses'                   => $_POST['eyeglasses'] ?? '',
    'under_physician_care'         => $cb('under_physician_care'),
    'serious_illness_operation'    => $cb('serious_illness_operation'),
    'hospitalized'                 => $cb('hospitalized'),
    'rheumatic_fever'              => $cb('rheumatic_fever'),
    'cardiovascular_disease'       => $cb('cardiovascular_disease'),
    'asthma_hayfever'              => $cb('asthma_hayfever'),
    'fainting_seizures'            => $cb('fainting_seizures'),
    'urinate_more'                 => $cb('urinate_more'),
    'mouth_dry'                    => $cb('mouth_dry'),
    'arthritis'                    => $cb('arthritis'),
    'kidney_trouble'               => $cb('kidney_trouble'),
    'venereal_disease'             => $cb('venereal_disease'),
    'anesthetic_allergy'           => $cb('anesthetic_allergy'),
    'penicillin_allergy'           => $cb('penicillin_allergy'),
    'aspirin_allergy'              => $cb('aspirin_allergy'),
    'latex_allergy'                => $cb('latex_allergy'),
    'other_allergy'                => $cb('other_allergy'),
    'pregnant'                     => $cb('pregnant'),
    'breast_feeding'               => $cb('breast_feeding'),
    'last_medical_physical_yes'    => $cb('last_medical_physical_yes'),
    'last_medical_physical_no'     => $cb('last_medical_physical_no'),
    'abnormal_bleeding_yes'        => $cb('abnormal_bleeding_yes'),
    'abnormal_bleeding_no'         => $cb('abnormal_bleeding_no'),
    'bruise_easily_yes'            => $cb('bruise_easily_yes'),
    'bruise_easily_no'             => $cb('bruise_easily_no'),
    'blood_transfusion_yes'        => $cb('blood_transfusion_yes'),
    'blood_transfusion_no'         => $cb('blood_transfusion_no'),
    'blood_disorder_yes'           => $cb('blood_disorder_yes'),
    'blood_disorder_no'            => $cb('blood_disorder_no'),
    'head_neck_radiation_yes'      => $cb('head_neck_radiation_yes'),
    'head_neck_radiation_no'       => $cb('head_neck_radiation_no'),
    'heart_abnormalities'          => $cb('heart_abnormalities'),
    'childhood_diseases'           => $cb('childhood_diseases'),
    'hives_skin_rash'              => $cb('hives_skin_rash'),
    'diabetes'                     => $cb('diabetes'),
    'thirsty'                      => $cb('thirsty'),
    'hepatitis'                    => $cb('hepatitis'),
    'stomach_ulcers'               => $cb('stomach_ulcers'),
    'tuberculosis'                 => $cb('tuberculosis'),
    'other_conditions'             => $cb('other_conditions'),
    'general_health_notes'         => $_POST['general_health_notes'] ?? '',
    'physical'                     => $_POST['physical'] ?? '',
    'mental'                       => $_POST['mental'] ?? '',
    'vital_signs'                  => $_POST['vital_signs'] ?? '',
    'extra_head_face'              => $_POST['extra_head_face'] ?? '',
    'extra_eyes'                   => $_POST['extra_eyes'] ?? '',
    'extra_ears'                   => $_POST['extra_ears'] ?? '',
    'extra_nose'                   => $_POST['extra_nose'] ?? '',
    'extra_hair'                   => $_POST['extra_hair'] ?? '',
    'extra_neck'                   => $_POST['extra_neck'] ?? '',
    'extra_paranasal'              => $_POST['extra_paranasal'] ?? '',
    'extra_lymph'                  => $_POST['extra_lymph'] ?? '',
    'extra_salivary'               => $_POST['extra_salivary'] ?? '',
    'extra_tmj'                    => $_POST['extra_tmj'] ?? '',
    'extra_muscles'                => $_POST['extra_muscles'] ?? '',
    'extra_other'                  => $_POST['extra_other'] ?? '',
    'intra_lips'                   => $_POST['intra_lips'] ?? '',
    'intra_buccal'                 => $_POST['intra_buccal'] ?? '',
    'intra_alveolar'               => $_POST['intra_alveolar'] ?? '',
    'intra_floor'                  => $_POST['intra_floor'] ?? '',
    'intra_tongue'                 => $_POST['intra_tongue'] ?? '',
    'intra_saliva'                 => $_POST['intra_saliva'] ?? '',
    'intra_pillars'                => $_POST['intra_pillars'] ?? '',
    'intra_tonsils'                => $_POST['intra_tonsils'] ?? '',
    'intra_uvula'                  => $_POST['intra_uvula'] ?? '',
    'intra_oropharynx'             => $_POST['intra_oropharynx'] ?? '',
    'intra_other'                  => $_POST['intra_other'] ?? '',
    // New periodontal radio button fields
    'perio_gingiva_status'         => $_POST['perio_gingiva_status'] ?? null,
    'perio_inflammation_degree'    => $_POST['perio_inflammation_degree'] ?? null,
    'perio_deposits_degree'        => $_POST['perio_deposits_degree'] ?? null,
    'perio_other'                  => $_POST['perio_other'] ?? '',
    'occl_molar_l'                 => $_POST['occl_molar_l'] ?? '',
    'occl_molar_r'                 => $_POST['occl_molar_r'] ?? '',
    'occl_canine'                  => $_POST['occl_canine'] ?? '',
    'occl_incisal'                 => $_POST['occl_incisal'] ?? '',
    'occl_overbite'                => $_POST['occl_overbite'] ?? '',
    'occl_overjet'                 => $_POST['occl_overjet'] ?? '',
    'occl_midline'                 => $_POST['occl_midline'] ?? '',
    'occl_crossbite'               => $_POST['occl_crossbite'] ?? '',
    'occl_appliances'              => $_POST['occl_appliances'] ?? '',
    'patient_signature'            => $_POST['patient_signature'] ?? '' // This is the printed name
];

/* ---------- 4. Build and execute SQL for patient_health table (FIXED) ---------- */
$updateFields = [];
foreach (array_keys($data) as $key) {
    // The primary key should not be in the UPDATE list
    if ($key !== 'patient_id') {
        $updateFields[] = "$key = VALUES($key)";
    }
}

$sql = "INSERT INTO patient_health (" . implode(', ', array_keys($data)) . ")
        VALUES (:" . implode(', :', array_keys($data)) . ")
        ON DUPLICATE KEY UPDATE " . implode(', ', $updateFields);

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);
} catch (PDOException $e) {
    // It's good practice to handle potential exceptions
    http_response_code(500);
    error_log("Save Step 2 Failed: " . $e->getMessage()); // Log error for debugging
    exit('A database error occurred while saving the health questionnaire.');
}

header("Location: edit_patient_step3.php?id=$patientId");
exit;
