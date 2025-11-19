<?php
require_once 'config.php';
requireAuth();

$patientId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$patientId) die('Invalid patient ID');

// Fetch existing dental examination record
$stmt = $pdo->prepare("SELECT * FROM dental_examination WHERE patient_id = ?");
$stmt->execute([$patientId]);
$exam = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$exam) {
    $pdo->prepare("INSERT INTO dental_examination (patient_id) VALUES (?)")->execute([$patientId]);
    $exam = [];
}

// Fetch patient's basic info for the header and printed name
$pt = $pdo->prepare("SELECT last_name, first_name, middle_initial, age, gender FROM patients WHERE id = ?");
$pt->execute([$patientId]);
$patient = $pt->fetch(PDO::FETCH_ASSOC);
$patientFullName = trim(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? ''));

// Fetch the shared signature path from the informed_consent table
$consentStmt = $pdo->prepare("SELECT data_privacy_signature_path FROM informed_consent WHERE patient_id = ?");
$consentStmt->execute([$patientId]);
$consentData = $consentStmt->fetch(PDO::FETCH_ASSOC);
$sharedSignaturePath = $consentData['data_privacy_signature_path'] ?? null;


/* ----------  CI name for page-3 (Updated to use assigned CI) ---------- */
$ciName = getAssignedClinicalInstructor($patientId) ?: '';

/* ---------- Get current user info for clinician name sync ---------- */
$currentUser = getCurrentUser();
$currentUserName = $currentUser['full_name'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Step 3 – Dental Examination</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdn.jsdelivr.net/npm/remixicon@2.5.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="dark-mode-override.css">
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
<header class="bg-white shadow-sm">
  <div class="container mx-auto px-4 py-4 flex justify-between items-center">
    <div class="flex items-center">
      <button onclick="window.location.href='patients.php';" class="mr-4 text-gray-500 hover:text-gray-700">
        <i class="ri-arrow-left-line"></i>
      </button>
      <h1 class="text-xl font-semibold text-gray-800">Step 3 – Dental Examination</h1>
    </div>
    <div class="flex space-x-3">
    <button id="generateReportBtn" class="bg-primary text-black px-4 py-2 rounded-button hover:bg-primary/90"
        onclick="window.openPrintModal();">
  Generate Report
</button>
      <button id="savePatientInfoBtn" type="submit" form="step3Form" class="bg-primary text-black px-4 py-2 rounded-button whitespace-nowrap">Save Record</button>
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
      <a href="edit_patient_step3.php?id=<?= $patientId ?>" class="w-8 h-8 rounded-full bg-blue-600 text-white text-sm font-medium flex items-center justify-center">3</a>
      <div class="flex-1 h-1 bg-blue-600"></div>
      <a href="edit_patient_step4.php?id=<?= $patientId ?>" class="w-8 h-8 rounded-full bg-gray-200 text-gray-700 text-sm font-medium flex items-center justify-center">4</a>
      <div class="flex-1 h-1 bg-gray-200"></div>
      <a href="edit_patient_step5.php?id=<?= $patientId ?>" class="w-8 h-8 rounded-full bg-gray-200 text-gray-700 text-sm font-medium flex items-center justify-center">5</a>
    </div>
  </div>

  <form id="step3Form" autocomplete="off" enctype="multipart/form-data">
    <input type="hidden" name="patient_id" value="<?= $patientId ?>">
    <div class="bg-white rounded shadow-sm p-6">
      <!-- Patient header -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
        <div><label class="block text-xs font-medium text-gray-700 mb-1">Last name</label>
          <input type="text" readonly value="<?= htmlspecialchars($patient['last_name'] ?? '') ?>" class="w-full px-2 py-1 border border-gray-300 rounded bg-gray-100" /></div>
        <div><label class="block text-xs font-medium text-gray-700 mb-1">First name</label>
          <input type="text" readonly value="<?= htmlspecialchars($patient['first_name'] ?? '') ?>" class="w-full px-2 py-1 border border-gray-300 rounded bg-gray-100" /></div>
        <div><label class="block text-xs font-medium text-gray-700 mb-1">MI</label>
          <input type="text" readonly value="<?= htmlspecialchars($patient['middle_initial'] ?? '') ?>" class="w-full px-2 py-1 border border-gray-300 rounded bg-gray-100" /></div>
        <div><label class="block text-xs font-medium text-gray-700 mb-1">Age/Gender</label>
          <input type="text" readonly value="<?= htmlspecialchars(($patient['age'] ?? '') .'/'. ($patient['gender'] ?? '')) ?>" class="w-full px-2 py-1 border border-gray-300 rounded bg-gray-100" /></div>
      </div>

      <!-- Date / Clinician -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
        <div><label class="block text-xs font-medium text-gray-700 mb-1">Date examined</label>
          <input type="date" name="date_examined" class="w-full px-2 py-1 border border-gray-300 rounded" value="<?= htmlspecialchars($exam['date_examined'] ?? date('Y-m-d')) ?>" /></div>
        <div><label class="block text-xs font-medium text-gray-700 mb-1">Clinician</label>
          <input type="text" name="clinician" readonly class="w-full px-2 py-1 border border-gray-300 rounded bg-gray-100" value="<?= htmlspecialchars($currentUserName ?: ($exam['clinician'] ?? '')) ?>" /></div>
        <div><label class="block text-xs font-medium text-gray-700 mb-1">Checked by</label>
          <input type="text" readonly class="w-full px-2 py-1 border border-gray-300 rounded bg-gray-100" value="<?= htmlspecialchars($ciName ?: ($exam['checked_by'] ?? '')) ?>" /></div>
      </div>

      <!-- tooth-chart section -->
      <div class="flex flex-col md:flex-row md:space-x-8 mb-6">
        <div class="flex-1 flex flex-col items-center">
          <div class="relative w-full max-w-2xl">
            <!-- Static tooth-chart background -->
            <img src="toothchart.jpg" alt="Tooth Chart" class="w-full h-auto border rounded shadow" id="toothChartImg" />

            <!-- If we have a saved drawing, show it instead of the default -->
            <?php if (!empty($exam['tooth_chart_drawing_path'])): ?>
              <img src="<?= $exam['tooth_chart_drawing_path'] ?>?<?= time() ?>" alt="Saved Drawing"
                   class="absolute top-0 left-0 w-full h-full pointer-events-none" id="savedDrawingImg" />
            <?php endif; ?>

            <canvas id="toothDrawCanvas" class="absolute top-0 left-0 w-full h-full"></canvas>
          </div>
          <div class="mt-2">
            <label class="text-xs font-medium text-gray-700">Upload new tooth-chart photo:</label>
            <input type="file" name="tooth_chart_photo" accept="image/*" class="text-xs" />
          </div>
          <div class="flex items-center space-x-2 mt-2">
            <label class="text-xs font-medium text-gray-700">Color:</label>
            <button type="button" class="color-btn w-6 h-6 rounded-full border-2 border-gray-300 bg-black" data-color="#000000"></button>
            <button type="button" class="color-btn w-6 h-6 rounded-full border-2 border-gray-300 bg-red-500" data-color="#ef4444"></button>
            <button type="button" class="color-btn w-6 h-6 rounded-full border-2 border-gray-300 bg-blue-500" data-color="#3b82f6"></button>
            <button type="button" class="color-btn w-6 h-6 rounded-full border-2 border-gray-300 bg-green-500" data-color="#22c55e"></button>
            <button type="button" class="color-btn w-6 h-6 rounded-full border-2 border-gray-300 bg-yellow-400" data-color="#facc15"></button>
          </div>
          <div class="flex items-center space-x-2 mt-2">
            <button type="button" id="resetDrawBtn" class="px-3 py-1 bg-gray-200 rounded text-xs font-medium">Reset</button>
            <button type="button" id="saveDrawBtn" class="px-3 py-1 bg-gray-200 rounded text-xs font-medium">Save</button>
          </div>
          <!-- old base-64 field removed -->
<input type="hidden" name="tooth_chart_drawing" id="tooth_chart_drawing" value="">
        </div>

        <div class="flex-1 flex flex-col">
          <div class="mb-4">
            <label class="block text-xs font-medium text-gray-700 mb-1">Diagnostic Tests:</label>
            <textarea name="diagnostic_tests" class="w-full px-2 py-1 border border-gray-300 rounded" rows="6"><?= htmlspecialchars($exam['diagnostic_tests'] ?? '') ?></textarea>
          </div>
        </div>
      </div>

      <!-- notes, assessment/plan, signatures, navigation identical -->
      <div class="mb-4">
        <label class="block text-xs font-medium text-gray-700 mb-1">Notes: (other clinical findings)</label>
        <textarea name="other_notes" class="w-full px-2 py-1 border border-gray-300 rounded" rows="3"><?= htmlspecialchars($exam['other_notes'] ?? '') ?></textarea>
      </div>

      <div class="mb-4">
        <table class="w-full border text-xs" id="assessmentTable">
          <thead>
            <tr class="bg-gray-100">
              <th colspan="2" class="border px-2 py-1">ASSESSMENT</th>
              <th colspan="3" class="border px-2 py-1">PLAN</th>
              <th class="border px-2 py-1">Actions</th>
            </tr>
            <tr>
              <th class="border px-2 py-1">SEQUENCE</th>
              <th class="border px-2 py-1">Tooth</th>
              <th class="border px-2 py-1">PROBLEMS/DIAGNOSES</th>
              <th class="border px-2 py-1">TREATMENT PLAN</th>
              <th class="border px-2 py-1">PROGNOSIS</th>
              <th class="border px-2 py-1">Add</th>
            </tr>
          </thead>
          <tbody>
            <?php
              $rows = json_decode($exam['assessment_plan_json'] ?? '[]', true) ?: [];
              $phases = [
                'MAIN CONCERN (PRIORITY)' => [],
                'I. SYSTEMIC PHASE' => [],
                'II. ACUTE PHASE' => [],
                'III. DISEASE CONTROL PHASE' => [],
                'IV. DEFINITIVE PHASE' => [],
                'V. MAINTENANCE PHASE' => []
              ];
              
              // Group existing rows by phase
              foreach ($rows as $row) {
                $sequence = $row['sequence'] ?? '';
                if (array_key_exists($sequence, $phases)) {
                  $phases[$sequence][] = $row;
                }
              }
              
              // Ensure each phase has at least one row
              foreach ($phases as $phase => $phaseRows) {
                if (empty($phaseRows)) {
                  $phases[$phase][] = ['sequence' => $phase, 'tooth' => '', 'diagnosis' => '', 'plan' => '', 'prognosis' => ''];
                }
              }
              
              foreach ($phases as $phase => $phaseRows):
                foreach ($phaseRows as $index => $row): ?>
                  <tr data-phase="<?= htmlspecialchars($phase) ?>">
                    <td class="border px-2 py-1">
                      <?php if ($index === 0): ?>
                        <?= $phase ?>
                        <input type="hidden" name="assess_sequence[]" value="<?= htmlspecialchars($phase) ?>" />
                      <?php else: ?>
                        <span class="text-gray-500 text-xs">↳ Additional</span>
                        <input type="hidden" name="assess_sequence[]" value="<?= htmlspecialchars($phase) ?>" />
                      <?php endif; ?>
                    </td>
                    <td class="border px-2 py-1"><input type="text" name="assess_tooth[]" class="w-full" value="<?= htmlspecialchars($row['tooth'] ?? '') ?>" /></td>
                    <td class="border px-2 py-1"><input type="text" name="assess_diagnosis[]" class="w-full" value="<?= htmlspecialchars($row['diagnosis'] ?? '') ?>" /></td>
                    <td class="border px-2 py-1"><input type="text" name="assess_plan[]" class="w-full" value="<?= htmlspecialchars($row['plan'] ?? '') ?>" /></td>
                    <td class="border px-2 py-1"><input type="text" name="assess_prognosis[]" class="w-full" value="<?= htmlspecialchars($row['prognosis'] ?? '') ?>" /></td>
                    <td class="border px-2 py-1">
                      <?php if ($index === 0 && in_array($phase, ['I. SYSTEMIC PHASE', 'II. ACUTE PHASE', 'III. DISEASE CONTROL PHASE', 'IV. DEFINITIVE PHASE', 'V. MAINTENANCE PHASE'])): ?>
                        <button type="button" onclick="addPhaseRow('<?= htmlspecialchars($phase) ?>')" class="text-blue-600 hover:text-blue-800 text-xs" title="Add row to this phase">✚</button>
                      <?php elseif ($index > 0): ?>
                        <button type="button" onclick="removePhaseRow(this)" class="text-red-600 hover:text-red-800 text-xs" title="Remove this row">✕</button>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach;
              endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Signature Section (Updated) -->
      <div class="flex flex-col md:flex-row md:space-x-8 mb-6 mt-6">
        <div class="flex-1">
            <label class="block text-xs font-medium text-gray-700 mb-2">Patient's name and signature</label>
            <div class="w-full max-w-[350px]">
                <div class="signature-pad-container">
                    <canvas id="step3SigPad" class="signature-pad"></canvas>
                </div>
                <button type="button" onclick="step3Pad.clear()" class="text-xs text-red-600 mt-1">Clear Signature</button>
                <input type="hidden" name="old_data_privacy_signature_path" value="<?= htmlspecialchars($sharedSignaturePath ?? '') ?>">
            </div>
            <!-- Printed Name (auto-filled from first + last name, read-only) -->
            <div class="w-full max-w-[350px] mt-2">
                <input  name="patient_signature"
                        type="text"
                        readonly
                        class="w-full px-2 py-1 border border-gray-300 rounded bg-gray-100"
                        value="<?= htmlspecialchars(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? '')) ?>" />
                <label class="block text-xs font-medium text-gray-700 mt-1">Printed Name</label>
            </div>
        </div>
        <!-- CASE HISTORY (compatible) -->
        <div class="flex-1 flex flex-col items-end">
          <div class="flex flex-col items-end">
            <label class="block text-xs font-medium text-gray-700 mb-1">CASE HISTORY</label>
            <div class="flex items-center space-x-2">
              <input type="text" name="history_performed_by" readonly class="w-32 border-0 border-b-2 border-gray-400 bg-gray-100 focus:ring-0 focus:border-primary text-sm" placeholder="Performed by" value="<?= htmlspecialchars($currentUserName ?: ($exam['history_performed_by'] ?? '')) ?>" />
              <input type="date" name="history_performed_date" class="w-24 border-0 border-b-2 border-gray-400 bg-transparent focus:ring-0 focus:border-primary text-sm" value="<?= htmlspecialchars($exam['history_performed_date'] ?? '') ?>" />

              <!-- Checked by (read-only, displays CI name) -->
              <input type="text" readonly class="w-32 border-0 border-b-2 border-gray-400 bg-transparent focus:ring-0 focus:border-primary text-sm" value="<?= htmlspecialchars($ciName) ?>" />

              <input type="date" name="history_checked_date" class="w-24 border-0 border-b-2 border-gray-400 bg-transparent focus:ring-0 focus:border-primary text-sm" value="<?= htmlspecialchars($exam['history_checked_date'] ?? '') ?>" />
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="mt-6 flex justify-end space-x-2">
    <a href="edit_patient_step2.php?id=<?= $patientId ?>" class="bg-gray-300 text-black px-6 py-2 rounded-button whitespace-nowrap">Previous</a>
      <button type="submit" class="next-btn bg-blue-600 text-white px-6 py-2 rounded-button whitespace-nowrap">Save & Next</button>
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
/* ========== TOOTH-CHART CANVAS (unchanged) ========== */
const canvas = document.getElementById('toothDrawCanvas');
const ctx    = canvas.getContext('2d');
const img    = document.getElementById('toothChartImg');
let color    = '#000000';
let drawing  = false;

function resizeCanvas() {
  canvas.width  = img.clientWidth;
  canvas.height = img.clientHeight;
  redraw();                       // redraw background + any saved overlay
}
function redraw() {
  ctx.clearRect(0, 0, canvas.width, canvas.height);
  ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
  const savedSrc = document.getElementById('savedDrawingImg')?.src;
  if (savedSrc) {
    const img2 = new Image();
    img2.onload = () => ctx.drawImage(img2, 0, 0, canvas.width, canvas.height);
    img2.src = savedSrc;
  }
}
function exportMergedCanvas() {
  const temp   = document.createElement('canvas');
  temp.width   = canvas.width;
  temp.height  = canvas.height;
  const tmpCtx = temp.getContext('2d');
  tmpCtx.drawImage(img, 0, 0, temp.width, temp.height);
  tmpCtx.drawImage(canvas, 0, 0);
  return temp.toDataURL('image/png');
}
window.addEventListener('load', () => {
  img.onload = resizeCanvas;
  if (img.complete) resizeCanvas();
});
window.addEventListener('resize', resizeCanvas);

document.querySelectorAll('.color-btn').forEach(btn =>
  btn.addEventListener('click', () => (color = btn.dataset.color))
);
['mousedown','touchstart'].forEach(ev =>
  canvas.addEventListener(ev, e => { drawing=true; draw(e); })
);
['mousemove','touchmove'].forEach(ev =>
  canvas.addEventListener(ev, draw)
);
['mouseup','touchend'].forEach(ev =>
  document.addEventListener(ev, () => drawing=false)
);
function draw(e) {
  if (!drawing) return;
  const rect = canvas.getBoundingClientRect();
  const x = (e.clientX ?? e.touches[0].clientX) - rect.left;
  const y = (e.clientY ?? e.touches[0].clientY) - rect.top;
  ctx.globalCompositeOperation = 'source-over';
  ctx.lineWidth   = 2;
  ctx.strokeStyle = color;
  ctx.lineCap     = 'round';
  ctx.beginPath();
  ctx.moveTo(x, y);
  ctx.lineTo(x, y);
  ctx.stroke();
}
document.getElementById('resetDrawBtn').addEventListener('click', () => {
  ctx.clearRect(0, 0, canvas.width, canvas.height);
  document.getElementById('tooth_chart_drawing').value = '';
});
document.getElementById('saveDrawBtn').addEventListener('click', () => {
  document.getElementById('tooth_chart_drawing').value = exportMergedCanvas();
  alert('Drawing saved (with background).');
});

/* ========== PHASE-SPECIFIC ASSESSMENT MANAGEMENT ========== */
function addPhaseRow(phaseName) {
  const tbody = document.querySelector('#assessmentTable tbody');
  const phaseRows = document.querySelectorAll(`tr[data-phase="${phaseName}"]`);
  const lastPhaseRow = phaseRows[phaseRows.length - 1];
  
  const newRow = document.createElement('tr');
  newRow.setAttribute('data-phase', phaseName);
  newRow.innerHTML = `
    <td class="border px-2 py-1">
      <span class="text-gray-500 text-xs">↳ Additional</span>
      <input type="hidden" name="assess_sequence[]" value="${phaseName}" />
    </td>
    <td class="border px-2 py-1"><input type="text" name="assess_tooth[]" class="w-full" value="" /></td>
    <td class="border px-2 py-1"><input type="text" name="assess_diagnosis[]" class="w-full" value="" /></td>
    <td class="border px-2 py-1"><input type="text" name="assess_plan[]" class="w-full" value="" /></td>
    <td class="border px-2 py-1"><input type="text" name="assess_prognosis[]" class="w-full" value="" /></td>
    <td class="border px-2 py-1">
      <button type="button" onclick="removePhaseRow(this)" class="text-red-600 hover:text-red-800 text-xs" title="Remove this row">✕</button>
    </td>
  `;
  
  // Insert after the last row of this phase
  lastPhaseRow.parentNode.insertBefore(newRow, lastPhaseRow.nextSibling);
}

function removePhaseRow(btn) {
  const row = btn.closest('tr');
  const phase = row.getAttribute('data-phase');
  const phaseRows = document.querySelectorAll(`tr[data-phase="${phase}"]`);
  
  // Don't allow removal if it's the only row for this phase
  if (phaseRows.length > 1) {
    row.remove();
  } else {
    alert('Each phase must have at least one row.');
  }
}

/* ========== SIGNATURE PAD (new) ========== */
const step3PadCanvas = document.getElementById('step3SigPad');
const step3Pad       = new SignaturePad(step3PadCanvas, { backgroundColor: '#fff' });

function resizeSignatureCanvas(canvas) {
  const ratio = Math.max(window.devicePixelRatio || 1, 1);
  canvas.width  = canvas.offsetWidth * ratio;
  canvas.height = canvas.offsetHeight * ratio;
  canvas.getContext('2d').scale(ratio, ratio);
  step3Pad.clear();
}
function loadSignature() {
  <?php
    $sharedSignaturePath = $consentData['data_privacy_signature_path'] ?? '';
    if (!empty($sharedSignaturePath) && file_exists(__DIR__ . '/' . $sharedSignaturePath)):
  ?>
    step3Pad.fromDataURL('<?= htmlspecialchars($sharedSignaturePath) ?>?t=<?= time() ?>');
  <?php endif; ?>
}
window.addEventListener('load', () => {
  resizeSignatureCanvas(step3PadCanvas);
  loadSignature();
});
window.addEventListener('resize', () => {
  resizeSignatureCanvas(step3PadCanvas);
  loadSignature();
});

/* ========== AJAX SUBMIT (updated) ========== */
document.getElementById('step3Form').addEventListener('submit', async e => {
  e.preventDefault();
  const fd = new FormData(e.target);

  /* tooth-chart drawing */
  fd.append('tooth_chart_drawing', exportMergedCanvas());

  /* signature */
  if (!step3Pad.isEmpty()) {
    fd.append('data_privacy_signature_path_base64', step3Pad.toDataURL('image/png'));
  }

  /* assessment / plan JSON */
  const sequences = fd.getAll('assess_sequence[]');
  const tooth = fd.getAll('assess_tooth[]');
  const diag  = fd.getAll('assess_diagnosis[]');
  const plan  = fd.getAll('assess_plan[]');
  const prog  = fd.getAll('assess_prognosis[]');
  fd.append('assessment_plan_json', JSON.stringify(
    sequences.map((s, i) => ({ sequence: s, tooth: tooth[i], diagnosis: diag[i], plan: plan[i], prognosis: prog[i] }))
  ));

  try {
    const res = await fetch('save_step3.php', { method: 'POST', body: fd });
    const msg = await res.text();
    if (res.ok) {
      alert('Saved successfully.');
      location.href = 'edit_patient_step4.php?id=<?= $patientId ?>';
    } else {
      alert('Error: ' + msg);
    }
  } catch (err) {
    alert('Network error. Please try again.');
    console.error(err);
  }
});
</script>
</body>
</html>