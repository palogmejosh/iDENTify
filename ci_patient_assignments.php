<?php
require_once 'config.php';
requireAuth();

$user = getCurrentUser();
$role = $user['role'] ?? '';
$fullName = $user['full_name'] ?? '';
$profilePicture = $user['profile_picture'] ?? null;

// Redirect if not Clinical Instructor or Admin
if (!in_array($role, ['Clinical Instructor', 'Admin'])) {
    header('Location: dashboard.php');
    exit;
}

// Get search parameter
$search = $_GET['search'] ?? '';

// Get pending assignments for this Clinical Instructor
$pendingAssignments = getCIPendingAssignments($user['id'], $search);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>iDENTify - Patient Assignments</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.0.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="dark-mode-override.css">
    <link rel="stylesheet" href="styles.css">
    <script>
        tailwind.config = { darkMode: 'class' }
    </script>
</head>

<body
    class="bg-gradient-to-br from-violet-25 via-white to-purple-25 dark:bg-gradient-to-br dark:from-violet-950 dark:via-gray-900 dark:to-purple-950 transition-colors duration-200">

    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main
        class="ml-64 mt-[64px] min-h-screen bg-gradient-to-br from-violet-50 to-purple-50 dark:from-gray-900 dark:to-violet-900 p-6">
        <div class="flex justify-between items-center mb-6">
            <h2
                class="text-2xl font-bold bg-gradient-to-r from-violet-700 to-purple-700 bg-clip-text text-transparent dark:from-violet-300 dark:to-purple-300">
                Patient Assignments</h2>
            <div class="flex items-center space-x-4">
                <span
                    class="text-sm bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200 px-3 py-1 rounded-full font-semibold">
                    <?php echo count($pendingAssignments); ?> Patient(s)
                </span>
            </div>
        </div>

        <div
            class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900 dark:to-indigo-900 border border-blue-200 dark:border-blue-700 rounded-lg p-4 mb-6">
            <div class="flex items-start">
                <i class="ri-information-line text-blue-600 dark:text-blue-400 text-xl mr-3 mt-1"></i>
                <div>
                    <h3 class="font-semibold text-gray-900 dark:text-white mb-1">About Patient Assignments</h3>
                    <p class="text-sm text-gray-700 dark:text-gray-300">The COD has assigned patients to you for review.
                        Accept or deny each assignment.</p>
                </div>
            </div>
        </div>

        <div
            class="bg-gradient-to-r from-white to-violet-50 dark:from-gray-800 dark:to-violet-900 rounded-lg shadow-lg border border-violet-200 dark:border-violet-700 p-6 mb-6">
            <h3
                class="text-lg font-semibold bg-gradient-to-r from-violet-700 to-purple-700 bg-clip-text text-transparent dark:from-violet-300 dark:to-purple-300 mb-4">
                Search Pending Patient Assignments</h3>
            <form method="GET" class="flex gap-4">
                <div class="flex-1">
                    <input type="text" name="search" placeholder="Search by patient name, email, or phone..."
                        class="w-full px-4 py-2 border border-violet-300 dark:border-violet-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-violet-900 dark:text-white"
                        value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <button type="submit"
                    class="bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-700 hover:to-purple-700 text-white px-6 py-2 rounded-md shadow-lg transition-all duration-200">
                    <i class="ri-search-line mr-2"></i>Search
                </button>
                <?php if (!empty($search)): ?>
                    <a href="ci_patient_assignments.php"
                        class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-md shadow-lg transition-all duration-200">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <div
            class="bg-gradient-to-br from-white to-violet-25 dark:from-gray-800 dark:to-violet-900 rounded-lg shadow-lg border border-violet-200 dark:border-violet-700 overflow-hidden">
            <div
                class="px-6 py-4 border-b border-violet-200 dark:border-violet-700 bg-gradient-to-r from-violet-50 to-purple-50 dark:from-violet-900 dark:to-purple-900">
                <h3
                    class="text-lg font-semibold bg-gradient-to-r from-violet-700 to-purple-700 bg-clip-text text-transparent dark:from-violet-300 dark:to-purple-300">
                    Pending Assignment Requests</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-violet-200 dark:divide-violet-700">
                    <thead
                        class="bg-gradient-to-r from-violet-100 to-purple-100 dark:from-violet-800 dark:to-purple-800">
                        <tr>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">
                                Patient Info</th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">
                                Created By</th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">
                                Procedure Details</th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">
                                Assigned Date</th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">
                                Assigned By</th>
                            <th
                                class="px-6 py-3 text-center text-xs font-medium text-black dark:text-white uppercase tracking-wider">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody
                        class="bg-gradient-to-b from-white to-violet-25 dark:from-gray-800 dark:to-violet-900 divide-y divide-violet-200 dark:divide-violet-700">
                        <?php if (!empty($pendingAssignments)): ?>
                            <?php foreach ($pendingAssignments as $patient): ?>
                                <tr class="hover:bg-gradient-to-r hover:from-violet-50 hover:to-purple-50 dark:hover:from-violet-900 dark:hover:to-purple-900"
                                    id="row-<?php echo $patient['assignment_id']; ?>">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                            <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            <?php echo htmlspecialchars($patient['email']); ?></div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            <?php echo htmlspecialchars($patient['phone']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                        <?php echo htmlspecialchars($patient['created_by_clinician'] ?? 'N/A'); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                        <?php if (!empty($patient['treatment_hint'])): ?>
                                            <span
                                                class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200"><?php echo htmlspecialchars($patient['treatment_hint']); ?></span>
                                        <?php else: ?>
                                            <span class="text-gray-400 dark:text-gray-500 text-xs">Not specified</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                        <?php echo date('M d, Y', strtotime($patient['assigned_at'])); ?>
                                        <div class="text-xs text-gray-500">
                                            <?php echo date('h:i A', strtotime($patient['assigned_at'])); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                        <?php echo htmlspecialchars($patient['assigned_by_cod'] ?? 'COD'); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-center">
                                        <div class="flex justify-center space-x-2">
                                            <button onclick="viewPatient(<?php echo $patient['id']; ?>)"
                                                class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-200"
                                                title="View Patient Details"><i class="ri-eye-line text-lg"></i></button>
                                            <button
                                                onclick="openDecisionModal(<?php echo htmlspecialchars(json_encode($patient)); ?>)"
                                                class="bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white px-3 py-1 rounded-md text-xs shadow-lg transition-all duration-200"><i
                                                    class="ri-checkbox-line mr-1"></i>Review</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center">
                                    <div class="flex flex-col items-center justify-center">
                                        <i class="ri-inbox-line text-5xl text-gray-400 dark:text-gray-600 mb-3"></i>
                                        <p class="text-gray-500 dark:text-gray-400 font-medium">No pending assignment
                                            requests</p>
                                        <p class="text-sm text-gray-400 dark:text-gray-500 mt-1">All assignments have been
                                            reviewed</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Decision Modal -->
    <div id="decisionModal" class="fixed inset-0 bg-black bg-opacity-50 hidden justify-center items-center px-4 z-50">
        <div
            class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-lg w-full max-w-lg mx-auto modal-fade max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Review Patient Assignment</h3>
                <button onclick="closeDecisionModal()"
                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"><i
                        class="ri-close-line text-xl"></i></button>
            </div>
            <div id="patientDetails" class="mb-6 p-4 bg-gray-50 dark:bg-gray-700 rounded-md"></div>
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Notes (Optional)</label>
                <textarea id="decisionNotes" rows="3"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-gray-700 dark:text-white"
                    placeholder="Add any notes about your decision..."></textarea>
            </div>
            <input type="hidden" id="assignmentId">
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeDecisionModal()"
                    class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-gray-100 border border-gray-300 dark:border-gray-600 rounded-md">Cancel</button>
                <button type="button" onclick="handleDecision('rejected')"
                    class="bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white px-4 py-2 rounded-md text-sm shadow-lg transition-all duration-200"><i
                        class="ri-close-line mr-1"></i>Deny Assignment</button>
                <button type="button" onclick="handleDecision('accepted')"
                    class="bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white px-4 py-2 rounded-md text-sm shadow-lg transition-all duration-200"><i
                        class="ri-check-line mr-1"></i>Accept Assignment</button>
            </div>
        </div>
    </div>

    <script>
        // Dark mode toggle
        const darkModeToggle = document.getElementById('darkModeToggle');
        const html = document.documentElement;
        const darkMode = localStorage.getItem('darkMode') === 'true';
        if (darkMode) { html.classList.add('dark'); }
        darkModeToggle?.addEventListener('click', () => {
            html.classList.toggle('dark');
            const isDark = html.classList.contains('dark');
            localStorage.setItem('darkMode', isDark);
        });

        // Alerts
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
            alertBox?.classList.remove('hidden');
            setTimeout(() => alertBox?.classList.add('hidden'), 5000);
        }

        function hideAlert() { document.getElementById('alertBox')?.classList.add('hidden'); }

        function openDecisionModal(patient) {
            document.getElementById('assignmentId').value = patient.assignment_id;
            document.getElementById('patientDetails').innerHTML = `
            <h4 class="font-semibold text-gray-900 dark:text-white mb-3">Patient Information:</h4>
            <div class="grid grid-cols-2 gap-3 text-sm">
                <div><strong class="text-gray-700 dark:text-gray-300">Name:</strong><br>${escapeHtml(patient.first_name + ' ' + patient.last_name)}</div>
                <div><strong class="text-gray-700 dark:text-gray-300">Age:</strong><br>${escapeHtml(patient.age)}</div>
                <div><strong class="text-gray-700 dark:text-gray-300">Email:</strong><br>${escapeHtml(patient.email)}</div>
                <div><strong class="text-gray-700 dark:text-gray-300">Phone:</strong><br>${escapeHtml(patient.phone)}</div>
                <div><strong class="text-gray-700 dark:text-gray-300">Created By:</strong><br>${escapeHtml(patient.created_by_clinician || 'Unknown')}</div>
                <div><strong class="text-gray-700 dark:text-gray-300">Procedure Details:</strong><br>${escapeHtml(patient.treatment_hint || 'Not specified')}</div>
            </div>
            ${patient.assignment_notes ? `
                <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-600">
                    <strong class="text-gray-700 dark:text-gray-300">Assignment Notes:</strong>
                    <p class="text-sm mt-1 text-gray-600 dark:text-gray-400">${escapeHtml(patient.assignment_notes)}</p>
                </div>
            ` : ''}
        `;
            document.getElementById('decisionModal').style.display = 'flex';
        }

        function closeDecisionModal() { document.getElementById('decisionModal').style.display = 'none'; document.getElementById('decisionNotes').value = ''; }

        function handleDecision(status) {
            const assignmentId = document.getElementById('assignmentId').value;
            const notes = document.getElementById('decisionNotes').value;
            const buttons = document.querySelectorAll('#decisionModal button');
            buttons.forEach(btn => btn.disabled = true);
            fetch('ajax_ci_assignments.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `action=update_assignment&assignment_id=${assignmentId}&status=${status}&notes=${encodeURIComponent(notes)}` })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        closeDecisionModal();
                        showAlert('Patient rejected. Assignment returned to COD as "Rejected".', 'success');
                        const row = document.getElementById(`row-${assignmentId}`);
                        if (row) { row.style.transition = 'opacity 0.3s'; row.style.opacity = '0'; setTimeout(() => row.remove(), 300); }
                        updatePendingBadge();
                    } else {
                        showAlert(data.message || 'Error processing request', 'error');
                    }
                })
                .catch(err => { console.error(err); showAlert('Network error occurred. Please try again.', 'error'); })
                .finally(() => buttons.forEach(btn => btn.disabled = false));
        }

        function updatePendingBadge() {
            const rows = document.querySelectorAll('tr[id^="row-"]').length;
            const badge = document.querySelector('.text-sm.bg-yellow-100');
            if (badge) badge.textContent = `${rows} Patient(s)`;
        }

        function viewPatient(id) { window.location.href = `view_patient.php?id=${id}`; }

        function escapeHtml(s) { if (!s) return ''; return s.toString().replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;'); }
    </script>

    <?php include 'includes/logout_modal.php'; ?>
</body>

</html>