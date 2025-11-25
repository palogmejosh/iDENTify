<?php
require_once 'config.php';
requireAuth();

$user = getCurrentUser();
$role = $user['role'] ?? '';

// Redirect if not COD or Admin
if (!in_array($role, ['COD', 'Admin'])) {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$messageType = 'success';

// Handle procedure assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'assign') {
        $procedureLogId = (int) $_POST['procedure_log_id'];
        $clinicalInstructorId = (int) $_POST['clinical_instructor_id'];
        $notes = $_POST['notes'] ?? '';

        $result = assignProcedureToClinicalInstructor($procedureLogId, $user['id'], $clinicalInstructorId, $notes);

        if ($result) {
            header("Location: cod_log_procedures_assignment.php?assigned=1");
            exit;
        } else {
            $message = 'Failed to assign procedure. Please try again.';
            $messageType = 'error';
        }
    } elseif ($_POST['action'] === 'auto_assign') {
        $procedureLogId = (int) $_POST['procedure_log_id'];
        $notes = $_POST['notes'] ?? '';
        $procedureDetails = $_POST['procedure_details'] ?? '';

        $result = autoAssignProcedureToBestCI($procedureLogId, $user['id'], $procedureDetails, $notes);

        if ($result['success']) {
            header("Location: cod_log_procedures_assignment.php?auto_assigned=1&assigned_to=" . urlencode($result['assigned_to']));
            exit;
        } else {
            $message = $result['message'] ?? 'Failed to automatically assign procedure. Please try again.';
            $messageType = 'error';
        }
    }
}

// Handle success messages
if (isset($_GET['assigned']) && $_GET['assigned'] == 1) {
    $message = 'Procedure assigned to Clinical Instructor successfully!';
} elseif (isset($_GET['auto_assigned']) && $_GET['auto_assigned'] == 1) {
    $assignedTo = $_GET['assigned_to'] ?? 'Clinical Instructor';
    $message = "Procedure automatically assigned to {$assignedTo} successfully!";
}

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$assignmentStatusFilter = $_GET['assignment_status'] ?? 'all';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Pagination parameters
$page = max(1, (int) ($_GET['page'] ?? 1));
$itemsPerPage = 6;

// Get procedure logs for COD (correct argument order: search, dateFrom, dateTo, assignmentStatus, page, itemsPerPage)
$codProceduresResult = getCODProcedureAssignments($search, $dateFrom, $dateTo, $assignmentStatusFilter, $page, $itemsPerPage);
$codProcedures = $codProceduresResult['procedures'];
$totalItems = $codProceduresResult['total'];
$totalPages = ceil($totalItems / $itemsPerPage);

// Perform automatic assignment for procedures that are not yet assigned
foreach ($codProcedures as &$procedure) {
    // Check if procedure is unassigned or pending rejection
    $assignmentStatus = $procedure['assignment_status'] ?? 'unassigned';
    if (empty($procedure['assigned_clinical_instructor']) || $assignmentStatus === 'unassigned' || $assignmentStatus === 'pending' || $assignmentStatus === 'rejected') {
        // Check if we haven't already assigned this procedure
        $checkAssignment = $pdo->prepare("SELECT id FROM procedure_assignments WHERE procedure_log_id = ? AND assignment_status = 'pending'");
        $checkAssignment->execute([$procedure['procedure_log_id']]);

        if (!$checkAssignment->fetch()) {
            // Auto-assign the procedure to the best available CI
            $procedureDetails = $procedure['procedure_details'] ?? '';
            $autoAssignResult = autoAssignProcedureToBestCI($procedure['procedure_log_id'], $user['id'], $procedureDetails, 'Auto-assigned via COD interface');

            if ($autoAssignResult['success']) {
                // Get the updated assignment data with timestamp
                $getUpdatedAssignment = $pdo->prepare("
                    SELECT pra.assigned_at, pra.notes as assignment_notes, u_ci.full_name as assigned_clinical_instructor
                    FROM procedure_assignments pra
                    LEFT JOIN users u_ci ON pra.clinical_instructor_id = u_ci.id
                    WHERE pra.procedure_log_id = ?
                ");
                $getUpdatedAssignment->execute([$procedure['procedure_log_id']]);
                $updatedAssignment = $getUpdatedAssignment->fetch();

                if ($updatedAssignment) {
                    // Update the procedure data to reflect the auto-assignment
                    $procedure['assigned_clinical_instructor'] = $updatedAssignment['assigned_clinical_instructor'];
                    $procedure['assignment_status'] = 'pending';
                    $procedure['assigned_at'] = $updatedAssignment['assigned_at'];
                    $procedure['assignment_notes'] = $updatedAssignment['assignment_notes'];
                }
            }
        }
    }
}
// Unset reference to avoid issues with subsequent loops
unset($procedure);

// Get Clinical Instructors for assignment dropdown
$clinicalInstructors = getAllClinicalInstructorsWithCounts();

// Get online Clinical Instructors
$onlineClinicalInstructors = getOnlineClinicalInstructors();

$fullName = $user ? $user['full_name'] : 'Unknown User';
$profilePicture = $user['profile_picture'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>iDENTify - Log Procedures Assignment</title>
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
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body
    class="bg-gradient-to-br from-violet-25 via-white to-purple-25 dark:bg-gradient-to-br dark:from-violet-950 dark:via-gray-900 dark:to-purple-950 transition-colors duration-200">

    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main
        class="ml-64 mt-16 p-6 min-h-screen bg-gradient-to-br from-violet-50 to-purple-50 dark:from-gray-900 dark:to-violet-900">
        <div class="flex justify-between items-center mb-6">
            <h2
                class="text-2xl font-bold bg-gradient-to-r from-violet-700 to-purple-700 bg-clip-text text-transparent dark:from-violet-300 dark:to-purple-300">
                Log Procedures Assignment</h2>
            <div class="flex items-center space-x-4">
                <span class="text-sm text-gray-700 dark:text-gray-300">
                    Total Procedures: <span class="total-records"><?php echo $totalItems; ?></span>
                </span>
                <?php if ($totalPages > 1): ?>
                    <span class="text-sm text-gray-700 dark:text-gray-300">
                        (Page <?php echo $page; ?> of <?php echo $totalPages; ?>)
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Notification/Alert Box -->
        <?php if ($message): ?>
            <div id="alertBox" class="mb-6">
                <div
                    class="<?php echo $messageType === 'success' ? 'bg-gradient-to-r from-green-100 to-emerald-100 dark:from-green-900 dark:to-emerald-900 border border-green-400 dark:border-green-600 text-green-700 dark:text-green-200' : 'bg-gradient-to-r from-red-100 to-rose-100 dark:from-red-900 dark:to-rose-900 border border-red-400 dark:border-red-600 text-red-700 dark:text-red-200'; ?> px-4 py-3 rounded-lg shadow-lg relative">
                    <span id="alertMessage"><?php echo htmlspecialchars($message); ?></span>
                    <button onclick="hideAlert()" class="absolute top-0 bottom-0 right-0 px-4 py-3">
                        <i class="ri-close-line"></i>
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <!-- Info Box -->
        <div
            class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900 dark:to-indigo-900 border border-blue-200 dark:border-blue-700 rounded-lg p-4 mb-6">
            <div class="flex items-start">
                <i class="ri-information-line text-blue-600 dark:text-blue-400 text-xl mr-3 mt-1"></i>
                <div>
                    <h3 class="font-semibold text-gray-900 dark:text-white mb-1">About Procedure Assignment</h3>
                    <p class="text-sm text-gray-700 dark:text-gray-300">
                        Review procedure logs submitted by Clinicians and Admin. Assign these procedures to Clinical
                        Instructors for review and approval. You can manually assign or use auto-assignment to
                        intelligently distribute procedures based on specialty and workload.
                    </p>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div
            class="bg-gradient-to-r from-white to-violet-50 dark:from-gray-800 dark:to-violet-900 rounded-lg shadow-lg border border-violet-200 dark:border-violet-700 p-6 mb-6">
            <h3
                class="text-lg font-semibold bg-gradient-to-r from-violet-700 to-purple-700 bg-clip-text text-transparent dark:from-violet-300 dark:to-purple-300 mb-4">
                Search & Filter Procedures</h3>
            <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-900 dark:text-white mb-2">Search</label>
                    <input type="text" name="search" placeholder="Patient, clinician, procedure..."
                        class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-violet-900 dark:text-white"
                        value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 dark:text-white mb-2">Assignment
                        Status</label>
                    <select name="assignment_status"
                        class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-violet-900 dark:text-white">
                        <option value="all" <?php echo ($assignmentStatusFilter === 'all') ? 'selected' : ''; ?>>All
                            Status</option>
                        <option value="unassigned" <?php echo ($assignmentStatusFilter === 'unassigned') ? 'selected' : ''; ?>>Unassigned</option>
                        <option value="pending" <?php echo ($assignmentStatusFilter === 'pending') ? 'selected' : ''; ?>>
                            Pending Review</option>
                        <option value="accepted" <?php echo ($assignmentStatusFilter === 'accepted') ? 'selected' : ''; ?>>Accepted</option>
                        <option value="rejected" <?php echo ($assignmentStatusFilter === 'rejected') ? 'selected' : ''; ?>>Rejected</option>
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
                    <button type="submit"
                        class="w-full bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-700 hover:to-purple-700 text-white px-4 py-2 rounded-md transition-all duration-200 shadow-lg">
                        <i class="ri-search-line mr-2"></i>Search
                    </button>
                </div>
            </form>
        </div>

        <!-- Online Clinical Instructors Section -->
        <div
            class="bg-gradient-to-br from-white to-violet-25 dark:from-gray-800 dark:to-violet-900 rounded-lg shadow-lg border border-violet-200 dark:border-violet-700 overflow-hidden mb-6">
            <div
                class="px-6 py-4 border-b border-violet-200 dark:border-violet-700 bg-gradient-to-r from-violet-50 to-purple-50 dark:from-violet-900 dark:to-purple-900">
                <div class="flex justify-between items-center">
                    <h3
                        class="text-lg font-semibold bg-gradient-to-r from-violet-700 to-purple-700 bg-clip-text text-transparent dark:from-violet-300 dark:to-purple-300">
                        Online Clinical Instructors</h3>
                    <span class="text-sm text-gray-700 dark:text-gray-300">
                        Online: <span id="onlineCICount"><?php echo count($onlineClinicalInstructors); ?></span>
                    </span>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-violet-200 dark:divide-violet-700">
                    <thead
                        class="bg-gradient-to-r from-violet-100 to-purple-100 dark:from-violet-800 dark:to-purple-800">
                        <tr>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">
                                CI Name</th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">
                                Status</th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">
                                Specialty</th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">
                                Current Workload</th>
                        </tr>
                    </thead>
                    <tbody
                        class="bg-gradient-to-b from-white to-violet-25 dark:from-gray-800 dark:to-violet-900 divide-y divide-violet-200 dark:divide-violet-700">
                        <?php if (!empty($onlineClinicalInstructors)): ?>
                            <?php foreach ($onlineClinicalInstructors as $ci): ?>
                                <tr
                                    class="hover:bg-gradient-to-r hover:from-violet-50 hover:to-purple-50 dark:hover:from-violet-900 dark:hover:to-purple-900">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($ci['full_name']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span
                                            class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                            <i class="ri-checkbox-blank-circle-fill mr-1 text-green-500"></i>Online
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                        <?php if (!empty($ci['specialty_hint'])): ?>
                                            <span
                                                class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                <i
                                                    class="ri-stethoscope-line mr-1"></i><?php echo htmlspecialchars($ci['specialty_hint']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-gray-500 dark:text-gray-400">General Practice</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span
                                            class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full <?php echo $ci['current_patient_count'] > 5 ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : ($ci['current_patient_count'] > 2 ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'); ?>">
                                            <i class="ri-user-line mr-1"></i><?php echo $ci['current_patient_count']; ?>
                                            patients
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                    <div class="flex flex-col items-center py-4">
                                        <i class="ri-user-unfollow-line text-4xl text-gray-400 mb-2"></i>
                                        <span class="text-lg font-medium">No Clinical Instructors Online</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Procedures Table -->
        <div
            class="bg-gradient-to-br from-white to-violet-25 dark:from-gray-800 dark:to-violet-900 rounded-lg shadow-lg border border-violet-200 dark:border-violet-700 overflow-hidden">
            <div
                class="px-6 py-4 border-b border-violet-200 dark:border-violet-700 bg-gradient-to-r from-violet-50 to-purple-50 dark:from-violet-900 dark:to-purple-900">
                <h3
                    class="text-lg font-semibold bg-gradient-to-r from-violet-700 to-purple-700 bg-clip-text text-transparent dark:from-violet-300 dark:to-purple-300">
                    Logged Procedures</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-violet-200 dark:divide-violet-700">
                    <thead
                        class="bg-gradient-to-r from-violet-100 to-purple-100 dark:from-violet-800 dark:to-purple-800">
                        <tr>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">
                                Patient Name</th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">
                                Procedure</th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">
                                Details</th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">
                                Clinician</th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">
                                Date Logged</th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">
                                Assignment Status</th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">
                                Assigned To</th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody
                        class="bg-gradient-to-b from-white to-violet-25 dark:from-gray-800 dark:to-violet-900 divide-y divide-violet-200 dark:divide-violet-700">
                        <?php if (!empty($codProcedures)): ?>
                            <?php foreach ($codProcedures as $procedure): ?>
                                <tr
                                    class="hover:bg-gradient-to-r hover:from-violet-50 hover:to-purple-50 dark:hover:from-violet-900 dark:hover:to-purple-900">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($procedure['patient_name']); ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                        <div class="max-w-xs"
                                            title="<?php echo htmlspecialchars($procedure['procedure_selected']); ?>">
                                            <?php echo htmlspecialchars(substr($procedure['procedure_selected'], 0, 50)) . (strlen($procedure['procedure_selected']) > 50 ? '...' : ''); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                        <?php if (!empty($procedure['procedure_details'])): ?>
                                            <span
                                                class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                <?php echo htmlspecialchars($procedure['procedure_details']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-gray-400 dark:text-gray-500 text-xs">Not specified</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                        <?php echo htmlspecialchars($procedure['clinician_name'] ?? 'N/A'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                        <?php echo date('M d, Y', strtotime($procedure['logged_at'])); ?>
                                        <div class="text-xs text-gray-500">
                                            <?php echo date('h:i A', strtotime($procedure['logged_at'])); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php
                                        $assignmentStatus = $procedure['assignment_status'] ?? 'unassigned';
                                        $statusClass = 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200';
                                        $statusText = 'Unassigned';

                                        switch ($assignmentStatus) {
                                            case 'pending':
                                                $statusClass = 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
                                                $statusText = 'Pending Review';
                                                break;
                                            case 'accepted':
                                                $statusClass = 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
                                                $statusText = 'Accepted';
                                                break;
                                            case 'rejected':
                                                $statusClass = 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
                                                $statusText = 'Rejected';
                                                break;
                                            case 'completed':
                                                $statusClass = 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
                                                $statusText = 'Completed';
                                                break;
                                        }
                                        ?>
                                        <span
                                            class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $statusClass; ?>">
                                            <?php echo $statusText; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                        <?php echo htmlspecialchars($procedure['assigned_clinical_instructor'] ?? 'Auto-assigning...'); ?>
                                        <?php if (!empty($procedure['assigned_at'])): ?>
                                            <div class="text-xs text-gray-500">
                                                <i
                                                    class="ri-time-line mr-1"></i><?php echo date('M d, Y H:i', strtotime($procedure['assigned_at'])); ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($procedure['assignment_notes']) && strpos($procedure['assignment_notes'], 'Auto-assigned') !== false): ?>
                                            <div class="text-xs text-blue-600 dark:text-blue-400">
                                                <i class="ri-magic-line mr-1"></i>Auto-assigned
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <?php
                                        $assignmentStatus = $procedure['assignment_status'] ?? 'unassigned';
                                        if ($assignmentStatus === 'unassigned' || empty($procedure['assigned_clinical_instructor'])):
                                            ?>
                                            <button
                                                onclick="openAssignModal(<?php echo htmlspecialchars(json_encode($procedure)); ?>)"
                                                class="inline-flex items-center px-3 py-1.5 bg-gradient-to-r from-blue-600 to-cyan-600 hover:from-blue-700 hover:to-cyan-700 text-white text-xs font-medium rounded-md transition-all duration-200 shadow-md hover:shadow-lg"
                                                title="Manually assign this procedure to a Clinical Instructor">
                                                <i class="ri-user-add-line mr-1"></i>Assign
                                            </button>
                                        <?php elseif ($assignmentStatus === 'pending' || $assignmentStatus === 'accepted'): ?>
                                            <button
                                                onclick="openAssignModal(<?php echo htmlspecialchars(json_encode($procedure)); ?>)"
                                                class="inline-flex items-center px-3 py-1.5 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white text-xs font-medium rounded-md transition-all duration-200 shadow-md hover:shadow-lg"
                                                title="Reassign this procedure to a different Clinical Instructor">
                                                <i class="ri-refresh-line mr-1"></i>Reassign
                                            </button>
                                        <?php else: ?>
                                            <span
                                                class="inline-flex items-center px-3 py-1.5 bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-400 text-xs font-medium rounded-md">
                                                <i class="ri-check-line mr-1"></i>Completed
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="px-6 py-8 text-center">
                                    <div class="flex flex-col items-center justify-center">
                                        <i class="ri-inbox-line text-5xl text-gray-400 dark:text-gray-600 mb-3"></i>
                                        <p class="text-gray-500 dark:text-gray-400 font-medium">No logged procedures found
                                        </p>
                                        <p class="text-sm text-gray-400 dark:text-gray-500 mt-1">Procedures logged by
                                            clinicians will appear here</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex flex-col sm:flex-row justify-between items-center space-y-3 sm:space-y-0">
                        <div class="text-sm text-gray-700 dark:text-gray-300">
                            Showing <?php echo (($page - 1) * $itemsPerPage + 1); ?> to
                            <?php echo min($page * $itemsPerPage, $totalItems); ?> of <?php echo $totalItems; ?> results
                        </div>
                        <div class="flex items-center space-x-2">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&assignment_status=<?php echo urlencode($assignmentStatusFilter); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>"
                                    class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300">
                                    <i class="ri-arrow-left-line mr-1"></i>Previous
                                </a>
                            <?php endif; ?>

                            <span class="px-3 py-2 text-sm bg-blue-600 text-white rounded-md"><?php echo $page; ?></span>

                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&assignment_status=<?php echo urlencode($assignmentStatusFilter); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>"
                                    class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300">
                                    Next<i class="ri-arrow-right-line ml-1"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Assignment Modal -->
    <div id="assignmentModal" class="fixed inset-0 bg-black bg-opacity-50 hidden justify-center items-center px-4 z-50">
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-lg w-full max-w-lg mx-auto modal-fade">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Assign Procedure to Clinical Instructor
                </h3>
                <button onclick="closeAssignModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>

            <form method="POST" id="assignmentForm">
                <input type="hidden" name="action" value="assign">
                <input type="hidden" id="procedureLogId" name="procedure_log_id">

                <div id="procedureDetails" class="mb-4 p-4 bg-gray-50 dark:bg-gray-700 rounded-md">
                    <!-- Populated by JS -->
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Select Clinical Instructor <span class="text-red-500">*</span>
                    </label>
                    <select name="clinical_instructor_id" required
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-gray-700 dark:text-white">
                        <option value="">-- Select Clinical Instructor --</option>
                        <?php foreach ($clinicalInstructors as $ci): ?>
                            <option value="<?php echo $ci['id']; ?>">
                                <?php echo htmlspecialchars($ci['full_name']); ?>
                                (<?php echo $ci['current_patient_count']; ?> patients)
                                <?php if (!empty($ci['specialty_hint'])): ?>
                                    - <?php echo htmlspecialchars($ci['specialty_hint']); ?>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Notes (Optional)
                    </label>
                    <textarea name="notes" rows="3"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-gray-700 dark:text-white"
                        placeholder="Add any notes about this assignment..."></textarea>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeAssignModal()"
                        class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-gray-100 border border-gray-300 dark:border-gray-600 rounded-md">
                        Cancel
                    </button>
                    <button type="submit"
                        class="bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-700 hover:to-purple-700 text-white px-4 py-2 rounded-md text-sm shadow-lg transition-all duration-200">
                        <i class="ri-check-line mr-1"></i>Assign Procedure
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Logout Modal -->
    <?php include 'includes/logout_modal.php'; ?>

    <script>
        // Dark mode toggle
        const darkModeToggle = document.getElementById('darkModeToggle');
        const html = document.documentElement;
        const darkMode = localStorage.getItem('darkMode') === 'true';
        if (darkMode) {
            html.classList.add('dark');
        }
        darkModeToggle.addEventListener('click', () => {
            html.classList.toggle('dark');
            const isDark = html.classList.contains('dark');
            localStorage.setItem('darkMode', isDark);
        });

        function hideAlert() {
            document.getElementById('alertBox')?.classList.add('hidden');
        }

        // Auto-hide alert after 5 seconds
        setTimeout(hideAlert, 5000);

        // Modal Functions
        function openAssignModal(procedure) {
            // Use correct key from API: procedure_log_id
            document.getElementById('procedureLogId').value = procedure.procedure_log_id || procedure.id;
            document.getElementById('procedureDetails').innerHTML = `
            <h4 class="font-semibold text-gray-900 dark:text-white mb-3">Procedure Information:</h4>
            <div class="grid grid-cols-2 gap-3 text-sm">
                <div><strong class="text-gray-700 dark:text-gray-300">Patient:</strong><br>${escapeHtml(procedure.patient_name)}</div>
                <div><strong class="text-gray-700 dark:text-gray-300">Clinician:</strong><br>${escapeHtml(procedure.clinician_name)}</div>
                <div class="col-span-2"><strong class="text-gray-700 dark:text-gray-300">Procedure:</strong><br>${escapeHtml(procedure.procedure_selected)}</div>
                ${procedure.procedure_details ? `<div class=\"col-span-2\"><strong class=\"text-gray-700 dark:text-gray-300\">Details:</strong><br>${escapeHtml(procedure.procedure_details)}</div>` : ''}
            </div>
        `;

            document.getElementById('assignmentModal').style.display = 'flex';
        }

        function closeAssignModal() {
            document.getElementById('assignmentModal').style.display = 'none';
            document.getElementById('assignmentForm').reset();
        }


        function escapeHtml(unsafe) {
            if (!unsafe) return '';
            return unsafe
                .toString()
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
    </script>

</body>

</html>