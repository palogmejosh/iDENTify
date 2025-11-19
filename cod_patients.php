<?php
require_once 'config.php';
requireAuth();

$user = getCurrentUser();
$role = $user['role'] ?? '';

// Redirect if not COD
if ($role !== 'COD') {
    header('Location: dashboard.php');
    exit;
}

$message = '';

// Handle patient assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'assign') {
        $patientId = (int) $_POST['patient_id'];
        $clinicalInstructorId = (int) $_POST['clinical_instructor_id'];
        $notes = $_POST['notes'] ?? '';
        
        $result = assignPatientToClinicalInstructor($patientId, $user['id'], $clinicalInstructorId, $notes);
        
        if ($result) {
            header("Location: cod_patients.php?assigned=1");
            exit;
        } else {
            $message = 'Failed to assign patient. Please try again.';
        }
    } elseif ($_POST['action'] === 'auto_assign') {
        $patientId = (int) $_POST['patient_id'];
        $notes = $_POST['notes'] ?? '';
        $treatmentHint = $_POST['treatment_hint'] ?? '';
        
        $result = autoAssignPatientToBestClinicalInstructor($patientId, $user['id'], $notes, $treatmentHint);
        
        if ($result['success']) {
            header("Location: cod_patients.php?auto_assigned=1&assigned_to=" . urlencode($result['assigned_to']));
            exit;
        } else {
            $message = $result['message'] ?? 'Failed to automatically assign patient. Please try again.';
        }
    }
}

// Handle success messages
if (isset($_GET['assigned']) && $_GET['assigned'] == 1) {
    $message = 'Patient assigned to Clinical Instructor successfully!';
} elseif (isset($_GET['auto_assigned']) && $_GET['auto_assigned'] == 1) {
    $assignedTo = $_GET['assigned_to'] ?? 'Clinical Instructor';
    $message = "Patient automatically assigned to {$assignedTo} successfully!";
}

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? 'all';
$assignmentStatusFilter = $_GET['assignment_status'] ?? 'all';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Pagination parameters
$page = max(1, (int)($_GET['page'] ?? 1));
$itemsPerPage = 6;

// Get patients created by Clinicians for COD oversight
$codPatientsResult = getCODPatients($search, $statusFilter, $dateFrom, $dateTo, $assignmentStatusFilter, $page, $itemsPerPage);
$codPatients = $codPatientsResult['patients'];
$totalItems = $codPatientsResult['total'];
$totalPages = ceil($totalItems / $itemsPerPage);

// Get Clinical Instructors for assignment dropdown (with patient counts)
$clinicalInstructors = getAllClinicalInstructorsWithCounts();

// Get online Clinical Instructors for the online status table
$onlineClinicalInstructors = getOnlineClinicalInstructors();

$fullName = $user ? $user['full_name'] : 'Unknown User';
$profilePicture = $user['profile_picture'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>iDENTify - COD Patient Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.0.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="dark-mode-override.css">
    <link rel="stylesheet" href="styles.css">
    <script>
        tailwind.config = {
            darkMode: 'class',
        }
    </script>
    <style>
        .modal-fade {
            animation: fadeIn 0.3s ease-in-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .specialty-match {
            background: linear-gradient(135deg, #10b981, #16a085);
            color: white;
            animation: glow 2s ease-in-out infinite alternate;
        }
        @keyframes glow {
            from { box-shadow: 0 0 5px rgba(16, 185, 129, 0.5); }
            to { box-shadow: 0 0 15px rgba(16, 185, 129, 0.8); }
        }
        .treatment-hint-badge {
            transition: all 0.2s ease;
        }
        .treatment-hint-badge:hover {
            transform: scale(1.05);
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
        .header-profile-placeholder {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background-color: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #e5e7eb;
            color: #6b7280;
        }
        .dark .header-profile-placeholder {
            background-color: #374151;
            border-color: #4b5563;
            color: #9ca3af;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-violet-25 via-white to-purple-25 dark:bg-gradient-to-br dark:from-violet-950 dark:via-gray-900 dark:to-purple-950 transition-colors duration-200">

<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<!-- Main Content -->
<main class="ml-64 mt-16 p-6 min-h-screen bg-gradient-to-br from-violet-50 to-purple-50 dark:from-gray-900 dark:to-violet-900">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold bg-gradient-to-r from-violet-700 to-purple-700 bg-clip-text text-transparent dark:from-violet-300 dark:to-purple-300">Patient Assignment Management</h2>
                <div id="codHeaderInfo" class="flex items-center space-x-4">
                    <span class="text-sm text-gray-700 dark:text-gray-300">
                        Total Patients: <span class="total-records"><?php echo $totalItems; ?></span>
                    </span>
                    <span class="page-info text-sm text-gray-700 dark:text-gray-300" <?php echo ($totalPages <= 1) ? 'style="display:none;"' : ''; ?>>
                        (Page <?php echo $page; ?> of <?php echo $totalPages; ?>)
                    </span>
                </div>
            </div>

            <!-- Notification/Alert Box -->
            <?php if ($message): ?>
                <div id="alertBox" class="mb-6">
                    <div class="bg-gradient-to-r from-green-100 to-emerald-100 dark:from-green-900 dark:to-emerald-900 border border-green-400 dark:border-green-600 text-green-700 dark:text-green-200 px-4 py-3 rounded-lg shadow-lg relative">
                        <span id="alertMessage"><?php echo htmlspecialchars($message); ?></span>
                        <button onclick="hideAlert()" class="absolute top-0 bottom-0 right-0 px-4 py-3 text-green-600 hover:text-green-800 dark:text-green-300 dark:hover:text-green-100">
                            <i class="ri-close-line"></i>
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Enhanced Filter Section -->
            <div class="bg-gradient-to-r from-white to-violet-50 dark:from-gray-800 dark:to-violet-900 rounded-lg shadow-lg border border-violet-200 dark:border-violet-700 p-6 mb-6">
                <h3 class="text-lg font-semibold bg-gradient-to-r from-violet-700 to-purple-700 bg-clip-text text-transparent dark:from-violet-300 dark:to-purple-300 mb-4">Search & Filter Patients</h3>
                <form id="codFilterForm" method="GET" onsubmit="return updateCODFilters()" class="grid grid-cols-1 md:grid-cols-6 gap-4 items-end">
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-2">Patient Search</label>
                        <input type="text" name="search" placeholder="Search by name, email, phone..." 
                               class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-violet-900 dark:text-white" 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-2">Patient Status</label>
                        <select name="status" class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-violet-900 dark:text-white">
                            <option value="all" <?php echo ($statusFilter === 'all') ? 'selected' : ''; ?>>All Status</option>
                            <option value="Pending" <?php echo ($statusFilter === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="Approved" <?php echo ($statusFilter === 'Approved') ? 'selected' : ''; ?>>Approved</option>
                            <option value="Disapproved" <?php echo ($statusFilter === 'Disapproved') ? 'selected' : ''; ?>>Declined</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-2">Assignment Status</label>
                        <select name="assignment_status" class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-violet-900 dark:text-white">
                            <option value="all" <?php echo ($assignmentStatusFilter === 'all') ? 'selected' : ''; ?>>All Assignments</option>
                            <option value="unassigned" <?php echo ($assignmentStatusFilter === 'unassigned') ? 'selected' : ''; ?>>Unassigned</option>
                            <option value="accepted" <?php echo ($assignmentStatusFilter === 'accepted') ? 'selected' : ''; ?>>Assigned</option>
                            <option value="completed" <?php echo ($assignmentStatusFilter === 'completed') ? 'selected' : ''; ?>>Completed</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-2">Date From</label>
                        <input type="date" name="date_from" 
                               class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-violet-900 dark:text-white" 
                               value="<?php echo htmlspecialchars($dateFrom); ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-2">Date To</label>
                        <input type="date" name="date_to" 
                               class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-violet-900 dark:text-white" 
                               value="<?php echo htmlspecialchars($dateTo); ?>">
                    </div>
                    <div>
                        <button type="submit" class="w-full bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-700 hover:to-purple-700 text-white px-4 py-2 rounded-md transition-all duration-200 shadow-lg">
                            <i class="ri-search-line mr-2"></i>Search
                        </button>
                    </div>
                </form>
            </div>

            <!-- Online Clinical Instructors Section -->
            <div class="bg-gradient-to-br from-white to-violet-25 dark:from-gray-800 dark:to-violet-900 rounded-lg shadow-lg border border-violet-200 dark:border-violet-700 overflow-hidden mb-6">
                <div class="px-6 py-4 border-b border-violet-200 dark:border-violet-700 bg-gradient-to-r from-violet-50 to-purple-50 dark:from-violet-900 dark:to-purple-900">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-semibold bg-gradient-to-r from-violet-700 to-purple-700 bg-clip-text text-transparent dark:from-violet-300 dark:to-purple-300">Online Clinical Instructors</h3>
                        <div class="flex items-center space-x-4">
                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                Online: <span id="onlineCICount"><?php echo count($onlineClinicalInstructors); ?></span>
                            </span>
                            <button onclick="showAddCIModal()" class="bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white px-4 py-2 rounded-md transition-all duration-200 shadow-lg text-sm">
                                <i class="ri-user-add-line mr-1"></i>Add CI to Pool
                            </button>
                            <button id="refreshCIButton" onclick="refreshOnlineCIs()" class="bg-gradient-to-r from-blue-600 to-cyan-600 hover:from-blue-700 hover:to-cyan-700 text-white px-3 py-2 rounded-md transition-all duration-200 shadow-lg text-sm" title="Refresh Online CIs (Auto-refreshes when wrong values detected)">
                                <i class="ri-refresh-line"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table id="onlineCITable" class="min-w-full divide-y divide-violet-200 dark:divide-violet-700">
                        <thead class="bg-gradient-to-r from-violet-100 to-purple-100 dark:from-violet-800 dark:to-purple-800">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">CI Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">Procedure Details</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">Current Patients</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">Last Activity</th>
                            </tr>
                        </thead>
                        <tbody id="onlineCITableBody" class="bg-gradient-to-b from-white to-violet-25 dark:from-gray-800 dark:to-violet-900 divide-y divide-violet-200 dark:divide-violet-700">
                            <?php if (!empty($onlineClinicalInstructors)): ?>
                                <?php foreach ($onlineClinicalInstructors as $ci): ?>
                                    <tr class="hover:bg-gradient-to-r hover:from-violet-50 hover:to-purple-50 dark:hover:from-violet-900 dark:hover:to-purple-900">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                            <?php echo htmlspecialchars($ci['full_name']); ?>
                                            <div class="text-xs text-gray-600 dark:text-gray-400"><?php echo htmlspecialchars($ci['email']); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                <i class="ri-checkbox-blank-circle-fill mr-1 text-green-500"></i>Online
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                            <?php if (!empty($ci['specialty_hint'])): ?>
                                                <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                    <i class="ri-stethoscope-line mr-1"></i><?php echo htmlspecialchars($ci['specialty_hint']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-gray-500 dark:text-gray-400">General Practice</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full <?php echo $ci['current_patient_count'] > 5 ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : ($ci['current_patient_count'] > 2 ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'); ?>">
                                                <i class="ri-user-line mr-1"></i><?php echo $ci['current_patient_count']; ?> patients
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                            <?php 
                                            if ($ci['last_activity']) {
                                                $lastActivity = new DateTime($ci['last_activity']);
                                                $now = new DateTime();
                                                $diff = $now->diff($lastActivity);
                                                if ($diff->i < 1) {
                                                    echo 'Just now';
                                                } elseif ($diff->i < 60) {
                                                    echo $diff->i . ' min ago';
                                                } else {
                                                    echo $lastActivity->format('M d, H:i');
                                                }
                                            } else {
                                                echo 'Recently active';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                        <div class="flex flex-col items-center py-4">
                                            <i class="ri-user-unfollow-line text-4xl text-gray-400 mb-2"></i>
                                            <span class="text-lg font-medium">No Clinical Instructors Online</span>
                                            <span class="text-sm">Add CIs to the pool or wait for them to come online</span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Patients Table -->
            <div class="bg-gradient-to-br from-white to-violet-25 dark:from-gray-800 dark:to-violet-900 rounded-lg shadow-lg border border-violet-200 dark:border-violet-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-violet-200 dark:border-violet-700 bg-gradient-to-r from-violet-50 to-purple-50 dark:from-violet-900 dark:to-purple-900">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-semibold bg-gradient-to-r from-violet-700 to-purple-700 bg-clip-text text-transparent dark:from-violet-300 dark:to-purple-300">Patient Assignment Records</h3>
                        <div class="flex items-center text-xs text-gray-700 dark:text-gray-300">
                            <i class="ri-information-line mr-1"></i>
                            <span>Patient details view restricted for COD role</span>
                        </div>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table id="codPatientsTable" class="min-w-full divide-y divide-violet-200 dark:divide-violet-700">
                        <thead class="bg-gradient-to-r from-violet-100 to-purple-100 dark:from-violet-800 dark:to-purple-800">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">Patient Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">Treatment Needed</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">Created By</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">Date Created</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">Patient Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">Assignment Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">Assigned Instructor</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-gradient-to-b from-white to-violet-25 dark:from-gray-800 dark:to-violet-900 divide-y divide-violet-200 dark:divide-violet-700">
                            <?php if (!empty($codPatients)): ?>
                                <?php foreach ($codPatients as $patient): ?>
                                    <tr class="hover:bg-gradient-to-r hover:from-violet-50 hover:to-purple-50 dark:hover:from-violet-900 dark:hover:to-purple-900">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                            <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                                            <div class="text-xs text-gray-600 dark:text-gray-400"><?php echo htmlspecialchars($patient['email']); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                            <?php if (!empty($patient['treatment_hint'])): ?>
                                                <span class="treatment-hint-badge inline-flex items-center px-3 py-1 text-xs font-semibold rounded-full bg-gradient-to-r from-purple-100 to-purple-200 text-purple-800 dark:from-purple-900 dark:to-purple-800 dark:text-purple-200 border border-purple-300 dark:border-purple-600" title="Treatment: <?php echo htmlspecialchars($patient['treatment_hint']); ?>">
                                                    <i class="ri-heart-pulse-line mr-1"></i>
                                                    <?php echo htmlspecialchars($patient['treatment_hint']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400">
                                                    <i class="ri-question-mark mr-1"></i>
                                                    Not specified
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                            <?php echo htmlspecialchars($patient['created_by_clinician'] ?? 'N/A'); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                            <?php echo date('M d, Y', strtotime($patient['created_at'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                                <?php 
                                                echo $patient['patient_status'] === 'Approved' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 
                                                     ($patient['patient_status'] === 'Pending' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : 
                                                      'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'); 
                                                ?>">
                                                <?php echo htmlspecialchars($patient['patient_status'] === 'Disapproved' ? 'Declined' : $patient['patient_status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                                <?php 
                                                $assignmentStatus = $patient['assignment_status'] ?? 'unassigned';
                                                echo $assignmentStatus === 'completed' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 
                                                     ($assignmentStatus === 'accepted' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : 
                                                      ($assignmentStatus === 'pending' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' :
                                                       'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200')); 
                                                ?>">
                                                <?php echo htmlspecialchars(ucfirst($assignmentStatus)); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                            <?php echo htmlspecialchars($patient['assigned_clinical_instructor'] ?? 'Auto-assigning...'); ?>
                                            <?php if (!empty($patient['assigned_at'])): ?>
                                                <div class="text-xs text-gray-600 dark:text-gray-400">
                                                    <i class="ri-time-line mr-1"></i><?php echo date('M d, Y H:i', strtotime($patient['assigned_at'])); ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($patient['assignment_notes']) && strpos($patient['assignment_notes'], 'Auto-assigned') !== false): ?>
                                                <div class="text-xs text-blue-600 dark:text-blue-400">
                                                    <i class="ri-magic-line mr-1"></i>Auto-assigned
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <?php 
                                            $assignmentStatus = $patient['assignment_status'] ?? 'unassigned';
                                            if ($assignmentStatus === 'unassigned' || empty($patient['assigned_clinical_instructor'])): 
                                            ?>
                                                <button onclick="openAssignModal(<?php echo htmlspecialchars(json_encode($patient)); ?>)" 
                                                        class="inline-flex items-center px-3 py-1.5 bg-gradient-to-r from-blue-600 to-cyan-600 hover:from-blue-700 hover:to-cyan-700 text-white text-xs font-medium rounded-md transition-all duration-200 shadow-md hover:shadow-lg" 
                                                        title="Manually assign this patient to a Clinical Instructor">
                                                    <i class="ri-user-add-line mr-1"></i>Assign
                                                </button>
                                            <?php elseif ($assignmentStatus === 'pending' || $assignmentStatus === 'accepted'): ?>
                                                <button onclick="openAssignModal(<?php echo htmlspecialchars(json_encode($patient)); ?>)" 
                                                        class="inline-flex items-center px-3 py-1.5 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white text-xs font-medium rounded-md transition-all duration-200 shadow-md hover:shadow-lg" 
                                                        title="Reassign this patient to a different Clinical Instructor">
                                                    <i class="ri-refresh-line mr-1"></i>Reassign
                                                </button>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-3 py-1.5 bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-400 text-xs font-medium rounded-md">
                                                    <i class="ri-check-line mr-1"></i>Completed
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                        No patients found matching your criteria.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination Controls -->
                <div id="codPatientsPagination">
                <?php if ($totalPages > 1): ?>
                <div class="px-6 py-4 border-t border-violet-200 dark:border-violet-700 bg-gradient-to-r from-violet-25 to-purple-25 dark:from-violet-900 dark:to-purple-900">
                    <div class="flex flex-col sm:flex-row justify-between items-center space-y-3 sm:space-y-0">
                        <!-- Results Info -->
                        <div class="text-sm text-gray-700 dark:text-gray-300">
                            Showing <?php echo (($page - 1) * $itemsPerPage + 1); ?> to <?php echo min($page * $itemsPerPage, $totalItems); ?> of <?php echo $totalItems; ?> results
                        </div>
                        
                        <!-- Pagination Buttons -->
                        <div class="flex items-center space-x-2">
                            <?php if ($page > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                                   class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300">
                                    <i class="ri-arrow-left-line mr-1"></i>Previous
                                </a>
                            <?php else: ?>
                                <span class="px-3 py-2 text-sm bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md text-gray-400 dark:text-gray-600 cursor-not-allowed">
                                    <i class="ri-arrow-left-line mr-1"></i>Previous
                                </span>
                            <?php endif; ?>
                            
                            <!-- Page Numbers -->
                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            
                            if ($startPage > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" 
                                   class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300">1</a>
                                <?php if ($startPage > 2): ?>
                                    <span class="px-2 text-gray-500 dark:text-gray-400">...</span>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <?php if ($i == $page): ?>
                                    <span class="px-3 py-2 text-sm bg-blue-600 text-white border border-blue-600 rounded-md"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                       class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($endPage < $totalPages): ?>
                                <?php if ($endPage < $totalPages - 1): ?>
                                    <span class="px-2 text-gray-500 dark:text-gray-400">...</span>
                                <?php endif; ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $totalPages])); ?>" 
                                   class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300"><?php echo $totalPages; ?></a>
                            <?php endif; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                                   class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300">
                                    Next<i class="ri-arrow-right-line ml-1"></i>
                                </a>
                            <?php else: ?>
                                <span class="px-3 py-2 text-sm bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md text-gray-400 dark:text-gray-600 cursor-not-allowed">
                                    Next<i class="ri-arrow-right-line ml-1"></i>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                </div>
            </div>
        </main>

    <!-- Assign Patient Modal -->
    <div id="assignModal" class="fixed inset-0 bg-black bg-opacity-50 hidden justify-center items-center px-4 z-50">
        <div class="bg-gradient-to-br from-white to-violet-50 dark:from-gray-800 dark:to-violet-900 p-5 rounded-lg shadow-xl border border-violet-200 dark:border-violet-700 w-full max-w-md mx-auto modal-fade max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold bg-gradient-to-r from-violet-700 to-purple-700 bg-clip-text text-transparent dark:from-violet-300 dark:to-purple-300">Assign Patient to Clinical Instructor</h3>
                <button onclick="closeAssignModal()" class="text-gray-400 hover:text-gray-600 dark:text-gray-400 dark:hover:text-gray-300">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>
            <form id="assignForm" method="POST">
                <input type="hidden" name="action" value="assign">
                <input type="hidden" id="assignPatientId" name="patient_id">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-2">Patient Information</label>
                        <div id="patientInfo" class="p-3 bg-gradient-to-r from-violet-50 to-purple-50 dark:from-violet-900 dark:to-purple-900 rounded-md text-sm border border-violet-200 dark:border-violet-700">
                            <!-- Populated by JS -->
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-2">
                            Clinical Instructor <span class="text-red-500">*</span>
                        </label>
                        <select name="clinical_instructor_id" id="clinicalInstructorSelect" required
                                class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md
                                       focus:outline-none focus:ring-2 focus:ring-violet-500
                                       dark:bg-violet-900 dark:text-white text-sm">
                            <option value="">?? Select Clinical Instructor (procedure details, online status & patient count shown)</option>
                            <?php foreach ($clinicalInstructors as $instructor): ?>
                                <option value="<?php echo $instructor['id']; ?>" 
                                        data-specialty="<?php echo htmlspecialchars($instructor['specialty_hint'] ?? ''); ?>"
                                        data-email="<?php echo htmlspecialchars($instructor['email'] ?? ''); ?>"
                                        data-patient-count="<?php echo $instructor['current_patient_count']; ?>"
                                        data-online-status="<?php echo $instructor['online_status']; ?>"
                                        class="<?php echo $instructor['online_status'] === 'online' ? 'bg-green-50 dark:bg-green-900' : ''; ?>">
                                    <?php echo htmlspecialchars($instructor['full_name']); ?>
                                    <?php echo $instructor['online_status'] === 'online' ? '??' : '??'; ?>
                                    (<?php echo $instructor['current_patient_count']; ?> patients)
                                    <?php if (!empty($instructor['specialty_hint'])): ?>
                                        ?? <?php echo htmlspecialchars($instructor['specialty_hint']); ?>
                                    <?php else: ?>
                                        - General Practice
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="instructorSpecialtyInfo" class="mt-2 p-3 bg-gradient-to-r from-violet-50 to-purple-50 dark:from-violet-900 dark:to-purple-900 border border-violet-200 dark:border-violet-800 rounded-md text-xs text-gray-900 dark:text-white hidden">
                            <div class="flex items-center mb-1">
                                <i class="ri-user-star-line mr-2 text-sm"></i>
                                <span class="font-semibold">Instructor Procedure Details</span>
                            </div>
                            <div class="ml-6">
                                <span id="selectedSpecialty"></span>
                            </div>
                            <div class="mt-2 ml-6 text-gray-700 dark:text-gray-300">
                                <span id="selectedEmail"></span>
                            </div>
                        </div>
                        <div id="specialtyMatchAlert" class="mt-2 p-3 rounded-md hidden">
                            <div class="flex items-center">
                                <i class="ri-check-double-line mr-2 text-sm"></i>
                                <span class="font-semibold">Perfect Match!</span>
                            </div>
                            <div class="mt-1 text-xs">
                                This instructor's procedure details match the patient's treatment needs.
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-2">
                            Assignment Notes
                        </label>
                        <textarea name="notes" rows="3" 
                                  class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md
                                         focus:outline-none focus:ring-2 focus:ring-violet-500
                                         dark:bg-violet-900 dark:text-white text-sm"
                                  placeholder="Optional notes for the Clinical Instructor..."></textarea>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-2 mt-6">
                    <button type="button" onclick="closeAssignModal()"
                            class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300
                                   hover:text-gray-800 dark:hover:text-gray-100 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-800">
                        Cancel
                    </button>
                    <button type="submit"
                            class="bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-700 hover:to-purple-700 text-white px-4 py-2 rounded-md text-sm shadow-lg transition-all duration-200">
                        Assign Patient
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add CI to Pool Modal -->
    <div id="addCIModal" class="fixed inset-0 bg-black bg-opacity-50 hidden justify-center items-center px-4 z-50">
        <div class="bg-gradient-to-br from-white to-violet-50 dark:from-gray-800 dark:to-violet-900 p-5 rounded-lg shadow-xl border border-violet-200 dark:border-violet-700 w-full max-w-md mx-auto modal-fade max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold bg-gradient-to-r from-violet-700 to-purple-700 bg-clip-text text-transparent dark:from-violet-300 dark:to-purple-300">Add CI to Assignment Pool</h3>
                <button onclick="closeAddCIModal()" class="text-gray-400 hover:text-gray-600 dark:text-gray-400 dark:hover:text-gray-300">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>
            
            <form id="addCIForm" onsubmit="return submitAddCIForm(event)">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-2">
                            Select Clinical Instructor <span class="text-red-500">*</span>
                        </label>
                        <select name="ci_id" id="addCISelect" required
                                class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md
                                       focus:outline-none focus:ring-2 focus:ring-violet-500
                                       dark:bg-violet-900 dark:text-white text-sm">
                            <option value="">Select a Clinical Instructor...</option>
                            <?php foreach ($clinicalInstructors as $instructor): ?>
                                <option value="<?php echo $instructor['id']; ?>" 
                                        data-online-status="<?php echo $instructor['online_status']; ?>"
                                        data-patient-count="<?php echo $instructor['current_patient_count']; ?>"
                                        class="<?php echo $instructor['online_status'] === 'offline' ? 'text-gray-500' : ''; ?>">
                                    <?php echo htmlspecialchars($instructor['full_name']); ?>
                                    <?php echo $instructor['online_status'] === 'online' ? '??' : '??'; ?>
                                    (<?php echo $instructor['current_patient_count']; ?> patients)
                                    <?php if (!empty($instructor['specialty_hint'])): ?>
                                        - <?php echo htmlspecialchars($instructor['specialty_hint']); ?>
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-gray-600 dark:text-gray-400 mt-2">
                            ?? Online CIs will be immediately available for assignments<br>
                            ?? Offline CIs will be added when they come online
                        </p>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-2 mt-6">
                    <button type="button" onclick="closeAddCIModal()"
                            class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300
                                   hover:text-gray-800 dark:hover:text-gray-100 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-800">
                        Cancel
                    </button>
                    <button type="submit"
                            class="bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white px-4 py-2 rounded-md text-sm shadow-lg transition-all duration-200">
                        <i class="ri-user-add-line mr-1"></i>Add to Pool
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Dark mode toggle script -->
    <script>
        (() => {
            const html = document.documentElement;
            const btn = document.getElementById('darkModeToggle');
            const key = 'darkMode';
            const isDark = localStorage.getItem(key) === 'true';

            if (isDark) html.classList.add('dark');

            btn.addEventListener('click', () => {
                html.classList.toggle('dark');
                localStorage.setItem(key, html.classList.contains('dark'));
            });
        })();
    </script>

    <script>
        // Alert Box Auto-hide
        function hideAlert() {
            document.getElementById('alertBox')?.remove();
        }
        setTimeout(hideAlert, 4000);

        // Modal Functions
        function openAssignModal(patient) {
            currentPatientData = patient; // Store patient data for matching
            document.getElementById('assignPatientId').value = patient.id;
            document.getElementById('patientInfo').innerHTML = `
                <div><strong>Name:</strong> ${escapeHtml(patient.first_name + ' ' + patient.last_name)}</div>
                <div><strong>Email:</strong> ${escapeHtml(patient.email)}</div>
                <div><strong>?? Procedure Details:</strong> ${
                    patient.treatment_hint ? 
                    `<span class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded-full bg-gradient-to-r from-purple-100 to-purple-200 text-purple-800 dark:from-purple-800 dark:to-purple-700 dark:text-purple-100 border border-purple-300 dark:border-purple-600"><i class="ri-heart-pulse-line mr-1"></i>${escapeHtml(patient.treatment_hint)}</span>` : 
                    '<span class="inline-flex items-center px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400"><i class="ri-question-mark mr-1"></i>Not specified</span>'
                }</div>
                <div><strong>Created by:</strong> ${escapeHtml(patient.created_by_clinician || 'Unknown')}</div>
                <div><strong>Status:</strong> ${escapeHtml(patient.patient_status)}</div>
            `;
            
            // Pre-select current instructor if reassigning
            if (patient.clinical_instructor_id) {
                document.getElementById('clinicalInstructorSelect').value = patient.clinical_instructor_id;
            }
            
            document.getElementById('assignModal').style.display = 'flex';
        }

        function openReassignModal(patient) {
            openAssignModal(patient);
        }

        function closeAssignModal() {
            document.getElementById('assignModal').style.display = 'none';
            document.getElementById('assignForm').reset();
        }

        function escapeHtml(unsafe) {
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
        
        // Global variable to store current patient data
        let currentPatientData = null;
        
        // Show Clinical Instructor procedure details when selected
        document.getElementById('clinicalInstructorSelect').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const specialty = selectedOption.getAttribute('data-specialty');
            const email = selectedOption.getAttribute('data-email');
            const specialtyInfo = document.getElementById('instructorSpecialtyInfo');
            const specialtySpan = document.getElementById('selectedSpecialty');
            const emailSpan = document.getElementById('selectedEmail');
            const matchAlert = document.getElementById('specialtyMatchAlert');
            
            if (selectedOption.value) {
                const displaySpecialty = specialty && specialty.trim() !== '' ? specialty : 'General Practice';
                specialtySpan.textContent = displaySpecialty;
                emailSpan.textContent = email || 'Email not available';
                specialtyInfo.classList.remove('hidden');
                
                // Check for specialty match with patient's procedure details
                if (currentPatientData && currentPatientData.treatment_hint && specialty) {
                    const isMatch = checkSpecialtyMatch(currentPatientData.treatment_hint, specialty);
                    if (isMatch) {
                        matchAlert.classList.remove('hidden');
                        matchAlert.className = 'mt-2 p-3 rounded-md specialty-match';
                        // Highlight the selected option
                        selectedOption.style.background = 'linear-gradient(135deg, #10b981, #16a085)';
                        selectedOption.style.color = 'white';
                    } else {
                        matchAlert.classList.add('hidden');
                    }
                } else {
                    matchAlert.classList.add('hidden');
                }
            } else {
                specialtyInfo.classList.add('hidden');
                matchAlert.classList.add('hidden');
            }
        });
        
        // Function to check if specialty matches procedure details
        function checkSpecialtyMatch(treatmentHint, specialty) {
            if (!treatmentHint || !specialty) return false;
            
            const treatment = treatmentHint.toLowerCase();
            const spec = specialty.toLowerCase();
            
            // Define keyword matches
            const matches = {
                'orthodontics': ['braces', 'alignment', 'bite', 'straightening', 'orthodontic'],
                'endodontics': ['root canal', 'endodontic', 'pulp', 'nerve'],
                'periodontics': ['gum', 'periodontal', 'gingivitis', 'periodontitis'],
                'oral surgery': ['extraction', 'surgery', 'surgical', 'wisdom tooth', 'implant'],
                'prosthodontics': ['crown', 'bridge', 'denture', 'restoration', 'prosthetic'],
                'pediatric': ['child', 'pediatric', 'kids', 'children'],
                'cosmetic': ['whitening', 'veneers', 'cosmetic', 'aesthetic', 'smile'],
                'general': ['cleaning', 'filling', 'checkup', 'routine', 'maintenance']
            };
            
            // Check if specialty keywords match treatment
            for (const [specialtyKey, keywords] of Object.entries(matches)) {
                if (spec.includes(specialtyKey)) {
                    return keywords.some(keyword => treatment.includes(keyword));
                }
            }
            
            // Fuzzy matching for similar words
            return treatment.includes(spec) || spec.includes(treatment);
        }
        
        // AJAX Pagination Functions
        let currentCODFilters = {
            search: '<?php echo addslashes($search); ?>',
            status: '<?php echo addslashes($statusFilter); ?>',
            assignment_status: '<?php echo addslashes($assignmentStatusFilter); ?>',
            date_from: '<?php echo addslashes($dateFrom); ?>',
            date_to: '<?php echo addslashes($dateTo); ?>'
        };

        function loadCODPage(page) {
            // Show loading state
            const tableBody = document.querySelector('#codPatientsTable tbody');
            const paginationContainer = document.querySelector('#codPatientsPagination');
            
            if (tableBody) {
                tableBody.innerHTML = '<tr><td colspan="8" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400"><i class="ri-loader-2-line animate-spin mr-2"></i>Loading...</td></tr>';
            }
            
            // Build query parameters
            const params = new URLSearchParams({
                page: page,
                ...currentCODFilters
            });
            
            // Make AJAX request
            fetch(`ajax_cod_patients.php?${params.toString()}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update table content
                        if (tableBody) {
                            tableBody.innerHTML = data.tableHTML;
                        }
                        
                        // Update pagination
                        if (paginationContainer) {
                            paginationContainer.innerHTML = data.paginationHTML;
                        }
                        
                        // Update header info
                        const headerInfo = document.querySelector('#codHeaderInfo');
                        if (headerInfo && data.totalRecords) {
                            const totalSpan = headerInfo.querySelector('.total-records');
                            const pageSpan = headerInfo.querySelector('.page-info');
                            
                            if (totalSpan) {
                                totalSpan.textContent = data.totalRecords;
                            }
                            
                            if (pageSpan) {
                                if (data.totalPages > 1) {
                                    pageSpan.textContent = `(Page ${data.currentPage} of ${data.totalPages})`;
                                    pageSpan.style.display = 'inline';
                                } else {
                                    pageSpan.style.display = 'none';
                                }
                            }
                        }
                        
                        // Smooth scroll to table
                        document.querySelector('#codPatientsTable').scrollIntoView({
                            behavior: 'smooth',
                            block: 'nearest'
                        });
                        
                    } else {
                        console.error('Error loading COD page:', data.error);
                        showAlert('Error loading page: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Network error:', error);
                    showAlert('Network error occurred. Please try again.');
                    
                    // Restore table content on error
                    if (tableBody) {
                        tableBody.innerHTML = '<tr><td colspan="8" class="px-6 py-4 text-center text-red-500 dark:text-red-400">Error loading data. Please refresh the page.</td></tr>';
                    }
                });
        }

        // Update filters when search form is submitted
        function updateCODFilters() {
            const form = document.querySelector('#codFilterForm');
            if (form) {
                const formData = new FormData(form);
                currentCODFilters = {
                    search: formData.get('search') || '',
                    status: formData.get('status') || 'all',
                    assignment_status: formData.get('assignment_status') || 'all',
                    date_from: formData.get('date_from') || '',
                    date_to: formData.get('date_to') || ''
                };
                
                // Load first page with new filters
                loadCODPage(1);
                return false; // Prevent form submission
            }
        }
        
        function showAlert(message) {
            const alertBox = document.getElementById('alertBox');
            const alertMessage = document.getElementById('alertMessage');
            
            if (alertBox && alertMessage) {
                alertMessage.textContent = message;
                alertBox.style.display = 'block';
                alertBox.classList.remove('hidden');
            }
        }
        
        // ========================================================
        // Online CI Management Functions
        // ========================================================
        
        // Show Add CI Modal
        function showAddCIModal() {
            document.getElementById('addCIModal').style.display = 'flex';
        }
        
        // Close Add CI Modal
        function closeAddCIModal() {
            document.getElementById('addCIModal').style.display = 'none';
            document.getElementById('addCIForm').reset();
        }
        
        // Submit Add CI Form
        function submitAddCIForm(event) {
            event.preventDefault();
            
            const form = document.getElementById('addCIForm');
            const formData = new FormData(form);
            formData.append('action', 'add_ci_to_pool');
            
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="ri-loader-2-line animate-spin mr-1"></i>Adding...';
            submitBtn.disabled = true;
            
            fetch('api_online_ci.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message);
                    closeAddCIModal();
                    refreshOnlineCIs();
                } else {
                    showAlert('Error: ' + (data.error || data.message || 'Failed to add CI to pool'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Network error occurred. Please try again.');
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
            
            return false;
        }
        
        // Refresh Online CIs with improved error handling and state management
        function refreshOnlineCIs() {
            // Prevent multiple simultaneous refresh calls
            if (window.isRefreshing) {
                return Promise.resolve();
            }
            
            window.isRefreshing = true;
            
            return fetch('api_online_ci.php?action=get_online_ci')
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        updateOnlineCITable(data.data);
                        const countElement = document.getElementById('onlineCICount');
                        if (countElement) {
                            countElement.textContent = data.count || 0;
                        }
                        
                        // Clear any previous error states
                        clearErrorState();
                        return data;
                    } else {
                        console.error('Error fetching online CIs:', data.error);
                        showErrorState('Error fetching online Clinical Instructors: ' + (data.error || 'Unknown error'));
                        throw new Error(data.error || 'Unknown error');
                    }
                })
                .catch(error => {
                    console.error('Network error fetching online CIs:', error);
                    showErrorState('Network error: Could not fetch online Clinical Instructors. Please check your connection.');
                    throw error;
                })
                .finally(() => {
                    window.isRefreshing = false;
                });
        }
        
        // Show error state in the CI table
        function showErrorState(message) {
            const tbody = document.getElementById('onlineCITableBody');
            if (tbody) {
                const errorRow = `
                    <tr id="error-row">
                        <td colspan="5" class="px-6 py-4 text-center text-red-500 dark:text-red-400">
                            <div class="flex flex-col items-center py-4">
                                <i class="ri-error-warning-line text-4xl text-red-400 mb-2"></i>
                                <span class="text-lg font-medium">Connection Issue</span>
                                <span class="text-sm">${escapeHtml(message)}</span>
                                <button onclick="refreshOnlineCIs()" class="mt-2 px-3 py-1 text-sm bg-red-100 hover:bg-red-200 text-red-800 rounded-md transition-colors">
                                    <i class="ri-refresh-line mr-1"></i>Retry
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
                tbody.innerHTML = errorRow;
            }
        }
        
        // Clear error state
        function clearErrorState() {
            const errorRow = document.getElementById('error-row');
            if (errorRow) {
                errorRow.remove();
            }
        }
        
        // Enhanced refresh button with automatic click functionality
        function performAutoRefresh(reason = 'automatic check') {
            const refreshButton = document.getElementById('refreshCIButton');
            if (!refreshButton || window.isRefreshing) {
                return false;
            }
            
            console.log(`Auto-refresh triggered: ${reason}`);
            
            // Visual feedback for automatic refresh
            const originalHtml = refreshButton.innerHTML;
            const originalTitle = refreshButton.title;
            
            refreshButton.innerHTML = '<i class="ri-loader-2-line animate-spin"></i>';
            refreshButton.title = 'Auto-refreshing to fix incorrect values...';
            refreshButton.classList.add('opacity-75');
            refreshButton.disabled = true;
            
            // Show brief notification
            const notification = document.createElement('div');
            notification.className = 'fixed top-4 right-4 bg-blue-500 text-white px-4 py-2 rounded-lg shadow-lg z-50 text-sm';
            notification.innerHTML = `<i class="ri-refresh-line mr-2"></i>Auto-refreshing CI data...`;
            document.body.appendChild(notification);
            
            // Perform the actual refresh
            refreshButton.click();
            
            // Restore button and remove notification after refresh completes
            setTimeout(() => {
                refreshButton.innerHTML = originalHtml;
                refreshButton.title = originalTitle;
                refreshButton.classList.remove('opacity-75');
                refreshButton.disabled = false;
                
                // Remove notification
                if (notification && notification.parentNode) {
                    notification.remove();
                }
            }, 2000);
            
            return true;
        }
        
        // Update Online CI Table with improved sync logic
        function updateOnlineCITable(onlineCIs) {
            const tbody = document.getElementById('onlineCITableBody');
            if (!tbody) return;
            
            if (onlineCIs.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                            <div class="flex flex-col items-center py-4">
                                <i class="ri-user-unfollow-line text-4xl text-gray-400 mb-2"></i>
                                <span class="text-lg font-medium">No Clinical Instructors Online</span>
                                <span class="text-sm">Add CIs to the pool or wait for them to come online</span>
                            </div>
                        </td>
                    </tr>
                `;
                return;
            }
            
            // Get existing rows to preserve scroll position and prevent flickering
            const existingRows = tbody.querySelectorAll('tr');
            const existingCIs = new Map();
            
            existingRows.forEach(row => {
                const ciData = row.dataset.ciId;
                if (ciData) {
                    existingCIs.set(ciData, row);
                }
            });
            
            // Build new table content with improved activity calculation
            const newRows = onlineCIs.map(ci => {
                const patientCountColor = ci.current_patient_count > 5 ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : 
                                         (ci.current_patient_count > 2 ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : 
                                          'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200');
                
                // Improved last activity calculation with better timezone handling
                let lastActivity = 'Recently active';
                if (ci.last_activity) {
                    try {
                        // Parse the datetime string properly
                        const lastActStr = ci.last_activity.replace(' ', 'T'); // Convert to ISO format
                        const lastAct = new Date(lastActStr);
                        const now = new Date();
                        
                        // Calculate difference in minutes with proper timezone consideration
                        const diffMilliseconds = now.getTime() - lastAct.getTime();
                        const diffMinutes = Math.floor(diffMilliseconds / (1000 * 60));
                        
                        if (diffMinutes < 0) {
                            // Future time (likely timezone issue), show as "Just now"
                            lastActivity = 'Just now';
                        } else if (diffMinutes < 1) {
                            lastActivity = 'Just now';
                        } else if (diffMinutes < 60) {
                            lastActivity = diffMinutes + ' min ago';
                        } else if (diffMinutes < 1440) { // Less than 24 hours
                            const diffHours = Math.floor(diffMinutes / 60);
                            lastActivity = diffHours + ' hour' + (diffHours > 1 ? 's' : '') + ' ago';
                        } else {
                            lastActivity = lastAct.toLocaleDateString('en-US', {month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'});
                        }
                    } catch (error) {
                        console.warn('Error parsing last activity time:', ci.last_activity, error);
                        lastActivity = 'Recently active';
                    }
                }
                
                return `
                    <tr data-ci-id="${ci.id}" class="hover:bg-gradient-to-r hover:from-violet-50 hover:to-purple-50 dark:hover:from-violet-900 dark:hover:to-purple-900">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                            ${escapeHtml(ci.full_name)}
                            <div class="text-xs text-gray-600 dark:text-gray-400">${escapeHtml(ci.email)}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                <i class="ri-checkbox-blank-circle-fill mr-1 text-green-500"></i>Online
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                            ${ci.specialty_hint ? 
                                `<span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                    <i class="ri-stethoscope-line mr-1"></i>${escapeHtml(ci.specialty_hint)}
                                </span>` : 
                                '<span class="text-gray-500 dark:text-gray-400">General Practice</span>'
                            }
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full ${patientCountColor}">
                                <i class="ri-user-line mr-1"></i>${ci.current_patient_count} patients
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300" data-last-activity="${ci.last_activity}">
                            ${lastActivity}
                        </td>
                    </tr>
                `;
            }).join('');
            
            // Update the table content smoothly
            tbody.innerHTML = newRows;
        }
        
        // Auto-assign patient to best available CI
        function autoAssignPatient(patient) {
            if (!confirm(`Auto-assign ${patient.first_name} ${patient.last_name} to the best available Clinical Instructor?`)) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'auto_assign');
            formData.append('patient_id', patient.id);
            formData.append('treatment_hint', patient.treatment_hint || '');
            formData.append('notes', 'Auto-assigned via COD interface');
            
            // Show loading state
            showAlert('Auto-assigning patient...');
            
            fetch('api_online_ci.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(`Patient successfully auto-assigned to ${data.assigned_to}!`);
                    // Refresh the page to show updated assignment
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    showAlert('Error: ' + (data.error || data.message || 'Failed to auto-assign patient'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Network error occurred. Please try again.');
            });
        }
        
        // Smart auto-refresh system with dynamic intervals
        let refreshInterval = 15000; // Start with 15 seconds
        let refreshTimer;
        let consecutiveErrors = 0;
        
        function startSmartRefresh() {
            // Clear any existing timer
            if (refreshTimer) {
                clearInterval(refreshTimer);
            }
            
            // Set up the refresh timer
            refreshTimer = setInterval(() => {
                refreshOnlineCIs();
                
                // Update refresh interval based on activity
                const onlineCount = parseInt(document.getElementById('onlineCICount')?.textContent || '0');
                if (onlineCount > 0) {
                    // More frequent updates when CIs are online
                    refreshInterval = Math.max(10000, refreshInterval * 0.95); // Gradually increase frequency, min 10s
                } else {
                    // Less frequent when no one is online
                    refreshInterval = Math.min(60000, refreshInterval * 1.1); // Gradually decrease frequency, max 60s
                }
                
                // Restart timer with new interval if it changed significantly
                const currentInterval = refreshInterval;
                setTimeout(() => {
                    if (Math.abs(refreshInterval - currentInterval) > 2000) {
                        startSmartRefresh();
                    }
                }, 100);
            }, refreshInterval);
        }
        
        // Override the refresh function to handle error counting
        const originalRefreshOnlineCIs = refreshOnlineCIs;
        refreshOnlineCIs = function() {
            return originalRefreshOnlineCIs()
                .then(() => {
                    consecutiveErrors = 0; // Reset error count on success
                })
                .catch((error) => {
                    consecutiveErrors++;
                    // Exponential backoff on errors
                    if (consecutiveErrors > 3) {
                        refreshInterval = Math.min(120000, refreshInterval * 1.5); // Max 2 minutes
                        startSmartRefresh();
                    }
                    throw error;
                });
        };
        
        // Start the smart refresh system
        startSmartRefresh();
        
        // ========================================================
        // Heartbeat System - Keep user online and update activity
        // ========================================================
        
        // Send heartbeat every 60 seconds to maintain online status
        function sendHeartbeat() {
            fetch('api_heartbeat.php', {
                method: 'POST',
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Heartbeat sent successfully:', data.timestamp);
                } else {
                    console.warn('Heartbeat failed:', data.error);
                }
            })
            .catch(error => {
                console.error('Heartbeat error:', error);
            });
        }
        
        // Send initial heartbeat
        sendHeartbeat();
        
        // Set up heartbeat interval (every 60 seconds)
        setInterval(sendHeartbeat, 60000);
        
        // Also send heartbeat on user interactions
        let lastHeartbeatTime = Date.now();
        document.addEventListener('click', () => {
            const now = Date.now();
            // Only send if it's been more than 30 seconds since last heartbeat
            if (now - lastHeartbeatTime > 30000) {
                sendHeartbeat();
                lastHeartbeatTime = now;
            }
        });
        
        // Enhanced activity time updates with automatic refresh button click
        setInterval(() => {
            const activityCells = document.querySelectorAll('[data-last-activity]');
            let needsRefresh = false;
            
            activityCells.forEach(cell => {
                const lastActivity = cell.dataset.lastActivity;
                if (lastActivity) {
                    try {
                        const lastActStr = lastActivity.replace(' ', 'T');
                        const lastAct = new Date(lastActStr);
                        const now = new Date();
                        
                        const diffMilliseconds = now.getTime() - lastAct.getTime();
                        const diffMinutes = Math.floor(diffMilliseconds / (1000 * 60));
                        
                        let newActivity;
                        if (diffMinutes < 0) {
                            newActivity = 'Just now';
                        } else if (diffMinutes < 1) {
                            newActivity = 'Just now';
                        } else if (diffMinutes < 60) {
                            newActivity = diffMinutes + ' min ago';
                        } else if (diffMinutes < 1440) {
                            const diffHours = Math.floor(diffMinutes / 60);
                            newActivity = diffHours + ' hour' + (diffHours > 1 ? 's' : '') + ' ago';
                        } else {
                            newActivity = lastAct.toLocaleDateString('en-US', {month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'});
                        }
                        
                        // Check if values are getting too old or inconsistent
                        if (diffMinutes > 10 || cell.textContent.includes('57') || cell.textContent.includes('58') || cell.textContent.includes('59')) {
                            needsRefresh = true;
                        }
                        
                        if (cell.textContent !== newActivity) {
                            cell.textContent = newActivity;
                        }
                    } catch (error) {
                        // Ignore errors in local time update
                        needsRefresh = true; // Trigger refresh on errors
                    }
                }
            });
            
            // Auto-click refresh button if needed to prevent wrong values
            if (needsRefresh) {
                performAutoRefresh('stale activity values detected');
            }
        }, 10000); // Update every 10 seconds
        
        // Additional automatic refresh trigger for specific wrong values
        setInterval(() => {
            const activityCells = document.querySelectorAll('[data-last-activity]');
            let hasWrongValues = false;
            
            activityCells.forEach(cell => {
                const text = cell.textContent;
                // Check for common wrong values that appear due to timezone issues
                if (text.includes('57 min') || text.includes('58 min') || text.includes('59 min') || 
                    text.match(/^[5-9][7-9] min/) || text.includes('hours ago')) {
                    hasWrongValues = true;
                }
            });
            
            if (hasWrongValues) {
                performAutoRefresh('wrong activity values detected (57-59 min)');
            }
        }, 15000); // Check every 15 seconds for wrong values
        
        // Auto-refresh when page becomes visible again (user switched back to tab)
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                setTimeout(() => {
                    performAutoRefresh('page became visible');
                }, 500); // Small delay to ensure page is fully active
            }
        });
        
        // Auto-refresh when user interacts with the CI table area
        const ciSection = document.querySelector('#onlineCITable');
        if (ciSection) {
            let lastInteraction = Date.now();
            
            ciSection.addEventListener('mouseenter', () => {
                const now = Date.now();
                // Only refresh if it's been more than 30 seconds since last interaction
                if (now - lastInteraction > 30000) {
                    performAutoRefresh('user interacted with CI table');
                    lastInteraction = now;
                }
            });
        }
        
        // Smart refresh on focus - prevent stale data when user returns
        window.addEventListener('focus', () => {
            console.log('Window gained focus, checking for stale data');
            setTimeout(() => {
                const activityCells = document.querySelectorAll('[data-last-activity]');
                let hasStaleData = false;
                
                activityCells.forEach(cell => {
                    const text = cell.textContent;
                    // Check if data looks stale (high minute values or old timestamps)
                    if (text.match(/[1-9][0-9] min/) || text.includes('hour') || text.includes('Recently active')) {
                        hasStaleData = true;
                    }
                });
                
                if (hasStaleData) {
                    performAutoRefresh('window gained focus with stale data');
                }
            }, 1000);
        });
        
        // Emergency refresh button click if CI count becomes 0 unexpectedly
        setInterval(() => {
            const ciCount = parseInt(document.getElementById('onlineCICount')?.textContent || '0');
            const tableRows = document.querySelectorAll('#onlineCITableBody tr:not(#error-row)');
            const actualRows = Array.from(tableRows).filter(row => !row.textContent.includes('No Clinical Instructors Online'));
            
            // If count shows 0 but we had CIs before, or if there's a mismatch
            if ((ciCount === 0 && window.lastKnownCICount > 0) || (ciCount !== actualRows.length && actualRows.length > 0)) {
                performAutoRefresh('CI count mismatch detected');
            }
            
            // Store current count for next check
            if (ciCount > 0) {
                window.lastKnownCICount = ciCount;
            }
        }, 20000); // Check every 20 seconds for count mismatches
    </script>

    <?php include 'includes/logout_modal.php'; ?>
</body>
</html>
