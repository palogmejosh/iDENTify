<?php
require_once 'config.php';
requireAuth();

$user = getCurrentUser();
$role = $user['role'] ?? '';
$userId = $user['id'] ?? 0;

$patientId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$patientId) die('Invalid patient ID');

// Role-based access control
if ($role === 'Clinical Instructor') {
    $accessCheck = $pdo->prepare(
        "SELECT pa.id FROM patient_assignments pa 
         WHERE pa.patient_id = ? 
         AND pa.clinical_instructor_id = ? 
         AND pa.assignment_status IN ('accepted', 'completed')"
    );
    $accessCheck->execute([$patientId, $userId]);
    if (!$accessCheck->fetch()) {
        header('Location: patients.php');
        exit;
    }
} elseif (!in_array($role, ['Admin', 'Clinician', 'COD'])) {
    header('Location: patients.php');
    exit;
}

/* ---------- Fetch existing progress notes ---------- */
$stmt = $pdo->prepare("SELECT * FROM progress_notes WHERE patient_id = ? ORDER BY id ASC");
$stmt->execute([$patientId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Map procedure_log_id => treatment plan text from procedure_logs
$tpMap = [];
$logIds = array_filter(array_unique(array_map(function($r){ return $r['procedure_log_id'] ?? null; }, $rows)));
if (!empty($logIds)) {
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

    // Store the original progress text without UI prefixes
    $row['original_progress'] = $row['progress'] ?? '';
    
    if (!empty($row['auto_generated']) && (int)$row['auto_generated'] === 1) {
        $row['is_auto_generated'] = true;
    } else {
        $row['is_auto_generated'] = false;
    }
}
unset($row); // Break the reference

// Provide a default empty row if no notes exist
if (!$rows) {
    $rows = [[
        'id' => null,
        'date' => '',
        'tooth' => '',
        'progress' => '',
        'original_progress' => '',
        'clinician' => '',
        'ci' => '',
        'remarks' => '',
        'patient_signature' => '',
        'treatment_plan' => '',
        'is_auto_generated' => false,
        'auto_generated' => 0
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
$currentUser = getCurrentUser();
$currentUserName = $currentUser['full_name'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Step 5 – Progress Notes</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdn.jsdelivr.net/npm/remixicon@2.5.0/fonts/remixicon.css" rel="stylesheet">
  <link rel="stylesheet" href="dark-mode-override.css">
  <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.5/dist/signature_pad.umd.min.js"></script>
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

<!-- Header -->
<header class="bg-white shadow-sm">
  <div class="container mx-auto px-4 py-4 flex justify-between items-center">
    <div class="flex items-center">
      <button onclick="window.location.href='patients.php';" class="mr-4 text-gray-500 hover:text-gray-700">
        <div class="w-6 h-6 flex items-center justify-center"><i class="ri-arrow-left-line"></i></div>
      </button>
      <h1 class="text-xl font-semibold text-gray-800">Step 5 – Progress Notes</h1>
    </div>
    <div class="flex space-x-3">
      <button id="generateReportBtn" class="bg-primary text-black px-4 py-2 rounded-button hover:bg-primary/90"
        onclick="window.open('print_selection.php?id=<?= $patientId ?>', '_blank', 'width=1100,height=700');">
        Generate Report
      </button>
      <button id="savePatientInfoBtn" type="submit" form="step5Form" class="bg-primary text-black px-4 py-2 rounded-button whitespace-nowrap">Save Record</button>
    </div>
  </div>
</header>

<div class="flex-1 container mx-auto px-4 py-6">
  <!-- Progress Bar -->
  <div class="mb-8">
    <div class="w-full flex items-center">
      <a href="edit_patient.php?id=<?= $patientId ?>" class="w-8 h-8 rounded-full bg-green-600 text-white text-sm font-medium flex items-center justify-center">1</a>
      <div class="flex-1 h-1 bg-green-600"></div>
      <a href="edit_patient_step2.php?id=<?= $patientId ?>" class="w-8 h-8 rounded-full bg-green-600 text-white text-sm font-medium flex items-center justify-center">2</a>
      <div class="flex-1 h-1 bg-green-600"></div>
      <a href="edit_patient_step3.php?id=<?= $patientId ?>" class="w-8 h-8 rounded-full bg-green-600 text-white text-sm font-medium flex items-center justify-center">3</a>
      <div class="flex-1 h-1 bg-green-600"></div>
      <a href="edit_patient_step4.php?id=<?= $patientId ?>" class="w-8 h-8 rounded-full bg-green-600 text-white text-sm font-medium flex items-center justify-center">4</a>
      <div class="flex-1 h-1 bg-green-600"></div>
      <a href="edit_patient_step5.php?id=<?= $patientId ?>" class="w-8 h-8 rounded-full bg-blue-600 text-white text-sm font-medium flex items-center justify-center">5</a>
    </div>
  </div>

  <form id="step5Form" autocomplete="off">
    <input type="hidden" name="patient_id" value="<?= $patientId ?>">

    <div class="bg-white rounded shadow-sm p-6 max-w-4xl mx-auto">
      <!-- Patient banner -->
      <div class="flex justify-between items-start mb-2">
        <div class="flex flex-col gap-1 text-xs">
          <div class="flex gap-2">
            <label class="font-medium">Patient's name</label>
            <input type="text" readonly value="<?= htmlspecialchars($patientFullName) ?>" class="border-b border-gray-400 bg-transparent w-40" />
            <label class="ml-6 font-medium">Age / Gender</label>
            <input type="text" readonly value="<?= htmlspecialchars($patient['age'].' / '.$patient['gender']) ?>" class="border-b border-gray-400 bg-transparent w-24" />
          </div>
        </div>
        <div class="flex flex-col items-end">
          <div class="text-xs text-right text-gray-500 font-mono">FM-LPU-DENT-01/09<br>Page 5 of 5</div>
          <div class="mt-2 bg-yellow-300 border border-yellow-500 px-4 py-1 rounded shadow text-xs font-bold text-gray-900">MEDICAL ALERT!</div>
        </div>
      </div>

      <!-- Dynamic table -->
      <div class="overflow-x-auto mt-4">
        <table id="notesTable" class="min-w-full border border-gray-300 text-xs">
          <thead>
            <tr class="bg-gray-100">
              <th class="border px-2 py-1">Date</th>
              <th class="border px-2 py-1">Tooth</th>
              <th class="border px-2 py-1">Progress Notes</th>
              <th class="border px-2 py-1">Clinician</th>
              <th class="border px-2 py-1">CI</th>
              <th class="border px-2 py-1">Remarks</th>
              <th class="border px-2 py-1">Del</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $row): ?>
            <tr>
              <input type="hidden" name="id[]" value="<?= htmlspecialchars($row['id'] ?? '') ?>">
              <td class="border px-2 py-1"><input type="date" name="date[]" value="<?= htmlspecialchars($row['date'] ?? '') ?>" class="w-full bg-transparent border-0 focus:ring-0 text-xs"></td>
              <td class="border px-2 py-1"><input type="text" name="tooth[]" value="<?= htmlspecialchars($row['tooth'] ?? '') ?>" class="w-full bg-transparent border-0 focus:ring-0 text-xs"></td>
              <td class="border px-2 py-1">
                <input type="text" name="progress[]" value="<?= htmlspecialchars($row['original_progress'] ?? '') ?>" class="w-full bg-transparent border-0 focus:ring-0 text-xs">
              </td>
              <td class="border px-2 py-1"><input type="text" name="clinician[]" readonly value="<?= htmlspecialchars($currentUserName ?: ($row['clinician'] ?? '')) ?>" class="w-full bg-gray-100 border-0 focus:ring-0 text-xs"></td>
              <td class="border px-2 py-1"><input type="text" name="ci[]" value="<?= htmlspecialchars($row['ci'] ?? '') ?>" class="w-full bg-transparent border-0 focus:ring-0 text-xs"></td>
              <td class="border px-2 py-1"><input type="text" name="remarks[]" value="<?= htmlspecialchars($row['remarks'] ?? '') ?>" class="w-full bg-transparent border-0 focus:ring-0 text-xs"></td>
              <td class="border px-2 py-1"><button type="button" onclick="delRow(this)" class="text-red-600 hover:text-red-800 text-xs">✕</button></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="mt-4">
        <button type="button" onclick="addRow()" class="bg-blue-600 text-white px-4 py-2 rounded-button text-xs">Add Row</button>
      </div>

      <!-- Signature Section -->
      <div class="mt-6 flex flex-col gap-2 text-xs">
        <label>Patient's name and signature</label>
        <div class="w-full max-w-[350px]">
            <div class="signature-pad-container">
                <canvas id="step5SigPad" class="signature-pad"></canvas>
            </div>
            <button type="button" onclick="step5Pad.clear()" class="text-xs text-red-600 mt-1">Clear Signature</button>
            <input type="hidden" name="old_data_privacy_signature_path" value="<?= htmlspecialchars($sharedSignaturePath ?? '') ?>">
        </div>
        <div class="w-full max-w-[350px] mt-2">
            <input type="text" name="patient_signature" readonly value="<?= htmlspecialchars($patientFullName) ?>" class="w-full px-2 py-1 border border-gray-300 rounded bg-gray-100" />
            <label class="block text-xs font-medium text-gray-700 mt-1">Printed Name</label>
        </div>
      </div>

      <!-- Navigation -->
      <div class="mt-6 flex justify-end">
        <a href="edit_patient_step4.php?id=<?= $patientId ?>" class="bg-gray-300 text-black px-6 py-2 rounded-button whitespace-nowrap">Previous</a>
      </div>
    </div>
  </form>
</div>

<script>
// --- Table Row Management ---
function addRow() {
  const tbody = document.querySelector('#notesTable tbody');
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <input type="hidden" name="id[]" value="">
    <td class="border px-2 py-1"><input type="date" name="date[]" class="w-full bg-transparent border-0 focus:ring-0 text-xs"></td>
    <td class="border px-2 py-1"><input type="text" name="tooth[]" class="w-full bg-transparent border-0 focus:ring-0 text-xs"></td>
    <td class="border px-2 py-1"><input type="text" name="progress[]" class="w-full bg-transparent border-0 focus:ring-0 text-xs"></td>
    <td class="border px-2 py-1"><input type="text" name="clinician[]" readonly value="<?= htmlspecialchars($currentUserName) ?>" class="w-full bg-gray-100 border-0 focus:ring-0 text-xs"></td>
    <td class="border px-2 py-1"><input type="text" name="ci[]" class="w-full bg-transparent border-0 focus:ring-0 text-xs"></td>
    <td class="border px-2 py-1"><input type="text" name="remarks[]" class="w-full bg-transparent border-0 focus:ring-0 text-xs"></td>
    <td class="border px-2 py-1"><button type="button" onclick="delRow(this)" class="text-red-600 hover:text-red-800 text-xs">✕</button></td>
  `;
  tbody.appendChild(tr);
}

function delRow(btn) {
  btn.closest('tr').remove();
}

// --- Signature Pad Setup ---
const step5PadCanvas = document.getElementById('step5SigPad');
const step5Pad = new SignaturePad(step5PadCanvas, { backgroundColor: '#fff' });

function resizeCanvas(canvas) {
    const ratio = Math.max(window.devicePixelRatio || 1, 1);
    canvas.width = canvas.offsetWidth * ratio;
    canvas.height = canvas.offsetHeight * ratio;
    canvas.getContext('2d').scale(ratio, ratio);
    step5Pad.clear();
}

function loadSignature() {
    <?php if (!empty($sharedSignaturePath) && file_exists(__DIR__ . '/' . $sharedSignaturePath)): ?>
    step5Pad.fromDataURL('<?= htmlspecialchars($sharedSignaturePath) ?>?t=<?= time() ?>');
    <?php endif; ?>
}

window.addEventListener('load', () => {
    resizeCanvas(step5PadCanvas);
    loadSignature();
});
window.addEventListener('resize', () => {
    resizeCanvas(step5PadCanvas);
    loadSignature();
});

// --- Form Submission ---
document.getElementById('step5Form').addEventListener('submit', async e => {
  e.preventDefault();
  
  // Disable submit button to prevent double submission
  const submitBtn = document.getElementById('savePatientInfoBtn');
  submitBtn.disabled = true;
  submitBtn.innerHTML = '<i class="ri-loader-4-line animate-spin mr-1"></i>Saving...';
  
  const fd = new FormData(e.target);

  // Add signature data to FormData if a new one was drawn
  if (!step5Pad.isEmpty()) {
    fd.append('data_privacy_signature_path_base64', step5Pad.toDataURL('image/png'));
  }
  
  // Create JSON from the table rows
  const rows = [];
  const ids = fd.getAll('id[]');
  const dates = fd.getAll('date[]');
  const teeth = fd.getAll('tooth[]');
  const progress = fd.getAll('progress[]');
  const clinicians = fd.getAll('clinician[]');
  const cis = fd.getAll('ci[]');
  const remarks = fd.getAll('remarks[]');
  
  console.log('Form data:', {
    ids: ids,
    dates: dates,
    teeth: teeth,
    progress: progress,
    clinicians: clinicians,
    cis: cis,
    remarks: remarks
  });
  
  for (let i = 0; i < dates.length; i++) {
    rows.push({
      id: ids[i] || null,
      date: dates[i],
      tooth: teeth[i],
      progress: progress[i],
      clinician: clinicians[i],
      ci: cis[i],
      remarks: remarks[i],
    });
  }
  
  console.log('Rows to save:', rows);
  fd.append('notes_json', JSON.stringify(rows));

  try {
    const res = await fetch('save_step5.php', {method: 'POST', body: fd});
    const msg = await res.text();
    
    console.log('Server response:', msg);
    console.log('Response status:', res.status);
    
    if (res.ok && msg.trim() === 'OK') {
        alert('Progress notes saved successfully!');
        location.href = 'patients.php';
    } else {
        alert('Error saving progress notes: ' + msg);
        submitBtn.disabled = false;
        submitBtn.innerHTML = 'Save Record';
    }
  } catch (error) {
      alert('A network error occurred. Please try again.');
      console.error('Save error:', error);
      submitBtn.disabled = false;
      submitBtn.innerHTML = 'Save Record';
  }
});
</script>
</body>
</html>