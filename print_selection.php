<?php
require_once 'config.php';
requireAuth();

$patientId = (int)($_GET['id'] ?? 0);
if (!$patientId) die('Invalid patient ID');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdn.jsdelivr.net/npm/remixicon@2.5.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="dark-mode-override.css">
  <style>
    .rounded-button{border-radius:20px}
    .custom-checkbox {
      width: 20px; height: 20px;
      border: 2px solid #d1d5db;
      border-radius: 4px;
      cursor: pointer;
      transition: background .2s;
    }
    .custom-checkbox.checked {
      background: #3b82f6;
      border-color: #3b82f6;
    }
  </style>
</head>
<body class="bg-white">

<!-- Modal container -->
<div id="printSelectionModal" class="fixed inset-0 bg-white z-50 flex flex-col">
  <header class="bg-white shadow-sm">
    <div class="container mx-auto px-4 py-4 flex justify-between items-center">
      <div class="flex items-center">
        <button id="closePrintSelectionModal" class="mr-4 text-gray-500 hover:text-gray-700">
          <i class="ri-arrow-left-line"></i>
        </button>
        <h1 class="text-xl font-semibold text-gray-800">Print Patient Record</h1>
      </div>
      <button id="printRecordBtn" class="bg-blue-600 text-white px-4 py-2 rounded-button flex items-center">
        <i class="ri-printer-line mr-2"></i> Print
      </button>
    </div>
  </header>

  <div class="flex-1 container mx-auto px-4 py-6">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Left panel – page selection -->
      <div class="lg:col-span-1">
        <div class="bg-white rounded shadow-sm p-6">
          <h3 class="text-lg font-semibold text-gray-800 mb-4">Select Pages to Print</h3>

          <div class="flex items-center justify-between mb-4">
            <span class="text-sm font-medium text-gray-700">Select All</span>
            <input type="checkbox" id="selectAll" class="w-5 h-5 accent-blue-600">
          </div>

          <div class="space-y-4">
            <?php
            $pages = [
                1 => 'Personal Information',
                2 => 'Medical History',
                3 => 'Dental History',
                4 => 'Consent Form',
                5 => 'Progress Notes',
            ];
            foreach ($pages as $p => $label): ?>
            <div class="flex items-center justify-between">
              <span class="text-sm text-gray-700"><?= $label ?></span>
              <div class="custom-checkbox" data-page="<?= $p ?>"></div>
            </div>
            <?php endforeach; ?>
          </div>

          <div class="mt-6 pt-6 border-t">
            <h4 class="text-sm font-semibold text-gray-800 mb-4">Print Settings</h4>
            <div class="space-y-4">
              <div>
                <label class="block text-sm text-gray-700 mb-1">Paper Size</label>
                <select id="paperSize" class="w-full px-4 py-2 border rounded">
                  <option>Letter (8.5" x 11")</option>
                  <option>A4 (210 x 297 mm)</option>
                  <option>Legal (8.5" x 14")</option>
                </select>
              </div>
              <div>
                <label class="block text-sm text-gray-700 mb-1">Orientation</label>
                <div class="flex space-x-4">
                  <label class="flex items-center">
                    <input type="radio" name="orientation" value="portrait" class="hidden" checked>
                    <div class="w-4 h-4 rounded-full border mr-2 flex items-center justify-center">
                      <div class="w-2 h-2 rounded-full bg-blue-600"></div>
                    </div>
                    <span class="text-sm text-gray-700">Portrait</span>
                  </label>
                  <label class="flex items-center">
                    <input type="radio" name="orientation" value="landscape" class="hidden">
                    <div class="w-4 h-4 rounded-full border mr-2 flex items-center justify-center"></div>
                    <span class="text-sm text-gray-700">Landscape</span>
                  </label>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Right panel – live preview -->
      <div class="lg:col-span-2">
        <div class="bg-white rounded shadow-sm p-6 h-full">
          <h3 class="text-lg font-semibold text-gray-800 mb-4">Preview</h3>
          <div class="border rounded p-4 h-[calc(100%-3rem)] flex items-center justify-center bg-gray-50">
            <iframe id="previewFrame" class="w-full h-full border-0"></iframe>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// --- Modal controls ---
const modal = document.getElementById('printSelectionModal');
const previewFrame = document.getElementById('previewFrame');
const patientId = <?= $patientId ?>;

function openPrintModal() {
  modal.classList.remove('hidden');
  updatePreview();   // load first page by default
}
function closePrintModal() {
  modal.classList.add('hidden');
}

// --- Checkbox logic ---
const checkboxes = document.querySelectorAll('.custom-checkbox');
const selectAll  = document.getElementById('selectAll');

function updatePreview() {
  const selected = [...checkboxes]
    .filter(cb => cb.classList.contains('checked'))
    .map(cb => cb.dataset.page);
  previewFrame.src = 'print_report.php?id=' + patientId + '&pages=' + selected.join(',');
}

checkboxes.forEach(cb => {
  cb.addEventListener('click', () => {
    cb.classList.toggle('checked');
    updatePreview();
  });
});
selectAll.addEventListener('change', () => {
  checkboxes.forEach(cb => {
    cb.classList.toggle('checked', selectAll.checked);
  });
  updatePreview();
});

// --- Print button ---
document.getElementById('printRecordBtn').addEventListener('click', () => {
  previewFrame.contentWindow.print();
});

// --- Close buttons ---
document.getElementById('closePrintSelectionModal').addEventListener('click', closePrintModal);

// Export for use in every edit page
window.openPrintModal = openPrintModal;
</script>
</body>
</html>