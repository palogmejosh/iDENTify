<?php
require_once 'config.php';
requireAuth();

$patientId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$patientId) die('Invalid patient ID');

/* existing consent row */
$stmt = $pdo->prepare("SELECT * FROM informed_consent WHERE patient_id = ?");
$stmt->execute([$patientId]);
$consent = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

/* patient info banner */
$pt = $pdo->prepare("SELECT last_name, first_name, middle_initial, age, gender FROM patients WHERE id = ?");
$pt->execute([$patientId]);
$patient = $pt->fetch(PDO::FETCH_ASSOC);

/* ---------- Get current user info for clinician name sync ---------- */
$currentUser = getCurrentUser();
$currentUserName = $currentUser['full_name'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Step 4 – Informed Consent</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdn.jsdelivr.net/npm/remixicon@2.5.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="dark-mode-override.css">
  <style>.rounded-button{border-radius:20px}</style>
  <!-- Signature pad -->
  <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.5/dist/signature_pad.umd.min.js"></script>
  <style>
    /* Ensure the canvas fills its container, and the container has a border */
    .signature-pad-container {
        border: 1px solid #aaa;
        border-radius: 4px;
        position: relative;
        width: 100%;
        height: 150px; /* Give a default height */
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
      <button onclick="window.location.href='patients.php'" class="mr-4 text-gray-500 hover:text-gray-700">
        <i class="ri-arrow-left-line"></i>
      </button>
      <h1 class="text-xl font-semibold text-gray-800">Step 4 – Informed Consent</h1>
    </div>
    <div class="flex space-x-3">
    <button id="generateReportBtn" class="bg-primary text-black px-4 py-2 rounded-button hover:bg-primary/90"
        onclick="window.openPrintModal();">
  Generate Report
</button>
      <button id="savePatientInfoBtn" type="submit" form="step4Form" class="bg-primary text-black px-4 py-2 rounded-button whitespace-nowrap">Save Record</button>
    </div>
  </div>
</header>

<div class="flex-1 container mx-auto px-4 py-6">
  <!-- Progress bar -->
  <div class="mb-8">
    <div class="w-full flex items-center">
      <a href="edit_patient.php?id=<?= $patientId ?>" class="w-8 h-8 rounded-full bg-green-600 text-white text-sm font-medium flex items-center justify-center">1</a>
      <div class="flex-1 h-1 bg-green-600"></div>
      <a href="edit_patient_step2.php?id=<?= $patientId ?>" class="w-8 h-8 rounded-full bg-green-600 text-white text-sm font-medium flex items-center justify-center">2</a>
      <div class="flex-1 h-1 bg-green-600"></div>
      <a href="edit_patient_step3.php?id=<?= $patientId ?>" class="w-8 h-8 rounded-full bg-green-600 text-white text-sm font-medium flex items-center justify-center">3</a>
      <div class="flex-1 h-1 bg-green-600"></div>
      <a href="edit_patient_step4.php?id=<?= $patientId ?>" class="w-8 h-8 rounded-full bg-blue-600 text-white text-sm font-medium flex items-center justify-center">4</a>
      <div class="flex-1 h-1 bg-gray-200"></div>
      <a href="edit_patient_step5.php?id=<?= $patientId ?>" class="w-8 h-8 rounded-full bg-gray-200 text-gray-700 text-sm font-medium flex items-center justify-center">5</a>
    </div>
  </div>

  <form id="step4Form" autocomplete="off">
    <input type="hidden" name="patient_id" value="<?= $patientId ?>">
    <div class="bg-white rounded shadow-sm p-6 max-w-3xl mx-auto">
      <div class="mb-4 text-right text-xs text-gray-500 font-mono">FM-LPU-DENT-01/09<br>Page 4 of 5</div>
      <h3 class="text-lg font-bold text-gray-800 mb-4">INFORMED CONSENT:</h3>

      <!-- Patient banner -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4 text-xs">
        <div>
          <label class="font-medium">Patient's name</label>
          <input type="text" readonly value="<?= htmlspecialchars($patient['first_name'].' '.$patient['last_name']) ?>" class="border-b border-gray-400 bg-transparent w-full" />
        </div>
        <div>
          <label class="font-medium">Age / Gender</label>
          <input type="text" readonly value="<?= htmlspecialchars($patient['age'].' / '.$patient['gender']) ?>" class="border-b border-gray-400 bg-transparent w-full" />
        </div>
      </div>

      <!-- Consent statements -->
      <div class="text-xs text-gray-800 space-y-3">
          <p><b>TREATMENT TO BE DONE.</b> I understand and consent to have any treatment done by the dentist after the procedure, the risk & benefits and cost have been fully explained. These treatments include, but are not limited to, oral surgery, cleaning, periodontal treatments, fillings, crowns, bridges, and prosthodontic, root canal treatments and orthodontic treatments, and all minor and major dental procedures. <span class="ml-2">Initial: <input type="text" maxlength="20" name="consent_treatment" value="<?= htmlspecialchars($consent['consent_treatment'] ?? '') ?>" class="inline-block border-b border-gray-400 w-16 px-1 text-xs bg-transparent focus:outline-none" /></span></p>
          <p><b>DRUGS & MEDICATIONS.</b> I understand that antibiotics, analgesics and other medications can cause allergic reactions (redness, swelling of tissues, pain, itching, vomiting, and/or anaphylactic shock). <span class="ml-2">Initial: <input type="text" maxlength="20" name="consent_drugs" value="<?= htmlspecialchars($consent['consent_drugs'] ?? '') ?>" class="inline-block border-b border-gray-400 w-16 px-1 text-xs bg-transparent focus:outline-none" /></span></p>
          <p><b>CHANGES IN TREATMENT PLAN.</b> I understand that during treatment, it may be necessary to change or add procedures because of conditions found while working on the teeth that were not discovered during examination. For example, root canal therapy may be necessary following routine restorative procedures. I give my permission to the dentist to make any/all changes and additions as necessary. <span class="ml-2">Initial: <input type="text" maxlength="20" name="consent_changes" value="<?= htmlspecialchars($consent['consent_changes'] ?? '') ?>" class="inline-block border-b border-gray-400 w-16 px-1 text-xs bg-transparent focus:outline-none" /></span></p>
          <p><b>RADIOGRAPHS.</b> I understand that x-rays (radiographs) may be necessary to complete and/or diagnose the tentative diagnosis of my dental problem. I give my permission to have such radiographs taken. <span class="ml-2">Initial: <input type="text" maxlength="20" name="consent_radiographs" value="<?= htmlspecialchars($consent['consent_radiographs'] ?? '') ?>" class="inline-block border-b border-gray-400 w-16 px-1 text-xs bg-transparent focus:outline-none" /></span></p>
          <p><b>REMOVAL OF TEETH.</b> I understand that alternatives to tooth removal (root canal therapy, crowns & periodontal surgery, etc.) and I agree to the removal of teeth if necessary. Removing teeth does not always remove all the infection, if present, and it may be necessary to have further treatment. I understand the risks involved in tooth removal, some of which are pain, swelling, spread of infection, dry socket, loss of feeling in my teeth, lips, tongue and surrounding tissue (paresthesia) that can last for an indefinite period of time or fractured jaw. I understand that I may need further treatment by a specialist if complications arise during or following treatment, the cost of which is my responsibility. <span class="ml-2">Initial: <input type="text" maxlength="20" name="consent_removal_teeth" value="<?= htmlspecialchars($consent['consent_removal_teeth'] ?? '') ?>" class="inline-block border-b border-gray-400 w-16 px-1 text-xs bg-transparent focus:outline-none" /></span></p>
          <p><b>CROWNS, CAPS, BRIDGES.</b> I understand that sometimes it is not possible to match the color of natural teeth exactly with artificial teeth. I further understand that I may be wearing temporary crowns, which may come off easily and that I must be careful to ensure that they are kept on until the permanent crowns are delivered. I realize the final opportunity to make changes in my new crown, bridge, or cap (including shape, fit, size, and color) will be before cementation. It is also my responsibility to return for permanent cementation within 20 days from tooth preparation. Excessive delay may allow for decay, tooth movement, gum disease, or other problems. If I fail to return within this time, the dentist is not responsible for any problems resulting from my failure to return. <span class="ml-2">Initial: <input type="text" maxlength="20" name="consent_crowns" value="<?= htmlspecialchars($consent['consent_crowns'] ?? '') ?>" class="inline-block border-b border-gray-400 w-16 px-1 text-xs bg-transparent focus:outline-none" /></span></p>
          <!-- NEW ENDODONTICS CONSENT -->
          <p><b>ENDODONTICS (ROOT CANAL).</b> I understand there is no guarantee that root canal treatment will save a tooth and that complications can occur from the treatment and that occasionally root canal filling materials may extend through that tooth which does not necessarily affect the success of the treatment. I understand that endodontic files and drills are very fine instruments and stresses vented in their manufacture & clarifications present in teeth can cause them to break during use. I understand that referral to the endodontist for additional treatments may be necessary following any root canal treatment and I agree that I am responsible for any additional cost for treatment performed by the endodontist. I understand that a tooth may require extraction in spite of all efforts to save it. <span class="ml-2">Initial: <input type="text" maxlength="20" name="consent_endodontics" value="<?= htmlspecialchars($consent['consent_endodontics'] ?? '') ?>" class="inline-block border-b border-gray-400 w-16 px-1 text-xs bg-transparent focus:outline-none" /></span></p>

          <!-- NEW PERIODONTAL CONSENT -->
          <p><b>PERIODONTAL DISEASE.</b> I understand that periodontal disease is a serious condition causing gums & bone inflammation and/or loss and that can lead to the loss of my teeth. I understand that alternative treatment plans to correct periodontal disease, including gum surgery tooth extractions with or without replacement. I understand that undertaking these treatments does not guarantee the elimination of the disease. <span class="ml-2">Initial: <input type="text" maxlength="20" name="consent_periodontal" value="<?= htmlspecialchars($consent['consent_periodontal'] ?? '') ?>" class="inline-block border-b border-gray-400 w-16 px-1 text-xs bg-transparent focus:outline-none" /></span></p>
          <p><b>DENTURES.</b> I understand the wearing of dentures is difficult. Sore spots, altered speech, and difficulty in eating are common problems. Immediate dentures (placement of dentures immediately after extractions) may be uncomfortable. I realize that dentures may require adjustments and relining (at additional cost) to fit properly. <span class="ml-2">Initial: <input type="text" maxlength="20" name="consent_dentures" value="<?= htmlspecialchars($consent['consent_dentures'] ?? '') ?>" class="inline-block border-b border-gray-400 w-16 px-1 text-xs bg-transparent focus:outline-none" /></span></p>
          <p><b>FILLINGS.</b> I understand that care must be exercised in chewing on fillings during the first 24 hours to avoid breakage. I understand that a more extensive filling than originally diagnosed may be required due to additional decay found during preparation. I understand that significant sensitivity is a common but usually temporary after effect of a newly placed filling. <span class="ml-2">Initial: <input type="text" maxlength="20" name="consent_fillings" value="<?= htmlspecialchars($consent['consent_fillings'] ?? '') ?>" class="inline-block border-b border-gray-400 w-16 px-1 text-xs bg-transparent focus:outline-none" /></span></p>
          <p class="mt-4"><b>I understand that dentistry is not an exact science and that no dentist can properly guarantee results.</b></p>
          <p class="mt-4">I hereby authorize any of the dentists to proceed with and perform the dental treatments & treatments as explained to me. I understand that dentistry is subject to medication depending on my individual circumstances that may arise during the course of treatment. I further understand that regardless of any dental insurance coverage I may have, I am responsible for payment of dental fees. I agree to pay my estimated portion and any costs or fees not covered by my insurance company at the time services are rendered. I understand that any unpaid balance will accrue interest and that any unpaid account may be referred to a collection agency. I understand that any dental work that remains unpaid for more than 30 days after billing will be subject to additional charges. I understand that adjustments of any kind after this initial period will be my responsibility. <span class="ml-2">Initial: <input type="text" maxlength="20" name="consent_guarantee" value="<?= htmlspecialchars($consent['consent_guarantee'] ?? '') ?>" class="inline-block border-b border-gray-400 w-16 px-1 text-xs bg-transparent focus:outline-none" /></span></p>
          <p class="mt-4">It is my free will with full trust and confidence to undergo dental treatment under their care.</p>
      </div>

      <!-- Signature section -->
      <div class="mt-8">
        <div class="flex flex-wrap gap-4 text-xs">
          <div class="flex-1 min-w-[350px]">
            <label class="block mb-1">Patient/Parent/Guardian's Signature</label>
            <div class="signature-pad-container">
                <canvas id="patientSigPad" class="signature-pad"></canvas>
            </div>
            <button type="button" onclick="patientPad.clear()" class="text-xs text-red-600 mt-1">Clear</button>
            <input type="hidden" name="old_patient_signature" value="<?= htmlspecialchars($consent['patient_signature'] ?? '') ?>">
          </div>
          <div class="flex-1">
            <label class="block mb-1">Witness</label>
            <input type="text" name="witness_signature" value="<?= htmlspecialchars($consent['witness_signature'] ?? '') ?>" class="w-full border-b border-gray-400 bg-transparent" />
          </div>
          <div class="flex-1">
            <label class="block mb-1">Date</label>
            <input type="date" name="consent_date" value="<?= htmlspecialchars($consent['consent_date'] ?? '') ?>" class="w-full border-b border-gray-400 bg-transparent" />
          </div>
        </div>

        <div class="flex flex-wrap gap-4 text-xs mt-4">
          <div class="flex-1">
            <label class="block mb-1">Clinician</label>
            <input type="text" name="clinician_signature" readonly value="<?= htmlspecialchars($currentUserName ?: ($consent['clinician_signature'] ?? '')) ?>" class="w-full border-b border-gray-400 bg-gray-100" />
          </div>
          <div class="flex-1">
            <label class="block mb-1">Date</label>
            <input type="date" name="clinician_date" value="<?= htmlspecialchars($consent['clinician_date'] ?? '') ?>" class="w-full border-b border-gray-400 bg-transparent" />
          </div>
        </div>
      </div>

      <hr class="my-6" />
      <h4 class="text-base font-semibold text-gray-800 mb-2">DATA PRIVACY CONSENT</h4>
      <div class="text-xs text-gray-800 space-y-2">
        <p>I hereby declare that by signing:</p>
        <ol class="list-decimal list-inside space-y-1 ml-4">
          <li>I attest that the information I have written is true and correct to the best of my personal knowledge.</li>
          <li>I signify my consent to the collection, use, recording, storing, organizing, consolidation, updating, processing access to transfer, disclosure and/or sharing of my personal and sensitive information by and among the staff of LPU-B including its medical staff, school/university, students, trainees, staff, administration, and/or consultants, as may be required for medical and/or legal and/or registration or for the purposes for which it was collected and such other lawful purposes I consent to.</li>
          <li>I understand and agree that my personal data is subject to designated office and staff of the LPU-B. I will be provided with the reasonable access to my personal data provided to LPU-B to verify the accuracy and completeness of my information and request for its amendment if necessary.</li>
          <li>I am aware that my consent for the collection and use of my data for LPU-B shall be effective immediately upon signing of this form and shall remain in effect unless I revoke the same in writing. Sixty working days upon receipt of the written revocation, LPU-B shall immediately cease from performing the acts mentioned under paragraph 2 herein concerning my personal and sensitive personal information.</li>
        </ol>
      </div>

      <!-- Signature Over Printed Name -->
      <div class="mt-8 flex flex-col gap-2 text-xs">
        <label>Signature over printed name</label>
        <div class="w-full max-w-[350px]">
            <div class="signature-pad-container">
                <canvas id="dataPrivacySigPad" class="signature-pad"></canvas>
            </div>
            <button type="button" id="clearDataPrivacyBtn" class="text-xs text-red-600 mt-1">Clear Signature</button>
            <input type="hidden" name="old_data_privacy_signature_path" value="<?= htmlspecialchars($consent['data_privacy_signature_path'] ?? '') ?>">
        </div>
        <div class="w-full max-w-[350px] mt-2">
            <input type="text" readonly value="<?= htmlspecialchars($patient['first_name'].' '.$patient['last_name']) ?>" class="w-full border-b border-gray-400 bg-transparent" />
        </div>
        <div class="mt-4 flex flex-col gap-2 text-xs">
        <label>Date</label>
        <input type="date" name="data_privacy_date" value="<?= htmlspecialchars($consent['data_privacy_date'] ?? '') ?>" class="w-full border-b border-gray-400 bg-transparent" />
      </div>
      </div>

      <!-- Patient's Name and Signature (Synchronized) -->
      <div class="mt-4 flex flex-col gap-2 text-xs">
        <label>Patient's name and signature</label>
         <div class="w-full max-w-[350px]">
            <div class="signature-pad-container">
                <canvas id="patientNameSigPad" class="signature-pad"></canvas>
            </div>
            <button type="button" id="clearPatientNameBtn" class="text-xs text-red-600 mt-1">Clear Signature</button>
        </div>
        <div class="w-full max-w-[350px] mt-2">
            <input type="text" readonly value="<?= htmlspecialchars($patient['first_name'].' '.$patient['last_name']) ?>" class="w-full border-b border-gray-400 bg-transparent" />
        </div>
      </div>

      <!-- Navigation -->
      <div class="mt-6 flex justify-end space-x-2">
      <a href="edit_patient_step3.php?id=<?= $patientId ?>" class="bg-gray-300 text-black px-6 py-2 rounded-button whitespace-nowrap">Previous</a>
        <button type="submit" class="next-btn bg-blue-600 text-white px-6 py-2 rounded-button">Save & Next</button>
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
// --- Enhanced Signature Pad Setup + Generate Report Button ---

document.getElementById('generateReportBtn').addEventListener('click', () => {
  window.open(`print_selection.php?id=<?= $patientId ?>`, '_blank', 'width=1100,height=700');
});

// Get canvas elements
const patientPadCanvas = document.getElementById('patientSigPad');
const dataPrivacyPadCanvas = document.getElementById('dataPrivacySigPad');
const patientNameSigPadCanvas = document.getElementById('patientNameSigPad');

// Initialize signature pad instances
const patientPad = new SignaturePad(patientPadCanvas, {backgroundColor:'#fff'});
const dataPrivacyPad = new SignaturePad(dataPrivacyPadCanvas, {backgroundColor:'#fff'});
const patientNamePad = new SignaturePad(patientNameSigPadCanvas, {backgroundColor:'#fff'});

// Function to resize canvas. This is crucial for accurate cursor tracking on responsive layouts.
function resizeCanvas(canvas) {
    const ratio =  Math.max(window.devicePixelRatio || 1, 1);
    canvas.width = canvas.offsetWidth * ratio;
    canvas.height = canvas.offsetHeight * ratio;
    canvas.getContext('2d').scale(ratio, ratio);
    const signaturePadInstance = [patientPad, dataPrivacyPad, patientNamePad].find(p => p.canvas === canvas);
    if (signaturePadInstance) {
        signaturePadInstance.clear();
    }
}

// Function to load existing signatures from the server
function loadSignatures() {
    <?php if (!empty($consent['patient_signature']) && file_exists(__DIR__ . '/' . $consent['patient_signature'])): ?>
    patientPad.fromDataURL('<?= htmlspecialchars($consent['patient_signature']) ?>?t=<?= time() ?>');
    <?php endif; ?>

    <?php if (!empty($consent['data_privacy_signature_path']) && file_exists(__DIR__ . '/' . $consent['data_privacy_signature_path'])): ?>
    const dataPrivacyUrl = '<?= htmlspecialchars($consent['data_privacy_signature_path']) ?>?t=<?= time() ?>';
    dataPrivacyPad.fromDataURL(dataPrivacyUrl);
    patientNamePad.fromDataURL(dataPrivacyUrl);
    <?php endif; ?>
}

// --- Synchronization Logic for Data Privacy Canvases ---
dataPrivacyPad.addEventListener("endStroke", () => {
  const data = dataPrivacyPad.toDataURL();
  if (patientNamePad.toDataURL() !== data) {
    patientNamePad.fromDataURL(data);
  }
});

patientNamePad.addEventListener("endStroke", () => {
  const data = patientNamePad.toDataURL();
  if (dataPrivacyPad.toDataURL() !== data) {
    dataPrivacyPad.fromDataURL(data);
  }
});

document.getElementById('clearDataPrivacyBtn').addEventListener('click', () => {
    dataPrivacyPad.clear();
    patientNamePad.clear();
});
document.getElementById('clearPatientNameBtn').addEventListener('click', () => {
    patientNamePad.clear();
    dataPrivacyPad.clear();
});

window.addEventListener('load', () => {
    resizeCanvas(patientPadCanvas);
    resizeCanvas(dataPrivacyPadCanvas);
    resizeCanvas(patientNameSigPadCanvas);
    loadSignatures();
});

window.addEventListener('resize', () => {
    resizeCanvas(patientPadCanvas);
    resizeCanvas(dataPrivacyPadCanvas);
    resizeCanvas(patientNameSigPadCanvas);
    loadSignatures();
});

// --- Form Submission Logic ---
document.getElementById('step4Form').addEventListener('submit', async e=>{
  e.preventDefault();

  const fd = new FormData(e.target);

  if (!patientPad.isEmpty()) {
    fd.append('patient_signature_base64', patientPad.toDataURL('image/png'));
  }
  if (!dataPrivacyPad.isEmpty()) {
    fd.append('data_privacy_signature_path_base64', dataPrivacyPad.toDataURL('image/png'));
  }

  try {
    const res = await fetch('save_step4.php', {method:'POST', body:fd});
    const msg  = await res.text();
    if (res.ok){
      alert('Consent saved successfully.');
      location.href = 'edit_patient_step5.php?id=<?= $patientId ?>';
    } else {
      alert('Error saving consent form: ' + msg);
    }
  } catch (error) {
    alert('A network error occurred. Please check your connection and try again.');
    console.error('Fetch error:', error);
  }
});
</script>
</body>
</html>
