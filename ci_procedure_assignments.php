<?php
require_once 'config.php';
requireAuth();

$user = getCurrentUser();
$role = $user['role'] ?? '';

// Redirect if not Clinical Instructor
if ($role !== 'Clinical Instructor') {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$messageType = 'success';

// Get search parameter
$search = $_GET['search'] ?? '';

// Get pending procedure assignments for this Clinical Instructor
$pendingProcedureAssignments = getCIPendingProcedureAssignments($user['id'], $search);

$fullName = $user ? $user['full_name'] : 'Unknown User';
$profilePicture = $user['profile_picture'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>iDENTify - Procedure Assignments</title>
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
    </style>
</head>
<body class="bg-gradient-to-br from-violet-25 via-white to-purple-25 dark:bg-gradient-to-br dark:from-violet-950 dark:via-gray-900 dark:to-purple-950 transition-colors duration-200">

<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<!-- Main Content -->
<main class="ml-64 mt-[64px] min-h-screen bg-gradient-to-br from-violet-50 to-purple-50 dark:from-gray-900 dark:to-violet-900 p-6">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold bg-gradient-to-r from-violet-700 to-purple-700 bg-clip-text text-transparent dark:from-violet-300 dark:to-purple-300">Procedure Assignment Requests</h2>
        <div class="flex items-center space-x-4">
            <span class="text-sm bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200 px-3 py-1 rounded-full font-semibold">
                <?php echo count($pendingProcedureAssignments); ?> Pending
            </span>
        </div>
    </div>

    <!-- Notification/Alert Box -->
    <div id="alertBox" class="mb-6 hidden">
        <div id="alertContainer" class="px-4 py-3 rounded-lg shadow-lg relative">
            <span id="alertMessage"></span>
            <button onclick="hideAlert()" class="absolute top-0 bottom-0 right-0 px-4 py-3">
                <i class="ri-close-line"></i>
            </button>
        </div>
    </div>

    <!-- Info Box -->
    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900 dark:to-indigo-900 border border-blue-200 dark:border-blue-700 rounded-lg p-4 mb-6">
        <div class="flex items-start">
            <i class="ri-information-line text-blue-600 dark:text-blue-400 text-xl mr-3 mt-1"></i>
            <div>
                <h3 class="font-semibold text-gray-900 dark:text-white mb-1">About Procedure Assignments</h3>
                <p class="text-sm text-gray-700 dark:text-gray-300">
                    The COD (Coordinator of Dental) has assigned these logged procedures to you for review. Please review each procedure's information and decide whether to <strong>accept</strong> or <strong>deny</strong> the assignment. Once accepted, you can proceed with reviewing and approving the procedure.
                </p>
            </div>
        </div>
    </div>

    <!-- Search Filter Section -->
    <div class="bg-gradient-to-r from-white to-violet-50 dark:from-gray-800 dark:to-violet-900 rounded-lg shadow-lg border border-violet-200 dark:border-violet-700 p-6 mb-6">
        <h3 class="text-lg font-semibold bg-gradient-to-r from-violet-700 to-purple-700 bg-clip-text text-transparent dark:from-violet-300 dark:to-purple-300 mb-4">Search Pending Procedure Assignments</h3>
        <form method="GET" class="flex gap-4">
            <div class="flex-1">
                <input type="text" name="search" placeholder="Search by patient name, clinician, or procedure..." 
                       class="w-full px-4 py-2 border border-violet-300 dark:border-violet-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-violet-900 dark:text-white" 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <button type="submit" class="bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-700 hover:to-purple-700 text-white px-6 py-2 rounded-md shadow-lg transition-all duration-200">
                <i class="ri-search-line mr-2"></i>Search
            </button>
            <?php if (!empty($search)): ?>
                <a href="ci_procedure_assignments.php" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-md shadow-lg transition-all duration-200">
                    Clear
                </a>
            <?php endif; ?>
        </form>
    </div>
    
    <!-- Pending Procedure Assignments Table -->
    <div class="bg-gradient-to-br from-white to-violet-25 dark:from-gray-800 dark:to-violet-900 rounded-lg shadow-lg border border-violet-200 dark:border-violet-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-violet-200 dark:border-violet-700 bg-gradient-to-r from-violet-50 to-purple-50 dark:from-violet-900 dark:to-purple-900">
            <h3 class="text-lg font-semibold bg-gradient-to-r from-violet-700 to-purple-700 bg-clip-text text-transparent dark:from-violet-300 dark:to-purple-300">Pending Procedure Assignment Requests</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-violet-200 dark:divide-violet-700">
                <thead class="bg-gradient-to-r from-violet-100 to-purple-100 dark:from-violet-800 dark:to-purple-800">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">Patient Info</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">Procedure</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">Details</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">Clinician</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">Logged Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">Assigned Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">Assigned By</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-black dark:text-white uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-gradient-to-b from-white to-violet-25 dark:from-gray-800 dark:to-violet-900 divide-y divide-violet-200 dark:divide-violet-700">
                    <?php if (!empty($pendingProcedureAssignments)): ?>
                        <?php foreach ($pendingProcedureAssignments as $procedure): ?>
                            <tr class="hover:bg-gradient-to-r hover:from-violet-50 hover:to-purple-50 dark:hover:from-violet-900 dark:hover:to-purple-900" id="procedure-row-<?php echo $procedure['assignment_id']; ?>">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($procedure['patient_name']); ?>
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        Age: <?php echo htmlspecialchars($procedure['age'] ?? 'N/A'); ?> | 
                                        Sex: <?php echo htmlspecialchars($procedure['sex'] ?? 'N/A'); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                    <div class="max-w-xs" title="<?php echo htmlspecialchars($procedure['procedure_selected']); ?>">
                                        <?php echo htmlspecialchars(substr($procedure['procedure_selected'], 0, 40)) . (strlen($procedure['procedure_selected']) > 40 ? '...' : ''); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                    <?php if (!empty($procedure['procedure_details'])): ?>
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
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
                                    <div class="text-xs text-gray-500"><?php echo date('h:i A', strtotime($procedure['logged_at'])); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                    <?php echo date('M d, Y', strtotime($procedure['assigned_at'])); ?>
                                    <div class="text-xs text-gray-500"><?php echo date('h:i A', strtotime($procedure['assigned_at'])); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                    <?php echo htmlspecialchars($procedure['assigned_by_cod'] ?? 'COD'); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-center">
                                    <div class="flex justify-center space-x-2">
                                        <button onclick="openProcedureDecisionModal(<?php echo htmlspecialchars(json_encode($procedure)); ?>)" 
                                                class="bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white px-3 py-1 rounded-md text-xs shadow-lg transition-all duration-200">
                                            <i class="ri-checkbox-line mr-1"></i>Review
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="px-6 py-8 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <i class="ri-inbox-line text-5xl text-gray-400 dark:text-gray-600 mb-3"></i>
                                    <p class="text-gray-500 dark:text-gray-400 font-medium">No pending procedure assignment requests</p>
                                    <p class="text-sm text-gray-400 dark:text-gray-500 mt-1">All procedure assignments have been reviewed</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Procedure Decision Modal -->
<div id="procedureDecisionModal" class="fixed inset-0 bg-black bg-opacity-50 hidden justify-center items-center px-4 z-50">
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-lg w-full max-w-lg mx-auto modal-fade max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Review Procedure Assignment</h3>
            <button onclick="closeProcedureDecisionModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <i class="ri-close-line text-xl"></i>
            </button>
        </div>
        
        <div id="procedureDetails" class="mb-6 p-4 bg-gray-50 dark:bg-gray-700 rounded-md">
            <!-- Populated by JS -->
        </div>
        
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Notes (Optional)
            </label>
            <textarea id="procedureDecisionNotes" rows="3" 
                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-gray-700 dark:text-white"
                      placeholder="Add any notes about your decision..."></textarea>
        </div>
        
        <input type="hidden" id="procedureAssignmentId">
        
        <div class="flex justify-end space-x-3">
            <button type="button" onclick="closeProcedureDecisionModal()"
                    class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-gray-100 border border-gray-300 dark:border-gray-600 rounded-md">
                Cancel
            </button>
            <button type="button" onclick="handleProcedureDecision('rejected')"
                    class="bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white px-4 py-2 rounded-md text-sm shadow-lg transition-all duration-200">
                <i class="ri-close-line mr-1"></i>Deny Assignment
            </button>
            <button type="button" onclick="handleProcedureDecision('accepted')"
                    class="bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white px-4 py-2 rounded-md text-sm shadow-lg transition-all duration-200">
                <i class="ri-check-line mr-1"></i>Accept Assignment
            </button>
        </div>
    </div>
</div>

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

    // Alert functions
    function showAlert(message, type = 'success') {
        const alertBox = document.getElementById('alertBox');
        const alertContainer = document.getElementById('alertContainer');
        const alertMessage = document.getElementById('alertMessage');
        
        const colors = {
            success: 'bg-gradient-to-r from-green-100 to-emerald-100 dark:from-green-900 dark:to-emerald-900 border border-green-400 dark:border-green-600 text-green-700 dark:text-green-200',
            error: 'bg-gradient-to-r from-red-100 to-pink-100 dark:from-red-900 dark:to-pink-900 border border-red-400 dark:border-red-600 text-red-700 dark:text-red-200',
            info: 'bg-gradient-to-r from-blue-100 to-indigo-100 dark:from-blue-900 dark:to-indigo-900 border border-blue-400 dark:border-blue-600 text-blue-700 dark:text-blue-200'
        };
        
        alertContainer.className = (colors[type] || colors.info) + ' px-4 py-3 rounded-lg shadow-lg relative';
        alertMessage.textContent = message;
        alertBox.classList.remove('hidden');
        
        setTimeout(hideAlert, 5000);
    }

    function hideAlert() {
        document.getElementById('alertBox')?.classList.add('hidden');
    }

    // Procedure Decision Modal Functions
    function openProcedureDecisionModal(procedure) {
        document.getElementById('procedureAssignmentId').value = procedure.assignment_id;
        document.getElementById('procedureDetails').innerHTML = `
            <h4 class="font-semibold text-gray-900 dark:text-white mb-3">Procedure Information:</h4>
            <div class="grid grid-cols-2 gap-3 text-sm">
                <div><strong class="text-gray-700 dark:text-gray-300">Patient Name:</strong><br>${escapeHtml(procedure.patient_name)}</div>
                <div><strong class="text-gray-700 dark:text-gray-300">Age/Sex:</strong><br>${escapeHtml(procedure.age || 'N/A')} / ${escapeHtml(procedure.sex || 'N/A')}</div>
                <div class="col-span-2"><strong class="text-gray-700 dark:text-gray-300">Procedure:</strong><br>${escapeHtml(procedure.procedure_selected)}</div>
                ${procedure.procedure_details ? `<div class="col-span-2"><strong class="text-gray-700 dark:text-gray-300">Details:</strong><br>${escapeHtml(procedure.procedure_details)}</div>` : ''}
                ${procedure.chair_number ? `<div><strong class="text-gray-700 dark:text-gray-300">Chair:</strong><br>${escapeHtml(procedure.chair_number)}</div>` : ''}
                ${procedure.remarks ? `<div class="col-span-2"><strong class="text-gray-700 dark:text-gray-300">Remarks:</strong><br>${escapeHtml(procedure.remarks)}</div>` : ''}
                <div><strong class="text-gray-700 dark:text-gray-300">Clinician:</strong><br>${escapeHtml(procedure.clinician_name || 'Unknown')}</div>
                <div><strong class="text-gray-700 dark:text-gray-300">Logged Date:</strong><br>${new Date(procedure.logged_at).toLocaleDateString()}</div>
            </div>
            ${procedure.assignment_notes ? `
                <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-600">
                    <strong class="text-gray-700 dark:text-gray-300">Assignment Notes from COD:</strong>
                    <p class="text-sm mt-1 text-gray-600 dark:text-gray-400">${escapeHtml(procedure.assignment_notes)}</p>
                </div>
            ` : ''}
        `;
        
        document.getElementById('procedureDecisionModal').style.display = 'flex';
    }

    function closeProcedureDecisionModal() {
        document.getElementById('procedureDecisionModal').style.display = 'none';
        document.getElementById('procedureDecisionNotes').value = '';
    }

    function handleProcedureDecision(status) {
        const assignmentId = document.getElementById('procedureAssignmentId').value;
        const notes = document.getElementById('procedureDecisionNotes').value;
        
        // Disable buttons to prevent double submission
        const buttons = document.querySelectorAll('#procedureDecisionModal button');
        buttons.forEach(btn => btn.disabled = true);
        
        // Send AJAX request
        fetch('ajax_ci_procedure_assignments.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=update_procedure_assignment&assignment_id=${assignmentId}&status=${status}&notes=${encodeURIComponent(notes)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                closeProcedureDecisionModal();
                showAlert(data.message, 'success');
                
                // Remove the row from the table
                const row = document.getElementById(`procedure-row-${assignmentId}`);
                if (row) {
                    row.style.transition = 'opacity 0.3s';
                    row.style.opacity = '0';
                    setTimeout(() => {
                        row.remove();
                        updateProcedureCounter();
                        
                        // Check if table is empty and reload if needed
                        const tbody = document.querySelector('tbody');
                        const dataRows = tbody.querySelectorAll('tr[id^="procedure-row-"]');
                        if (dataRows.length === 0) {
                            setTimeout(() => location.reload(), 1000);
                        }
                    }, 300);
                }
            } else {
                showAlert(data.message || 'Error processing request', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Network error occurred. Please try again.', 'error');
        })
        .finally(() => {
            buttons.forEach(btn => btn.disabled = false);
        });
    }

    function updateProcedureCounter() {
        const tbody = document.querySelector('tbody');
        const rows = tbody.querySelectorAll('tr[id^="procedure-row-"]');
        const counter = document.querySelector('.bg-purple-100');
        if (counter) {
            const count = rows.length;
            counter.textContent = `${count} Pending`;
        }
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

<?php include 'includes/logout_modal.php'; ?>
</body>
</html>
