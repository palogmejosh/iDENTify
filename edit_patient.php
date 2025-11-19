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
if (!$patientId) {
    header('Location: patients.php');
    exit;
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ---------- fetch data from patient_pir and patients tables ---------- */
$baseStmt = $pdo->prepare("SELECT first_name, last_name, age, email FROM patients WHERE id = ?");
$baseStmt->execute([$patientId]);
$baseData = $baseStmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT * FROM patient_pir WHERE patient_id = ?");
$stmt->execute([$patientId]);
$formData = $stmt->fetch(PDO::FETCH_ASSOC);

$formData = array_merge($baseData ?: [], $formData ?: []);

/* ---------- fetch shared signature from informed_consent table ---------- */
$consentStmt = $pdo->prepare("SELECT data_privacy_signature_path FROM informed_consent WHERE patient_id = ?");
$consentStmt->execute([$patientId]);
$consentData = $consentStmt->fetch(PDO::FETCH_ASSOC);
$formData['data_privacy_signature_path'] = $consentData['data_privacy_signature_path'] ?? null;


function val($key) {
    global $formData;
    return htmlspecialchars($formData[$key] ?? '');
}

$photoSrc = !empty($formData['photo']) ? $formData['photo'] : null;
$patientFullName = trim(val('first_name') . ' ' . val('last_name'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Patient Information Record – Personal Data</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdn.jsdelivr.net/npm/remixicon@2.5.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="dark-mode-override.css">
  
  <!-- DigitalPersona WebSDK and Devices library for fingerprint capture -->
  <!-- IMPORTANT: Update paths in js/fingerprint-config.js if SDK is installed elsewhere -->
  <script src="js/fingerprint-config.js"></script>
  <script src="node_modules/@digitalpersona/websdk/dist/websdk.client.min.js"></script>
  <script src="node_modules/@digitalpersona/core/dist/es5.bundles/index.umd.min.js"></script>
  <script src="node_modules/@digitalpersona/devices/dist/es5.bundles/index.umd.min.js"></script>
  
  <!-- Signature Pad styles -->
  <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.5/dist/signature_pad.umd.min.js"></script>
  <!-- Remove.bg API integration - no client-side AI libraries needed -->
  <style>
    .processing-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.5);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 9999;
    }
    .processing-content {
      background: white;
      padding: 2rem;
      border-radius: 8px;
      text-align: center;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    }
  </style>
  <style>
    .rounded-button { border-radius: 20px; }
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

<header class="bg-white shadow-sm">
  <div class="container mx-auto px-4 py-4 flex justify-between items-center">
    <div class="flex items-center">
      <button onclick="window.location.href='patients.php';" class="mr-4 text-gray-500 hover:text-gray-700">
        <i class="ri-arrow-left-line"></i>
      </button>
      <h1 class="text-xl font-semibold text-gray-800">
        Patient Information Record – Personal Data
      </h1>
    </div>
    <div class="flex space-x-3">
    <button id="generateReportBtn" class="bg-primary text-black px-4 py-2 rounded-button hover:bg-primary/90"
        onclick="window.openPrintModal();">
  Generate Report
</button>
      <button id="savePatientInfoBtn" type="submit" form="patientForm" class="bg-primary text-black px-4 py-2 rounded-button">
        Save Record
      </button>
    </div>
  </div>
</header>

<div class="flex-1 container mx-auto px-4 py-6">
  <!-- Progress Bar -->
  <div class="mb-8">
    <div class="w-full flex items-center">
      <a href="edit_patient.php?id=<?= $patientId ?>" class="w-8 h-8 rounded-full bg-blue-600 text-white text-sm font-medium flex items-center justify-center">1</a>
      <div class="flex-1 h-1 bg-blue-600"></div>
      <a href="edit_patient_step2.php?id=<?= $patientId ?>" class="w-8 h-8 rounded-full bg-gray-200 text-gray-700 text-sm font-medium flex items-center justify-center">2</a>
      <div class="flex-1 h-1 bg-gray-200"></div>
      <a href="edit_patient_step3.php?id=<?= $patientId ?>" class="w-8 h-8 rounded-full bg-gray-200 text-gray-700 text-sm font-medium flex items-center justify-center">3</a>
      <div class="flex-1 h-1 bg-gray-200"></div>
      <a href="edit_patient_step4.php?id=<?= $patientId ?>" class="w-8 h-8 rounded-full bg-gray-200 text-gray-700 text-sm font-medium flex items-center justify-center">4</a>
      <div class="flex-1 h-1 bg-gray-200"></div>
      <a href="edit_patient_step5.php?id=<?= $patientId ?>" class="w-8 h-8 rounded-full bg-gray-200 text-gray-700 text-sm font-medium flex items-center justify-center">5</a>
    </div>
  </div>

  <!-- Form Start -->
  <form id="patientForm" method="POST" action="save_pir.php" enctype="multipart/form-data">
    <input type="hidden" name="patient_id" value="<?= $patientId ?>">
    <div class="bg-white rounded shadow-sm p-6">
      <h3 class="text-lg font-semibold text-gray-800 mb-4">Personal Data</h3>
      
      <!-- Personal & Contact Info... (rest of the form is unchanged) -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
          <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Fields from Last name to Emergency contact -->
            <div><label class="block text-xs font-medium text-gray-700 mb-1">Last name</label><input name="last_name" type="text" value="<?= val('last_name') ?>" required class="w-full px-2 py-1 border border-gray-300 rounded"></div>
            <div><label class="block text-xs font-medium text-gray-700 mb-1">First name</label><input name="first_name" type="text" value="<?= val('first_name') ?>" required class="w-full px-2 py-1 border border-gray-300 rounded"></div>
            <div><label class="block text-xs font-medium text-gray-700 mb-1">MI</label><input name="mi" type="text" value="<?= val('mi') ?>" class="w-full px-2 py-1 border border-gray-300 rounded"></div>
            <div><label class="block text-xs font-medium text-gray-700 mb-1">Nickname</label><input name="nickname" type="text" value="<?= val('nickname') ?>" class="w-full px-2 py-1 border border-gray-300 rounded"></div>
            <div><label class="block text-xs font-medium text-gray-700 mb-1">Age</label><input name="age" type="number" min="0" max="120" value="<?= val('age') ?>" class="w-full px-2 py-1 border border-gray-300 rounded"></div>
            <div><label class="block text-xs font-medium text-gray-700 mb-1">Gender</label><select name="gender" class="w-full px-2 py-1 border border-gray-300 rounded"><option value="">Select</option><option value="male" <?= val('gender')==='male'?'selected':''?>>Male</option><option value="female" <?= val('gender')==='female'?'selected':''?>>Female</option><option value="other" <?= val('gender')==='other'?'selected':''?>>Other</option></select></div>
            <div><label class="block text-xs font-medium text-gray-700 mb-1">Date of Birth</label><input name="date_of_birth" type="date" value="<?= val('date_of_birth') ?>" class="w-full px-2 py-1 border border-gray-300 rounded"></div>
            <div><label class="block text-xs font-medium text-gray-700 mb-1">Civil Status</label><input name="civil_status" type="text" value="<?= val('civil_status') ?>" class="w-full px-2 py-1 border border-gray-300 rounded"></div>
            <div><label class="block text-xs font-medium text-gray-700 mb-1">Home Address</label><input name="home_address" type="text" value="<?= val('home_address') ?>" class="w-full px-2 py-1 border border-gray-300 rounded"></div>
            <div><label class="block text-xs font-medium text-gray-700 mb-1">Home Phone</label><input name="home_phone" type="text" value="<?= val('home_phone') ?>" class="w-full px-2 py-1 border border-gray-300 rounded"></div>
            <div><label class="block text-xs font-medium text-gray-700 mb-1">Mobile No</label><input name="mobile_no" type="text" value="<?= val('mobile_no') ?>" class="w-full px-2 py-1 border border-gray-300 rounded"></div>
            <div><label class="block text-xs font-medium text-gray-700 mb-1">Email</label><input name="email" type="email" value="<?= val('email') ?>" class="w-full px-2 py-1 border border-gray-300 rounded"></div>
            <div><label class="block text-xs font-medium text-gray-700 mb-1">Occupation</label><input name="occupation" type="text" value="<?= val('occupation') ?>" class="w-full px-2 py-1 border border-gray-300 rounded"></div>
            <div><label class="block text-xs font-medium text-gray-700 mb-1">Work Address</label><input name="work_address" type="text" value="<?= val('work_address') ?>" class="w-full px-2 py-1 border border-gray-300 rounded"></div>
            <div><label class="block text-xs font-medium text-gray-700 mb-1">Work Phone</label><input name="work_phone" type="text" value="<?= val('work_phone') ?>" class="w-full px-2 py-1 border border-gray-300 rounded"></div>
            <div><label class="block text-xs font-medium text-gray-700 mb-1">Nationality/Ethnicity</label><input name="ethnicity" type="text" value="<?= val('ethnicity') ?>" class="w-full px-2 py-1 border border-gray-300 rounded"></div>
            <div><label class="block text-xs font-medium text-gray-700 mb-1">For minors: Parent/Guardian</label><input name="guardian_name" type="text" value="<?= val('guardian_name') ?>" class="w-full px-2 py-1 border border-gray-300 rounded"></div>
            <div><label class="block text-xs font-medium text-gray-700 mb-1">Contact Number</label><input name="guardian_contact" type="text" value="<?= val('guardian_contact') ?>" class="w-full px-2 py-1 border border-gray-300 rounded"></div>
            <div><label class="block text-xs font-medium text-gray-700 mb-1">Emergency Contact</label><input name="emergency_contact_name" type="text" value="<?= val('emergency_contact_name') ?>" class="w-full px-2 py-1 border border-gray-300 rounded"></div>
            <div><label class="block text-xs font-medium text-gray-700 mb-1">Contact Number</label><input name="emergency_contact_number" type="text" value="<?= val('emergency_contact_number') ?>" class="w-full px-2 py-1 border border-gray-300 rounded"></div>
          </div>
          <!-- Photo & Thumbmark -->
          <div class="flex flex-col items-center justify-start md:pl-4 mt-4 md:mt-0">
            <label class="block text-sm font-semibold text-gray-700 mb-2">1×1 Picture</label>
            <div class="w-32 h-32 border border-gray-300 rounded-lg flex items-center justify-center mb-3 bg-gray-50 overflow-hidden shadow-sm">
              <img id="photo-preview" src="<?= $photoSrc ?: '' ?>" class="object-cover w-full h-full <?= $photoSrc ? '' : 'hidden' ?>" alt="Photo">
              <span class="text-xs text-gray-400 <?= $photoSrc ? 'hidden' : '' ?>" id="photo-preview-placeholder">Photo</span>
            </div>
            
            <!-- Camera and File Upload Controls -->
            <div class="flex flex-col space-y-2 mb-4">
              <div class="flex space-x-2">
                <button type="button" id="openCameraBtn" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-xs transition-colors">
                  <i class="ri-camera-line mr-1"></i>Camera
                </button>
                <label for="photo" class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-xs cursor-pointer transition-colors inline-flex items-center">
                  <i class="ri-upload-line mr-1"></i>Upload
                </label>
              </div>
              <?php if ($photoSrc): ?>
              <?php endif; ?>
            </div>
            <input name="photo" id="photo" type="file" accept="image/*" class="hidden" onchange="previewPhoto(event,'photo-preview','photo-preview-placeholder')">
            <input name="photo_camera_data" id="photo-camera-data" type="hidden">
            
            <label class="block text-sm font-semibold text-gray-700 mb-2">
              <i class="ri-fingerprint-line text-lg"></i> Fingerprint
            </label>
            <div class="w-32 h-32 border-2 border-dashed border-blue-300 rounded-lg flex items-center justify-center mb-3 bg-white hover:border-blue-400 transition-colors relative shadow-sm">
              <img id="thumb-preview" src="<?= val('thumbmark') ?: '' ?>" class="<?= val('thumbmark') ? '' : 'hidden' ?>" alt="Fingerprint" style="max-width: 100%; max-height: 100%; object-fit: contain; position: absolute;">
              <span class="text-xs text-blue-400 <?= val('thumbmark') ? 'hidden' : '' ?>" id="thumb-placeholder">
                <i class="ri-fingerprint-line text-5xl block mb-1"></i>
                <span class="text-xs font-medium">Scan</span>
              </span>
            </div>
            
            <!-- Fingerprint capture buttons -->
            <div class="flex flex-col space-y-2 mb-4">
              <div class="flex space-x-2">
                <button type="button" id="captureFingerprintBtn" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-xs transition-colors disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                  <i class="ri-fingerprint-line mr-1"></i><span id="captureButtonText">Capture</span>
                </button>
                <label for="thumbmark" class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-xs cursor-pointer transition-colors inline-flex items-center">
                  <i class="ri-upload-line mr-1"></i>Upload
                </label>
              </div>
              <!-- Clear button (always present when there's a fingerprint) -->
              <button type="button" id="clearFingerprintBtn" onclick="clearFingerprint()" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-xs transition-colors <?= val('thumbmark') ? '' : 'hidden' ?>">
                <i class="ri-delete-bin-line mr-1"></i>Clear
              </button>
              <input name="thumbmark" id="thumbmark" type="file" accept="image/*" class="hidden" onchange="previewPhoto(event,'thumb-preview','thumb-placeholder')">
              <input type="hidden" name="thumbmark_camera_data" id="thumbmark-camera-data">
            </div>
            
            <!-- Status and Instructions -->
            <div class="mt-2 p-2 bg-blue-50 border border-blue-200 rounded text-[10px] text-blue-700">
              <p class="font-medium mb-1" id="scannerStatus">🔍 Initializing scanner...</p>
              <p class="text-[9px] text-gray-600" id="scannerInfo">Please wait...</p>
            </div>
            
            <!-- Help Information -->
            <div class="mt-2 p-2 bg-green-50 border border-green-200 rounded text-[10px] text-green-700">
              <p class="font-medium mb-1">💡 Fingerprint Capture Options</p>
              <ol class="list-decimal list-inside space-y-1 text-[9px]">
                <li><strong>Direct Capture:</strong> Click "Capture" button when scanner is ready</li>
                <li><strong>Upload File:</strong> Click "Upload" to select a saved fingerprint image</li>
              </ol>
              <p class="mt-2 text-[9px]">Ensure U.are.U fingerprint scanner is connected via USB</p>
            </div>
          </div>
      </div>
      <hr class="my-4" />
      <!-- Other form sections... (unchanged) -->
      <!-- === Clinic & Date === -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
          <div><label class="block text-xs font-medium text-gray-700 mb-1">Date Today</label><input name="date_today" type="date" value="<?= val('date_today') ?>" class="w-full px-2 py-1 border border-gray-300 rounded"></div>
          <div><label class="block text-xs font-medium text-gray-700 mb-1">Clinician</label><input name="clinician" type="text" readonly value="<?= htmlspecialchars(($user['full_name'] ?? '') ?: val('clinician')) ?>" class="w-full px-2 py-1 border border-gray-300 rounded bg-gray-100"></div>
          <div><label class="block text-xs font-medium text-gray-700 mb-1">Clinic</label>
            <div class="flex items-center space-x-4">
              <?php foreach (['I','II','III','IV'] as $v): ?>
              <label class="flex items-center space-x-1"><input type="radio" name="clinic" value="<?= $v ?>" <?= val('clinic')===$v?'checked':'' ?> class="mr-1"> <?= $v ?></label>
              <?php endforeach; ?>
            </div>
          </div>
      </div>
      <!-- === Complaints & Histories === -->
      <div class="mb-4"><label class="block text-xs font-medium text-gray-700 mb-1">Chief Complaint</label><textarea name="chief_complaint" rows="2" class="w-full px-2 py-1 border border-gray-300 rounded"><?= val('chief_complaint') ?></textarea></div>
      <div class="mb-4"><label class="block text-xs font-medium text-gray-700 mb-1">History of Present Illness</label><textarea name="present_illness" rows="2" class="w-full px-2 py-1 border border-gray-300 rounded"><?= val('present_illness') ?></textarea></div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
          <div><label class="block text-xs font-medium text-gray-700 mb-1">Medical History</label><textarea name="medical_history" rows="3" placeholder="Medications taken (Why?), Past and present illnesses, Last time examined by a physician (Why? Result?), Hospitalization experience, Bleeding tendencies?, Females only (contraceptives, pregnancy, changes in menstrual pattern, breastfeeding?)" class="w-full px-2 py-1 border border-gray-300 rounded"><?= val('medical_history') ?></textarea></div>
          <div><label class="block text-xs font-medium text-gray-700 mb-1">Dental History</label><textarea name="dental_history" rows="3" class="w-full px-2 py-1 border border-gray-300 rounded"><?= val('dental_history') ?></textarea></div>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
          <div><label class="block text-xs font-medium text-gray-700 mb-1">Family History</label><textarea name="family_history" rows="2" class="w-full px-2 py-1 border border-gray-300 rounded"><?= val('family_history') ?></textarea></div>
          <div><label class="block text-xs font-medium text-gray-700 mb-1">Personal and Social History</label><textarea name="personal_history" rows="2" class="w-full px-2 py-1 border border-gray-300 rounded"><?= val('personal_history') ?></textarea></div>
      </div>
      <!-- === Review of Systems === -->
      <div class="mb-4">
          <label class="block text-xs font-medium text-gray-700 mb-1">Review of Systems (do not leave any blanks)</label>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
              <div><label class="block text-xs text-gray-700 mb-1">Skin</label><input name="skin" type="text" value="<?= val('skin') ?>" class="px-2 py-1 border border-gray-300 rounded w-full"></div>
              <div><label class="block text-xs text-gray-700 mb-1">Extremities</label><input name="extremities" type="text" value="<?= val('extremities') ?>" class="px-2 py-1 border border-gray-300 rounded w-full"></div>
              <div><label class="block text-xs text-gray-700 mb-1">Eyes</label><input name="eyes" type="text" value="<?= val('eyes') ?>" class="px-2 py-1 border border-gray-300 rounded w-full"></div>
              <div><label class="block text-xs text-gray-700 mb-1">Ears, nose, throat</label><input name="ent" type="text" value="<?= val('ent') ?>" class="px-2 py-1 border border-gray-300 rounded w-full"></div>
              <div><label class="block text-xs text-gray-700 mb-1">Respiratory</label><input name="respiratory" type="text" value="<?= val('respiratory') ?>" class="px-2 py-1 border border-gray-300 rounded w-full"></div>
              <div><label class="block text-xs text-gray-700 mb-1">Cardiovascular</label><input name="cardiovascular" type="text" value="<?= val('cardiovascular') ?>" class="px-2 py-1 border border-gray-300 rounded w-full"></div>
              <div><label class="block text-xs text-gray-700 mb-1">Gastrointestinal</label><input name="gastrointestinal" type="text" value="<?= val('gastrointestinal') ?>" class="px-2 py-1 border border-gray-300 rounded w-full"></div>
              <div><label class="block text-xs text-gray-700 mb-1">Genitourinary</label><input name="genitourinary" type="text" value="<?= val('genitourinary') ?>" class="px-2 py-1 border border-gray-300 rounded w-full"></div>
              <div><label class="block text-xs text-gray-700 mb-1">Endocrine</label><input name="endocrine" type="text" value="<?= val('endocrine') ?>" class="px-2 py-1 border border-gray-300 rounded w-full"></div>
              <div><label class="block text-xs text-gray-700 mb-1">Hematopoietic</label><input name="hematopoietic" type="text" value="<?= val('hematopoietic') ?>" class="px-2 py-1 border border-gray-300 rounded w-full"></div>
              <div><label class="block text-xs text-gray-700 mb-1">Neurological</label><input name="neurological" type="text" value="<?= val('neurological') ?>" class="px-2 py-1 border border-gray-300 rounded w-full"></div>
              <div><label class="block text-xs text-gray-700 mb-1">Psychiatric</label><input name="psychiatric" type="text" value="<?= val('psychiatric') ?>" class="px-2 py-1 border border-gray-300 rounded w-full"></div>
              <div><label class="block text-xs text-gray-700 mb-1">Growth or tumor</label><input name="growth_or_tumor" type="text" value="<?= val('growth_or_tumor') ?>" class="px-2 py-1 border border-gray-300 rounded w-full"></div>
          </div>
          <label class="block text-xs text-gray-700 mt-2 mb-1">Summary</label><input name="summary" type="text" value="<?= val('summary') ?>" class="w-full px-2 py-1 border border-gray-300 rounded">
      </div>
      <!-- === ASA Classification === -->
      <div class="mb-4">
          <label class="block text-xs font-medium text-gray-700 mb-1">Health Assessment: ASA (encircle)</label>
          <div class="flex items-center space-x-4">
              <?php foreach (['I','II','III','IV'] as $v): ?>
              <label class="flex items-center space-x-1"><input type="radio" name="asa" value="<?= $v ?>" <?= val('asa')===$v?'checked':'' ?> class="mr-1"> <?= $v ?></label>
              <?php endforeach; ?>
          </div>
          <input name="asa_notes" type="text" value="<?= val('asa_notes') ?>" placeholder="Notes" class="w-full mt-2 px-2 py-1 border border-gray-300 rounded">
      </div>

      <!-- === Signature Section (Updated) === -->
      <div class="mb-4">
        <label class="block text-xs font-medium text-gray-700 mb-2">Patient's name and signature</label>
        <div class="w-full max-w-[350px]">
            <div class="signature-pad-container">
                <canvas id="pirSigPad" class="signature-pad"></canvas>
            </div>
            <button type="button" onclick="pirPad.clear()" class="text-xs text-red-600 mt-1">Clear Signature</button>
            <input type="hidden" name="old_data_privacy_signature_path" value="<?= val('data_privacy_signature_path') ?>">
        </div>
              <div class="w-full max-w-[350px] mt-2">
          <input name="patient_signature" 
                id="printedName" 
                type="text" 
                readonly 
                class="w-full px-2 py-1 border border-gray-300 rounded bg-gray-100">
          <label class="block text-xs font-medium text-gray-700 mt-1">Printed Name</label>
      </div>
      </div>
    </div>

    <!-- Navigation -->
    <div class="mt-6 flex justify-end">
      <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-button whitespace-nowrap">
        Save & Next
      </button>
    </div>
  </form>
</div>

<!-- Camera Capture Modal -->
<div id="cameraModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
  <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
    <div class="flex justify-between items-center mb-4">
      <h3 class="text-lg font-semibold text-gray-800">Capture Patient Photo</h3>
      <button id="closeCameraBtn" class="text-gray-500 hover:text-gray-700">
        <i class="ri-close-line text-xl"></i>
      </button>
    </div>
    
    <!-- Camera Stream -->
    <div class="relative mb-4">
      <video id="cameraStream" class="w-full h-64 bg-gray-900 rounded" autoplay muted playsinline></video>
      <div id="cameraError" class="hidden absolute inset-0 flex items-center justify-center bg-gray-200 rounded">
        <div class="text-center text-gray-600">
          <i class="ri-camera-off-line text-4xl mb-2"></i>
          <p class="text-sm">Camera not available</p>
        </div>
      </div>
    </div>
    
    <!-- Capture Controls -->
    <div class="flex justify-center space-x-4">
      <button id="captureBtn" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded flex items-center">
        <i class="ri-camera-line mr-2"></i>Capture
      </button>
      <button id="cancelCameraBtn" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded flex items-center">
        <i class="ri-close-line mr-2"></i>Cancel
      </button>
    </div>
    
    <!-- Processing indicator -->
    <div id="processingIndicator" class="hidden mt-4 text-center">
      <div class="inline-flex items-center px-4 py-2 bg-blue-100 rounded-lg">
        <div class="animate-spin rounded-full h-4 w-4 border-2 border-blue-600 border-t-transparent mr-2"></div>
        <span class="text-blue-600 text-sm">Processing image...</span>
      </div>
    </div>
    
    <!-- Preview captured image -->
    <div id="capturePreview" class="hidden mt-4">
      <div class="space-y-3">
        <!-- Background removal toggle -->
        <div class="flex items-center justify-center space-x-3 bg-gray-50 p-3 rounded">
          <label class="flex items-center space-x-2 cursor-pointer">
            <input type="checkbox" id="removeBackgroundToggle" class="form-checkbox h-4 w-4 text-blue-600" checked>
            <span class="text-sm text-gray-700">Professional background removal (powered by remove.bg)</span>
            <i class="ri-magic-line text-blue-500"></i>
          </label>
        </div>
        
        <!-- Canvas for displaying processed image -->
        <canvas id="captureCanvas" class="w-full rounded border"></canvas>
        
        <!-- Action buttons -->
        <div class="flex justify-center space-x-3">
          <button id="retakeBtn" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded text-sm">
            <i class="ri-refresh-line mr-1"></i>Retake
          </button>
          <button id="processBtn" class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded text-sm">
            <i class="ri-magic-line mr-1"></i>Process
          </button>
          <button id="usePhotoBtn" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded text-sm">
            <i class="ri-check-line mr-1"></i>Use Photo
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Fingerprint Capture Modal -->
<div id="fingerprintModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
  <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
    <div class="flex justify-between items-center mb-4">
      <h3 class="text-lg font-semibold text-gray-800">Capture Fingerprint</h3>
      <button id="closeFingerprintBtn" class="text-gray-500 hover:text-gray-700">
        <i class="ri-close-line text-xl"></i>
      </button>
    </div>
    
    <!-- Fingerprint Preview -->
    <div class="relative mb-4 flex items-center justify-center">
      <div class="w-64 h-64 border-2 border-blue-300 rounded-lg bg-gray-50 flex items-center justify-center overflow-hidden">
        <img id="fingerprintPreview" class="w-full h-full object-contain hidden" alt="Fingerprint">
        <div id="fingerprintPlaceholder" class="text-center text-gray-400">
          <i class="ri-fingerprint-line text-6xl mb-2"></i>
          <p class="text-sm">Place finger on scanner</p>
        </div>
      </div>
    </div>
    
    <!-- Status Message -->
    <div id="fingerprintStatus" class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded text-sm text-blue-700 text-center">
      <i class="ri-information-line mr-1"></i>
      <span id="fingerprintStatusText">Ready to capture</span>
    </div>
    
    <!-- Quality Indicator -->
    <div id="qualityIndicator" class="hidden mb-4">
      <div class="flex items-center justify-between mb-1">
        <span class="text-xs text-gray-600">Quality:</span>
        <span id="qualityValue" class="text-xs font-semibold">0%</span>
      </div>
      <div class="w-full bg-gray-200 rounded-full h-2">
        <div id="qualityBar" class="bg-blue-600 h-2 rounded-full transition-all" style="width: 0%"></div>
      </div>
    </div>
    
    <!-- Capture Controls -->
    <div class="flex justify-center space-x-4">
      <button id="startCaptureFingerprintBtn" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded flex items-center">
        <i class="ri-fingerprint-line mr-2"></i>
        <span id="captureBtnText">Start Capture</span>
      </button>
      <button id="useFingerprintBtn" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded flex items-center hidden">
        <i class="ri-check-line mr-2"></i>Use Fingerprint
      </button>
      <button id="cancelFingerprintBtn" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded flex items-center">
        <i class="ri-close-line mr-2"></i>Cancel
      </button>
    </div>
  </div>
</div>

<script>
// DigitalPersona Fingerprint Scanner Integration using @digitalpersona/devices
class FingerprintScanner {
  constructor() {
    this.reader = null;
    this.currentSample = null;
    this.isCapturing = false;
    this.isInitialized = false;
    this.init();
  }
  
  init() {
    // Check if DigitalPersona libraries are loaded
    if (typeof dp === 'undefined' || typeof dp.devices === 'undefined') {
      this.updateStatus('error', 'DigitalPersona libraries not loaded. Please refresh the page.');
      document.getElementById('captureFingerprintBtn').disabled = true;
      return;
    }
    
    this.initializeDevice();
  }
  
  initializeDevice() {
    try {
      this.updateStatus('info', 'Initializing fingerprint reader...');
      
      // Create FingerprintReader instance
      this.reader = new dp.devices.FingerprintReader();
      
      // Set up event handlers
      this.reader.on('DeviceConnected', (event) => {
        console.log('Device connected:', event.deviceId);
        this.isInitialized = true;
        this.updateStatus('success', 'Scanner ready: ' + event.deviceId);
        document.getElementById('captureFingerprintBtn').disabled = false;
      });
      
      this.reader.on('DeviceDisconnected', (event) => {
        console.log('Device disconnected:', event.deviceId);
        this.updateStatus('error', 'Scanner disconnected');
      });
      
      this.reader.on('SamplesAcquired', (event) => {
        console.log('Samples acquired:', event);
        this.handleSamplesAcquired(event);
      });
      
      this.reader.on('QualityReported', (event) => {
        console.log('Quality reported:', event.quality);
        this.updateModalStatus('info', `Scan quality: ${event.quality}%`);
        this.showQuality(event.quality);
      });
      
      this.reader.on('AcquisitionStarted', (event) => {
        console.log('Acquisition started:', event.deviceId);
        this.updateModalStatus('info', 'Place your finger on the scanner...');
      });
      
      this.reader.on('AcquisitionStopped', (event) => {
        console.log('Acquisition stopped:', event.deviceId);
      });
      
      this.reader.on('ErrorOccurred', (event) => {
        console.error('Reader error:', event.error);
        this.updateModalStatus('error', 'Error: ' + event.error);
      });
      
      this.reader.on('CommunicationFailed', () => {
        this.isInitialized = false;
        this.updateStatus('error', 'Communication failed - check DigitalPersona service');
        document.getElementById('captureFingerprintBtn').disabled = true;
      });
      
      // List devices to verify connection
      this.reader.enumerateDevices()
        .then(devices => {
          console.log('Available devices:', devices);
          if (devices && devices.length > 0) {
            this.isInitialized = true;
            this.updateStatus('success', `Scanner ready (${devices.length} device(s) found)`);
            document.getElementById('captureFingerprintBtn').disabled = false;
          } else {
            this.updateStatus('error', 'No fingerprint readers detected');
          }
        })
        .catch(error => {
          console.error('Failed to enumerate devices:', error);
          this.updateStatus('error', 'Failed to connect: ' + error.message);
        });
      
    } catch (error) {
      console.error('Fingerprint scanner initialization error:', error);
      this.updateStatus('error', 'Failed to initialize scanner: ' + error.message);
      document.getElementById('captureFingerprintBtn').disabled = true;
    }
  }
  
  updateStatus(type, message) {
    const statusEl = document.getElementById('scannerStatus');
    const infoEl = document.getElementById('scannerInfo');
    
    const icons = {
      success: '✅',
      error: '❌',
      info: '🔍',
      warning: '⚠️'
    };
    
    statusEl.textContent = `${icons[type] || icons.info} ${type === 'success' ? 'Scanner Ready' : type === 'error' ? 'Scanner Error' : 'Initializing'}`;
    infoEl.textContent = message;
  }
  
  openCaptureModal() {
    if (!this.isInitialized) {
      alert('Fingerprint scanner is not ready. Please check your device connection.');
      return;
    }
    
    const modal = document.getElementById('fingerprintModal');
    modal.classList.remove('hidden');
    this.resetCapture();
    this.updateModalStatus('info', 'Ready to capture fingerprint');
  }
  
  closeCaptureModal() {
    const modal = document.getElementById('fingerprintModal');
    modal.classList.add('hidden');
    this.resetCapture();
  }
  
  async startCapture() {
    if (this.isCapturing) {
      return;
    }
    
    if (!this.isInitialized || !this.reader) {
      alert('Fingerprint scanner is not ready. Please check that:\n1. DigitalPersona Lite Client is installed\n2. DigitalPersona service is running\n3. Scanner device is connected via USB');
      return;
    }
    
    this.isCapturing = true;
    const captureBtn = document.getElementById('startCaptureFingerprintBtn');
    const btnText = document.getElementById('captureBtnText');
    
    captureBtn.disabled = true;
    btnText.textContent = 'Capturing...';
    
    try {
      // Start fingerprint acquisition with PngImage format
      await this.reader.startAcquisition(dp.devices.SampleFormat.PngImage);
      this.updateModalStatus('info', 'Acquisition started. Place your finger on the scanner...');
      console.log('Fingerprint acquisition started');
    } catch (error) {
      console.error('Failed to start acquisition:', error);
      this.updateModalStatus('error', 'Failed to start capture: ' + error.message);
      this.isCapturing = false;
      captureBtn.disabled = false;
      btnText.textContent = 'Start Capture';
    }
  }
  
  handleSamplesAcquired(event) {
    console.log('handleSamplesAcquired called:', event);
    console.log('Samples:', event.samples);
    console.log('Sample format:', event.sampleFormat);
    
    if (!event.samples || event.samples.length === 0) {
      this.updateModalStatus('error', 'No samples received');
      return;
    }
    
    // Get the first sample
    let sample = event.samples[0];
    console.log('Sample data length (original):', sample.length);
    console.log('Sample preview (first 100 chars):', sample.substring(0, 100));
    console.log('Sample preview (last 100 chars):', sample.substring(sample.length - 100));
    
    // Step 1: Remove only whitespace characters (preserve all base64 characters)
    const originalLength = sample.length;
    sample = sample.replace(/\s/g, ''); // Only remove whitespace
    console.log('Sample data length (after whitespace removal):', sample.length);
    console.log('Whitespace characters removed:', originalLength - sample.length);
    
    // Step 2: Convert URL-safe Base64 to standard Base64
    // DigitalPersona returns URL-safe Base64 (using '-' and '_')
    // Browsers require standard Base64 (using '+' and '/')
    console.log('Converting URL-safe Base64 to standard Base64...');
    const beforeConversion = sample.substring(0, 100);
    sample = sample.replace(/-/g, '+').replace(/_/g, '/');
    console.log('Before conversion (first 100 chars):', beforeConversion);
    console.log('After conversion (first 100 chars):', sample.substring(0, 100));
    
    // Step 3: Validate the converted base64 string
    const base64Regex = /^[A-Za-z0-9+\/]+={0,2}$/;
    if (!base64Regex.test(sample)) {
      console.error('❌ Base64 string still contains invalid characters after conversion!');
      const invalidChars = sample.match(/[^A-Za-z0-9+\/=]/g);
      if (invalidChars) {
        console.error('Invalid characters found:', invalidChars.slice(0, 10));
        console.error('Total invalid chars:', invalidChars.length);
      }
      this.updateModalStatus('error', 'Invalid base64 data from scanner');
      return;
    }
    
    console.log('✅ Base64 string is now valid standard Base64');
    
    // Validate base64 string length
    if (sample.length === 0) {
      this.updateModalStatus('error', 'Invalid sample data received');
      console.error('Empty sample after cleaning');
      return;
    }
    
    // Check if sample starts with PNG signature (base64 encoded)
    // Standard Base64 PNG starts with "iVBORw0KGgo" (the PNG magic bytes)
    if (sample.startsWith('iVBORw0KGgo')) {
      console.log('✅ PNG signature detected at start of base64 data');
    } else {
      console.warn('⚠️ PNG signature not detected - data may be corrupted');
      console.log('First 20 chars of base64:', sample.substring(0, 20));
    }
    
    // Create standard base64 data URI for PNG image
    const imageUrl = `data:image/png;base64,${sample}`;
    console.log('Created data URI, total length:', imageUrl.length);
    
    // Re-encode through canvas to ensure browser compatibility
    // This also handles any remaining edge cases with the image data
    this.reencodeImageThroughCanvas(imageUrl);
  }
  
  reencodeImageThroughCanvas(imageUrl) {
    console.log('Re-encoding image through canvas for browser compatibility...');
    
    // Create a temporary image to load the scanner data
    const tempImg = new Image();
    
    tempImg.onload = () => {
      console.log('✅ Scanner image loaded, dimensions:', tempImg.width, 'x', tempImg.height);
      
      try {
        // Create canvas and re-encode to clean PNG
        const canvas = document.createElement('canvas');
        canvas.width = tempImg.width;
        canvas.height = tempImg.height;
        
        const ctx = canvas.getContext('2d');
        
        // Fill with white background first (in case PNG has transparency issues)
        ctx.fillStyle = '#FFFFFF';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        
        // Draw the fingerprint
        ctx.drawImage(tempImg, 0, 0);
        
        // Convert to clean base64 PNG
        const cleanImageUrl = canvas.toDataURL('image/png');
        console.log('✅ Re-encoded image, new size:', cleanImageUrl.length);
        
        // Store the clean version
        this.currentSample = cleanImageUrl;
        
        // Display fingerprint in modal
        const preview = document.getElementById('fingerprintPreview');
        const placeholder = document.getElementById('fingerprintPlaceholder');
        
        preview.src = cleanImageUrl;
        preview.classList.remove('hidden');
        placeholder.classList.add('hidden');
        
        this.updateModalStatus('success', 'Fingerprint captured successfully!');
        
        // Show use button
        document.getElementById('useFingerprintBtn').classList.remove('hidden');
        
        const btnText = document.getElementById('captureBtnText');
        btnText.textContent = 'Recapture';
        
        // Re-enable capture button
        const captureBtn = document.getElementById('startCaptureFingerprintBtn');
        captureBtn.disabled = false;
        this.isCapturing = false;
        
        // Stop acquisition
        this.stopCapture();
        
      } catch (error) {
        console.error('Error re-encoding image:', error);
        this.updateModalStatus('error', 'Failed to process fingerprint image');
      }
    };
    
    tempImg.onerror = (e) => {
      console.error('❌ Failed to load scanner image for re-encoding');
      console.error('Original base64 length:', imageUrl.length);
      console.error('Error:', e);
      
      // Try to decode and diagnose the issue
      const base64Match = imageUrl.match(/^data:image\/png;base64,(.+)$/);
      if (base64Match) {
        const base64Data = base64Match[1];
        console.log('Base64 data length:', base64Data.length);
        console.log('First 50 chars:', base64Data.substring(0, 50));
        console.log('Last 50 chars:', base64Data.substring(base64Data.length - 50));
        
        // Check for invalid characters
        const invalidChars = base64Data.match(/[^A-Za-z0-9+/=]/g);
        if (invalidChars) {
          console.error('Invalid base64 characters found:', invalidChars.length);
          console.error('Sample invalid chars:', invalidChars.slice(0, 20));
        }
        
        // Try to decode a portion
        try {
          const testDecode = atob(base64Data.substring(0, Math.min(1000, base64Data.length)));
          console.log('Partial decode successful, first 100 bytes:', testDecode.substring(0, 100));
        } catch (decodeErr) {
          console.error('Base64 decode failed:', decodeErr);
        }
      }
      
      this.updateModalStatus('error', 'Failed to process fingerprint image. The PNG data from scanner is corrupted or incompatible. Please try capturing again or use "Upload from File" option.');
      
      // Re-enable capture button so user can retry
      const captureBtn = document.getElementById('startCaptureFingerprintBtn');
      captureBtn.disabled = false;
      this.isCapturing = false;
    };
    
    // Set the source to trigger loading
    tempImg.src = imageUrl;
  }
  
  async stopCapture() {
    try {
      if (this.reader) {
        await this.reader.stopAcquisition();
        console.log('Fingerprint acquisition stopped');
      }
      this.isCapturing = false;
    } catch (error) {
      console.error('Error stopping capture:', error);
    }
  }
  
  useFingerprint() {
    if (!this.currentSample) {
      alert('No fingerprint captured yet.');
      return;
    }
    
    console.log('useFingerprint called, sample length:', this.currentSample.length);
    console.log('Sample preview:', this.currentSample.substring(0, 50));
    
    // currentSample is already a base64 data URL from the scanner
    const imageUrl = this.currentSample;
    
    // Update preview in form
    const thumbPreview = document.getElementById('thumb-preview');
    const thumbPlaceholder = document.getElementById('thumb-placeholder');
    const clearBtn = document.getElementById('clearFingerprintBtn');
    
    // Clear any previous handlers
    thumbPreview.onload = null;
    thumbPreview.onerror = null;
    
    // Set up load and error handlers BEFORE setting src
    thumbPreview.onload = function() {
      console.log('✅ Fingerprint image loaded successfully in form preview!');
      console.log('Image dimensions:', thumbPreview.naturalWidth, 'x', thumbPreview.naturalHeight);
      // Simply toggle visibility classes - no conflicting inline styles
      thumbPreview.classList.remove('hidden');
      thumbPlaceholder.classList.add('hidden');
      clearBtn.classList.remove('hidden');
      
      // Verify it's actually visible
      const rect = thumbPreview.getBoundingClientRect();
      console.log('Image position:', rect);
      console.log('Image computed style display:', window.getComputedStyle(thumbPreview).display);
      console.log('Image is visible:', rect.width > 0 && rect.height > 0);
    };
    
    thumbPreview.onerror = function(e) {
      console.error('❌ Failed to load fingerprint image in form preview');
      console.error('Image src length:', thumbPreview.src.length);
      console.error('Error event:', e);
      console.error('Trying to diagnose issue...');
      
      // Try to extract base64 and test it
      const base64Match = thumbPreview.src.match(/^data:image\/png;base64,(.+)$/);
      if (base64Match) {
        try {
          const base64Data = base64Match[1];
          const testDecode = atob(base64Data.substring(0, 100));
          console.log('Base64 appears valid, test decode successful');
        } catch (decodeError) {
          console.error('Base64 decode failed:', decodeError);
        }
      }
      
      // Still try to show it
      thumbPreview.classList.remove('hidden');
      thumbPlaceholder.classList.add('hidden');
    };
    
    console.log('Setting image src...');
    // Now set the source to trigger load
    thumbPreview.src = imageUrl;
    
    console.log('Image element:', thumbPreview);
    console.log('Image src set to:', thumbPreview.src.substring(0, 50));
    console.log('Image current classes:', thumbPreview.className);
    
    // Store in hidden field for form submission
    document.getElementById('thumbmark-camera-data').value = imageUrl;
    document.getElementById('thumbmark').value = ''; // Clear file input
    
    // Close modal
    this.closeCaptureModal();
    
    // Show success message immediately (don't wait for image load)
    console.log('✅ Fingerprint data stored successfully!');
    
    // Double-check after a brief delay
    setTimeout(() => {
      console.log('--- Post-capture verification ---');
      console.log('Image complete:', thumbPreview.complete);
      console.log('Image naturalWidth:', thumbPreview.naturalWidth);
      console.log('Image naturalHeight:', thumbPreview.naturalHeight);
      console.log('Image displayed:', thumbPreview.offsetWidth > 0 && thumbPreview.offsetHeight > 0);
      console.log('Image classList:', thumbPreview.classList.toString());
      
      if (thumbPreview.complete && thumbPreview.naturalHeight > 0) {
        console.log('✅✅ SUCCESS: Fingerprint is fully loaded and should be visible');
      } else {
        console.warn('⚠️ WARNING: Image may not have loaded correctly');
        // Try one more time to ensure visibility
        thumbPreview.classList.remove('hidden');
        thumbPlaceholder.classList.add('hidden');
      }
    }, 300);
  }
  
  resetCapture() {
    this.currentSample = null;
    this.isCapturing = false;
    
    const preview = document.getElementById('fingerprintPreview');
    const placeholder = document.getElementById('fingerprintPlaceholder');
    const useBtn = document.getElementById('useFingerprintBtn');
    const btnText = document.getElementById('captureBtnText');
    const qualityIndicator = document.getElementById('qualityIndicator');
    
    preview.classList.add('hidden');
    placeholder.classList.remove('hidden');
    useBtn.classList.add('hidden');
    btnText.textContent = 'Start Capture';
    qualityIndicator.classList.add('hidden');
  }
  
  showQuality(quality) {
    const indicator = document.getElementById('qualityIndicator');
    const valueEl = document.getElementById('qualityValue');
    const barEl = document.getElementById('qualityBar');
    
    indicator.classList.remove('hidden');
    valueEl.textContent = quality + '%';
    barEl.style.width = quality + '%';
    
    // Color based on quality
    if (quality >= 80) {
      barEl.className = 'bg-green-600 h-2 rounded-full transition-all';
    } else if (quality >= 60) {
      barEl.className = 'bg-yellow-600 h-2 rounded-full transition-all';
    } else {
      barEl.className = 'bg-red-600 h-2 rounded-full transition-all';
    }
  }
  
  updateModalStatus(type, message) {
    const statusEl = document.getElementById('fingerprintStatus');
    const textEl = document.getElementById('fingerprintStatusText');
    
    const classes = {
      success: 'bg-green-50 border-green-200 text-green-700',
      error: 'bg-red-50 border-red-200 text-red-700',
      info: 'bg-blue-50 border-blue-200 text-blue-700',
      warning: 'bg-yellow-50 border-yellow-200 text-yellow-700'
    };
    
    statusEl.className = `mb-4 p-3 border rounded text-sm text-center ${classes[type] || classes.info}`;
    textEl.textContent = message;
  }
  
}

// Note: arrayBufferToBase64 method removed as it's now handled by the DigitalPersonaFingerprint library

// Initialize fingerprint scanner
let fingerprintScanner = null;
document.addEventListener('DOMContentLoaded', () => {
  fingerprintScanner = new FingerprintScanner();
  
  // Bind fingerprint capture button
  document.getElementById('captureFingerprintBtn').addEventListener('click', () => {
    fingerprintScanner.openCaptureModal();
  });
  
  // Bind fingerprint modal buttons
  document.getElementById('closeFingerprintBtn').addEventListener('click', () => {
    fingerprintScanner.closeCaptureModal();
  });
  
  document.getElementById('cancelFingerprintBtn').addEventListener('click', () => {
    fingerprintScanner.closeCaptureModal();
  });
  
  document.getElementById('startCaptureFingerprintBtn').addEventListener('click', () => {
    fingerprintScanner.startCapture();
  });
  
  document.getElementById('useFingerprintBtn').addEventListener('click', () => {
    fingerprintScanner.useFingerprint();
  });
});

// Camera Capture with Remove.bg API Background Removal
class CameraCapture {
  constructor() {
    this.stream = null;
    this.capturedImageData = null;
    this.originalImageData = null;
    this.isProcessing = false;
    this.tempPhotoUrls = []; // Track temporary photo URLs for cleanup
    this.finalPhotoPath = null; // Track the final photo path that should be kept
    this.init();
  }

  init() {
    // Get DOM elements
    this.modal = document.getElementById('cameraModal');
    this.video = document.getElementById('cameraStream');
    this.canvas = document.getElementById('captureCanvas');
    this.ctx = this.canvas.getContext('2d');
    this.cameraError = document.getElementById('cameraError');
    this.capturePreview = document.getElementById('capturePreview');
    this.processingIndicator = document.getElementById('processingIndicator');

    // Bind event listeners
    document.getElementById('openCameraBtn').addEventListener('click', () => this.openCamera());
    document.getElementById('closeCameraBtn').addEventListener('click', () => this.closeCamera());
    document.getElementById('cancelCameraBtn').addEventListener('click', () => this.closeCamera());
    document.getElementById('captureBtn').addEventListener('click', () => this.capturePhoto());
    document.getElementById('retakeBtn').addEventListener('click', () => this.retakePhoto());
    document.getElementById('processBtn').addEventListener('click', () => this.processImage());
    document.getElementById('usePhotoBtn').addEventListener('click', () => this.usePhoto());
    document.getElementById('clearPhotoBtn')?.addEventListener('click', () => this.clearPhoto());
    document.getElementById('removeBackgroundToggle').addEventListener('change', () => this.toggleBackgroundRemoval());
  }

  // Remove.bg API doesn't require model loading
  // API is ready immediately

  async openCamera() {
    try {
      // Request camera access
      this.stream = await navigator.mediaDevices.getUserMedia({
        video: {
          width: { ideal: 640 },
          height: { ideal: 480 },
          facingMode: 'user' // Front camera preferred for patient photos
        }
      });
      
      this.video.srcObject = this.stream;
      this.modal.classList.remove('hidden');
      this.cameraError.classList.add('hidden');
      this.video.classList.remove('hidden');
      this.capturePreview.classList.add('hidden');
      this.processingIndicator.classList.add('hidden');
      
    } catch (error) {
      console.error('Camera access error:', error);
      this.showCameraError();
    }
  }

  closeCamera() {
    if (this.stream) {
      this.stream.getTracks().forEach(track => track.stop());
      this.stream = null;
    }
    this.modal.classList.add('hidden');
    this.capturePreview.classList.add('hidden');
    this.processingIndicator.classList.add('hidden');
    this.video.classList.remove('hidden');
    
    // Clean up temporary photos if user didn't use any photo
    if (!this.finalPhotoPath && this.tempPhotoUrls.length > 0) {
      this.cleanupTemporaryPhotos();
    }
    
    this.capturedImageData = null;
    this.originalImageData = null;
  }

  showCameraError() {
    this.modal.classList.remove('hidden');
    this.cameraError.classList.remove('hidden');
    this.video.classList.add('hidden');
  }

  capturePhoto() {
    const videoActualWidth = this.video.videoWidth;
    const videoActualHeight = this.video.videoHeight;
    
    // Set canvas size to capture square photo (1:1 aspect ratio)
    const size = Math.min(videoActualWidth, videoActualHeight);
    this.canvas.width = size;
    this.canvas.height = size;
    
    // Calculate the position to center the square capture
    const x = (videoActualWidth - size) / 2;
    const y = (videoActualHeight - size) / 2;
    
    // Draw the captured frame
    this.ctx.drawImage(this.video, x, y, size, size, 0, 0, size, size);
    
    // Store original image data (before processing)
    this.originalImageData = this.canvas.toDataURL('image/jpeg', 0.8);
    this.capturedImageData = this.originalImageData;
    
    // Show preview and automatically process if background removal is enabled
    this.video.classList.add('hidden');
    this.capturePreview.classList.remove('hidden');
    
    // Auto-process if background removal is enabled
    const removeBackground = document.getElementById('removeBackgroundToggle').checked;
    if (removeBackground) {
      setTimeout(() => this.processImage(), 100);
    }
  }

  async processImage() {
    if (this.isProcessing) {
      alert('Processing already in progress. Please wait.');
      return;
    }
    
    if (!this.originalImageData) {
      alert('No image to process. Please capture a photo first.');
      return;
    }
    
    const removeBackground = document.getElementById('removeBackgroundToggle').checked;
    
    if (!removeBackground) {
      // If background removal is off, use original image
      const img = new Image();
      img.onload = () => {
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        this.ctx.drawImage(img, 0, 0);
        this.capturedImageData = this.canvas.toDataURL('image/jpeg', 0.8);
      };
      img.src = this.originalImageData;
      return;
    }
    
    // Show processing indicator
    this.showProcessingOverlay('Removing background with AI...\nThis may take a few seconds');
    this.isProcessing = true;
    
    try {
      // Convert base64 to blob for API call
      const response = await fetch(this.originalImageData);
      const blob = await response.blob();
      
      // Call remove.bg API
      const processedBlob = await this.callRemoveBgAPI(blob);
      
      // Create processed image URL
      const processedImageUrl = URL.createObjectURL(processedBlob);
      
      // Create image with white background
      const processedImg = new Image();
      processedImg.onload = () => {
        // Clear canvas and fill with white background
        this.ctx.fillStyle = '#FFFFFF';
        this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
        
        // Draw the processed image (transparent background removed)
        this.ctx.drawImage(processedImg, 0, 0, this.canvas.width, this.canvas.height);
        
        // Update captured image data
        this.capturedImageData = this.canvas.toDataURL('image/jpeg', 0.8);
        
        // Clean up
        URL.revokeObjectURL(processedImageUrl);
        this.hideProcessingOverlay();
        this.isProcessing = false;
      };
      processedImg.src = processedImageUrl;
      
    } catch (error) {
      console.error('Error processing image with remove.bg:', error);
      this.hideProcessingOverlay();
      this.isProcessing = false;
      alert('Error processing image: ' + error.message + '. Using original image.');
      
      // Fallback to original image
      const img = new Image();
      img.onload = () => {
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        this.ctx.drawImage(img, 0, 0);
        this.capturedImageData = this.canvas.toDataURL('image/jpeg', 0.8);
      };
      img.src = this.originalImageData;
    }
  }
  
  // Remove.bg API call through PHP backend
  async callRemoveBgAPI(imageBlob) {
    const formData = new FormData();
    formData.append('image', imageBlob, 'image.jpg');
    
    const response = await fetch('api/remove_bg.php', {
      method: 'POST',
      body: formData
    });
    
    const result = await response.json();
    
    if (!result.success) {
      throw new Error(result.error || 'Background removal failed');
    }
    
    // Track this as a temporary photo for potential cleanup
    this.tempPhotoUrls.push(result.image_path);
    
    // Fetch the processed image from the saved path
    const imageResponse = await fetch(result.image_path);
    if (!imageResponse.ok) {
      throw new Error('Failed to load processed image');
    }
    
    return await imageResponse.blob();
  }
  
  // Show processing overlay
  showProcessingOverlay(message) {
    // Remove existing overlay if any
    this.hideProcessingOverlay();
    
    const overlay = document.createElement('div');
    overlay.id = 'processingOverlay';
    overlay.className = 'processing-overlay';
    overlay.innerHTML = `
      <div class="processing-content">
        <div class="animate-spin rounded-full h-12 w-12 border-4 border-blue-500 border-t-transparent mx-auto mb-4"></div>
        <h3 class="text-lg font-semibold text-gray-800 mb-2">Processing Image</h3>
        <p class="text-gray-600 whitespace-pre-line">${message}</p>
      </div>
    `;
    
    document.body.appendChild(overlay);
  }
  
  // Hide processing overlay
  hideProcessingOverlay() {
    const overlay = document.getElementById('processingOverlay');
    if (overlay) {
      overlay.remove();
    }
  }
  
  // Schedule cleanup of temporary photos
  scheduleTemporaryCleanup() {
    // Clean up after a short delay to ensure final photo is saved
    setTimeout(() => {
      this.cleanupTemporaryPhotos();
    }, 2000);
  }
  
  // Clean up temporary photos that weren't used
  async cleanupTemporaryPhotos() {
    if (this.tempPhotoUrls.length === 0) {
      return;
    }
    
    try {
      // Send cleanup request to server
      const response = await fetch('api/cleanup_temp_photos.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          temp_files: this.tempPhotoUrls,
          keep_file: this.finalPhotoPath
        })
      });
      
      if (response.ok) {
        const result = await response.json();
        if (result.success) {
          console.log(`Cleaned up ${result.deleted_count} temporary photos, freed ${result.freed_space}`);
        }
      }
    } catch (error) {
      console.error('Error cleaning up temporary photos:', error);
    }
    
    // Clear the temporary URLs list
    this.tempPhotoUrls = [];
  }
  
  toggleBackgroundRemoval() {
    if (this.originalImageData) {
      this.processImage();
    }
  }

  retakePhoto() {
    this.video.classList.remove('hidden');
    this.capturePreview.classList.add('hidden');
    this.processingIndicator.classList.add('hidden');
    this.capturedImageData = null;
    this.originalImageData = null;
  }

  usePhoto() {
    if (this.capturedImageData) {
      // Update the photo preview in the form
      const photoPreview = document.getElementById('photo-preview');
      const placeholder = document.getElementById('photo-preview-placeholder');
      
      photoPreview.src = this.capturedImageData;
      photoPreview.classList.remove('hidden');
      placeholder.classList.add('hidden');
      
      // Store the image data in hidden input for processing
      document.getElementById('photo-camera-data').value = this.capturedImageData;
      
      // Clear the file input as we're using camera data
      document.getElementById('photo').value = '';
      
      // Mark this as the final photo (for cleanup purposes)
      this.finalPhotoPath = this.capturedImageData;
      
      // Show clear button
      this.showClearButton();
      
      // Schedule cleanup of unused temporary photos
      this.scheduleTemporaryCleanup();
    }
    
    this.closeCamera();
  }

  clearPhoto() {
    const photoPreview = document.getElementById('photo-preview');
    const placeholder = document.getElementById('photo-preview-placeholder');
    
    photoPreview.src = '';
    photoPreview.classList.add('hidden');
    placeholder.classList.remove('hidden');
    
    // Clear both file input and camera data
    document.getElementById('photo').value = '';
    document.getElementById('photo-camera-data').value = '';
    
    // Clean up any temporary photos since we're clearing everything
    if (this.tempPhotoUrls.length > 0) {
      this.cleanupTemporaryPhotos();
    }
    
    // Clear captured image data
    this.capturedImageData = null;
    this.originalImageData = null;
    this.finalPhotoPath = null;
    
    // Hide clear button
    this.hideClearButton();
  }

  showClearButton() {
    const clearBtn = document.getElementById('clearPhotoBtn');
    if (!clearBtn) {
      // Create clear button if it doesn't exist
      const buttonContainer = document.querySelector('.flex.flex-col.space-y-2.mb-4');
      const newClearBtn = document.createElement('button');
      newClearBtn.type = 'button';
      newClearBtn.id = 'clearPhotoBtn';
      newClearBtn.className = 'bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-xs transition-colors';
      newClearBtn.innerHTML = '<i class="ri-delete-bin-line mr-1"></i>Clear';
      newClearBtn.addEventListener('click', () => this.clearPhoto());
      buttonContainer.appendChild(newClearBtn);
    } else {
      clearBtn.style.display = 'block';
    }
  }

  hideClearButton() {
    const clearBtn = document.getElementById('clearPhotoBtn');
    if (clearBtn) {
      clearBtn.style.display = 'none';
    }
  }
  
  // Method to process uploaded images with remove.bg API
  async processUploadedImage(sourceCanvas) {
    try {
      // Convert canvas to blob
      const blob = await new Promise(resolve => {
        sourceCanvas.toBlob(resolve, 'image/jpeg', 0.8);
      });
      
      // Call remove.bg API
      const processedBlob = await this.callRemoveBgAPI(blob);
      
      // Create result canvas with white background
      const resultCanvas = document.createElement('canvas');
      const resultCtx = resultCanvas.getContext('2d');
      resultCanvas.width = sourceCanvas.width;
      resultCanvas.height = sourceCanvas.height;
      
      // Fill with white background
      resultCtx.fillStyle = '#FFFFFF';
      resultCtx.fillRect(0, 0, resultCanvas.width, resultCanvas.height);
      
      // Load processed image and draw on white background
      const processedImg = new Image();
      const processedImageUrl = URL.createObjectURL(processedBlob);
      
      return new Promise((resolve) => {
        processedImg.onload = () => {
          resultCtx.drawImage(processedImg, 0, 0, resultCanvas.width, resultCanvas.height);
          URL.revokeObjectURL(processedImageUrl);
          resolve(resultCanvas.toDataURL('image/jpeg', 0.8));
        };
        processedImg.src = processedImageUrl;
      });
      
    } catch (error) {
      console.error('Error processing uploaded image:', error);
      throw error;
    }
  }
}

// Initialize camera capture when page loads
document.addEventListener('DOMContentLoaded', () => {
  const cameraCapture = new CameraCapture();
  
  // Make cameraCapture globally accessible for file upload processing
  window.cameraCapture = cameraCapture;
  
  // Update button to show remove.bg API is ready
  const btn = document.getElementById('openCameraBtn');
  btn.innerHTML = '<i class="ri-camera-line mr-1"></i>Open Camera';
  btn.disabled = false;
  btn.className = 'bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-xs transition-colors';
  btn.title = 'Professional background removal powered by remove.bg API';
});

// Check camera support for remove.bg API integration
if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
  document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('openCameraBtn').disabled = true;
    document.getElementById('openCameraBtn').innerHTML = '<i class="ri-camera-off-line mr-1"></i>Camera Not Supported';
    document.getElementById('openCameraBtn').className = 'bg-gray-400 text-white px-3 py-1 rounded text-xs cursor-not-allowed';
    document.getElementById('openCameraBtn').title = 'Camera not available on this device';
  });
}

// Note: Remove.bg API doesn't require any client-side libraries
// Background removal works entirely through the API
</script>

<script>
  // Loads the modal when the button is clicked
  function openPrintModal() {
    const pid = "<?= $patientId ?>";
    window.open('print_selection.php?id='+pid, '_blank', 'width=1100,height=700');
  }
  document.getElementById('generateReportBtn').addEventListener('click', openPrintModal);
</script>

<script>
/* keep the printed name in sync with first & last name */
function syncPrintedName() {
    const fn = document.querySelector('input[name="first_name"]').value.trim();
    const ln = document.querySelector('input[name="last_name"]').value.trim();
    document.getElementById('printedName').value = `${fn} ${ln}`.trim();
}
/* run on every keystroke in first/last name */
document.querySelectorAll('input[name="first_name"], input[name="last_name"]')
        .forEach(el => el.addEventListener('input', syncPrintedName));
/* initial population */
syncPrintedName();
</script>

<script>
// --- Clear Fingerprint Function ---
function clearFingerprint() {
  if (confirm('Are you sure you want to clear the fingerprint image?')) {
    document.getElementById('thumb-preview').src = '';
    document.getElementById('thumb-preview').classList.add('hidden');
    document.getElementById('thumb-placeholder').classList.remove('hidden');
    document.getElementById('thumbmark').value = '';
    document.getElementById('thumbmark-camera-data').value = '';
    
    // Hide the clear button
    const clearBtn = document.getElementById('clearFingerprintBtn');
    clearBtn.classList.add('hidden');
  }
}

// --- Enhanced Photo Preview Script with Background Removal ---
function previewPhoto(evt, imgId, placeholderId) {
  const file = evt.target.files[0];
  if (!file) return;
  
  const reader = new FileReader();
  reader.onload = async (e) => {
    let img = document.getElementById(imgId);
    let placeholder = document.getElementById(placeholderId);
    
    // For photo preview, also enable background removal option
    if (imgId === 'photo-preview') {
      // Show a small processing dialog for uploaded files
      const shouldRemoveBackground = confirm(
        'Would you like to automatically remove the background and make it white?\n\n' +
        'Click OK for white background removal, or Cancel to keep original.'
      );
      
      if (shouldRemoveBackground && window.cameraCapture) {
        try {
          // Create temporary image and canvas for processing
          const tempImg = new Image();
          tempImg.onload = async () => {
            const tempCanvas = document.createElement('canvas');
            const tempCtx = tempCanvas.getContext('2d');
            
            // Make it square (1:1 ratio)
            const size = Math.min(tempImg.width, tempImg.height);
            tempCanvas.width = size;
            tempCanvas.height = size;
            
            const x = (tempImg.width - size) / 2;
            const y = (tempImg.height - size) / 2;
            tempCtx.drawImage(tempImg, x, y, size, size, 0, 0, size, size);
            
            // Process with background removal
            try {
              const processedImageData = await window.cameraCapture.processUploadedImage(tempCanvas);
              img.src = processedImageData;
              
              // Store processed image in camera data field for saving
              document.getElementById('photo-camera-data').value = processedImageData;
              document.getElementById('photo').value = ''; // Clear file input since we're using processed data
            } catch (error) {
              console.error('Background removal failed:', error);
              img.src = e.target.result; // Fallback to original
            }
          };
          tempImg.src = e.target.result;
        } catch (error) {
          console.error('Error processing uploaded image:', error);
          img.src = e.target.result; // Fallback to original
        }
      } else {
        img.src = e.target.result;
      }
    } else {
      img.src = e.target.result;
    }
    
    img.classList.remove('hidden');
    placeholder.classList.add('hidden');
    
    // Show clear button for photo preview
    if (imgId === 'photo-preview' && window.cameraCapture) {
      window.cameraCapture.showClearButton();
    }
  };
  reader.readAsDataURL(file);
}

// --- Signature Pad Setup ---
const pirPadCanvas = document.getElementById('pirSigPad');
const pirPad = new SignaturePad(pirPadCanvas, { backgroundColor: '#fff' });

function resizeCanvas(canvas) {
    const ratio = Math.max(window.devicePixelRatio || 1, 1);
    canvas.width = canvas.offsetWidth * ratio;
    canvas.height = canvas.offsetHeight * ratio;
    canvas.getContext('2d').scale(ratio, ratio);
    pirPad.clear();
}

window.addEventListener('load', () => {
    resizeCanvas(pirPadCanvas);
    <?php if (!empty($formData['data_privacy_signature_path']) && file_exists(__DIR__ . '/' . $formData['data_privacy_signature_path'])): ?>
    pirPad.fromDataURL('<?= htmlspecialchars($formData['data_privacy_signature_path']) ?>?t=<?= time() ?>');
    <?php endif; ?>
});
window.addEventListener('resize', () => resizeCanvas(pirPadCanvas));

// --- Form Submission ---
const patientForm = document.getElementById('patientForm');
patientForm.addEventListener('submit', function(event) {
    // Add signature data to a hidden input before submitting
    if (!pirPad.isEmpty()) {
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'data_privacy_signature_path_base64';
        hiddenInput.value = pirPad.toDataURL('image/png');
        patientForm.appendChild(hiddenInput);
    }
});
</script>
</body>
</html>
