<?php
require_once 'config.php';
requireAuth();

$user = getCurrentUser();
$role = $user['role'] ?? '';
if (!in_array($role, ['Admin', 'Clinician'])) {
    header('Location: patients.php');
    exit;
}

$patientId = $_GET['id'] ?? null;
if (!$patientId) die('Patient ID missing.');
$patientId = (int)$patientId;

/* --- Fetch all necessary data --- */
// Fetch existing health record
$stmt = $pdo->prepare("SELECT * FROM patient_health WHERE patient_id = ?");
$stmt->execute([$patientId]);
$old = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

// Fetch patient's basic info
$pt = $pdo->prepare("SELECT last_name, first_name, middle_initial, nickname, age, gender FROM patients WHERE id = ?");
$pt->execute([$patientId]);
$patient = $pt->fetch(PDO::FETCH_ASSOC);
if (!$patient) {
    die('Patient not found');
}
$patientFullName = trim(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? ''));

// Fetch the shared signature path from the informed_consent table
$consentStmt = $pdo->prepare("SELECT data_privacy_signature_path FROM informed_consent WHERE patient_id = ?");
$consentStmt->execute([$patientId]);
$consentData = $consentStmt->fetch(PDO::FETCH_ASSOC);
$sharedSignaturePath = $consentData['data_privacy_signature_path'] ?? null;


/* helper for checkbox pre-checking */
function checked($key, $array) {
    return (!empty($array[$key])) ? 'checked' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Step 2 – Health Questionnaire & Clinical Examination</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdn.jsdelivr.net/npm/remixicon@2.5.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="dark-mode-override.css">
  <!-- Signature Pad styles and script -->
  <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.5/dist/signature_pad.umd.min.js"></script>
  <style>
    .rounded-button{border-radius:20px}
    .signature-pad-container {
        border: 1px solid #aaa;
        border-radius: 4px;
        position: relative;
        width: 100%;
        height: 150px;
    }
    .signature-pad {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        cursor: crosshair;
    }
  </style>
</head>
<body class="bg-gray-100">

<!-- Header -->
<header class="bg-white shadow-sm">
  <div class="container mx-auto px-4 py-4 flex justify-between items-center">
    <div class="flex items-center">
      <button onclick="window.location.href='patients.php'" class="mr-4 text-gray-500 hover:text-gray-700">
        <div class="w-6 h-6 flex items-center justify-center"><i class="ri-arrow-left-line"></i></div>
      </button>
      <h1 class="text-xl font-semibold text-gray-800">Step 2 – Health Questionnaire</h1>
    </div>
    <div class="flex space-x-3">
    <button id="generateReportBtn" class="bg-primary text-black px-4 py-2 rounded-button hover:bg-primary/90"
        onclick="window.openPrintModal();">
  Generate Report
</button>
      <button type="submit" form="step2Form" class="bg-primary text-black px-4 py-2 rounded-button whitespace-nowrap">Save Record</button>
    </div>
  </div>
</header>

<div class="container mx-auto px-4 py-6">
  <!-- Progress bar -->
  <div class="mb-8">
    <div class="flex items-center justify-between">
      <div class="w-full flex items-center">
        <a href="edit_patient.php?id=<?=$patientId?>" class="w-8 h-8 rounded-full bg-green-600 text-white text-sm font-medium flex items-center justify-center">1</a>
        <div class="flex-1 h-1 bg-green-600"></div>
        <a href="edit_patient_step2.php?id=<?=$patientId?>" class="w-8 h-8 rounded-full bg-blue-600 text-white text-sm font-medium flex items-center justify-center">2</a>
        <div class="flex-1 h-1 bg-blue-600"></div>
        <a href="edit_patient_step3.php?id=<?=$patientId?>" class="w-8 h-8 rounded-full bg-gray-200 text-gray-700 text-sm font-medium flex items-center justify-center">3</a>
        <div class="flex-1 h-1 bg-gray-200"></div>
        <a href="edit_patient_step4.php?id=<?=$patientId?>" class="w-8 h-8 rounded-full bg-gray-200 text-gray-700 text-sm font-medium flex items-center justify-center">4</a>
        <div class="flex-1 h-1 bg-gray-200"></div>
        <a href="edit_patient_step5.php?id=<?=$patientId?>" class="w-8 h-8 rounded-full bg-gray-200 text-gray-700 text-sm font-medium flex items-center justify-center">5</a>
      </div>
    </div>
  </div>

  <!-- Form -->
  <form id="step2Form" method="post" action="save_step2.php" autocomplete="off">
    <input type="hidden" name="patient_id" value="<?=$patientId?>">
    <div class="bg-white rounded shadow-sm p-6">
      <h3 class="text-lg font-semibold text-gray-800 mb-4">Step 2 – Health Questionnaire & Clinical Examination</h3>

      <!-- Patient banner -->
      <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-4">
        <div><label class="block text-xs font-medium text-gray-700 mb-1">Last name</label><input type="text" readonly value="<?=htmlspecialchars($patient['last_name'])?>" class="w-full px-2 py-1 border rounded bg-gray-100"></div>
        <div><label class="block text-xs font-medium text-gray-700 mb-1">First name</label><input type="text" readonly value="<?=htmlspecialchars($patient['first_name'])?>" class="w-full px-2 py-1 border rounded bg-gray-100"></div>
        <div><label class="block text-xs font-medium text-gray-700 mb-1">MI</label><input type="text" readonly value="<?=htmlspecialchars($patient['middle_initial'])?>" class="w-full px-2 py-1 border rounded bg-gray-100"></div>
        <div><label class="block text-xs font-medium text-gray-700 mb-1">Nickname</label><input type="text" readonly value="<?=htmlspecialchars($patient['nickname'])?>" class="w-full px-2 py-1 border rounded bg-gray-100"></div>
        <div><label class="block text-xs font-medium text-gray-700 mb-1">Age/Gender</label><input type="text" readonly value="<?=$patient['age']?> / <?=htmlspecialchars($patient['gender'])?>" class="w-full px-2 py-1 border rounded bg-gray-100"></div>
      </div>

      <!-- Health Questionnaire Table -->
      <div class="mb-6">
        <h3 class="text-base font-semibold text-gray-800 mb-2">HEALTH QUESTIONNAIRE</h3>
        <p class="text-xs text-gray-600 mb-2">Check the box to answer all questions.</p>
        <div class="overflow-x-auto">
          <table class="min-w-full border text-xs">
            <thead>
              <tr class="bg-gray-100">
                <th class="border px-2 py-1 text-center">Yes</th>
                <th class="border px-2 py-1 text-center">No</th>
                <th class="border px-2 py-1">Question</th>
                <th class="border px-2 py-1 text-center">Yes</th>
                <th class="border px-2 py-1 text-center">No</th>
                <th class="border px-2 py-1">Question</th>
              </tr>
            </thead>
            <tbody>
              <!-- Row 1 -->
              <tr>
                <td class="border px-2 py-1 text-center"><input type="checkbox" name="last_medical_physical_yes" <?=checked('last_medical_physical_yes', $old)?> /></td>
                <td class="border px-2 py-1 text-center"><input type="checkbox" name="last_medical_physical_no" <?=checked('last_medical_physical_no', $old)?> /></td>
                <td class="border px-2 py-1">Last medical physical: <input type="text" name="last_medical_physical" value="<?=htmlspecialchars($old['last_medical_physical'] ?? '')?>" class="border-b border-gray-400 ml-1 w-24"></td>

                <td class="border px-2 py-1 text-center"><input type="checkbox" name="abnormal_bleeding_yes" <?=checked('abnormal_bleeding_yes', $old)?> /></td>
                <td class="border px-2 py-1 text-center"><input type="checkbox" name="abnormal_bleeding_no" <?=checked('abnormal_bleeding_no', $old)?> /></td>
                <td class="border px-2 py-1">Abnormal bleeding?</td>
              </tr>

              <!-- Row 2 -->
              <tr>
                <td></td><td></td>
                <td class="border px-2 py-1">Physician name/addr: <input type="text" name="physician_name_addr" value="<?=htmlspecialchars($old['physician_name_addr'] ?? '')?>" class="border-b border-gray-400 ml-1 w-32"></td>

                <td class="border px-2 py-1 text-center"><input type="checkbox" name="bruise_easily_yes" <?=checked('bruise_easily_yes', $old)?> /></td>
                <td class="border px-2 py-1 text-center"><input type="checkbox" name="bruise_easily_no" <?=checked('bruise_easily_no', $old)?> /></td>
                <td class="border px-2 py-1">Bruise easily?</td>
              </tr>

              <!-- Row 3 -->
              <tr>
                <td class="border px-2 py-1 text-center"><input type="checkbox" name="under_physician_care" <?=checked('under_physician_care', $old)?> /></td>
                <td class="border px-2 py-1 text-center"><input type="checkbox" /></td>
                <td class="border px-2 py-1">Under physician care? <input type="text" name="under_physician_care_note" value="<?=htmlspecialchars($old['under_physician_care_note'] ?? '')?>" class="border-b border-gray-400 ml-1 w-24"></td>

                <td class="border px-2 py-1 text-center"><input type="checkbox" name="blood_transfusion_yes" <?=checked('blood_transfusion_yes', $old)?> /></td>
                <td class="border px-2 py-1 text-center"><input type="checkbox" name="blood_transfusion_no" <?=checked('blood_transfusion_no', $old)?> /></td>
                <td class="border px-2 py-1">Blood transfusion?</td>
              </tr>

              <!-- Row 4 -->
              <tr>
                <td class="border px-2 py-1 text-center"><input type="checkbox" name="serious_illness_operation" <?=checked('serious_illness_operation', $old)?> /></td>
                <td class="border px-2 py-1 text-center"><input type="checkbox" /></td>
                <td class="border px-2 py-1">Serious illness/op? <input type="text" name="serious_illness_operation_note" value="<?=htmlspecialchars($old['serious_illness_operation_note'] ?? '')?>" class="border-b border-gray-400 ml-1 w-24"></td>

                <td class="border px-2 py-1 text-center"><input type="checkbox" name="blood_disorder_yes" <?=checked('blood_disorder_yes', $old)?> /></td>
                <td class="border px-2 py-1 text-center"><input type="checkbox" name="blood_disorder_no" <?=checked('blood_disorder_no', $old)?> /></td>
                <td class="border px-2 py-1">Blood disorder?</td>
              </tr>

              <!-- Row 5 -->
              <tr>
                <td class="border px-2 py-1 text-center"><input type="checkbox" name="hospitalized" <?=checked('hospitalized', $old)?> /></td>
                <td class="border px-2 py-1 text-center"><input type="checkbox" /></td>
                <td class="border px-2 py-1">Hospitalized? <input type="text" name="hospitalized_note" value="<?=htmlspecialchars($old['hospitalized_note'] ?? '')?>" class="border-b border-gray-400 ml-1 w-24"></td>

                <td class="border px-2 py-1 text-center"><input type="checkbox" name="head_neck_radiation_yes" <?=checked('head_neck_radiation_yes', $old)?> /></td>
                <td class="border px-2 py-1 text-center"><input type="checkbox" name="head_neck_radiation_no" <?=checked('head_neck_radiation_no', $old)?> /></td>
                <td class="border px-2 py-1">Head/neck radiation?</td>
              </tr>

              <!-- Section header -->
              <tr>
                <td colspan="6" class="border px-2 py-1 font-semibold">Diseases or problems</td>
              </tr>

              <!-- Disease rows -->
              <tr>
                <td class="border px-2 py-1 text-center"><input type="checkbox" name="rheumatic_fever" <?=checked('rheumatic_fever', $old)?> /></td>
                <td class="border px-2 py-1 text-center"><input type="checkbox" /></td>
                <td class="border px-2 py-1">Rheumatic fever</td>
                <td class="border px-2 py-1 text-center"><input type="checkbox" name="heart_abnormalities" <?=checked('heart_abnormalities', $old)?> /></td>
                <td class="border px-2 py-1 text-center"><input type="checkbox" /></td>
                <td class="border px-2 py-1">Heart abnormalities</td>
              </tr>
              <tr>
                <td class="border px-2 py-1 text-center"><input type="checkbox" name="cardiovascular_disease" <?=checked('cardiovascular_disease', $old)?> /></td>
                <td class="border px-2 py-1 text-center"><input type="checkbox" /></td>
                <td class="border px-2 py-1">Cardiovascular disease</td>
                <td class="border px-2 py-1 text-center"><input type="checkbox" name="childhood_diseases" <?=checked('childhood_diseases', $old)?> /></td>
                <td class="border px-2 py-1 text-center"><input type="checkbox" /></td>
                <td class="border px-2 py-1">Childhood diseases? <input type="text" name="childhood_diseases_note" value="<?=htmlspecialchars($old['childhood_diseases_note'] ?? '')?>" class="border-b border-gray-400 ml-1 w-24"></td>
              </tr>
              <tr>
                <td class="border px-2 py-1 text-center"><input type="checkbox" name="asthma_hayfever" <?=checked('asthma_hayfever', $old)?> /></td>
                <td class="border px-2 py-1 text-center"><input type="checkbox" /></td>
                <td class="border px-2 py-1">Asthma / hay fever</td>
                <td class="border px-2 py-1 text-center"><input type="checkbox" name="hives_skin_rash" <?=checked('hives_skin_rash', $old)?> /></td>
                <td class="border px-2 py-1 text-center"><input type="checkbox" /></td>
                <td class="border px-2 py-1">Hives / skin rash</td>
              </tr>
              <tr>
                <td class="border px-2 py-1 text-center"><input type="checkbox" name="fainting_seizures" <?=checked('fainting_seizures', $old)?> /></td>
                <td class="border px-2 py-1 text-center"><input type="checkbox" /></td>
                <td class="border px-2 py-1">Fainting spells / seizures</td>
                <td class="border px-2 py-1 text-center"><input type="checkbox" name="diabetes" <?=checked('diabetes', $old)?> /></td>
                <td class="border px-2 py-1 text-center"><input type="checkbox" /></td>
                <td class="border px-2 py-1">Diabetes</td>
              </tr>
              <tr>
                <td class="border px-2 py-1 text-center"><input type="checkbox" name="urinate_more" <?=checked('urinate_more', $old)?> /></td>
                <td class="border px-2 py-1 text-center"><input type="checkbox" /></td>
                <td class="border px-2 py-1">Urinate >6×/day</td>
                <td class="border px-2 py-1 text-center"><input type="checkbox" name="thirsty" <?=checked('thirsty', $old)?> /></td>
                <td class="border px-2 py-1 text-center"><input type="checkbox" /></td>
                <td class="border px-2 py-1">Thirsty often</td>
              </tr>
              <tr>
                <td class="border px-2 py-1 text-center"><input type="checkbox" name="mouth_dry" <?=checked('mouth_dry', $old)?> /></td>
                <td class="border px-2 py-1 text-center"><input type="checkbox" /></td>
                <td class="border px-2 py-1">Mouth dry</td>
                <td class="border px-2 py-1 text-center"><input type="checkbox" name="hepatitis" <?=checked('hepatitis', $old)?> /></td>
                <td class="border px-2 py-1 text-center"><input type="checkbox" /></td>
                <td class="border px-2 py-1">Hepatitis / liver</td>
              </tr>
              <tr>
                <td class="border px-2 py-1 text-center"><input type="checkbox" name="arthritis" <?=checked('arthritis', $old)?> /></td>
                <td class="border px-2 py-1 text-center"><input type="checkbox" /></td>
                <td class="border px-2 py-1">Arthritis</td>
                <td class="border px-2 py-1 text-center"><input type="checkbox" name="stomach_ulcers" <?=checked('stomach_ulcers', $old)?> /></td>
                <td class="border px-2 py-1 text-center"><input type="checkbox" /></td>
                <td class="border px-2 py-1">Stomach ulcers</td>
              </tr>
              <tr>
                <td class="border px-2 py-1 text-center"><input type="checkbox" name="kidney_trouble" <?=checked('kidney_trouble', $old)?> /></td>
                <td class="border px-2 py-1 text-center"><input type="checkbox" /></td>
                <td class="border px-2 py-1">Kidney trouble</td>
                <td class="border px-2 py-1 text-center"><input type="checkbox" name="tuberculosis" <?=checked('tuberculosis', $old)?> /></td>
                <td class="border px-2 py-1 text-center"><input type="checkbox" /></td>
                <td class="border px-2 py-1">Tuberculosis</td>
              </tr>
              <tr>
                <td class="border px-2 py-1 text-center"><input type="checkbox" name="venereal_disease" <?=checked('venereal_disease', $old)?> /></td>
                <td class="border px-2 py-1 text-center"><input type="checkbox" /></td>
                <td class="border px-2 py-1">Venereal disease</td>
                <td class="border px-2 py-1 text-center"><input type="checkbox" name="other_conditions" <?=checked('other_conditions', $old)?> /></td>
                <td class="border px-2 py-1 text-center"><input type="checkbox" /></td>
                <td class="border px-2 py-1">Other conditions? <input type="text" name="other_conditions_note" value="<?=htmlspecialchars($old['other_conditions_note'] ?? '')?>" class="border-b border-gray-400 ml-1 w-24"></td>
              </tr>

              <!-- Allergies -->
              <tr>
                <td colspan="6" class="border px-2 py-1 font-semibold">Allergies / adverse reactions</td>
              </tr>
              <tr>
                <td class="border px-2 py-1 text-center"><input type="checkbox" name="anesthetic_allergy" <?=checked('anesthetic_allergy', $old)?> /></td>
                <td class="border px-2 py-1 text-center"><input type="checkbox" /></td>
                <td class="border px-2 py-1">Local anesthetics</td>
                <td class="border px-2 py-1 text-center"><input type="checkbox" name="penicillin_allergy" <?=checked('penicillin_allergy', $old)?> /></td>
                <td class="border px-2 py-1 text-center"><input type="checkbox" /></td>
                <td class="border px-2 py-1">Penicillin / antibiotics</td>
              </tr>
              <tr>
                <td class="border px-2 py-1 text-center"><input type="checkbox" name="aspirin_allergy" <?=checked('aspirin_allergy', $old)?> /></td>
                <td class="border px-2 py-1 text-center"><input type="checkbox" /></td>
                <td class="border px-2 py-1">Aspirin</td>
                <td class="border px-2 py-1 text-center"><input type="checkbox" name="latex_allergy" <?=checked('latex_allergy', $old)?> /></td>
                <td class="border px-2 py-1 text-center"><input type="checkbox" /></td>
                <td class="border px-2 py-1">Latex</td>
              </tr>
              <tr>
                <td class="border px-2 py-1 text-center"><input type="checkbox" name="other_allergy" <?=checked('other_allergy', $old)?> /></td>
                <td class="border px-2 py-1 text-center"><input type="checkbox" /></td>
                <td class="border px-2 py-1">Other allergy</td>
                <td></td><td></td><td></td>
              </tr>

              <!-- Free-text rows -->
              <tr>
                <td colspan="6" class="border px-2 py-1 font-semibold">
                  Taking any drugs/medicines? <input type="text" name="taking_drugs" value="<?=htmlspecialchars($old['taking_drugs'] ?? '')?>" class="border-b border-gray-400 ml-1 w-32">
                </td>
              </tr>
              <tr>
                <td colspan="6" class="border px-2 py-1 font-semibold">
                  Previous dental trouble? <input type="text" name="previous_dental_trouble" value="<?=htmlspecialchars($old['previous_dental_trouble'] ?? '')?>" class="border-b border-gray-400 ml-1 w-32">
                </td>
              </tr>
              <tr>
                <td colspan="6" class="border px-2 py-1 font-semibold">
                  Other conditions / notes <input type="text" name="other_problem" value="<?=htmlspecialchars($old['other_problem'] ?? '')?>" class="border-b border-gray-400 ml-1 w-32">
                </td>
              </tr>
              <tr>
                <td colspan="6" class="border px-2 py-1 font-semibold">
                  X-ray exposure? <input type="text" name="xray_exposure" value="<?=htmlspecialchars($old['xray_exposure'] ?? '')?>" class="border-b border-gray-400 ml-1 w-32">
                </td>
              </tr>
              <tr>
                <td colspan="6" class="border px-2 py-1 font-semibold">
                  Eyeglasses / contacts? <input type="text" name="eyeglasses" value="<?=htmlspecialchars($old['eyeglasses'] ?? '')?>" class="border-b border-gray-400 ml-1 w-32">
                </td>
              </tr>

              <!-- WOMEN -->
              <tr>
                <td colspan="3" class="border px-2 py-1 font-semibold">WOMEN</td>
                <td colspan="3"></td>
              </tr>
              <tr>
                <td class="border px-2 py-1 text-center"><input type="checkbox" name="pregnant" <?=checked('pregnant', $old)?> /></td>
                <td class="border px-2 py-1 text-center"><input type="checkbox" /></td>
                <td class="border px-2 py-1">Pregnant / missed period</td>
                <td class="border px-2 py-1 text-center"><input type="checkbox" name="breast_feeding" <?=checked('breast_feeding', $old)?> /></td>
                <td class="border px-2 py-1 text-center"><input type="checkbox" /></td>
                <td class="border px-2 py-1">Breast feeding</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Clinical Examination (unchanged) -->
      <div class="mb-4">
        <h3 class="text-base font-semibold text-gray-800 mb-2">OBJECTIVE</h3>
        <h4 class="text-sm font-semibold text-gray-700 mb-2">CLINICAL EXAMINATION <span class="font-normal text-xs">(Do not leave any blanks)</span></h4>

        <!-- GENERAL APPRAISAL -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-2 mb-2">
          <div><label class="block text-xs text-gray-700 mb-1">General health notes</label><input type="text" name="general_health_notes" value="<?=htmlspecialchars($old['general_health_notes'] ?? '')?>" class="px-2 py-1 border rounded w-full"></div>
          <div><label class="block text-xs text-gray-700 mb-1">Physical</label><input type="text" name="physical" value="<?=htmlspecialchars($old['physical'] ?? '')?>" class="px-2 py-1 border rounded w-full"></div>
          <div><label class="block text-xs text-gray-700 mb-1">Mental</label><input type="text" name="mental" value="<?=htmlspecialchars($old['mental'] ?? '')?>" class="px-2 py-1 border rounded w-full"></div>
          <div><label class="block text-xs text-gray-700 mb-1">Vital Signs</label><input type="text" name="vital_signs" value="<?=htmlspecialchars($old['vital_signs'] ?? '')?>" class="px-2 py-1 border rounded w-full"></div>
        </div>

        <!-- Extra-oral / Intra-oral / Periodontal / Occlusion (same as before) -->
                <!-- EXTRAORAL -->
        <div class="mb-2">
          <label class="block text-xs font-medium text-gray-700 mb-1">EXTRAORAL EXAMINATION</label>
          <div class="grid grid-cols-2 md:grid-cols-6 gap-2 mb-2">
            <?php
            $extraFields = ['head_face','eyes','ears','nose','hair','neck',
                            'paranasal','lymph','salivary','tmj','muscles','other'];
            foreach ($extraFields as $f) {
                echo "<div>
                        <label class=\"block text-xs text-gray-700 mb-1\">".ucwords(str_replace('_',' ',$f))."</label>
                        <input type=\"text\" name=\"extra_$f\" value=\"".htmlspecialchars($old["extra_$f"] ?? '')."\" class=\"px-2 py-1 border border-gray-300 rounded w-full\" />
                      </div>";
            }
            ?>
          </div>
        </div>

        <!-- INTRAORAL -->
        <div class="mb-2">
          <label class="block text-xs font-medium text-gray-700 mb-1">INTRAORAL EXAMINATION</label>
          <div class="grid grid-cols-2 md:grid-cols-6 gap-2 mb-2">
            <?php
            $intraFields = ['lips','buccal','alveolar','floor','tongue','saliva',
                            'pillars','tonsils','uvula','oropharynx','other'];
            foreach ($intraFields as $f) {
                echo "<div>
                        <label class=\"block text-xs text-gray-700 mb-1\">".ucwords(str_replace('_',' ',$f))."</label>
                        <input type=\"text\" name=\"intra_$f\" value=\"".htmlspecialchars($old["intra_$f"] ?? '')."\" class=\"px-2 py-1 border border-gray-300 rounded w-full\" />
                      </div>";
            }
            ?>
          </div>
        </div>

        <!-- Periodontal -->
        <div class="mb-2">
          <label class="block text-xs font-medium text-gray-700 mb-1">Periodontal Examination (encircle)</label>
          <div class="border border-gray-300 rounded p-3">
            <!-- Gingiva Status -->
            <div class="mb-3">
              <div class="flex items-center space-x-4">
                <span class="text-xs font-medium text-gray-700 min-w-[100px]">Gingiva:</span>
                <label class="flex items-center space-x-1">
                  <input type="radio" name="perio_gingiva_status" value="Healthy" 
                         <?= ($old['perio_gingiva_status'] ?? '') == 'Healthy' ? 'checked' : '' ?> 
                         class="mr-1">
                  <span class="text-xs">Healthy</span>
                </label>
                <label class="flex items-center space-x-1">
                  <input type="radio" name="perio_gingiva_status" value="Inflamed" 
                         <?= ($old['perio_gingiva_status'] ?? '') == 'Inflamed' ? 'checked' : '' ?> 
                         class="mr-1">
                  <span class="text-xs">Inflamed</span>
                </label>
              </div>
            </div>
            
            <!-- Degree of Inflammation -->
            <div class="mb-3">
              <div class="flex items-center space-x-4">
                <span class="text-xs font-medium text-gray-700 min-w-[100px]">Degree of inflammation:</span>
                <label class="flex items-center space-x-1">
                  <input type="radio" name="perio_inflammation_degree" value="Mild" 
                         <?= ($old['perio_inflammation_degree'] ?? '') == 'Mild' ? 'checked' : '' ?> 
                         class="mr-1">
                  <span class="text-xs">Mild</span>
                </label>
                <label class="flex items-center space-x-1">
                  <input type="radio" name="perio_inflammation_degree" value="Moderate" 
                         <?= ($old['perio_inflammation_degree'] ?? '') == 'Moderate' ? 'checked' : '' ?> 
                         class="mr-1">
                  <span class="text-xs">Moderate</span>
                </label>
                <label class="flex items-center space-x-1">
                  <input type="radio" name="perio_inflammation_degree" value="Severe" 
                         <?= ($old['perio_inflammation_degree'] ?? '') == 'Severe' ? 'checked' : '' ?> 
                         class="mr-1">
                  <span class="text-xs">Severe</span>
                </label>
              </div>
            </div>
            
            <!-- Degree of Deposits -->
            <div class="mb-3">
              <div class="flex items-center space-x-4">
                <span class="text-xs font-medium text-gray-700 min-w-[100px]">Degree of deposits:</span>
                <label class="flex items-center space-x-1">
                  <input type="radio" name="perio_deposits_degree" value="Light" 
                         <?= ($old['perio_deposits_degree'] ?? '') == 'Light' ? 'checked' : '' ?> 
                         class="mr-1">
                  <span class="text-xs">Light</span>
                </label>
                <label class="flex items-center space-x-1">
                  <input type="radio" name="perio_deposits_degree" value="Moderate" 
                         <?= ($old['perio_deposits_degree'] ?? '') == 'Moderate' ? 'checked' : '' ?> 
                         class="mr-1">
                  <span class="text-xs">Moderate</span>
                </label>
                <label class="flex items-center space-x-1">
                  <input type="radio" name="perio_deposits_degree" value="Heavy" 
                         <?= ($old['perio_deposits_degree'] ?? '') == 'Heavy' ? 'checked' : '' ?> 
                         class="mr-1">
                  <span class="text-xs">Heavy</span>
                </label>
              </div>
            </div>
            
            <!-- Other (optional text field) -->
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Other:</label>
              <input type="text" name="perio_other" value="<?=htmlspecialchars($old['perio_other'] ?? '')?>" 
                     class="px-2 py-1 border border-gray-300 rounded w-full text-xs" 
                     placeholder="Additional notes (optional)" />
            </div>
          </div>
        </div>

        <!-- Occlusion -->
        <div class="mb-2">
          <label class="block text-xs font-medium text-gray-700 mb-1">Occlusion</label>
          <div class="grid grid-cols-2 md:grid-cols-6 gap-2 mb-2">
            <?php
            $occFields = ['molar_l','molar_r','canine','incisal','overbite',
                          'overjet','midline','crossbite','appliances'];
            foreach ($occFields as $f) {
                echo "<div>
                        <label class=\"block text-xs text-gray-700 mb-1\">".ucwords(str_replace('_',' ',$f))."</label>
                        <input type=\"text\" name=\"occl_$f\" value=\"".htmlspecialchars($old["occl_$f"] ?? '')."\" class=\"px-2 py-1 border border-gray-300 rounded w-full\" />
                      </div>";
            }
            ?>
          </div>
        </div>

        <!-- Patient signature (Updated with Signature Pad) -->
      <div class="mb-2 flex flex-col items-start mt-6">
        <label class="block text-xs font-medium text-gray-700 mb-2">Patient's name and signature</label>
        <div class="w-full max-w-[350px]">
            <div class="signature-pad-container">
                <canvas id="step2SigPad" class="signature-pad"></canvas>
            </div>
            <button type="button" onclick="step2Pad.clear()" class="text-xs text-red-600 mt-1">Clear Signature</button>
            <input type="hidden" name="old_data_privacy_signature_path" value="<?= htmlspecialchars($sharedSignaturePath ?? '') ?>">
        </div>
        <!-- Printed Name (auto-filled from first + last name, read-only) -->
        <div class="w-full max-w-[350px] mt-2">
            <input  name="patient_signature"
                    type="text"
                    value="<?= htmlspecialchars(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? '')) ?>"
                    class="w-full px-2 py-1 border border-gray-300 rounded bg-gray-100"
                    readonly />
            <label class="block text-xs font-medium text-gray-700 mt-1">Printed Name</label>
        </div>
      </div>

      <!-- Navigation -->
      <div class="mt-6 flex justify-end space-x-2">
        <a href="edit_patient.php?id=<?= $patientId ?>" class="bg-gray-300 text-black px-6 py-2 rounded-button whitespace-nowrap">Previous</a>
        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-button whitespace-nowrap">Save & Next</button>
      </div>
    </div>
  </form>
</div>

<script>
  // Loads the modal when the button is clicked
  function openPrintModal() {
    const pid = "<?= $patientId ?>";
    window.open('print_selection.php?id='+pid, '_blank', 'width=1100,height=700');
  }
  document.getElementById('generateReportBtn').addEventListener('click', openPrintModal);
</script>

<script>
// --- Signature Pad Setup ---
const step2PadCanvas = document.getElementById('step2SigPad');
const step2Pad = new SignaturePad(step2PadCanvas, { backgroundColor: '#fff' });

function resizeCanvas(canvas) {
    const ratio = Math.max(window.devicePixelRatio || 1, 1);
    canvas.width = canvas.offsetWidth * ratio;
    canvas.height = canvas.offsetHeight * ratio;
    canvas.getContext('2d').scale(ratio, ratio);
    step2Pad.clear();
}

function loadSignature() {
    <?php if (!empty($sharedSignaturePath) && file_exists(__DIR__ . '/' . $sharedSignaturePath)): ?>
    step2Pad.fromDataURL('<?= htmlspecialchars($sharedSignaturePath) ?>?t=<?= time() ?>');
    <?php endif; ?>
}

window.addEventListener('load', () => {
    resizeCanvas(step2PadCanvas);
    loadSignature();
});
window.addEventListener('resize', () => {
    resizeCanvas(step2PadCanvas);
    loadSignature();
});


// --- Form Submission ---
document.getElementById('step2Form').addEventListener('submit', function(event) {
    // Add signature data to a hidden input before submitting
    if (!step2Pad.isEmpty()) {
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'data_privacy_signature_path_base64';
        hiddenInput.value = step2Pad.toDataURL('image/png');
        this.appendChild(hiddenInput);
    }
});
</script>
</body>
</html>
