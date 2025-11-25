<?php
require_once 'config.php';
requireAuth();

$user = getCurrentUser();
$role = $user['role'] ?? '';
$userFullName = $user['full_name'] ?? '';

// Restrict COD users from viewing patient details
if ($role === 'COD') {
    header('Location: patients.php?error=access_denied');
    exit;
}

$patientId = $_GET['id'] ?? null;
if (!$patientId) {
    header('Location: patients.php');
    exit;
}

// Handle approval/disapproval actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role === 'Clinical Instructor') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $notes = $_POST['approval_notes'] ?? '';
        
        // Get the assignment ID for this patient and CI
        $stmt = $pdo->prepare("
            SELECT pa.id 
            FROM patient_assignments pa
            WHERE pa.patient_id = ? 
            AND pa.clinical_instructor_id = ? 
            AND pa.assignment_status IN ('accepted', 'completed')
            LIMIT 1
        ");
        $stmt->execute([$patientId, $user['id']]);
        $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($assignment) {
            $status = ($action === 'approve') ? 'approved' : 'rejected';
            $result = updatePatientApproval($assignment['id'], $user['id'], $status, $notes);
            
            if ($result) {
                $message = ($action === 'approve') ? 'Patient approved successfully!' : 'Patient declined successfully!';
                header("Location: view_patient.php?id=$patientId&message=" . urlencode($message));
                exit;
            }
        }
    }
}

// Fetch all patient data
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Basic patient info
$stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
$stmt->execute([$patientId]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    header('Location: patients.php');
    exit;
}

// Patient Information Record (PIR) - Step 1
$stmt = $pdo->prepare("SELECT * FROM patient_pir WHERE patient_id = ?");
$stmt->execute([$patientId]);
$pirData = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

// Health Questionnaire - Step 2
$stmt = $pdo->prepare("SELECT * FROM patient_health WHERE patient_id = ?");
$stmt->execute([$patientId]);
$healthData = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

// Dental Examination - Step 3
$stmt = $pdo->prepare("SELECT * FROM dental_examination WHERE patient_id = ?");
$stmt->execute([$patientId]);
$dentalExam = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

// Get real-time assigned clinical instructor name
$assignedClinicalInstructor = getAssignedClinicalInstructor($patientId) ?: '';

// Informed Consent - Step 4
$stmt = $pdo->prepare("SELECT * FROM informed_consent WHERE patient_id = ?");
$stmt->execute([$patientId]);
$consentData = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

// Progress Notes - Step 5
$stmt = $pdo->prepare("SELECT * FROM progress_notes WHERE patient_id = ? ORDER BY date DESC, id DESC");
$stmt->execute([$patientId]);
$progressNotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if this CI can approve/disapprove this patient
$canApprove = false;
if ($role === 'Clinical Instructor') {
    $stmt = $pdo->prepare("
        SELECT pa.*, papp.approval_status 
        FROM patient_assignments pa
        LEFT JOIN patient_approvals papp ON pa.id = papp.assignment_id
        WHERE pa.patient_id = ? 
        AND pa.clinical_instructor_id = ? 
        AND pa.assignment_status IN ('accepted', 'completed')
        LIMIT 1
    ");
    $stmt->execute([$patientId, $user['id']]);
    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
    $canApprove = !empty($assignment) && (!$assignment['approval_status'] || $assignment['approval_status'] === 'pending');
}

$message = $_GET['message'] ?? '';
$patientFullName = trim($patient['first_name'] . ' ' . $patient['last_name']);
$profilePicture = $user['profile_picture'] ?? null;

function val($key, $array = []) {
    return htmlspecialchars($array[$key] ?? '');
}

function checked($key, $array) {
    return (!empty($array[$key])) ? 'checked' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Patient - <?php echo htmlspecialchars($patientFullName); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.0.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="dark-mode-override.css">
    <script>
        tailwind.config = {
            darkMode: 'class',
        }
    </script>
    <style>
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .tab-button.active {
            background-color: rgb(37, 99, 235);
            color: white;
        }
        .print-section {
            page-break-inside: avoid;
        }
        @media print {
            .no-print {
                display: none !important;
            }
        }
        .header-profile-pic {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e5e7eb;
        }
        .dark .header-profile-pic {
            border-color: #4b5563;
        }
        .sticky-nav {
            position: sticky;
            top: 0;
            z-index: 40;
            background: white;
            border-bottom: 1px solid #e5e7eb;
        }
        .dark .sticky-nav {
            background: rgb(31 41 55);
            border-color: #374151;
        }
        .approval-section {
            animation: slideDown 0.3s ease-in-out;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .info-grid {
            display: grid;
            gap: 0.5rem;
        }
        @media (min-width: 768px) {
            .info-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            }
        }
        .signature-display {
            max-width: 300px;
            height: 150px;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f9fafb;
        }
        .signature-display img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
    </style>
</head>
<body class="bg-gray-50 dark:bg-gray-900 transition-colors duration-200">

<!-- Header -->
<header class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700 no-print">
    <div class="flex items-center justify-between px-6 py-4">
        <div class="flex items-center">
            <button onclick="window.location.href='patients.php'" class="mr-4 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                <i class="ri-arrow-left-line text-xl"></i>
            </button>
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Patient Information Review</h1>
        </div>
        <div class="flex items-center space-x-4">
            <?php if ($profilePicture && file_exists(__DIR__ . '/' . $profilePicture)): ?>
                <img src="<?php echo htmlspecialchars($profilePicture); ?>" alt="Profile" class="header-profile-pic">
            <?php else: ?>
                <div class="header-profile-pic bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                    <i class="ri-user-3-line text-sm"></i>
                </div>
            <?php endif; ?>
            <span class="text-sm text-gray-600 dark:text-gray-300">
                <?php echo htmlspecialchars($userFullName . ' (' . $role . ')'); ?>
            </span>
            <button id="darkModeToggle" class="p-2 rounded-md text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700">
                <i class="ri-moon-line dark:hidden"></i>
                <i class="ri-sun-line hidden dark:inline"></i>
            </button>
            <button onclick="openPrintReport()" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                <i class="ri-printer-line mr-1"></i>Print Report
            </button>
            <a href="logout.php" class="text-sm text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300">
                <i class="ri-logout-line mr-1"></i>Logout
            </a>
        </div>
    </div>
</header>

<div class="container mx-auto px-4 py-6 max-w-7xl">
    
    <?php if ($message): ?>
        <div class="mb-6 bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-600 text-green-700 dark:text-green-200 px-4 py-3 rounded relative approval-section">
            <span><?php echo htmlspecialchars($message); ?></span>
            <button onclick="this.parentElement.style.display='none'" class="absolute top-0 bottom-0 right-0 px-4 py-3">
                <i class="ri-close-line"></i>
            </button>
        </div>
    <?php endif; ?>

    <!-- Patient Header Card -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 mb-6 patient-header">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4">
            <div>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo htmlspecialchars($patientFullName); ?></h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Patient ID: #<?php echo str_pad($patient['id'], 4, '0', STR_PAD_LEFT); ?></p>
            </div>
            <div class="flex items-center space-x-4 mt-4 md:mt-0">
                <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full status-badge 
                    <?php 
                    echo $patient['status'] === 'Approved' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 
                         ($patient['status'] === 'Pending' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : 
                          'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'); 
                    ?>">
                    <?php echo htmlspecialchars($patient['status']); ?>
                </span>
                <?php if (isset($pirData['photo']) && $pirData['photo']): ?>
                    <img src="<?php echo htmlspecialchars($pirData['photo']); ?>" alt="Patient Photo" class="w-16 h-16 rounded-full object-cover border-2 border-gray-200 dark:border-gray-600">
                <?php endif; ?>
            </div>
        </div>
        
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div>
                <span class="text-gray-500 dark:text-gray-400">Age:</span>
                <span class="ml-2 font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($patient['age']); ?> years</span>
            </div>
            <div>
                <span class="text-gray-500 dark:text-gray-400">Gender:</span>
                <span class="ml-2 font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($patient['gender'] ?? $pirData['gender'] ?? 'Not specified'); ?></span>
            </div>
            <div>
                <span class="text-gray-500 dark:text-gray-400">Phone:</span>
                <span class="ml-2 font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($patient['phone']); ?></span>
            </div>
            <div>
                <span class="text-gray-500 dark:text-gray-400">Email:</span>
                <span class="ml-2 font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($patient['email']); ?></span>
            </div>
        </div>
    </div>

    <!-- Approval/Disapproval Section for Clinical Instructors -->
    <?php if ($canApprove): ?>
    <div class="bg-blue-50 dark:bg-blue-900/20 border-2 border-blue-200 dark:border-blue-700 rounded-lg p-6 mb-6 no-print">
        <h3 class="text-lg font-semibold text-blue-900 dark:text-blue-200 mb-4">
            <i class="ri-shield-check-line mr-2"></i>Clinical Review & Approval
        </h3>
        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Review Notes</label>
                <textarea name="approval_notes" rows="3" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white" placeholder="Enter your review comments..."></textarea>
            </div>
            <div class="flex space-x-4">
                <button type="submit" name="action" value="approve" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-md flex items-center">
                    <i class="ri-check-line mr-2"></i>Approve Patient
                </button>
                <button type="submit" name="action" value="disapprove" class="bg-red-600 hover:bg-red-700 text-white px-6 py-2 rounded-md flex items-center">
                    <i class="ri-close-line mr-2"></i>Decline Patient
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- Navigation Tabs -->
    <div class="sticky-nav bg-white dark:bg-gray-800 rounded-lg shadow-md mb-6 no-print">
        <div class="flex flex-wrap">
            <button class="tab-button active px-4 py-3 text-sm font-medium rounded-tl-lg transition-colors duration-200" data-tab="personal">
                <i class="ri-user-line mr-2"></i>Personal Data
            </button>
            <button class="tab-button px-4 py-3 text-sm font-medium transition-colors duration-200" data-tab="health">
                <i class="ri-heart-pulse-line mr-2"></i>Health Questionnaire
            </button>
            <button class="tab-button px-4 py-3 text-sm font-medium transition-colors duration-200" data-tab="dental">
                <i class="ri-first-aid-kit-line mr-2"></i>Dental Examination
            </button>
            <button class="tab-button px-4 py-3 text-sm font-medium transition-colors duration-200" data-tab="consent">
                <i class="ri-file-text-line mr-2"></i>Informed Consent
            </button>
            <button class="tab-button px-4 py-3 text-sm font-medium rounded-tr-lg transition-colors duration-200" data-tab="progress">
                <i class="ri-file-list-2-line mr-2"></i>Progress Notes
            </button>
        </div>
    </div>

    <!-- Tab Content -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
        
        <!-- Tab 1: Personal Data -->
<div id="personal" class="tab-content active">

<!-- ======  A.  Header  ====== -->
<header class="flex items-center mb-8">
  <i class="ri-user-3-line text-indigo-600 dark:text-indigo-400 text-2xl mr-3"></i>
  <h3 class="text-2xl font-bold text-gray-900 dark:text-white">
    Personal Information Record
  </h3>
</header>

<!-- ======  B.  Quick-stats bar  ====== -->
<section class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
  <?php
    $stats = [
      'Full Name' => trim(val('first_name', $pirData) . ' ' . val('mi', $pirData) . ' ' . val('last_name', $pirData)),
      'Birthdate' => val('date_of_birth', $pirData) ?: '—',
      'Civil Status' => val('civil_status', $pirData) ?: '—',
      'Mobile' => val('mobile_no', $pirData) ?: '—'
    ];
    foreach ($stats as $label => $value):
  ?>
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 border border-gray-200 dark:border-gray-700">
      <p class="text-xs text-gray-500 dark:text-gray-400"><?= $label ?></p>
      <p class="text-sm font-semibold text-gray-900 dark:text-white truncate"><?= $value ?></p>
    </div>
  <?php endforeach; ?>
</section>

<!-- ======  C.  Two-column master layout  ====== -->
<section class="grid md:grid-cols-3 gap-6">

  <!-- Left column: pictures + signature -->
  <aside class="md:col-span-1 space-y-6">
    <!-- Photos -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-200 dark:border-gray-700">
      <h4 class="text-base font-semibold text-gray-800 dark:text-gray-100 mb-4">Patient Images</h4>
      <div class="grid grid-cols-2 gap-4">
        <?php foreach (['photo' => '1×1 Picture', 'thumbmark' => 'Thumbmark'] as $key => $title): ?>
          <?php if (isset($pirData[$key]) && $pirData[$key]): ?>
            <div>
              <p class="text-xs text-gray-500 dark:text-gray-400 mb-2"><?= $title ?></p>
              <div class="w-full aspect-square rounded-lg overflow-hidden border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700">
                <img src="<?= htmlspecialchars($pirData[$key]) ?>" alt="<?= $title ?>" class="w-full h-full object-cover">
              </div>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Signature -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-200 dark:border-gray-700">
      <h4 class="text-base font-semibold text-gray-800 dark:text-gray-100 mb-4">PIR Signature</h4>
      <?php if (isset($consentData['data_privacy_signature_path']) && $consentData['data_privacy_signature_path']): ?>
        <img src="<?= htmlspecialchars($consentData['data_privacy_signature_path']) ?>" class="w-full max-h-32 object-contain rounded border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700">
      <?php else: ?>
        <p class="text-sm text-gray-500 dark:text-gray-400 italic">Signature not provided</p>
      <?php endif; ?>
      <p class="text-xs text-gray-500 dark:text-gray-400 mt-3">Printed Name:</p>
      <p class="text-sm font-semibold text-gray-900 dark:text-white"><?= trim(val('first_name', $pirData) . ' ' . val('last_name', $pirData)) ?: '—' ?></p>
    </div>
  </aside>

  <!-- Right column: data cards -->
  <div class="md:col-span-2 space-y-6">

    <!-- reusable card macro -->
    <?php
      $card = function(string $title, array $rows) {
        echo '<div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-200 dark:border-gray-700">';
        echo '<h4 class="text-base font-semibold text-gray-800 dark:text-gray-100 mb-4">' . $title . '</h4>';
        echo '<dl class="divide-y divide-gray-100 dark:divide-gray-700">';
        foreach ($rows as $dt => $dd) {
          $dd = $dd ?: '—';
          echo "
            <div class=\"py-3 flex justify-between items-center\">
              <dt class=\"text-sm text-gray-600 dark:text-gray-400\">$dt</dt>
              <dd class=\"text-sm font-medium text-gray-900 dark:text-white\">$dd</dd>
            </div>";
        }
        echo '</dl></div>';
      };

      /* ---------- data sets ---------- */
      $card('Personal Details', [
        'Full Name' => trim(val('first_name', $pirData) . ' ' . val('mi', $pirData) . ' ' . val('last_name', $pirData)),
        'Nickname' => val('nickname', $pirData),
        'Date of Birth' => val('date_of_birth', $pirData),
        'Civil Status' => val('civil_status', $pirData),
        'Ethnicity' => val('ethnicity', $pirData)
      ]);

      $card('Contact Information', [
        'Home Address' => val('home_address', $pirData),
        'Home Phone' => val('home_phone', $pirData),
        'Mobile' => val('mobile_no', $pirData),
        'Occupation' => val('occupation', $pirData),
        'Work Address' => val('work_address', $pirData),
        'Work Phone' => val('work_phone', $pirData)
      ]);

      $emer = ['Contact Name' => val('emergency_contact_name', $pirData), 'Contact Number' => val('emergency_contact_number', $pirData)];
      if (val('guardian_name', $pirData)) {
        $emer['Guardian'] = val('guardian_name', $pirData);
        $emer['Guardian Contact'] = val('guardian_contact', $pirData);
      }
      $card('Emergency Contact', $emer);

      $card('Clinical Information', [
        'Date Today' => val('date_today', $pirData),
        'Clinician' => val('clinician', $pirData),
        'Clinic' => val('clinic', $pirData)
      ]);
    ?>

    <!-- Medical History (prose) -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-200 dark:border-gray-700">
      <h4 class="text-base font-semibold text-gray-800 dark:text-gray-100 mb-4">Medical History</h4>
      <?php
        $prose = [
          'Chief Complaint' => val('chief_complaint', $pirData),
          'History of Present Illness' => val('present_illness', $pirData),
          'Medical History' => val('medical_history', $pirData),
          'Dental History' => val('dental_history', $pirData),
          'Family History' => val('family_history', $pirData),
          'Personal/Social History' => val('personal_history', $pirData)
        ];
        foreach ($prose as $label => $text):
      ?>
        <div class="mb-4 last:mb-0">
          <p class="text-sm font-medium text-gray-700 dark:text-gray-300"><?= $label ?></p>
          <p class="mt-1 text-sm text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-700/60 rounded-lg p-3"><?= $text ?: 'None specified' ?></p>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Review of Systems -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-200 dark:border-gray-700">
      <h4 class="text-base font-semibold text-gray-800 dark:text-gray-100 mb-4">Review of Systems</h4>
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
        <?php
          $systems = ['skin', 'extremities', 'eyes', 'ent', 'respiratory', 'cardiovascular', 'gastrointestinal', 'genitourinary', 'endocrine', 'hematopoietic', 'neurological', 'psychiatric', 'growth_or_tumor'];
          foreach ($systems as $s):
            $val = val($s, $pirData) ?: 'N/A';
        ?>
          <div class="bg-gray-50 dark:bg-gray-700/60 rounded-lg p-3">
            <p class="text-xs text-gray-500 dark:text-gray-400 capitalize"><?= str_replace('_', ' ', $s) ?></p>
            <p class="text-sm font-medium text-gray-900 dark:text-white mt-1"><?= $val ?></p>
          </div>
        <?php endforeach; ?>
      </div>
      <?php if (val('summary', $pirData)): ?>
        <div class="mt-4">
          <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Summary</p>
          <p class="mt-1 text-sm text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-700/60 rounded-lg p-3"><?= val('summary', $pirData) ?></p>
        </div>
      <?php endif; ?>
    </div>

    <!-- ASA Classification -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-200 dark:border-gray-700">
      <h4 class="text-base font-semibold text-gray-800 dark:text-gray-100 mb-4">ASA Classification</h4>
      <dl class="divide-y divide-gray-100 dark:divide-gray-700">
        <div class="py-3 flex justify-between items-center">
          <dt class="text-sm text-gray-600 dark:text-gray-400">ASA Class</dt>
          <dd class="text-sm font-medium text-gray-900 dark:text-white"><?= val('asa', $pirData) ? 'Class ' . val('asa', $pirData) : 'Not assessed' ?></dd>
        </div>
        <?php if (val('asa_notes', $pirData)): ?>
          <div class="py-3">
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notes</p>
            <p class="text-sm text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-700/60 rounded-lg p-3"><?= val('asa_notes', $pirData) ?></p>
          </div>
        <?php endif; ?>
      </dl>
    </div>

  </div><!-- /right column -->
</section>
</div>

        <!-- Tab 2: Health Questionnaire -->
<div id="health" class="tab-content">

<!-- ======  A.  Header  ====== -->
<header class="flex items-center mb-8">
  <i class="ri-heart-pulse-line text-emerald-600 dark:text-emerald-400 text-2xl mr-3"></i>
  <h3 class="text-2xl font-bold text-gray-900 dark:text-white">
    Health Questionnaire & Clinical Examination
  </h3>
</header>

<!-- ======  B.  Quick pills  ====== -->
<section class="flex flex-wrap gap-3 mb-8">
  <?php
    $pills = [
      'Under physician care' => checked('under_physician_care', $healthData),
      'Serious illness/operation' => checked('serious_illness_operation', $healthData),
      'Abnormal bleeding' => checked('abnormal_bleeding_yes', $healthData),
      'Blood disorder' => checked('blood_disorder_yes', $healthData),
      'Pregnant' => ($patient['gender'] ?? $pirData['gender'] ?? '') === 'female' && checked('pregnant', $healthData),
    ];
    foreach ($pills as $label => $active):
      if ($active):
  ?>
    <span class="px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300">
      <?= $label ?>
    </span>
  <?php endif; endforeach; ?>
</section>

<!-- ======  C.  Master grid - Updated for full width  ====== -->
<section class="grid grid-cols-1 gap-6">

  <!-- Left: questionnaire - Expanded to take more space -->
  <div class="xl:col-span-3 lg:col-span-2 space-y-6">

    <!-- reusable card -->
    <?php
      $card = fn(string $title, string $body) => '
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-200 dark:border-gray-700">
          <h4 class="text-base font-semibold text-gray-800 dark:text-gray-100 mb-4">' . $title . '</h4>
          ' . $body . '
        </div>';
    ?>

    <!-- Health questions (table) -->
    <?= $card('Health Questions', '
      <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 dark:bg-gray-700/60">
            <tr>
              <th class="px-4 py-3 text-left font-medium text-gray-700 dark:text-gray-300">Question</th>
              <th class="px-4 py-3 text-center w-20">Yes</th>
              <th class="px-4 py-3 text-center w-20">No</th>
              <th class="px-4 py-3 text-left">Details</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
            ' . call_user_func(function() use ($healthData) {
              $rows = [
                ['Last medical physical', 'last_medical_physical_yes', 'last_medical_physical_no', 'last_medical_physical'],
                ['Physician name/address', '', '', 'physician_name_addr'],
                ['Under physician care', 'under_physician_care', '', 'under_physician_care_note'],
                ['Serious illness/operation', 'serious_illness_operation', '', 'serious_illness_operation_note'],
                ['Hospitalized', 'hospitalized', '', 'hospitalized_note'],
                ['Abnormal bleeding', 'abnormal_bleeding_yes', 'abnormal_bleeding_no', ''],
                ['Bruise easily', 'bruise_easily_yes', 'bruise_easily_no', ''],
                ['Blood transfusion', 'blood_transfusion_yes', 'blood_transfusion_no', ''],
                ['Blood disorder', 'blood_disorder_yes', 'blood_disorder_no', ''],
                ['Head/neck radiation', 'head_neck_radiation_yes', 'head_neck_radiation_no', '']
              ];
              $html = '';
              foreach ($rows as [$q, $y, $n, $det]) {
                $yes = $y && checked($y, $healthData);
                $no  = $n && checked($n, $healthData);
                $html .= '
                <tr>
                  <td class="px-4 py-3 text-gray-900 dark:text-white">' . $q . '</td>
                  <td class="px-4 py-3 text-center">' . ($yes ? '✅' : '') . '</td>
                  <td class="px-4 py-3 text-center">' . ($no ? '✅' : '') . '</td>
                  <td class="px-4 py-3 text-gray-700 dark:text-gray-300">' . val($det, $healthData) . '</td>
                </tr>';
              }
              return $html;
            }) . '
          </tbody>
        </table>
      </div>') ?>

    <!-- Diseases & Allergies in a 2-column layout -->
    <div class="grid lg:grid-cols-2 gap-6">
      
      <!-- Diseases -->
      <?= $card('Diseases or Problems', '
        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-3 text-sm">
          ' . call_user_func(function() use ($healthData) {
            $cond = [
              'rheumatic_fever' => 'Rheumatic Fever',
              'heart_abnormalities' => 'Heart Abnormalities',
              'cardiovascular_disease' => 'Cardiovascular Disease',
              'childhood_diseases' => 'Childhood Diseases',
              'asthma_hayfever' => 'Asthma / Hay Fever',
              'hives_skin_rash' => 'Hives / Skin Rash',
              'fainting_seizures' => 'Fainting Spells / Seizures',
              'diabetes' => 'Diabetes',
              'urinate_more' => 'Urinate >6×/day',
              'thirsty' => 'Thirsty Often',
              'mouth_dry' => 'Mouth Dry',
              'hepatitis' => 'Hepatitis / Liver',
              'arthritis' => 'Arthritis',
              'stomach_ulcers' => 'Stomach Ulcers',
              'kidney_trouble' => 'Kidney Trouble',
              'tuberculosis' => 'Tuberculosis',
              'venereal_disease' => 'Venereal Disease',
              'other_conditions' => 'Other Conditions'
            ];
            $html = '';
            foreach ($cond as $field => $label) {
              $on = checked($field, $healthData);
              $note = in_array($field, ['childhood_diseases', 'other_conditions']) ? val($field . '_note', $healthData) : '';
              $html .= '
              <div class="flex items-start space-x-2">
                <span class="' . ($on ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-gray-600 dark:text-gray-400') . '">
                  ' . ($on ? '✓' : '○') . '
                </span>
                <span class="' . ($on ? 'text-red-600 dark:text-red-400 font-medium' : 'text-gray-700 dark:text-gray-300') . '">
                  ' . $label . '
                  ' . ($note ? '<span class="text-xs text-gray-500 ml-1">(' . $note . ')</span>' : '') . '
                </span>
              </div>';
            }
            return $html;
          }) . '
        </div>') ?>

      <!-- Allergies -->
      <?= $card('Allergies / Adverse Reactions', '
        <div class="grid grid-cols-1 gap-3 text-sm">
          ' . call_user_func(function() use ($healthData) {
            $aller = [
              'anesthetic_allergy' => 'Local Anesthetics',
              'penicillin_allergy' => 'Penicillin / Antibiotics',
              'aspirin_allergy' => 'Aspirin',
              'latex_allergy' => 'Latex',
              'other_allergy' => 'Other Allergy'
            ];
            $html = '';
            foreach ($aller as $field => $label) {
              $on = checked($field, $healthData);
              $html .= '
              <div class="flex items-center space-x-2">
                <span class="' . ($on ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-gray-600 dark:text-gray-400') . '">
                  ' . ($on ? '✓' : '○') . '
                </span>
                <span class="' . ($on ? 'text-red-600 dark:text-red-400 font-medium' : 'text-gray-700 dark:text-gray-300') . '">' . $label . '</span>
              </div>';
            }
            return $html;
          }) . '
        </div>') ?>

    </div>

    <!-- Additional info -->
    <?= $card('Additional Health Information', '
      <div class="grid md:grid-cols-3 gap-4 text-sm">
        <div>
          <p class="text-gray-600 dark:text-gray-400 font-medium mb-1">Taking any drugs/medicines?</p>
          <p class="text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-700/60 rounded-lg p-3 min-h-[4rem]">' . (val('taking_drugs', $healthData) ?: 'None') . '</p>
        </div>
        <div>
          <p class="text-gray-600 dark:text-gray-400 font-medium mb-1">Previous dental trouble?</p>
          <p class="text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-700/60 rounded-lg p-3 min-h-[4rem]">' . (val('previous_dental_trouble', $healthData) ?: 'None') . '</p>
        </div>
        <div>
          <p class="text-gray-600 dark:text-gray-400 font-medium mb-1">Other conditions / notes</p>
          <p class="text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-700/60 rounded-lg p-3 min-h-[4rem]">' . (val('other_problem', $healthData) ?: 'None') . '</p>
        </div>
        <div>
          <p class="text-gray-600 dark:text-gray-400 font-medium mb-1">X-ray exposure?</p>
          <p class="text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-700/60 rounded-lg p-3 min-h-[4rem]">' . (val('xray_exposure', $healthData) ?: 'None') . '</p>
        </div>
        <div>
          <p class="text-gray-600 dark:text-gray-400 font-medium mb-1">Eyeglasses / contacts?</p>
          <p class="text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-700/60 rounded-lg p-3 min-h-[4rem]">' . (val('eyeglasses', $healthData) ?: 'None') . '</p>
        </div>
      </div>') ?>

    <!-- For Women Only -->
    <?php if (($patient['gender'] ?? $pirData['gender'] ?? '') === 'female'): ?>
      <?= $card('For Women Only', '
        <div class="flex gap-6 text-sm">
          <div class="flex items-center space-x-2">
            <span class="' . (checked('pregnant', $healthData) ? 'text-emerald-600 dark:text-emerald-400 font-semibold' : 'text-gray-600 dark:text-gray-400') . '">
              ' . (checked('pregnant', $healthData) ? '✅' : '○') . '
            </span>
            <span class="' . (checked('pregnant', $healthData) ? 'text-emerald-700 dark:text-emerald-300 font-medium' : 'text-gray-700 dark:text-gray-300') . '">Pregnant / Missed Period</span>
          </div>
          <div class="flex items-center space-x-2">
            <span class="' . (checked('breast_feeding', $healthData) ? 'text-emerald-600 dark:text-emerald-400 font-semibold' : 'text-gray-600 dark:text-gray-400') . '">
              ' . (checked('breast_feeding', $healthData) ? '✅' : '○') . '
            </span>
            <span class="' . (checked('breast_feeding', $healthData) ? 'text-emerald-700 dark:text-emerald-300 font-medium' : 'text-gray-700 dark:text-gray-300') . '">Breast Feeding</span>
          </div>
        </div>') ?>
    <?php endif; ?>

  </div><!-- /left -->

  <!-- Right: clinical examination - Now single column -->
  <aside class="xl:col-span-1 lg:col-span-1 space-y-6">

    <!-- General Appraisal -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-200 dark:border-gray-700">
      <h4 class="text-base font-semibold text-gray-800 dark:text-gray-100 mb-4">General Appraisal</h4>
      <div class="space-y-3 text-sm">
        <?php
          foreach ([
            'General Health Notes' => 'general_health_notes',
            'Physical' => 'physical',
            'Mental' => 'mental',
            'Vital Signs' => 'vital_signs'
          ] as $label => $field):
        ?>
          <div>
            <p class="text-gray-600 dark:text-gray-400 font-medium"><?= $label ?></p>
            <p class="mt-1 text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-700/60 rounded-lg p-3"><?= val($field, $healthData) ?: 'N/A' ?></p>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Extraoral -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-200 dark:border-gray-700">
      <h4 class="text-base font-semibold text-gray-800 dark:text-gray-100 mb-4">Extraoral Examination</h4>
      <div class="space-y-3 text-sm">
        <?php
          $extra = [
            'Head Face' => 'extra_head_face',
            'Eyes' => 'extra_eyes',
            'Ears' => 'extra_ears',
            'Nose' => 'extra_nose',
            'Hair' => 'extra_hair',
            'Neck' => 'extra_neck',
            'Paranasal' => 'extra_paranasal',
            'Lymph' => 'extra_lymph',
            'Salivary' => 'extra_salivary',
            'TMJ' => 'extra_tmj',
            'Muscles' => 'extra_muscles',
            'Other' => 'extra_other'
          ];
          foreach ($extra as $label => $field):
        ?>
          <div>
            <p class="text-gray-600 dark:text-gray-400 font-medium"><?= $label ?></p>
            <p class="mt-1 text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-700/60 rounded-lg p-3"><?= val($field, $healthData) ?: 'N/A' ?></p>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Intraoral -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-200 dark:border-gray-700">
      <h4 class="text-base font-semibold text-gray-800 dark:text-gray-100 mb-4">Intraoral Examination</h4>
      <div class="space-y-3 text-sm">
        <?php
          $intra = [
            'Lips' => 'intra_lips',
            'Buccal' => 'intra_buccal',
            'Alveolar' => 'intra_alveolar',
            'Floor' => 'intra_floor',
            'Tongue' => 'intra_tongue',
            'Saliva' => 'intra_saliva',
            'Pillars' => 'intra_pillars',
            'Tonsils' => 'intra_tonsils',
            'Uvula' => 'intra_uvula',
            'Oropharynx' => 'intra_oropharynx',
            'Other' => 'intra_other'
          ];
          foreach ($intra as $label => $field):
        ?>
          <div>
            <p class="text-gray-600 dark:text-gray-400 font-medium"><?= $label ?></p>
            <p class="mt-1 text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-700/60 rounded-lg p-3"><?= val($field, $healthData) ?: 'N/A' ?></p>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Periodontal -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-200 dark:border-gray-700">
      <h4 class="text-base font-semibold text-gray-800 dark:text-gray-100 mb-4">Periodontal Examination</h4>
      <div class="space-y-3 text-sm">
        <div>
          <p class="text-gray-600 dark:text-gray-400 font-medium">Gingiva Status</p>
          <p class="mt-1 text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-700/60 rounded-lg p-3"><?= val('perio_gingiva_status', $healthData) ?: 'Not assessed' ?></p>
        </div>
        <div>
          <p class="text-gray-600 dark:text-gray-400 font-medium">Degree of Inflammation</p>
          <p class="mt-1 text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-700/60 rounded-lg p-3"><?= val('perio_inflammation_degree', $healthData) ?: 'Not assessed' ?></p>
        </div>
        <div>
          <p class="text-gray-600 dark:text-gray-400 font-medium">Degree of Deposits</p>
          <p class="mt-1 text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-700/60 rounded-lg p-3"><?= val('perio_deposits_degree', $healthData) ?: 'Not assessed' ?></p>
        </div>
        <?php if (val('perio_other', $healthData)): ?>
          <div>
            <p class="text-gray-600 dark:text-gray-400 font-medium">Other Notes</p>
            <p class="mt-1 text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-700/60 rounded-lg p-3"><?= val('perio_other', $healthData) ?></p>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Occlusion -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-200 dark:border-gray-700">
      <h4 class="text-base font-semibold text-gray-800 dark:text-gray-100 mb-4">Occlusion</h4>
      <div class="space-y-3 text-sm">
        <?php
          $occ = [
            'Molar L' => 'occl_molar_l',
            'Molar R' => 'occl_molar_r',
            'Canine' => 'occl_canine',
            'Incisal' => 'occl_incisal',
            'Overbite' => 'occl_overbite',
            'Overjet' => 'occl_overjet',
            'Midline' => 'occl_midline',
            'Crossbite' => 'occl_crossbite',
            'Appliances' => 'occl_appliances'
          ];
          foreach ($occ as $label => $field):
        ?>
          <div>
            <p class="text-gray-600 dark:text-gray-400 font-medium"><?= $label ?></p>
            <p class="mt-1 text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-700/60 rounded-lg p-3"><?= val($field, $healthData) ?: 'N/A' ?></p>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Signature -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-200 dark:border-gray-700">
      <h4 class="text-base font-semibold text-gray-800 dark:text-gray-100 mb-4">Patient Signature</h4>
      <?php if (isset($consentData['data_privacy_signature_path']) && $consentData['data_privacy_signature_path']): ?>
        <img src="<?= htmlspecialchars($consentData['data_privacy_signature_path']) ?>" class="w-full max-h-32 object-contain rounded border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700">
      <?php else: ?>
        <p class="text-sm text-gray-500 dark:text-gray-400 italic">Signature not provided</p>
      <?php endif; ?>
      <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Printed Name:</p>
      <p class="text-sm font-semibold text-gray-900 dark:text-white"><?= trim($patient['first_name'] . ' ' . $patient['last_name']) ?: '—' ?></p>
    </div>

  </aside><!-- /right -->

</section>
</div>

        <!-- Tab 3: Dental Examination -->
        <div id="dental" class="tab-content">
            <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-6 print-section">
                <i class="ri-tooth-line mr-2"></i>Dental Examination
            </h3>
            
            <div class="space-y-6">
                <!-- Examination Information -->
                <div>
                    <h4 class="text-lg font-medium text-gray-800 dark:text-gray-200 mb-4">Examination Information</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Date Examined:</span>
                            <p class="text-sm text-gray-900 dark:text-white mt-1"><?php echo val('date_examined', $dentalExam) ?: 'Not examined'; ?></p>
                        </div>
                        <div>
                            <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Clinician:</span>
                            <p class="text-sm text-gray-900 dark:text-white mt-1"><?php echo val('clinician', $dentalExam) ?: 'N/A'; ?></p>
                        </div>
                        <div>
                            <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Checked By:</span>
                            <p class="text-sm text-gray-900 dark:text-white mt-1"><?php echo $assignedClinicalInstructor ?: (val('checked_by', $dentalExam) ?: 'N/A'); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Tooth Chart -->
                <div>
                    <h4 class="text-lg font-medium text-gray-800 dark:text-gray-200 mb-4">Tooth Chart</h4>
                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded">
                        <?php if (!empty($dentalExam['tooth_chart_photo']) && file_exists(__DIR__ . '/' . $dentalExam['tooth_chart_photo'])): ?>
                            <div class="mb-4">
                                <h5 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Uploaded Tooth Chart Photo:</h5>
                                <img src="<?php echo htmlspecialchars($dentalExam['tooth_chart_photo']); ?>?t=<?php echo time(); ?>" alt="Tooth Chart Photo" class="max-w-full h-auto rounded border">
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($dentalExam['tooth_chart_drawing_path']) && file_exists(__DIR__ . '/' . $dentalExam['tooth_chart_drawing_path'])): ?>
                            <div>
                                <h5 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Tooth Chart with Annotations:</h5>
                                <img src="<?php echo htmlspecialchars($dentalExam['tooth_chart_drawing_path']); ?>?t=<?php echo time(); ?>" alt="Tooth Chart Drawing" class="max-w-full h-auto rounded border">
                            </div>
                        <?php endif; ?>
                        
                        <?php if (empty($dentalExam['tooth_chart_photo']) && empty($dentalExam['tooth_chart_drawing_path'])): ?>
                            <div class="text-center py-8">
                                <i class="ri-image-line text-4xl text-gray-400 dark:text-gray-500 mb-4"></i>
                                <p class="text-sm text-gray-500 dark:text-gray-400">No tooth chart images available</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Diagnostic Tests -->
                <div>
                    <h4 class="text-lg font-medium text-gray-800 dark:text-gray-200 mb-4">Diagnostic Tests</h4>
                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded">
                        <p class="text-sm text-gray-900 dark:text-white whitespace-pre-wrap"><?php echo val('diagnostic_tests', $dentalExam) ?: 'No diagnostic tests performed'; ?></p>
                    </div>
                </div>

                <!-- Clinical Findings -->
                <div>
                    <h4 class="text-lg font-medium text-gray-800 dark:text-gray-200 mb-4">Clinical Findings & Notes</h4>
                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded">
                        <p class="text-sm text-gray-900 dark:text-white whitespace-pre-wrap"><?php echo val('other_notes', $dentalExam) ?: 'No additional findings'; ?></p>
                    </div>
                </div>

                <!-- Assessment and Plan -->
                <div>
                    <h4 class="text-lg font-medium text-gray-800 dark:text-gray-200 mb-4">Assessment & Treatment Plan</h4>
                    <div class="overflow-x-auto">
                        <table class="min-w-full border border-gray-200 dark:border-gray-700">
                            <thead class="bg-gray-100 dark:bg-gray-700">
                                <tr>
                                    <th class="border px-4 py-2 text-left text-sm font-medium text-gray-700 dark:text-gray-300">Sequence</th>
                                    <th class="border px-4 py-2 text-left text-sm font-medium text-gray-700 dark:text-gray-300">Tooth</th>
                                    <th class="border px-4 py-2 text-left text-sm font-medium text-gray-700 dark:text-gray-300">Problems/Diagnoses</th>
                                    <th class="border px-4 py-2 text-left text-sm font-medium text-gray-700 dark:text-gray-300">Treatment Plan</th>
                                    <th class="border px-4 py-2 text-left text-sm font-medium text-gray-700 dark:text-gray-300">Prognosis</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $assessmentPlan = json_decode($dentalExam['assessment_plan_json'] ?? '[]', true);
                                $sequences = ['MAIN CONCERN (PRIORITY)', 'I. SYSTEMIC PHASE', 'II. ACUTE PHASE', 'III. DISEASE CONTROL PHASE', 'IV. DEFINITIVE PHASE', 'V. MAINTENANCE PHASE'];
                                foreach ($sequences as $i => $seq):
                                    $row = $assessmentPlan[$i] ?? [];
                                ?>
                                <tr>
                                    <td class="border px-4 py-2 text-sm"><?php echo $seq; ?></td>
                                    <td class="border px-4 py-2 text-sm"><?php echo htmlspecialchars($row['tooth'] ?? ''); ?></td>
                                    <td class="border px-4 py-2 text-sm"><?php echo htmlspecialchars($row['diagnosis'] ?? ''); ?></td>
                                    <td class="border px-4 py-2 text-sm"><?php echo htmlspecialchars($row['plan'] ?? ''); ?></td>
                                    <td class="border px-4 py-2 text-sm"><?php echo htmlspecialchars($row['prognosis'] ?? ''); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Case History -->
                <div>
                    <h4 class="text-lg font-medium text-gray-800 dark:text-gray-200 mb-4">Case History</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h5 class="text-md font-medium text-gray-700 dark:text-gray-300 mb-3">Performed By</h5>
                            <div class="space-y-3">
                                <div>
                                    <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Clinician:</span>
                                    <p class="text-sm text-gray-900 dark:text-white mt-1"><?php echo val('history_performed_by', $dentalExam) ?: 'N/A'; ?></p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Date:</span>
                                    <p class="text-sm text-gray-900 dark:text-white mt-1"><?php echo val('history_performed_date', $dentalExam) ?: 'N/A'; ?></p>
                                </div>
                            </div>
                        </div>
                        <div>
                            <h5 class="text-md font-medium text-gray-700 dark:text-gray-300 mb-3">Checked By</h5>
                            <div class="space-y-3">
                                <div>
                                    <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Clinical Instructor:</span>
                                    <p class="text-sm text-gray-900 dark:text-white mt-1"><?php echo $assignedClinicalInstructor ?: (val('checked_by', $dentalExam) ?: 'N/A'); ?></p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Date:</span>
                                    <p class="text-sm text-gray-900 dark:text-white mt-1"><?php echo val('history_checked_date', $dentalExam) ?: 'N/A'; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Patient Signature -->
                <div>
                    <h4 class="text-lg font-medium text-gray-800 dark:text-gray-200 mb-4">Dental Examination Signature</h4>
                    <div class="space-y-4">
                        <?php if (isset($consentData['data_privacy_signature_path']) && $consentData['data_privacy_signature_path']): ?>
                        <div>
                            <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Patient's Signature:</span>
                            <div class="signature-display mt-2">
                                <img src="<?php echo htmlspecialchars($consentData['data_privacy_signature_path']); ?>" alt="Dental Examination Patient Signature">
                            </div>
                        </div>
                        <?php else: ?>
                        <div>
                            <span class="text-sm text-gray-600 dark:text-gray-400">Patient's signature not provided</span>
                        </div>
                        <?php endif; ?>
                        <div>
                            <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Printed Name:</span>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white"><?php echo trim($patient['first_name'] . ' ' . $patient['last_name']) ?: 'N/A'; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab 4: Informed Consent -->
        <div id="consent" class="tab-content">
            <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-6 print-section">
                <i class="ri-file-text-line mr-2"></i>Informed Consent
            </h3>
            
            <div class="space-y-6">
                <!-- Patient Information Header -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-200 dark:border-gray-700">
                    <h4 class="text-lg font-bold text-gray-800 dark:text-gray-100 mb-4">INFORMED CONSENT:</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4 text-sm">
                        <div>
                            <label class="font-medium text-gray-600 dark:text-gray-400">Patient's Name:</label>
                            <p class="text-gray-900 dark:text-white mt-1 pb-2 border-b border-gray-300 dark:border-gray-600"><?php echo htmlspecialchars(trim($patient['first_name'] . ' ' . $patient['last_name'])); ?></p>
                        </div>
                        <div>
                            <label class="font-medium text-gray-600 dark:text-gray-400">Age / Gender:</label>
                            <p class="text-gray-900 dark:text-white mt-1 pb-2 border-b border-gray-300 dark:border-gray-600"><?php echo htmlspecialchars($patient['age'] . ' / ' . ($patient['gender'] ?? $pirData['gender'] ?? 'Not specified')); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Detailed Consent Statements -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-200 dark:border-gray-700">
                    <h4 class="text-lg font-medium text-gray-800 dark:text-gray-200 mb-4">Consent Statements & Acknowledgments</h4>
                    <div class="space-y-4 text-sm text-gray-800 dark:text-gray-200">
                        
                        <div class="bg-gray-50 dark:bg-gray-700/60 p-4 rounded-lg">
                            <h5 class="font-semibold text-gray-900 dark:text-white mb-2">TREATMENT TO BE DONE</h5>
                            <p class="text-sm mb-3">I understand and consent to have any treatment done by the dentist after the procedure, the risk & benefits and cost have been fully explained. These treatments include, but are not limited to, oral surgery, cleaning, periodontal treatments, fillings, crowns, bridges, and prosthodontic, root canal treatments and orthodontic treatments, and all minor and major dental procedures.</p>
                            <div class="flex justify-between items-center">
                                <span class="font-medium">Patient Initial:</span>
                                <span class="bg-white dark:bg-gray-600 px-3 py-1 rounded border"><?php echo val('consent_treatment', $consentData) ?: 'Not initialed'; ?></span>
                            </div>
                        </div>

                        <div class="bg-gray-50 dark:bg-gray-700/60 p-4 rounded-lg">
                            <h5 class="font-semibold text-gray-900 dark:text-white mb-2">DRUGS & MEDICATIONS</h5>
                            <p class="text-sm mb-3">I understand that antibiotics, analgesics and other medications can cause allergic reactions (redness, swelling of tissues, pain, itching, vomiting, and/or anaphylactic shock).</p>
                            <div class="flex justify-between items-center">
                                <span class="font-medium">Patient Initial:</span>
                                <span class="bg-white dark:bg-gray-600 px-3 py-1 rounded border"><?php echo val('consent_drugs', $consentData) ?: 'Not initialed'; ?></span>
                            </div>
                        </div>

                        <div class="bg-gray-50 dark:bg-gray-700/60 p-4 rounded-lg">
                            <h5 class="font-semibold text-gray-900 dark:text-white mb-2">CHANGES IN TREATMENT PLAN</h5>
                            <p class="text-sm mb-3">I understand that during treatment, it may be necessary to change or add procedures because of conditions found while working on the teeth that were not discovered during examination. For example, root canal therapy may be necessary following routine restorative procedures. I give my permission to the dentist to make any/all changes and additions as necessary.</p>
                            <div class="flex justify-between items-center">
                                <span class="font-medium">Patient Initial:</span>
                                <span class="bg-white dark:bg-gray-600 px-3 py-1 rounded border"><?php echo val('consent_changes', $consentData) ?: 'Not initialed'; ?></span>
                            </div>
                        </div>

                        <div class="bg-gray-50 dark:bg-gray-700/60 p-4 rounded-lg">
                            <h5 class="font-semibold text-gray-900 dark:text-white mb-2">RADIOGRAPHS</h5>
                            <p class="text-sm mb-3">I understand that x-rays (radiographs) may be necessary to complete and/or diagnose the tentative diagnosis of my dental problem. I give my permission to have such radiographs taken.</p>
                            <div class="flex justify-between items-center">
                                <span class="font-medium">Patient Initial:</span>
                                <span class="bg-white dark:bg-gray-600 px-3 py-1 rounded border"><?php echo val('consent_radiographs', $consentData) ?: 'Not initialed'; ?></span>
                            </div>
                        </div>

                        <div class="bg-gray-50 dark:bg-gray-700/60 p-4 rounded-lg">
                            <h5 class="font-semibold text-gray-900 dark:text-white mb-2">REMOVAL OF TEETH</h5>
                            <p class="text-sm mb-3">I understand that alternatives to tooth removal (root canal therapy, crowns & periodontal surgery, etc.) and I agree to the removal of teeth if necessary. Removing teeth does not always remove all the infection, if present, and it may be necessary to have further treatment. I understand the risks involved in tooth removal, some of which are pain, swelling, spread of infection, dry socket, loss of feeling in my teeth, lips, tongue and surrounding tissue (paresthesia) that can last for an indefinite period of time or fractured jaw. I understand that I may need further treatment by a specialist if complications arise during or following treatment, the cost of which is my responsibility.</p>
                            <div class="flex justify-between items-center">
                                <span class="font-medium">Patient Initial:</span>
                                <span class="bg-white dark:bg-gray-600 px-3 py-1 rounded border"><?php echo val('consent_removal_teeth', $consentData) ?: 'Not initialed'; ?></span>
                            </div>
                        </div>

                        <div class="bg-gray-50 dark:bg-gray-700/60 p-4 rounded-lg">
                            <h5 class="font-semibold text-gray-900 dark:text-white mb-2">CROWNS, CAPS, BRIDGES</h5>
                            <p class="text-sm mb-3">I understand that sometimes it is not possible to match the color of natural teeth exactly with artificial teeth. I further understand that I may be wearing temporary crowns, which may come off easily and that I must be careful to ensure that they are kept on until the permanent crowns are delivered. I realize the final opportunity to make changes in my new crown, bridge, or cap (including shape, fit, size, and color) will be before cementation. It is also my responsibility to return for permanent cementation within 20 days from tooth preparation. Excessive delay may allow for decay, tooth movement, gum disease, or other problems. If I fail to return within this time, the dentist is not responsible for any problems resulting from my failure to return.</p>
                            <div class="flex justify-between items-center">
                                <span class="font-medium">Patient Initial:</span>
                                <span class="bg-white dark:bg-gray-600 px-3 py-1 rounded border"><?php echo val('consent_crowns', $consentData) ?: 'Not initialed'; ?></span>
                            </div>
                        </div>

                        <div class="bg-gray-50 dark:bg-gray-700/60 p-4 rounded-lg">
                            <h5 class="font-semibold text-gray-900 dark:text-white mb-2">ENDODONTICS (ROOT CANAL)</h5>
                            <p class="text-sm mb-3">I understand there is no guarantee that root canal treatment will save a tooth and that complications can occur from the treatment and that occasionally root canal filling materials may extend through that tooth which does not necessarily affect the success of the treatment. I understand that endodontic files and drills are very fine instruments and stresses vented in their manufacture & clarifications present in teeth can cause them to break during use. I understand that referral to the endodontist for additional treatments may be necessary following any root canal treatment and I agree that I am responsible for any additional cost for treatment performed by the endodontist. I understand that a tooth may require extraction in spite of all efforts to save it.</p>
                            <div class="flex justify-between items-center">
                                <span class="font-medium">Patient Initial:</span>
                                <span class="bg-white dark:bg-gray-600 px-3 py-1 rounded border"><?php echo val('consent_endodontics', $consentData) ?: 'Not initialed'; ?></span>
                            </div>
                        </div>

                        <div class="bg-gray-50 dark:bg-gray-700/60 p-4 rounded-lg">
                            <h5 class="font-semibold text-gray-900 dark:text-white mb-2">PERIODONTAL DISEASE</h5>
                            <p class="text-sm mb-3">I understand that periodontal disease is a serious condition causing gums & bone inflammation and/or loss and that can lead to the loss of my teeth. I understand that alternative treatment plans to correct periodontal disease, including gum surgery tooth extractions with or without replacement. I understand that undertaking these treatments does not guarantee the elimination of the disease.</p>
                            <div class="flex justify-between items-center">
                                <span class="font-medium">Patient Initial:</span>
                                <span class="bg-white dark:bg-gray-600 px-3 py-1 rounded border"><?php echo val('consent_periodontal', $consentData) ?: 'Not initialed'; ?></span>
                            </div>
                        </div>

                        <div class="bg-gray-50 dark:bg-gray-700/60 p-4 rounded-lg">
                            <h5 class="font-semibold text-gray-900 dark:text-white mb-2">DENTURES</h5>
                            <p class="text-sm mb-3">I understand the wearing of dentures is difficult. Sore spots, altered speech, and difficulty in eating are common problems. Immediate dentures (placement of dentures immediately after extractions) may be uncomfortable. I realize that dentures may require adjustments and relining (at additional cost) to fit properly.</p>
                            <div class="flex justify-between items-center">
                                <span class="font-medium">Patient Initial:</span>
                                <span class="bg-white dark:bg-gray-600 px-3 py-1 rounded border"><?php echo val('consent_dentures', $consentData) ?: 'Not initialed'; ?></span>
                            </div>
                        </div>

                        <div class="bg-gray-50 dark:bg-gray-700/60 p-4 rounded-lg">
                            <h5 class="font-semibold text-gray-900 dark:text-white mb-2">FILLINGS</h5>
                            <p class="text-sm mb-3">I understand that care must be exercised in chewing on fillings during the first 24 hours to avoid breakage. I understand that a more extensive filling than originally diagnosed may be required due to additional decay found during preparation. I understand that significant sensitivity is a common but usually temporary after effect of a newly placed filling.</p>
                            <div class="flex justify-between items-center">
                                <span class="font-medium">Patient Initial:</span>
                                <span class="bg-white dark:bg-gray-600 px-3 py-1 rounded border"><?php echo val('consent_fillings', $consentData) ?: 'Not initialed'; ?></span>
                            </div>
                        </div>

                        <div class="bg-yellow-50 dark:bg-yellow-900/20 p-4 rounded-lg border border-yellow-200 dark:border-yellow-700">
                            <h5 class="font-semibold text-gray-900 dark:text-white mb-2">GENERAL DISCLAIMER</h5>
                            <p class="text-sm mb-3">I understand that dentistry is not an exact science and that no dentist can properly guarantee results. I hereby authorize any of the dentists to proceed with and perform the dental treatments & treatments as explained to me. I understand that dentistry is subject to medication depending on my individual circumstances that may arise during the course of treatment. I further understand that regardless of any dental insurance coverage I may have, I am responsible for payment of dental fees. I agree to pay my estimated portion and any costs or fees not covered by my insurance company at the time services are rendered. I understand that any unpaid balance will accrue interest and that any unpaid account may be referred to a collection agency. I understand that any dental work that remains unpaid for more than 30 days after billing will be subject to additional charges. I understand that adjustments of any kind after this initial period will be my responsibility.</p>
                            <div class="flex justify-between items-center">
                                <span class="font-medium">Patient Initial:</span>
                                <span class="bg-white dark:bg-gray-600 px-3 py-1 rounded border"><?php echo val('consent_guarantee', $consentData) ?: 'Not initialed'; ?></span>
                            </div>
                        </div>

                        <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg border border-blue-200 dark:border-blue-700">
                            <p class="text-sm font-medium text-blue-900 dark:text-blue-200">It is my free will with full trust and confidence to undergo dental treatment under their care.</p>
                        </div>
                    </div>
                </div>

                <!-- Signatures Section -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-200 dark:border-gray-700">
                    <h4 class="text-lg font-medium text-gray-800 dark:text-gray-200 mb-4">Signatures & Authorization</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Patient/Parent/Guardian Signature:</span>
                            <?php if (isset($consentData['patient_signature']) && $consentData['patient_signature']): ?>
                                <div class="signature-display mt-2">
                                    <img src="<?php echo htmlspecialchars($consentData['patient_signature']); ?>" alt="Patient Signature">
                                </div>
                            <?php else: ?>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Not signed</p>
                            <?php endif; ?>
                        </div>
                        <div>
                            <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Witness:</span>
                            <p class="text-sm text-gray-900 dark:text-white mt-1 pb-2 border-b border-gray-300 dark:border-gray-600"><?php echo val('witness_signature', $consentData) ?: 'Not witnessed'; ?></p>
                        </div>
                        <div>
                            <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Consent Date:</span>
                            <p class="text-sm text-gray-900 dark:text-white mt-1 pb-2 border-b border-gray-300 dark:border-gray-600"><?php echo val('consent_date', $consentData) ? date('F d, Y', strtotime(val('consent_date', $consentData))) : 'Not dated'; ?></p>
                        </div>
                        <div>
                            <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Clinician:</span>
                            <p class="text-sm text-gray-900 dark:text-white mt-1 pb-2 border-b border-gray-300 dark:border-gray-600"><?php echo val('clinician_signature', $consentData) ?: 'Not signed'; ?></p>
                        </div>
                        <?php if (val('clinician_date', $consentData)): ?>
                        <div class="md:col-span-2">
                            <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Clinician Date:</span>
                            <p class="text-sm text-gray-900 dark:text-white mt-1"><?php echo date('F d, Y', strtotime(val('clinician_date', $consentData))); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Data Privacy Consent -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-200 dark:border-gray-700">
                    <h4 class="text-lg font-medium text-gray-800 dark:text-gray-200 mb-4">Data Privacy Consent</h4>
                    <div class="text-sm text-gray-800 dark:text-gray-200 mb-4">
                        <p class="mb-3">I hereby declare that by signing:</p>
                        <ol class="list-decimal list-inside space-y-2 ml-4 text-sm">
                            <li>I attest that the information I have written is true and correct to the best of my personal knowledge.</li>
                            <li>I signify my consent to the collection, use, recording, storing, organizing, consolidation, updating, processing access to transfer, disclosure and/or sharing of my personal and sensitive information by and among the staff of LPU-B including its medical staff, school/university, students, trainees, staff, administration, and/or consultants, as may be required for medical and/or legal and/or registration or for the purposes for which it was collected and such other lawful purposes I consent to.</li>
                            <li>I understand and agree that my personal data is subject to designated office and staff of the LPU-B. I will be provided with the reasonable access to my personal data provided to LPU-B to verify the accuracy and completeness of my information and request for its amendment if necessary.</li>
                            <li>I am aware that my consent for the collection and use of my data for LPU-B shall be effective immediately upon signing of this form and shall remain in effect unless I revoke the same in writing. Sixty working days upon receipt of the written revocation, LPU-B shall immediately cease from performing the acts mentioned under paragraph 2 herein concerning my personal and sensitive personal information.</li>
                        </ol>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Signature over printed name:</span>
                            <?php if (isset($consentData['data_privacy_signature_path']) && $consentData['data_privacy_signature_path']): ?>
                                <div class="signature-display mt-2">
                                    <img src="<?php echo htmlspecialchars($consentData['data_privacy_signature_path']); ?>" alt="Data Privacy Signature">
                                </div>
                                <p class="text-sm text-gray-900 dark:text-white mt-2 pb-2 border-b border-gray-300 dark:border-gray-600"><?php echo htmlspecialchars(trim($patient['first_name'] . ' ' . $patient['last_name'])); ?></p>
                            <?php else: ?>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Data privacy consent not signed</p>
                                <p class="text-sm text-gray-900 dark:text-white mt-2 pb-2 border-b border-gray-300 dark:border-gray-600"><?php echo htmlspecialchars(trim($patient['first_name'] . ' ' . $patient['last_name'])); ?></p>
                            <?php endif; ?>
                        </div>
                        <div>
                            <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Date:</span>
                            <p class="text-sm text-gray-900 dark:text-white mt-1 pb-2 border-b border-gray-300 dark:border-gray-600"><?php echo val('data_privacy_date', $consentData) ? date('F d, Y', strtotime(val('data_privacy_date', $consentData))) : 'Not dated'; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab 5: Progress Notes -->
        <div id="progress" class="tab-content">
            <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-6 print-section">
                <i class="ri-file-text-line mr-2"></i>Progress Notes
            </h3>
            
            <div class="space-y-6">
                <!-- Patient Information Header -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-200 dark:border-gray-700">
                    <div class="flex justify-between items-start mb-4">
                        <div class="flex flex-col gap-2 text-sm">
                            <div class="flex gap-4">
                                <div>
                                    <label class="font-medium text-gray-600 dark:text-gray-400">Patient's Name:</label>
                                    <p class="text-gray-900 dark:text-white mt-1 pb-2 border-b border-gray-300 dark:border-gray-600"><?php echo htmlspecialchars(trim($patient['first_name'] . ' ' . $patient['last_name'])); ?></p>
                                </div>
                                <div>
                                    <label class="font-medium text-gray-600 dark:text-gray-400">Age / Gender:</label>
                                    <p class="text-gray-900 dark:text-white mt-1 pb-2 border-b border-gray-300 dark:border-gray-600"><?php echo htmlspecialchars($patient['age'] . ' / ' . ($patient['gender'] ?? $pirData['gender'] ?? 'Not specified')); ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col items-end">
                            <div class="text-xs text-right text-gray-500 dark:text-gray-400 font-mono mb-2">FM-LPU-DENT-01/09<br>Page 5 of 5</div>
                            <div class="bg-yellow-200 dark:bg-yellow-900/50 border border-yellow-400 dark:border-yellow-600 px-3 py-1 rounded shadow text-xs font-bold text-yellow-900 dark:text-yellow-200">
                                <i class="ri-alert-line mr-1"></i>MEDICAL ALERT!
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Progress Notes Table -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-200 dark:border-gray-700">
                    <h4 class="text-lg font-medium text-gray-800 dark:text-gray-200 mb-4">
                        <i class="ri-calendar-check-line mr-2"></i>Progress Notes Records
                    </h4>
                    
                    <?php if ($progressNotes): ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full border border-gray-200 dark:border-gray-700 text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="border border-gray-200 dark:border-gray-600 px-3 py-2 text-left font-medium text-gray-700 dark:text-gray-300">Date</th>
                                        <th class="border border-gray-200 dark:border-gray-600 px-3 py-2 text-left font-medium text-gray-700 dark:text-gray-300">Tooth</th>
                                        <th class="border border-gray-200 dark:border-gray-600 px-3 py-2 text-left font-medium text-gray-700 dark:text-gray-300">Progress Notes</th>
                                        <th class="border border-gray-200 dark:border-gray-600 px-3 py-2 text-left font-medium text-gray-700 dark:text-gray-300">Clinician</th>
                                        <th class="border border-gray-200 dark:border-gray-600 px-3 py-2 text-left font-medium text-gray-700 dark:text-gray-300">CI</th>
                                        <th class="border border-gray-200 dark:border-gray-600 px-3 py-2 text-left font-medium text-gray-700 dark:text-gray-300">Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($progressNotes as $index => $note): ?>
                                    <tr class="<?php echo $index % 2 === 0 ? 'bg-white dark:bg-gray-800' : 'bg-gray-50 dark:bg-gray-750'; ?> hover:bg-blue-50 dark:hover:bg-gray-700 transition-colors duration-150">
                                        <td class="border border-gray-200 dark:border-gray-600 px-3 py-2 text-gray-900 dark:text-white">
                                            <?php echo $note['date'] ? date('M d, Y', strtotime($note['date'])) : '—'; ?>
                                        </td>
                                        <td class="border border-gray-200 dark:border-gray-600 px-3 py-2 text-gray-900 dark:text-white font-medium">
                                            <?php echo htmlspecialchars($note['tooth']) ?: '—'; ?>
                                        </td>
                                        <td class="border border-gray-200 dark:border-gray-600 px-3 py-2 text-gray-900 dark:text-white">
                                            <div class="max-w-md">
                                                <?php echo htmlspecialchars($note['progress']) ?: '—'; ?>
                                            </div>
                                        </td>
                                        <td class="border border-gray-200 dark:border-gray-600 px-3 py-2 text-gray-900 dark:text-white">
                                            <?php echo htmlspecialchars($note['clinician']) ?: '—'; ?>
                                        </td>
                                        <td class="border border-gray-200 dark:border-gray-600 px-3 py-2 text-gray-900 dark:text-white">
                                            <?php echo htmlspecialchars($note['ci']) ?: '—'; ?>
                                        </td>
                                        <td class="border border-gray-200 dark:border-gray-600 px-3 py-2 text-gray-900 dark:text-white">
                                            <div class="max-w-md">
                                                <?php echo htmlspecialchars($note['remarks']) ?: '—'; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Progress Notes Statistics -->
                        <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="bg-blue-50 dark:bg-blue-900/20 p-3 rounded-lg border border-blue-200 dark:border-blue-700">
                                <div class="flex items-center">
                                    <i class="ri-file-list-line text-blue-600 dark:text-blue-400 text-lg mr-2"></i>
                                    <div>
                                        <p class="text-xs text-blue-600 dark:text-blue-400 font-medium">Total Entries</p>
                                        <p class="text-lg font-bold text-blue-700 dark:text-blue-300"><?php echo count($progressNotes); ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-green-50 dark:bg-green-900/20 p-3 rounded-lg border border-green-200 dark:border-green-700">
                                <div class="flex items-center">
                                    <i class="ri-calendar-line text-green-600 dark:text-green-400 text-lg mr-2"></i>
                                    <div>
                                        <p class="text-xs text-green-600 dark:text-green-400 font-medium">Last Entry</p>
                                        <p class="text-sm font-medium text-green-700 dark:text-green-300">
                                            <?php echo $progressNotes[0]['date'] ? date('M d, Y', strtotime($progressNotes[0]['date'])) : 'No entries'; ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-purple-50 dark:bg-purple-900/20 p-3 rounded-lg border border-purple-200 dark:border-purple-700">
                                <div class="flex items-center">
                                    <i class="ri-user-line text-purple-600 dark:text-purple-400 text-lg mr-2"></i>
                                    <div>
                                        <p class="text-xs text-purple-600 dark:text-purple-400 font-medium">Last Clinician</p>
                                        <p class="text-sm font-medium text-purple-700 dark:text-purple-300">
                                            <?php echo htmlspecialchars($progressNotes[0]['clinician'] ?? 'None'); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-12">
                            <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 dark:bg-gray-700 rounded-full mb-4">
                                <i class="ri-file-list-3-line text-2xl text-gray-400 dark:text-gray-500"></i>
                            </div>
                            <h5 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No Progress Notes</h5>
                            <p class="text-gray-500 dark:text-gray-400 max-w-sm mx-auto">
                                No progress notes have been recorded for this patient yet. Progress notes will appear here once they are added.
                            </p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Patient Signature Section -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-200 dark:border-gray-700">
                    <h4 class="text-lg font-medium text-gray-800 dark:text-gray-200 mb-4">
                        <i class="ri-quill-pen-line mr-2"></i>Patient Acknowledgment & Signature
                    </h4>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Patient's Name and Signature:</span>
                            <?php if (isset($consentData['data_privacy_signature_path']) && $consentData['data_privacy_signature_path']): ?>
                                <div class="signature-display mt-2">
                                    <img src="<?php echo htmlspecialchars($consentData['data_privacy_signature_path']); ?>" alt="Patient Signature" class="max-w-full h-auto rounded border border-gray-200 dark:border-gray-600">
                                </div>
                            <?php else: ?>
                                <div class="mt-2 p-4 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg text-center">
                                    <i class="ri-quill-pen-line text-2xl text-gray-400 dark:text-gray-500 mb-2"></i>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">No signature available</p>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mt-3">
                                <label class="text-xs font-medium text-gray-600 dark:text-gray-400">Printed Name:</label>
                                <p class="text-sm text-gray-900 dark:text-white mt-1 pb-2 border-b border-gray-300 dark:border-gray-600">
                                    <?php echo htmlspecialchars(trim($patient['first_name'] . ' ' . $patient['last_name'])); ?>
                                </p>
                            </div>
                        </div>
                        
                        <div>
                            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                <h5 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Signature Details</h5>
                                <div class="space-y-2 text-xs text-gray-600 dark:text-gray-400">
                                    <div class="flex justify-between">
                                        <span>Status:</span>
                                        <span class="<?php echo (isset($consentData['data_privacy_signature_path']) && $consentData['data_privacy_signature_path']) ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'; ?>">
                                            <?php echo (isset($consentData['data_privacy_signature_path']) && $consentData['data_privacy_signature_path']) ? 'Signed' : 'Not signed'; ?>
                                        </span>
                                    </div>
                                    <?php if (val('data_privacy_date', $consentData)): ?>
                                    <div class="flex justify-between">
                                        <span>Date Signed:</span>
                                        <span class="text-gray-900 dark:text-white"><?php echo date('M d, Y', strtotime(val('data_privacy_date', $consentData))); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <div class="flex justify-between">
                                        <span>Progress Entries:</span>
                                        <span class="text-gray-900 dark:text-white"><?php echo count($progressNotes); ?> entries</span>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($progressNotes): ?>
                            <div class="mt-4">
                                <h6 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Treatment Summary</h6>
                                <div class="bg-blue-50 dark:bg-blue-900/20 p-3 rounded border border-blue-200 dark:border-blue-700">
                                    <p class="text-xs text-blue-800 dark:text-blue-200">
                                        This patient has <strong><?php echo count($progressNotes); ?> progress note entries</strong> 
                                        spanning from the first entry to the most recent treatment session.
                                    </p>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Dark mode toggle
const darkModeToggle = document.getElementById('darkModeToggle');
const html = document.documentElement;

// Check for saved dark mode preference
if (localStorage.getItem('darkMode') === 'true') {
    html.classList.add('dark');
}

darkModeToggle.addEventListener('click', () => {
    html.classList.toggle('dark');
    localStorage.setItem('darkMode', html.classList.contains('dark'));
});

// Tab navigation
const tabButtons = document.querySelectorAll('.tab-button');
const tabContents = document.querySelectorAll('.tab-content');

tabButtons.forEach(button => {
    button.addEventListener('click', () => {
        const targetTab = button.dataset.tab;
        
        // Update active button
        tabButtons.forEach(btn => {
            btn.classList.remove('active', 'bg-blue-600', 'text-white');
            btn.classList.add('text-gray-700', 'dark:text-gray-300', 'hover:bg-gray-100', 'dark:hover:bg-gray-700');
        });
        button.classList.add('active', 'bg-blue-600', 'text-white');
        button.classList.remove('text-gray-700', 'dark:text-gray-300', 'hover:bg-gray-100', 'dark:hover:bg-gray-700');
        
        // Show target content
        tabContents.forEach(content => {
            content.classList.remove('active');
        });
        document.getElementById(targetTab).classList.add('active');
    });
});

// Initialize first tab button styling
tabButtons[0].classList.add('bg-blue-600', 'text-white');
tabButtons[0].classList.remove('text-gray-700', 'dark:text-gray-300');

// Print Report Function
function openPrintReport() {
    const patientId = "<?php echo $patientId; ?>";
    window.open('print_selection.php?id=' + patientId, '_blank', 'width=1100,height=700');
}
</script>

</body>
</html>