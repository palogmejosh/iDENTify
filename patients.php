<?php
require_once 'config.php';
requireAuth();

$user = getCurrentUser();
$role = $user['role'] ?? '';
$message = '';

// MODIFIED: Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        // Collect all form data
        $patientData = [
            'first_name' => $_POST['firstName'],
            'middle_initial' => $_POST['middleInitial'] ?? null,
            'nickname' => $_POST['nickname'] ?? null,
            'gender' => $_POST['gender'] ?? null,
            'last_name' => $_POST['lastName'],
            'birth_date' => !empty($_POST['birthDate']) ? $_POST['birthDate'] : null,
            'age' => (int)$_POST['age'],
            'phone' => $_POST['phone'],
            'email' => $_POST['email'],
            'status' => ($role === 'Clinician') ? 'Pending' : ($_POST['status'] ?? 'Pending'),
            'treatment_hint' => $_POST['treatmentHint'] ?? null,
            'created_by' => $user['id'] ?? null
        ];

        // Build dynamic SQL query
        $columns = array_keys($patientData);
        $placeholders = ':' . implode(', :', $columns);
        $columnsList = implode(', ', $columns);
        
        try {
            $stmt = $pdo->prepare("INSERT INTO patients ({$columnsList}) VALUES ({$placeholders})");
            $result = $stmt->execute($patientData);
            
            if ($result) {
                header("Location: patients.php?success=1");
                exit;
            } else {
                $message = 'Failed to add patient. Please try again.';
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // Duplicate entry error
                $message = 'Email address already exists. Please use a different email.';
            } else {
                $message = 'Database error: ' . $e->getMessage();
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'update_status' && $role === 'Clinical Instructor') {
        // Handle direct status updates for Clinical Instructors
        $patientId = (int) $_POST['patient_id'];
        $newStatus = $_POST['status'];
        $ciId = $user['id'];
        
        // Validate that the Clinical Instructor can update this patient
        if (updatePatientStatusByClinicalInstructor($patientId, $ciId, $newStatus)) {
            header("Location: patients.php?status_updated=1");
            exit;
        } else {
            $message = 'Error updating patient status. You can only update patients assigned to you.';
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'approve_patient' && $role === 'Clinical Instructor') {
        $approvalStatus = $_POST['approval_status'];
        $assignmentId = $_POST['assignment_id'];
        $approvalNotes = $_POST['approval_notes'] ?? '';
        $ciId = $user['id']; // Get current Clinical Instructor's ID

        // Use the dedicated approval function
        $result = updatePatientApproval($assignmentId, $ciId, $approvalStatus, $approvalNotes);
        
        if ($result) {
            header("Location: patients.php?approval_updated=1");
            exit;
        } else {
            $message = 'Error updating patient approval. Please try again.';
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'edit_treatment_hint' && in_array($role, ['Admin', 'Clinician', 'COD'])) {
        // Handle procedure details update
        $patientId = (int) $_POST['patient_id'];
        $newTreatmentHint = trim($_POST['treatment_hint'] ?? '');
        
        // Validate patient exists and user has permission
        $patientStmt = $pdo->prepare("SELECT id, created_by FROM patients WHERE id = ?");
        $patientStmt->execute([$patientId]);
        $patient = $patientStmt->fetch();
        
        if ($patient) {
            // Check permissions: Admin and COD can edit all, Clinicians can only edit their own patients
            $canEdit = false;
            if ($role === 'Admin' || $role === 'COD') {
                $canEdit = true;
            } elseif ($role === 'Clinician' && $patient['created_by'] == $user['id']) {
                $canEdit = true;
            }
            
            if ($canEdit) {
                try {
                    $updateStmt = $pdo->prepare("UPDATE patients SET treatment_hint = ?, updated_at = NOW() WHERE id = ?");
                    $result = $updateStmt->execute([$newTreatmentHint, $patientId]);
                    
                    if ($result) {
                        header("Location: patients.php?treatment_hint_updated=1");
                        exit;
                    } else {
                        $message = 'Failed to update procedure details. Please try again.';
                    }
                } catch (PDOException $e) {
                    $message = 'Database error: ' . $e->getMessage();
                }
            } else {
                $message = 'You do not have permission to edit this patient\'s procedure details.';
            }
        } else {
            $message = 'Patient not found.';
        }
    }
}

if (isset($_GET['success']) && $_GET['success'] == 1) {
    $message = 'Patient added successfully!';
} elseif (isset($_GET['status_updated']) && $_GET['status_updated'] == 1) {
    $message = 'Status updated successfully!';
} elseif (isset($_GET['approval_updated']) && $_GET['approval_updated'] == 1) {
    $message = 'Patient approval updated successfully!';
} elseif (isset($_GET['treatment_hint_updated']) && $_GET['treatment_hint_updated'] == 1) {
    $message = 'Procedure details updated successfully!';
} elseif (isset($_GET['transfer_success']) && $_GET['transfer_success'] == 1) {
    $patientName = isset($_GET['patient_name']) ? htmlspecialchars($_GET['patient_name']) : 'Patient';
    $ciName = isset($_GET['ci_name']) ? htmlspecialchars($_GET['ci_name']) : 'the clinical instructor';
    $message = "✅ Success! {$patientName} has been successfully passed to {$ciName}. The transfer request is now pending their approval.";
} elseif (isset($_GET['info']) && $_GET['info'] == 'approvals_moved') {
    $message = 'ℹ️ Patient approvals functionality has been integrated here! You can now update patient status directly from this page using the status edit button.';
} elseif (isset($_GET['error']) && $_GET['error'] == 'access_denied') {
    $message = 'Access denied: COD users are not authorized to view patient details. Use Patient Assignments tab for management activities.';
}

// Enhanced filtering with date range and pagination
$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? 'all';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1)); // Current page, minimum 1
$itemsPerPage = 6; // Number of items per page
$offset = ($page - 1) * $itemsPerPage;

// Get all filtered patients first to calculate total count
$allFilteredPatients = getPatients($search, $statusFilter, $dateFrom, $dateTo, $role, $user['id'] ?? null);
$totalItems = count($allFilteredPatients);
$totalPages = ceil($totalItems / $itemsPerPage);

// Get patients for current page
$filteredPatients = array_slice($allFilteredPatients, $offset, $itemsPerPage);

function getUserDisplayName($user) {
    return $user['full_name'] ?? $user['username'] ?? 'User';
}

$userDisplayName = getUserDisplayName($user);
$fullName = $user ? $user['full_name'] : 'Unknown User';
$profilePicture = $user['profile_picture'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>iDENTify - Patients</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.0.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="dark-mode-override.css">
    <link rel="stylesheet" href="styles.css">
    <script>
        // Dark mode configuration
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
<main class="ml-64 mt-16 p-6 min-h-screen bg-gradient-to-br from-violet-25 via-white to-purple-25 dark:bg-gradient-to-br dark:from-violet-950 dark:via-gray-900 dark:to-purple-950">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold bg-gradient-to-r from-violet-600 via-purple-600 to-indigo-600 bg-clip-text text-transparent">
                <?php if ($role === 'COD'): ?>
                    All Patient Records
                    <span class="text-sm font-normal text-gray-700 dark:text-gray-300">(Clinician Oversight)</span>
                <?php elseif ($role === 'Clinical Instructor'): ?>
                    Assigned Patient Records
                <?php else: ?>
                    Patient Records
                <?php endif; ?>
            </h2>
            <?php if ($role !== 'Clinical Instructor' && $role !== 'COD'): ?>
                <div class="flex space-x-2">
                    <button onclick="document.getElementById('addPatientModal').style.display='flex'" class="bg-gradient-to-r from-violet-500 to-purple-600 hover:from-violet-600 hover:to-purple-700 text-white px-4 py-2 rounded-md flex items-center shadow-lg">
                        <i class="ri-add-line mr-2"></i>Add New Patient
                    </button>
                </div>
            <?php elseif ($role === 'COD'): ?>
                <div class="flex space-x-2">
                    <a href="cod_patients.php" class="bg-gradient-to-r from-purple-500 to-indigo-600 hover:from-purple-600 hover:to-indigo-700 text-white px-4 py-2 rounded-md flex items-center shadow-lg">
                        <i class="ri-user-settings-line mr-2"></i>Manage Assignments
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Notification/Alert Box -->
        <?php if ($message): ?>
            <div id="alertBox" class="mb-6">
                <?php if (isset($_GET['error']) && $_GET['error'] == 'access_denied'): ?>
                    <div class="bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-600 text-red-700 dark:text-red-200 px-4 py-3 rounded relative">
                        <span id="alertMessage"><i class="ri-error-warning-line mr-2"></i><?php echo htmlspecialchars($message); ?></span>
                        <button onclick="hideAlert()" class="absolute top-0 bottom-0 right-0 px-4 py-3">
                            <i class="ri-close-line"></i>
                        </button>
                    </div>
                <?php elseif (isset($_GET['transfer_success']) && $_GET['transfer_success'] == 1): ?>
                    <!-- Special styling for transfer success message -->
                    <div class="bg-gradient-to-r from-green-50 to-emerald-50 dark:from-green-900 dark:to-emerald-900 border-2 border-green-500 dark:border-green-400 text-green-800 dark:text-green-100 px-6 py-4 rounded-lg shadow-lg relative">
                        <div class="flex items-start">
                            <i class="ri-checkbox-circle-fill text-3xl text-green-600 dark:text-green-400 mr-4 mt-1"></i>
                            <div class="flex-1">
                                <h3 class="font-bold text-lg mb-1">Patient Transfer Request Successful!</h3>
                                <span id="alertMessage" class="text-sm"><?php echo $message; ?></span>
                            </div>
                        </div>
                        <button onclick="hideAlert()" class="absolute top-0 bottom-0 right-0 px-4 py-3 text-green-600 dark:text-green-400 hover:text-green-800 dark:hover:text-green-200">
                            <i class="ri-close-line text-xl"></i>
                        </button>
                    </div>
                <?php else: ?>
                    <div class="bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-600 text-green-700 dark:text-green-200 px-4 py-3 rounded relative">
                        <span id="alertMessage"><i class="ri-checkbox-circle-line mr-2"></i><?php echo htmlspecialchars($message); ?></span>
                        <button onclick="hideAlert()" class="absolute top-0 bottom-0 right-0 px-4 py-3">
                            <i class="ri-close-line"></i>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Search and Filter Form -->
        <div class="bg-gradient-to-r from-violet-50 to-purple-50 dark:bg-gradient-to-r dark:from-violet-900 dark:to-purple-900 rounded-lg shadow-lg border border-violet-200 dark:border-violet-700 p-6 mb-6">
            <h3 class="text-lg font-semibold bg-gradient-to-r from-violet-600 via-purple-600 to-indigo-600 bg-clip-text text-transparent mb-4">Search & Filter Patients</h3>
            <form id="patientsFilterForm" method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                <input type="hidden" name="page" value="1"> <!-- Reset to page 1 when searching -->
                <div>
                    <label class="block text-sm font-medium text-gray-900 dark:text-white mb-2">Patient Name or Email</label>
                    <input type="text" name="search" placeholder="Search by name or email..." 
                           class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-violet-800 dark:text-white bg-white" 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 dark:text-white mb-2">Date From</label>
                    <input type="date" name="date_from" 
                           class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-violet-800 dark:text-white bg-white" 
                           value="<?php echo htmlspecialchars($dateFrom); ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 dark:text-white mb-2">Date To</label>
                    <input type="date" name="date_to" 
                           class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-violet-800 dark:text-white bg-white" 
                           value="<?php echo htmlspecialchars($dateTo); ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 dark:text-white mb-2">Status</label>
                    <select name="status" class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-violet-800 dark:text-white bg-white">
                        <option value="all" <?php echo ($statusFilter === 'all') ? 'selected' : ''; ?>>All Status</option>
                        <option value="Approved" <?php echo ($statusFilter === 'Approved') ? 'selected' : ''; ?>>Approved</option>
                        <option value="Pending" <?php echo ($statusFilter === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                        <option value="Disapproved" <?php echo ($statusFilter === 'Disapproved') ? 'selected' : ''; ?>>Declined</option>
                    </select>
                </div>
                <div class="flex space-x-2">
                    <button type="submit" class="flex-1 bg-gradient-to-r from-violet-500 to-purple-600 hover:from-violet-600 hover:to-purple-700 text-white px-4 py-2 rounded-md shadow-lg">
                        <i class="ri-search-line mr-2"></i>Search
                    </button>
                    <a href="patients.php" class="bg-gradient-to-r from-gray-500 to-gray-600 hover:from-gray-600 hover:to-gray-700 text-white px-4 py-2 rounded-md flex items-center justify-center shadow-lg">
                        <i class="ri-refresh-line"></i>
                    </a>
                </div>
            </form>
        </div>

        <!-- Patients Table -->
        <div class="bg-gradient-to-br from-violet-50 to-purple-50 dark:bg-gradient-to-br dark:from-violet-900 dark:to-purple-900 rounded-lg shadow-lg border border-violet-200 dark:border-violet-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-violet-200 dark:border-violet-700">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-semibold bg-gradient-to-r from-violet-600 via-purple-600 to-indigo-600 bg-clip-text text-transparent">Patient Records</h3>
                    <div class="flex items-center space-x-4">
                        <div id="patientsHeaderInfo" class="flex items-center space-x-4">
                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                Total: <span class="total-records"><?php echo $totalItems; ?></span>
                            </span>
                            <span class="page-info text-sm text-gray-700 dark:text-gray-300" <?php echo ($totalPages <= 1) ? 'style="display:none;"' : ''; ?>>
                                (Page <?php echo $page; ?> of <?php echo $totalPages; ?>)
                            </span>
                        </div>
                        <?php if ($role === 'COD'): ?>
                            <div class="flex items-center text-xs text-blue-600 dark:text-blue-400">
                                <i class="ri-information-line mr-1"></i>
                                <span>View access restricted for COD role</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table id="patientsTable" class="min-w-full divide-y divide-violet-200 dark:divide-violet-700">
                    <thead class="bg-gradient-to-r from-violet-100 to-purple-100 dark:bg-gradient-to-r dark:from-violet-800 dark:to-purple-800">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">Patient Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">ID Number</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">Age</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">Phone</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">Date Created</th>
                            <?php if (in_array($role, ['Admin', 'Clinician', 'COD'])): ?>
                            <th class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">Procedure Details</th>
                            <?php endif; ?>
                            <th class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">Status</th>
                            <?php if ($role !== 'COD'): ?>
                            <th class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-violet-900 divide-y divide-violet-200 dark:divide-violet-700">
                        <?php if ($filteredPatients): ?>
                            <?php foreach ($filteredPatients as $patient): ?>
                                <tr class="hover:bg-gradient-to-r hover:from-violet-50 hover:to-purple-50 dark:hover:from-violet-800 dark:hover:to-purple-800">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                        #<?php echo str_pad($patient['id'], 4, '0', STR_PAD_LEFT); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                        <?php echo htmlspecialchars($patient['age']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                        <?php echo htmlspecialchars($patient['phone']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                        <?php echo htmlspecialchars($patient['email']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                        <?php echo isset($patient['created_at']) ? date('M d, Y', strtotime($patient['created_at'])) : 'N/A'; ?>
                                    </td>
                                    <?php if (in_array($role, ['Admin', 'Clinician', 'COD'])): ?>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                        <?php if (!empty($patient['treatment_hint'])): ?>
                                            <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full bg-gradient-to-r from-blue-100 to-blue-200 text-blue-800 dark:from-blue-900 dark:to-blue-800 dark:text-blue-200 border border-blue-300 dark:border-blue-600" title="<?php echo htmlspecialchars($patient['treatment_hint']); ?>">
                                                <i class="ri-heart-pulse-line mr-1"></i>
                                                <?php echo htmlspecialchars(strlen($patient['treatment_hint']) > 20 ? substr($patient['treatment_hint'], 0, 20) . '...' : $patient['treatment_hint']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-gray-400 dark:text-gray-500 text-xs italic">Not specified</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php endif; ?>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                            <?php 
                                            echo $patient['status'] === 'Approved' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 
                                                 ($patient['status'] === 'Pending' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : 
                                                  'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'); 
                                            ?>">
                                            <?php echo htmlspecialchars($patient['status'] === 'Disapproved' ? 'Declined' : $patient['status']); ?>
                                        </span>
                                    </td>
                                    <?php if ($role !== 'COD'): ?>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <a href="view_patient.php?id=<?php echo $patient['id']; ?>" 
                                               class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-200" title="View">
                                                <i class="ri-eye-line"></i>
                                            </a>
                                            <?php if ($role === 'Admin' || $role === 'Clinician'): ?>
                                                <a href="edit_patient.php?id=<?php echo $patient['id']; ?>" 
                                                   class="text-green-600 dark:text-green-400 hover:text-green-900 dark:hover:text-green-200" title="Edit">
                                                    <i class="ri-edit-line"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if (in_array($role, ['Admin', 'Clinician', 'COD'])): ?>
                                                <button onclick="openTreatmentHintModal(<?php echo $patient['id']; ?>, '<?php echo htmlspecialchars($patient['treatment_hint'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name'], ENT_QUOTES); ?>')" 
                                                        class="text-purple-600 dark:text-purple-400 hover:text-purple-900 dark:hover:text-purple-200" title="Edit Procedure Details">
                                                    <i class="ri-medicine-bottle-line"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($role === 'Clinical Instructor'): ?>
                                                <button onclick="openCIEditModal(<?php echo $patient['id']; ?>, '<?php echo htmlspecialchars($patient['status']); ?>', '<?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>')" 
                                                        class="text-green-600 dark:text-green-400 hover:text-green-900 dark:hover:text-green-200" title="Edit Patient">
                                                    <i class="ri-edit-line"></i>
                                                </button>
                                                <button onclick="openTransferModal(<?php echo htmlspecialchars(json_encode(['id' => $patient['id'], 'first_name' => $patient['first_name'], 'last_name' => $patient['last_name'], 'email' => $patient['email'], 'assignment_id' => $patient['assignment_id'] ?? null]), ENT_QUOTES, 'UTF-8'); ?>)" 
                                                        class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-200" title="Transfer Patient">
                                                    <i class="ri-exchange-line"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($role === 'Admin'): ?>
                                                <button onclick="deletePatient(<?php echo $patient['id']; ?>)" 
                                                        class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-200" title="Delete">
                                                    <i class="ri-delete-bin-line"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?php echo ($role === 'COD') ? (in_array($role, ['Admin', 'Clinician', 'COD']) ? '8' : '7') : (in_array($role, ['Admin', 'Clinician', 'COD']) ? '9' : '8'); ?>" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                    No patient records found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination Controls -->
            <div id="patientsPagination">
            <?php if ($totalPages > 1): ?>
            <div class="px-6 py-4 border-t border-violet-200 dark:border-violet-700">
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

        <!-- Floating Add Button (Mobile) -->
        <div class="fixed bottom-6 right-6 md:hidden">
            <button onclick="document.getElementById('addPatientModal').style.display='flex'" class="bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-700 hover:to-purple-700 text-white p-4 rounded-full shadow-lg">
                <i class="ri-add-line text-xl"></i>
            </button>
        </div>
</main>

<!-- Add Patient Modal -->
<div id="addPatientModal" class="fixed inset-0 bg-black bg-opacity-50 hidden justify-center items-center px-4 z-50">
    <div class="bg-gradient-to-br from-violet-50 to-purple-50 dark:bg-gradient-to-br dark:from-violet-900 dark:to-purple-900 p-5 rounded-lg shadow-lg border border-violet-200 dark:border-violet-700 w-full max-w-md mx-auto modal-fade max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold bg-gradient-to-r from-violet-600 via-purple-600 to-indigo-600 bg-clip-text text-transparent">Add New Patient</h3>
            <button onclick="document.getElementById('addPatientModal').style.display='none'" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <i class="ri-close-line text-xl"></i>
            </button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">First Name <span class="text-red-500">*</span></label>
                    <input type="text" name="firstName" required class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-violet-800 dark:text-white bg-white text-sm">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">Middle Initial</label>
                    <input type="text" name="middleInitial" maxlength="10" class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-violet-800 dark:text-white bg-white text-sm">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">Nickname</label>
                    <input type="text" name="nickname" maxlength="100" class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-violet-800 dark:text-white bg-white text-sm">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">Gender</label>
                    <select name="gender" class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-violet-800 dark:text-white bg-white text-sm">
                        <option value="">Select Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">Last Name <span class="text-red-500">*</span></label>
                    <input type="text" name="lastName" required class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-violet-800 dark:text-white bg-white text-sm">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">Birth Date</label>
                    <input type="date" name="birthDate" class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-violet-800 dark:text-white bg-white text-sm">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">Age <span class="text-red-500">*</span></label>
                    <input type="number" name="age" required min="1" max="120" class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-violet-800 dark:text-white bg-white text-sm">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">Phone <span class="text-red-500">*</span></label>
                    <input type="tel" name="phone" required class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-violet-800 dark:text-white bg-white text-sm">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">Email <span class="text-red-500">*</span></label>
                    <input type="email" name="email" required class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-violet-800 dark:text-white bg-white text-sm">
                </div>

                <!-- Procedure Details Field - Visible for all roles except Clinical Instructors -->
                <?php if ($role !== 'Clinical Instructor'): ?>
                <div style="background: #e7f3ff; border: 2px solid #0084ff; padding: 10px; border-radius: 5px; margin: 10px 0;">
                    <label class="block text-sm font-medium text-blue-800 dark:text-blue-200 mb-1">
                        🩺 Procedure Details
                        <span class="text-xs text-blue-600 dark:text-blue-300 ml-1">(helps COD assign to right Clinical Instructor)</span>
                    </label>
                    <input type="text" name="treatmentHint" placeholder="e.g., Orthodontics, Oral Surgery, Periodontics, General Dentistry" maxlength="255" class="w-full px-3 py-2 border border-blue-300 dark:border-blue-500 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-700 dark:text-white text-sm">
                    <small class="block text-xs text-blue-600 dark:text-blue-300 mt-1">
                        This field is visible because you are: <?php echo htmlspecialchars($role); ?>
                    </small>
                </div>
                <?php else: ?>
                <div style="background: #fff3cd; border: 2px solid #ffc107; padding: 10px; border-radius: 5px; margin: 10px 0;">
                    <p class="text-sm text-yellow-800 dark:text-yellow-200">
                        ℹ️ Procedure details field is not shown for Clinical Instructors since they don't add patients.
                    </p>
                </div>
                <?php endif; ?>

                <?php if ($role !== 'Clinician'): ?>
                <div>
                    <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">Status</label>
                    <select name="status" class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-violet-800 dark:text-white bg-white text-sm">
                        <option value="Pending">Pending</option>
                        <option value="Approved">Approved</option>
                        <option value="Disapproved">Declined</option>
                    </select>
                </div>
                <?php else: ?>
                <!-- Hidden input for Clinicians to ensure status defaults to Pending -->
                <input type="hidden" name="status" value="Pending">
                <?php endif; ?>
            </div>
            <div class="flex justify-end space-x-2 mt-5">
                <button type="button" onclick="document.getElementById('addPatientModal').style.display='none'" class="px-3 py-1.5 text-sm text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-gray-100">Cancel</button>
                <button type="submit" class="bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-700 hover:to-purple-700 text-white px-4 py-1.5 rounded-md text-sm shadow-lg">Add Patient</button>
            </div>
        </form>
    </div>
</div>

<!-- Status Update Modal -->
<div id="statusModal" class="fixed inset-0 bg-black bg-opacity-50 hidden justify-center items-center px-4 z-50">
    <div class="bg-gradient-to-br from-violet-50 to-purple-50 dark:bg-gradient-to-br dark:from-violet-900 dark:to-purple-900 p-5 rounded-lg shadow-lg border border-violet-200 dark:border-violet-700 w-full max-w-sm mx-auto modal-fade">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold bg-gradient-to-r from-violet-600 via-purple-600 to-indigo-600 bg-clip-text text-transparent">
                <?php if ($role === 'Clinical Instructor'): ?>
                    🩺 Update Patient Status
                <?php else: ?>
                    Update Status
                <?php endif; ?>
            </h3>
            <button onclick="document.getElementById('statusModal').style.display='none'" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <i class="ri-close-line text-xl"></i>
            </button>
        </div>
        <div class="mb-4 p-3 bg-violet-50 dark:bg-violet-900/20 border border-violet-200 dark:border-violet-700 rounded-md">
            <div class="text-sm text-gray-900 dark:text-white">
                <strong>Patient:</strong> <span id="patientName" class="font-bold"></span>
                <?php if ($role === 'Clinical Instructor'): ?>
                    <br><small class="text-gray-700 dark:text-gray-300 mt-1 block">
                        ⚡ As a Clinical Instructor, you can directly update the patient status for assigned patients.
                    </small>
                <?php endif; ?>
            </div>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="patient_id" id="statusPatientId">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-900 dark:text-white mb-2">Select New Status</label>
                <select name="status" id="statusSelect" class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-violet-800 dark:text-white bg-white text-sm">
                    <option value="Pending">⏳ Pending - Needs review</option>
                    <option value="Approved">✅ Approved - Ready for treatment</option>
                    <option value="Disapproved">❌ Declined - Cannot proceed</option>
                </select>
                <?php if ($role === 'Clinical Instructor'): ?>
                <p class="mt-2 text-xs text-gray-700 dark:text-gray-300">
                    💡 This will update both the patient status and your approval record.
                </p>
                <?php endif; ?>
            </div>
            <div class="flex justify-end space-x-2">
                <button type="button" onclick="document.getElementById('statusModal').style.display='none'" class="px-3 py-1.5 text-sm text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-gray-100">Cancel</button>
                <button type="submit" class="bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-700 hover:to-purple-700 text-white px-4 py-1.5 rounded-md text-sm shadow-lg">Update Status</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Procedure Details Modal -->
<div id="treatmentHintModal" class="fixed inset-0 bg-black bg-opacity-50 hidden justify-center items-center px-4 z-50">
    <div class="bg-gradient-to-br from-violet-50 to-purple-50 dark:bg-gradient-to-br dark:from-violet-900 dark:to-purple-900 p-5 rounded-lg shadow-lg border border-violet-200 dark:border-violet-700 w-full max-w-md mx-auto modal-fade">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold bg-gradient-to-r from-violet-600 via-purple-600 to-indigo-600 bg-clip-text text-transparent">
                🩺 Edit Procedure Details
            </h3>
            <button onclick="document.getElementById('treatmentHintModal').style.display='none'" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <i class="ri-close-line text-xl"></i>
            </button>
        </div>
        <div class="mb-4 p-3 bg-violet-50 dark:bg-violet-900/20 border border-violet-200 dark:border-violet-700 rounded-md">
            <div class="text-sm text-gray-900 dark:text-white">
                <strong>Patient:</strong> <span id="treatmentHintPatientName" class="font-bold"></span>
                <br><small class="text-gray-700 dark:text-gray-300 mt-1 block">
                    💡 Procedure details help COD assign patients to the most suitable Clinical Instructor based on their procedure details.
                </small>
            </div>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit_treatment_hint">
            <input type="hidden" name="patient_id" id="treatmentHintPatientId">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-900 dark:text-white mb-2">Procedure Details</label>
                <input type="text" 
                       name="treatment_hint" 
                       id="treatmentHintInput" 
                       placeholder="e.g., Orthodontics, Oral Surgery, Periodontics, General Dentistry" 
                       maxlength="255" 
                       class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-violet-800 dark:text-white bg-white text-sm">
                <small class="block text-xs text-gray-600 dark:text-gray-400 mt-1">
                    Enter the type of treatment this patient needs. Leave blank if not specified.
                </small>
            </div>
            <div class="flex justify-end space-x-2">
                <button type="button" onclick="document.getElementById('treatmentHintModal').style.display='none'" class="px-3 py-1.5 text-sm text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-gray-100">Cancel</button>
                <button type="submit" class="bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 text-white px-4 py-1.5 rounded-md text-sm shadow-lg">
                    <i class="ri-medicine-bottle-line mr-1"></i>Update Procedure Details
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Dark mode toggle
const darkModeToggle = document.getElementById('darkModeToggle');
const html = document.documentElement;

// Check for saved dark mode preference or default to light mode
const darkMode = localStorage.getItem('darkMode') === 'true';
if (darkMode) {
    html.classList.add('dark');
}

darkModeToggle.addEventListener('click', () => {
    html.classList.toggle('dark');
    const isDark = html.classList.contains('dark');
    localStorage.setItem('darkMode', isDark);
});

// Alert functions
function hideAlert() {
    document.getElementById('alertBox').style.display = 'none';
}

// Modal functions
function openStatusModal(patientId, currentStatus, patientName) {
    document.getElementById('statusModal').style.display = 'flex';
    document.getElementById('statusPatientId').value = patientId;
    document.getElementById('statusSelect').value = currentStatus;
    document.getElementById('patientName').textContent = patientName;
}

function openTreatmentHintModal(patientId, currentTreatmentHint, patientName) {
    document.getElementById('treatmentHintModal').style.display = 'flex';
    document.getElementById('treatmentHintPatientId').value = patientId;
    document.getElementById('treatmentHintInput').value = currentTreatmentHint || '';
    document.getElementById('treatmentHintPatientName').textContent = patientName;
}

// Action functions
function generateReport(patientId) {
    // Add actual report generation logic here
    alert('Generating report for patient ID: ' + patientId);
}

function deletePatient(patientId) {
    // Get patient name from the table row for a more personalized message
    const row = document.querySelector(`button[onclick*="deletePatient(${patientId})"]`).closest('tr');
    const patientName = row ? row.querySelector('td:nth-child(2)').textContent.trim() : 'this patient';
    
    showDeleteModal(
        `Are you sure you want to delete <strong>${patientName}</strong>?<br><br><span class="text-red-600 dark:text-red-400 text-sm">⚠️ This action cannot be undone and will permanently remove all patient data including medical records.</span>`,
        function() {
            // Show loading state
            const confirmBtn = document.getElementById('confirmDeleteBtn');
            const originalText = confirmBtn.innerHTML;
            confirmBtn.innerHTML = '<i class="ri-loader-2-line animate-spin mr-1"></i>Deleting...';
            confirmBtn.disabled = true;
            
            // Make AJAX call to delete the patient from the database
            const formData = new FormData();
            formData.append('patient_id', patientId);
            
            fetch('delete_patient.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    showAlert(data.message || 'Patient deleted successfully');
                    
                    // Remove the row from the table with animation
                    if (row) {
                        row.style.transition = 'opacity 0.3s ease-out';
                        row.style.opacity = '0';
                        setTimeout(() => {
                            row.remove();
                            
                            // Check if table is now empty
                            const tbody = document.querySelector('#patientsTable tbody');
                            const remainingRows = tbody.querySelectorAll('tr');
                            if (remainingRows.length === 0) {
                                // Reload page to show empty state properly
                                location.reload();
                            }
                        }, 300);
                    } else {
                        // Reload page if we can't find the row
                        setTimeout(() => location.reload(), 1000);
                    }
                } else {
                    // Show error message
                    showAlert('Error: ' + (data.error || 'Failed to delete patient'));
                    confirmBtn.innerHTML = originalText;
                    confirmBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error deleting patient:', error);
                showAlert('Network error: Could not delete patient. Please try again.');
                confirmBtn.innerHTML = originalText;
                confirmBtn.disabled = false;
            });
        }
    );
}

// AJAX Pagination Functions
let currentPatientsFilters = {
    search: '<?php echo addslashes($search); ?>',
    status: '<?php echo addslashes($statusFilter); ?>',
    date_from: '<?php echo addslashes($dateFrom); ?>',
    date_to: '<?php echo addslashes($dateTo); ?>'
};

function loadPatientsPage(page) {
    // Show loading state
    const tableBody = document.querySelector('#patientsTable tbody');
    const paginationContainer = document.querySelector('#patientsPagination');
    
    if (tableBody) {
        tableBody.innerHTML = '<tr><td colspan="7" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400"><i class="ri-loader-2-line animate-spin mr-2"></i>Loading...</td></tr>';
    }
    
    // Build query parameters
    const params = new URLSearchParams({
        page: page,
        ...currentPatientsFilters
    });
    
    // Make AJAX request
    fetch(`ajax_patients.php?${params.toString()}`)
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
                const headerInfo = document.querySelector('#patientsHeaderInfo');
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
                document.querySelector('#patientsTable').scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest'
                });
                
            } else {
                console.error('Error loading patients page:', data.error);
                showAlert('Error loading page: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Network error:', error);
            showAlert('Network error occurred. Please try again.');
            
            // Restore table content on error
            if (tableBody) {
                tableBody.innerHTML = '<tr><td colspan="7" class="px-6 py-4 text-center text-red-500 dark:text-red-400">Error loading data. Please refresh the page.</td></tr>';
            }
        });
}

// Update filters when search form is submitted
function updatePatientsFilters() {
    const form = document.querySelector('#patientsFilterForm');
    if (form) {
        const formData = new FormData(form);
        currentPatientsFilters = {
            search: formData.get('search') || '',
            status: formData.get('status') || 'all',
            date_from: formData.get('date_from') || '',
            date_to: formData.get('date_to') || ''
        };
        
        // Load first page with new filters
        loadPatientsPage(1);
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

// Close modals when clicking outside
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('fixed') && e.target.classList.contains('inset-0')) {
        e.target.style.display = 'none';
    }
});

// Auto-hide alerts after 5 seconds
setTimeout(() => {
    const alertBox = document.getElementById('alertBox');
    if (alertBox && !alertBox.classList.contains('hidden')) {
        hideAlert();
    }
}, 5000);
</script>

<!-- Transfer Patient Modal -->
<?php if ($role === 'Clinical Instructor'): ?>
<div id="transferPatientModal" class="fixed inset-0 bg-black bg-opacity-50 hidden justify-center items-center px-4 z-50">
    <div class="bg-gradient-to-br from-violet-50 to-purple-50 dark:bg-gradient-to-br dark:from-violet-900 dark:to-purple-900 p-5 rounded-lg shadow-lg border border-violet-200 dark:border-violet-700 w-full max-w-md mx-auto modal-fade">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold bg-gradient-to-r from-violet-600 via-purple-600 to-indigo-600 bg-clip-text text-transparent">
                <i class="ri-exchange-line mr-2"></i>Transfer Patient
            </h3>
            <button onclick="closeTransferModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <i class="ri-close-line text-xl"></i>
            </button>
        </div>
        
        <div id="transferPatientDetails" class="mb-4 p-3 bg-violet-50 dark:bg-violet-900/20 border border-violet-200 dark:border-violet-700 rounded-md">
            <div class="text-sm text-gray-900 dark:text-white">
                <strong>Patient:</strong> <span id="transferPatientName" class="font-bold"></span>
                <br><small class="text-gray-700 dark:text-gray-300 mt-1 block">
                    Select the Clinical Instructor you want to transfer this patient to.
                </small>
            </div>
        </div>
        
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-900 dark:text-white mb-2">Transfer To <span class="text-red-500">*</span></label>
            <select id="transferToCISelect" class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-violet-800 dark:text-white bg-white text-sm">
                <option value="">-- Select Clinical Instructor --</option>
            </select>
        </div>
        
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-900 dark:text-white mb-2">Transfer Reason <span class="text-red-500">*</span></label>
            <textarea id="transferReason" rows="3" 
                      class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-violet-800 dark:text-white bg-white text-sm"
                      placeholder="Explain why you are transferring this patient..." required></textarea>
        </div>
        
        <input type="hidden" id="transferPatientId">
        <input type="hidden" id="transferAssignmentId">
        
        <div class="flex justify-end space-x-2">
            <button type="button" onclick="closeTransferModal()" class="px-3 py-1.5 text-sm text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-gray-100">Cancel</button>
            <button type="button" onclick="submitTransfer()" id="submitTransferBtn" class="bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white px-4 py-1.5 rounded-md text-sm shadow-lg">
                <i class="ri-exchange-line mr-1"></i>Submit Transfer Request
            </button>
        </div>
    </div>
</div>

<script>
let availableCIs = [];

// Fetch available CIs for transfer
fetch('ajax_ci_patient_transfer.php?action=get_available_cis')
    .then(response => response.json())
    .then(data => {
        if (data.success && data.cis) {
            availableCIs = data.cis;
        }
    })
    .catch(error => console.error('Error fetching CIs:', error));

function openTransferModal(patient) {
    const modal = document.getElementById('transferPatientModal');
    const patientNameSpan = document.getElementById('transferPatientName');
    const ciSelect = document.getElementById('transferToCISelect');
    
    // Set patient details
    document.getElementById('transferPatientId').value = patient.id;
    document.getElementById('transferAssignmentId').value = patient.assignment_id || '';
    patientNameSpan.textContent = patient.first_name + ' ' + patient.last_name;
    
    // Populate CI dropdown
    ciSelect.innerHTML = '<option value="">-- Select Clinical Instructor --</option>';
    availableCIs.forEach(ci => {
        const option = document.createElement('option');
        option.value = ci.id;
        option.textContent = `${ci.full_name} (${ci.patient_count} patients)`;
        ciSelect.appendChild(option);
    });
    
    // Clear previous values
    document.getElementById('transferReason').value = '';
    
    modal.style.display = 'flex';
}

function closeTransferModal() {
    document.getElementById('transferPatientModal').style.display = 'none';
}

function submitTransfer() {
    const patientId = document.getElementById('transferPatientId').value;
    const assignmentId = document.getElementById('transferAssignmentId').value;
    const toCIId = document.getElementById('transferToCISelect').value;
    const transferReason = document.getElementById('transferReason').value.trim();
    const submitBtn = document.getElementById('submitTransferBtn');
    
    // Validation
    if (!toCIId) {
        alert('Please select a Clinical Instructor to transfer to.');
        return;
    }
    
    if (!transferReason) {
        alert('Please provide a reason for the transfer.');
        return;
    }
    
    if (!assignmentId) {
        alert('Assignment ID not found. Please refresh the page and try again.');
        return;
    }
    
    // Disable button and show loading
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="ri-loader-4-line animate-spin mr-1"></i>Processing...';
    
    // Submit transfer request
    fetch('ajax_ci_patient_transfer.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=create_transfer&patient_id=${patientId}&assignment_id=${assignmentId}&to_ci_id=${toCIId}&transfer_reason=${encodeURIComponent(transferReason)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Get patient name from the modal
            const patientName = document.getElementById('transferPatientName').textContent;
            
            // Get CI name from the selected option
            const ciSelect = document.getElementById('transferToCISelect');
            const selectedOption = ciSelect.options[ciSelect.selectedIndex];
            const ciName = selectedOption.textContent.split(' (')[0]; // Extract name without patient count
            
            // Show success state on button
            submitBtn.className = 'bg-gradient-to-r from-green-500 to-emerald-600 text-white px-4 py-1.5 rounded-md text-sm shadow-lg';
            submitBtn.innerHTML = '<i class="ri-checkbox-circle-line mr-1"></i>Transfer Request Sent!';
            
            // Show success alert
            showAlert('✅ Transfer request submitted successfully! Redirecting...', 'success');
            
            // Close modal and redirect after short delay
            setTimeout(() => {
                closeTransferModal();
                window.location.href = `patients.php?transfer_success=1&patient_name=${encodeURIComponent(patientName)}&ci_name=${encodeURIComponent(ciName)}`;
            }, 1500);
        } else {
            alert(data.message || 'Error submitting transfer request');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="ri-exchange-line mr-1"></i>Submit Transfer Request';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Network error occurred. Please try again.');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="ri-exchange-line mr-1"></i>Submit Transfer Request';
    });
}

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    if (e.target.id === 'transferPatientModal') {
        closeTransferModal();
    }
});
</script>
<?php endif; ?>

<!-- CI Comprehensive Edit Modal -->
<?php if ($role === 'Clinical Instructor'): ?>
<div id="ciEditModal" class="fixed inset-0 bg-black bg-opacity-50 hidden justify-center items-center px-4 z-50 overflow-y-auto">
    <div class="bg-gradient-to-br from-violet-50 to-purple-50 dark:bg-gradient-to-br dark:from-violet-900 dark:to-purple-900 p-6 rounded-lg shadow-lg border border-violet-200 dark:border-violet-700 w-full max-w-5xl mx-auto modal-fade my-8">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-semibold bg-gradient-to-r from-violet-600 via-purple-600 to-indigo-600 bg-clip-text text-transparent">
                <i class="ri-edit-line mr-2"></i>Edit Patient Record
            </h3>
            <button onclick="closeCIEditModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <i class="ri-close-line text-2xl"></i>
            </button>
        </div>
        
        <div id="ciEditPatientInfo" class="mb-4 p-4 bg-violet-100 dark:bg-violet-900/40 border border-violet-300 dark:border-violet-600 rounded-md">
            <div class="text-sm text-gray-900 dark:text-white">
                <strong>Patient:</strong> <span id="ciEditPatientName" class="font-bold text-lg"></span>
                <span class="ml-4 text-gray-600 dark:text-gray-300">Age/Gender: <span id="ciEditPatientAge"></span></span>
            </div>
        </div>
        
        <!-- Tabs -->
        <div class="border-b border-violet-300 dark:border-violet-600 mb-4">
            <nav class="flex space-x-4">
                <button onclick="switchCITab('status')" id="ciTabStatus" class="ci-tab-btn px-4 py-2 text-sm font-medium border-b-2 border-violet-600 text-violet-600">
                    <i class="ri-clipboard-line mr-1"></i>Patient Status
                </button>
                <button onclick="switchCITab('notes')" id="ciTabNotes" class="ci-tab-btn px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-600 dark:text-gray-300 hover:text-violet-600">
                    <i class="ri-file-list-3-line mr-1"></i>Progress Notes
                </button>
            </nav>
        </div>
        
        <!-- Status Tab Content -->
        <div id="ciTabContentStatus" class="ci-tab-content">
            <form id="ciStatusForm">
                <input type="hidden" id="ciStatusPatientId">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-900 dark:text-white mb-2">Patient Status</label>
                    <select id="ciStatusSelect" class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-violet-800 dark:text-white bg-white">
                        <option value="Pending">⏳ Pending - Needs review</option>
                        <option value="Approved">✅ Approved - Ready for treatment</option>
                        <option value="Disapproved">❌ Declined - Cannot proceed</option>
                    </select>
                    <p class="mt-2 text-xs text-gray-600 dark:text-gray-400">
                        💡 This will update both the patient status and your approval record.
                    </p>
                </div>
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="closeCIEditModal()" class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-gray-100">Cancel</button>
                    <button type="submit" class="bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-700 hover:to-purple-700 text-white px-6 py-2 rounded-md text-sm shadow-lg">
                        <i class="ri-save-line mr-1"></i>Update Status
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Progress Notes Tab Content -->
        <div id="ciTabContentNotes" class="ci-tab-content hidden">
            <form id="ciProgressNotesForm">
                <input type="hidden" id="ciNotesPatientId">
                
                <div class="mb-4">
                    <div class="overflow-x-auto">
                        <table id="ciNotesTable" class="min-w-full border border-gray-300 text-xs">
                            <thead>
                                <tr class="bg-gray-100 dark:bg-gray-700">
                                    <th class="border px-2 py-1">Date</th>
                                    <th class="border px-2 py-1">Tooth</th>
                                    <th class="border px-2 py-1">Progress Notes</th>
                                    <th class="border px-2 py-1">Clinician</th>
                                    <th class="border px-2 py-1">CI</th>
                                    <th class="border px-2 py-1">Remarks</th>
                                    <th class="border px-2 py-1">Del</th>
                                </tr>
                            </thead>
                            <tbody id="ciNotesTableBody">
                                <!-- Rows will be populated dynamically -->
                            </tbody>
                        </table>
                    </div>
                    <button type="button" onclick="addCINotesRow()" class="mt-2 bg-blue-600 text-white px-4 py-1.5 rounded-md text-xs hover:bg-blue-700">
                        <i class="ri-add-line mr-1"></i>Add Row
                    </button>
                </div>
                
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="closeCIEditModal()" class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-gray-100">Cancel</button>
                    <button type="submit" class="bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-700 hover:to-purple-700 text-white px-6 py-2 rounded-md text-sm shadow-lg">
                        <i class="ri-save-line mr-1"></i>Save Progress Notes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
/* ---------- CI EDIT MODAL FIXES FOR PROGRESS NOTES SYNCHRONIZATION ---------- */
// These functions ensure that progress notes edited in the CI modal on patients.php
// are properly synchronized with edit_patient_step5.php:
// 1. Hidden ID inputs are properly handled for existing rows
// 2. Data structure is consistent between both interfaces
// 3. Save operations properly handle updates vs inserts
// 4. Debugging and validation ensure data integrity

let currentCITab = 'status';
let ciCurrentUserName = '';

// Function to validate progress notes data integrity
function validateProgressNotesData(rows) {
    console.log('Validating progress notes data:');
    rows.forEach((row, index) => {
        console.log(`Row ${index + 1}:`, {
            id: row.id,
            hasData: !!(row.date || row.tooth || row.progress || row.clinician || row.ci || row.remarks),
            isEmpty: !(row.date || row.tooth || row.progress || row.clinician || row.ci || row.remarks)
        });
    });
    return true;
}

function switchCITab(tabName) {
    currentCITab = tabName;
    
    // Update tab buttons
    document.querySelectorAll('.ci-tab-btn').forEach(btn => {
        btn.classList.remove('border-violet-600', 'text-violet-600');
        btn.classList.add('border-transparent', 'text-gray-600', 'dark:text-gray-300');
    });
    
    const activeTab = document.getElementById('ciTab' + tabName.charAt(0).toUpperCase() + tabName.slice(1));
    if (activeTab) {
        activeTab.classList.remove('border-transparent', 'text-gray-600', 'dark:text-gray-300');
        activeTab.classList.add('border-violet-600', 'text-violet-600');
    }
    
    // Update content visibility
    document.querySelectorAll('.ci-tab-content').forEach(content => {
        content.classList.add('hidden');
    });
    
    const activeContent = document.getElementById('ciTabContent' + tabName.charAt(0).toUpperCase() + tabName.slice(1));
    if (activeContent) {
        activeContent.classList.remove('hidden');
    }
}

function openCIEditModal(patientId, currentStatus, patientName) {
    const modal = document.getElementById('ciEditModal');
    
    // Set patient info
    document.getElementById('ciStatusPatientId').value = patientId;
    document.getElementById('ciNotesPatientId').value = patientId;
    document.getElementById('ciEditPatientName').textContent = patientName;
    document.getElementById('ciStatusSelect').value = currentStatus;
    
    // Load progress notes
    loadCIProgressNotes(patientId);
    
    // Show modal and default to status tab
    switchCITab('status');
    modal.style.display = 'flex';
}

function closeCIEditModal() {
    document.getElementById('ciEditModal').style.display = 'none';
}

function loadCIProgressNotes(patientId) {
    console.log('Loading progress notes for patient ID:', patientId);
    
    fetch(`ci_edit_progress_notes.php?id=${patientId}`)
        .then(response => {
            const contentType = response.headers.get("content-type");
            if (contentType && contentType.indexOf("application/json") !== -1) {
                return response.json();
            } else {
                return response.text().then(text => {
                    console.error('Non-JSON response:', text);
                    throw new Error('Server returned non-JSON response');
                });
            }
        })
        .then(data => {
            console.log('Progress notes API response:', data);
            
            if (data.success) {
                ciCurrentUserName = data.currentUserName;
                document.getElementById('ciEditPatientAge').textContent = `${data.patient.age} / ${data.patient.gender}`;
                
                // Populate progress notes table
                const tbody = document.getElementById('ciNotesTableBody');
                tbody.innerHTML = '';
                
                if (data.progressNotes && data.progressNotes.length > 0) {
                    console.log('Adding', data.progressNotes.length, 'progress note rows');
                    data.progressNotes.forEach((note, index) => {
                        console.log(`Adding note ${index + 1}:`, note);
                        addCINotesRowWithData(note);
                    });
                } else {
                    console.log('No existing progress notes found, adding empty row');
                    // Add an empty row by default
                    addCINotesRow();
                }
            } else {
                console.error('API returned error:', data);
                alert('Error loading progress notes: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error loading progress notes:', error);
            alert('Failed to load progress notes. Please try again.');
        });
}

function addCINotesRow() {
    addCINotesRowWithData({
        id: '',
        date: '', 
        tooth: '', 
        progress: '', 
        clinician: ciCurrentUserName, 
        ci: '', 
        remarks: ''
    });
}

function addCINotesRowWithData(data) {
    const tbody = document.getElementById('ciNotesTableBody');
    const tr = document.createElement('tr');
    const isAuto = !!data.is_auto_generated;
    const tpBadge = data.treatment_plan ? `<span title="Treatment Plan" class="inline-flex items-center px-1.5 py-0.5 text-[10px] font-semibold rounded bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">TP</span>` : '';
    const autoBadge = isAuto ? `<span title="Auto-generated from procedure log" class="inline-flex items-center px-1.5 py-0.5 text-[10px] font-semibold rounded bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200">AUTO</span>` : '';
    
    tr.innerHTML = `
        <td class="border px-2 py-1">
            <input type="hidden" name="id[]" value="${data.id || ''}">
            <input type="date" name="date[]" value="${data.date || ''}" class="w-full bg-transparent border-0 focus:ring-0 text-xs dark:bg-gray-700 dark:text-white">
        </td>
        <td class="border px-2 py-1"><input type="text" name="tooth[]" value="${data.tooth || ''}" class="w-full bg-transparent border-0 focus:ring-0 text-xs dark:bg-gray-700 dark:text-white"></td>
        <td class="border px-2 py-1">
            <div class="flex items-center gap-1">
                <input type="text" name="progress[]" value="${data.progress || ''}" class="flex-1 bg-transparent border-0 focus:ring-0 text-xs dark:bg-gray-700 dark:text-white">
                ${tpBadge}
                ${autoBadge}
            </div>
        </td>
        <td class="border px-2 py-1"><input type="text" name="clinician[]" readonly value="${data.clinician || ciCurrentUserName}" class="w-full bg-gray-100 dark:bg-gray-600 border-0 focus:ring-0 text-xs"></td>
        <td class="border px-2 py-1"><input type="text" name="ci[]" value="${data.ci || ''}" class="w-full bg-transparent border-0 focus:ring-0 text-xs dark:bg-gray-700 dark:text-white"></td>
        <td class="border px-2 py-1"><input type="text" name="remarks[]" value="${data.remarks || ''}" class="w-full bg-transparent border-0 focus:ring-0 text-xs dark:bg-gray-700 dark:text-white"></td>
        <td class="border px-2 py-1"><button type="button" onclick="this.closest('tr').remove()" class="text-red-600 hover:text-red-800 text-xs">✕</button></td>
    `;
    tbody.appendChild(tr);
}

// Handle status form submission
document.addEventListener('DOMContentLoaded', function() {
    const statusForm = document.getElementById('ciStatusForm');
    if (statusForm) {
        statusForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('action', 'update_status');
            formData.append('patient_id', document.getElementById('ciStatusPatientId').value);
            formData.append('status', document.getElementById('ciStatusSelect').value);
            
            fetch('patients.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    closeCIEditModal();
                    location.reload();
                } else {
                    alert('Error updating status');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Network error');
            });
        });
    }
    
    // Handle progress notes form submission
    const notesForm = document.getElementById('ciProgressNotesForm');
    if (notesForm) {
        notesForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = e.target.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="ri-loader-4-line animate-spin mr-1"></i>Saving...';
            
            const formData = new FormData();
            formData.append('patient_id', document.getElementById('ciNotesPatientId').value);
            
            // Collect all progress notes with hidden IDs
            const tbody = document.getElementById('ciNotesTableBody');
            const rows = [];
            
            tbody.querySelectorAll('tr').forEach(tr => {
                const id = tr.querySelector('input[name="id[]"]')?.value || '';
                const date = tr.querySelector('input[name="date[]"]')?.value || '';
                const tooth = tr.querySelector('input[name="tooth[]"]')?.value || '';
                const progress = tr.querySelector('input[name="progress[]"]')?.value || '';
                const clinician = tr.querySelector('input[name="clinician[]"]')?.value || '';
                const ci = tr.querySelector('input[name="ci[]"]')?.value || '';
                const remarks = tr.querySelector('input[name="remarks[]"]')?.value || '';
                
                // Only add rows that have some data (not completely empty)
                if (date || tooth || progress || clinician || ci || remarks) {
                    rows.push({
                        id: id || null,  // Ensure null for new rows, not empty string
                        date: date,
                        tooth: tooth,
                        progress: progress,
                        clinician: clinician,
                        ci: ci,
                        remarks: remarks
                    });
                }
            });
            
            console.log('Submitting rows to save_step5.php:', rows);
            console.log('Patient ID:', document.getElementById('ciNotesPatientId').value);
            console.log('Patient signature:', document.getElementById('ciEditPatientName').textContent);
            
            // Validate data integrity
            validateProgressNotesData(rows);
            
            formData.append('notes_json', JSON.stringify(rows));
            formData.append('patient_signature', document.getElementById('ciEditPatientName').textContent);
            
            try {
                const res = await fetch('save_step5.php', {method: 'POST', body: formData});
                const msg = await res.text();
                
                console.log('Server response status:', res.status);
                console.log('Server response message:', msg);
                
                if (res.ok && msg.trim() === 'OK') {
                    closeCIEditModal();
                    alert('Progress notes saved successfully!');
                    // Reload the page to ensure data is refreshed
                    location.reload();
                } else {
                    alert('Error saving progress notes: ' + msg);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="ri-save-line mr-1"></i>Save Progress Notes';
                }
            } catch (error) {
                alert('Network error occurred while saving progress notes');
                console.error('Network error:', error);
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="ri-save-line mr-1"></i>Save Progress Notes';
            }
        });
    }
});

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    if (e.target.id === 'ciEditModal') {
        closeCIEditModal();
    }
});
</script>
<?php endif; ?>

<?php include 'includes/logout_modal.php'; ?>
<?php include 'includes/delete_modal.php'; ?>
</body>
</html>
