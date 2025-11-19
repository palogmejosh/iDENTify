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

// Redirect to patients.php as this functionality is now integrated there
header('Location: patients.php?info=approvals_moved');
exit;

$message = '';

// Handle approval actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'approve_patient') {
    $approvalStatus = $_POST['approval_status'];
    $assignmentId = $_POST['assignment_id'];
    $approvalNotes = $_POST['approval_notes'] ?? '';
    $ciId = $user['id'];

    $result = updatePatientApproval($assignmentId, $ciId, $approvalStatus, $approvalNotes);
    
    if ($result) {
        header("Location: ci_approvals.php?approval_updated=1");
        exit;
    } else {
        $message = 'Error updating patient approval. Please try again.';
    }
}

// Handle success messages
if (isset($_GET['approval_updated']) && $_GET['approval_updated'] == 1) {
    $message = 'Patient approval updated successfully!';
}

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? 'all';

// Get assigned patients for this Clinical Instructor
$assignedPatients = getClinicalInstructorPatients($user['id'], $search, $statusFilter);

$fullName = $user ? $user['full_name'] : 'Unknown User';
$profilePicture = $user['profile_picture'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>iDENTify - Patient Approvals</title>
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
    <!-- Header -->
    <header class="bg-gradient-to-r from-violet-50 to-purple-50 dark:bg-gradient-to-r dark:from-violet-900 dark:to-purple-900 shadow-lg border-b-2 border-violet-200 dark:border-violet-700">
        <div class="flex items-center justify-between px-6 py-4">
            <h1 class="text-xl font-bold bg-gradient-to-r from-violet-600 via-purple-600 to-indigo-600 bg-clip-text text-transparent">iDENTify</h1>
            <div class="flex items-center space-x-4">
                <!-- Profile Picture -->
                <a href="profile.php" class="flex items-center space-x-3 hover:opacity-80 transition-opacity" title="Go to My Profile">
                    <?php if ($profilePicture && file_exists(__DIR__ . '/' . $profilePicture)): ?>
                        <img src="<?php echo htmlspecialchars($profilePicture); ?>" 
                             alt="Profile Picture" 
                             class="header-profile-pic">
                    <?php else: ?>
                        <div class="header-profile-placeholder">
                            <i class="ri-user-3-line text-sm"></i>
                        </div>
                    <?php endif; ?>
                    <span class="text-sm text-gray-900 dark:text-white">
                        <?php echo htmlspecialchars($fullName . ' (' . $role . ')'); ?>
                    </span>
                </a>
                <button id="darkModeToggle" class="p-2 rounded-md text-gray-700 dark:text-gray-300 hover:bg-violet-100 dark:hover:bg-violet-700">
                    <i class="ri-moon-line dark:hidden"></i>
                    <i class="ri-sun-line hidden dark:inline"></i>
                </button>
                <button onclick="showLogoutModal()" class="text-sm text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 flex items-center">
                    <i class="ri-logout-line mr-1"></i>Logout
                </a>
            </div>
        </div>
    </header>

    <div class="flex">
        <!-- Sidebar -->
        <nav class="w-64 bg-gradient-to-b from-white to-violet-50 dark:bg-gradient-to-b dark:from-gray-800 dark:to-violet-900 shadow-lg h-screen sidebar border-r-2 border-violet-100 dark:border-violet-800">
            <div class="p-6">
                <ul class="space-y-2">
                    <li>
                        <a href="dashboard.php" class="sidebar-link flex items-center px-4 py-2 text-gray-900 dark:text-white rounded-md hover:bg-violet-100 dark:hover:bg-violet-800">
                            <i class="ri-dashboard-line mr-3"></i>Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="patients.php" class="sidebar-link flex items-center px-4 py-2 text-gray-900 dark:text-white rounded-md hover:bg-violet-100 dark:hover:bg-violet-800">
                            <i class="ri-user-line mr-3"></i>Patients
                        </a>
                    </li>
                    <li>
                        <a href="ci_patient_assignments.php" class="sidebar-link flex items-center px-4 py-2 text-gray-900 dark:text-white rounded-md hover:bg-violet-100 dark:hover:bg-violet-800">
                            <i class="ri-user-add-line mr-3"></i>Patient Assignments
                        </a>
                    </li>
                    <li>
                        <a href="ci_approvals.php" class="sidebar-link sidebar-active flex items-center px-4 py-2 bg-gradient-to-r from-violet-100 to-purple-100 dark:from-violet-800 dark:to-purple-800 text-black dark:text-white rounded-md hover:from-violet-200 hover:to-purple-200 dark:hover:from-violet-700 dark:hover:to-purple-700 border-l-4 border-violet-500">
                            <i class="ri-check-line mr-3"></i>Patient Approvals
                        </a>
                    </li>
                    <li>
                        <a href="profile.php" class="sidebar-link flex items-center px-4 py-2 text-gray-900 dark:text-white rounded-md hover:bg-violet-100 dark:hover:bg-violet-800">
                            <i class="ri-user-3-line mr-3"></i>My Profile
                        </a>
                    </li>
                    <li>
                        <a href="settings.php" class="sidebar-link flex items-center px-4 py-2 text-gray-900 dark:text-white rounded-md hover:bg-violet-100 dark:hover:bg-violet-800">
                            <i class="ri-settings-3-line mr-3"></i>Settings
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="flex-1 bg-gradient-to-br from-violet-50 to-purple-50 dark:from-gray-900 dark:to-violet-900 p-6 main-content">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold bg-gradient-to-r from-violet-700 to-purple-700 bg-clip-text text-transparent dark:from-violet-300 dark:to-purple-300">Patient Approval Management</h2>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-700 dark:text-gray-300">
                        Total Assigned: <?php echo count($assignedPatients); ?>
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
                <h3 class="text-lg font-semibold bg-gradient-to-r from-violet-700 to-purple-700 bg-clip-text text-transparent dark:from-violet-300 dark:to-purple-300 mb-4">Search & Filter Assigned Patients</h3>
                <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-2">Patient Search</label>
                        <input type="text" name="search" placeholder="Search by name, email, phone..." 
                               class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-violet-900 dark:text-white" 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-2">Approval Status</label>
                        <select name="status" class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-violet-900 dark:text-white">
                            <option value="all" <?php echo ($statusFilter === 'all') ? 'selected' : ''; ?>>All Status</option>
                            <option value="approval_pending" <?php echo ($statusFilter === 'approval_pending') ? 'selected' : ''; ?>>Pending Approval</option>
                            <option value="approved" <?php echo ($statusFilter === 'approved') ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo ($statusFilter === 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="w-full bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-700 hover:to-purple-700 text-white px-4 py-2 rounded-md shadow-lg transition-all duration-200">
                            <i class="ri-search-line mr-2"></i>Search
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Assigned Patients Table -->
            <div class="bg-gradient-to-br from-white to-violet-25 dark:from-gray-800 dark:to-violet-900 rounded-lg shadow-lg border border-violet-200 dark:border-violet-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-violet-200 dark:border-violet-700 bg-gradient-to-r from-violet-50 to-purple-50 dark:from-violet-900 dark:to-purple-900">
                    <h3 class="text-lg font-semibold bg-gradient-to-r from-violet-700 to-purple-700 bg-clip-text text-transparent dark:from-violet-300 dark:to-purple-300">Assigned Patient Records</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-violet-200 dark:divide-violet-700">
                        <thead class="bg-gradient-to-r from-violet-100 to-purple-100 dark:from-violet-800 dark:to-purple-800">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">Patient Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">Created By</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">Assigned Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">Patient Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">Approval Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-gradient-to-b from-white to-violet-25 dark:from-gray-800 dark:to-violet-900 divide-y divide-violet-200 dark:divide-violet-700">
                            <?php if (!empty($assignedPatients)): ?>
                                <?php foreach ($assignedPatients as $patient): ?>
                                    <?php 
                                    // Debug: Verify assignment_id exists
                                    if (!isset($patient['assignment_id'])) {
                                        error_log("WARNING: Patient {$patient['id']} missing assignment_id");
                                    }
                                    ?>
                                    <tr class="hover:bg-gradient-to-r hover:from-violet-50 hover:to-purple-50 dark:hover:from-violet-900 dark:hover:to-purple-900">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                            <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($patient['email']); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                            <?php echo htmlspecialchars($patient['created_by_clinician'] ?? 'N/A'); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                            <?php echo date('M d, Y', strtotime($patient['assigned_at'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                                <?php 
                                                echo $patient['patient_status'] === 'Approved' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 
                                                     ($patient['patient_status'] === 'Pending' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : 
                                                      'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'); 
                                                ?>">
                                                <?php echo htmlspecialchars($patient['patient_status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                                <?php 
                                                $approvalStatus = $patient['approval_status'] ?? 'pending';
                                                echo $approvalStatus === 'approved' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 
                                                     ($approvalStatus === 'rejected' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : 
                                                      'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'); 
                                                ?>">
                                                <?php echo htmlspecialchars(ucfirst($approvalStatus)); ?>
                                            </span>
                                            <?php if (!empty($patient['approved_at'])): ?>
                                                <div class="text-xs text-gray-400">
                                                    <?php echo date('M d, Y', strtotime($patient['approved_at'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex space-x-2">
                                                <a href="view_patient.php?id=<?php echo $patient['id']; ?>" 
                                                   class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-200" title="View Patient">
                                                    <i class="ri-eye-line"></i>
                                                </a>
                                                
                                                <?php if (($patient['approval_status'] ?? 'pending') === 'pending'): ?>
                                                    <button onclick='openApprovalModal(<?php echo json_encode($patient, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' 
                                                            class="text-green-600 dark:text-green-400 hover:text-green-900 dark:hover:text-green-200" title="Review & Approve">
                                                        <i class="ri-check-line"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button onclick='openApprovalModal(<?php echo json_encode($patient, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' 
                                                            class="text-orange-600 dark:text-orange-400 hover:text-orange-900 dark:hover:text-orange-200" title="Update Approval">
                                                        <i class="ri-edit-line"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                        No assigned patients found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Approval Modal -->
    <div id="approvalModal" class="fixed inset-0 bg-black bg-opacity-50 hidden justify-center items-center px-4 z-50">
        <div class="bg-gradient-to-br from-violet-50 to-purple-50 dark:from-gray-800 dark:to-violet-900 p-5 rounded-lg shadow-xl border border-violet-200 dark:border-violet-700 w-full max-w-md mx-auto modal-fade max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold bg-gradient-to-r from-violet-700 to-purple-700 bg-clip-text text-transparent dark:from-violet-300 dark:to-purple-300">Patient Approval Review</h3>
                <button onclick="closeApprovalModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>
            <form id="approvalForm" method="POST">
                <input type="hidden" name="action" value="approve_patient">
                <input type="hidden" id="approvalAssignmentId" name="assignment_id">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-2">Patient Information</label>
                        <div id="patientInfo" class="p-3 bg-violet-50 dark:bg-violet-900/20 border border-violet-200 dark:border-violet-700 rounded-md text-sm text-gray-900 dark:text-white">
                            <!-- Populated by JS -->
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-2">
                            Approval Decision <span class="text-red-500">*</span>
                        </label>
                        <select name="approval_status" id="approvalStatus" required
                                class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md
                                       focus:outline-none focus:ring-2 focus:ring-violet-500
                                       dark:bg-violet-900 dark:text-white text-sm">
                            <option value="">Select Decision</option>
                            <option value="approved">Approve Patient</option>
                            <option value="rejected">Reject Patient</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-2">
                            Approval Notes
                        </label>
                        <textarea name="approval_notes" rows="4" 
                                  class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md
                                         focus:outline-none focus:ring-2 focus:ring-violet-500
                                         dark:bg-violet-900 dark:text-white text-sm"
                                  placeholder="Add notes about your approval decision..."></textarea>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-2 mt-6">
                    <button type="button" onclick="closeApprovalModal()"
                            class="px-3 py-1.5 text-sm text-gray-600 dark:text-gray-300
                                   hover:text-gray-800 dark:hover:text-gray-100">
                        Cancel
                    </button>
                    <button type="submit"
                            class="bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-700 hover:to-purple-700 text-white px-4 py-2 rounded-md text-sm shadow-lg transition-all duration-200">
                        <i class="ri-check-line mr-1"></i>Submit Decision
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
        function openApprovalModal(patient) {
            console.log('Opening approval modal for patient:', patient);
            
            // Check if required fields exist
            if (!patient.assignment_id) {
                console.error('Missing assignment_id for patient:', patient);
                alert('Error: Missing assignment information. Please refresh the page and try again.');
                return;
            }
            
            document.getElementById('approvalAssignmentId').value = patient.assignment_id;
            console.log('Set assignment_id:', patient.assignment_id);
            
            document.getElementById('patientInfo').innerHTML = `
                <div><strong>Name:</strong> ${escapeHtml(patient.first_name + ' ' + patient.last_name)}</div>
                <div><strong>Email:</strong> ${escapeHtml(patient.email)}</div>
                <div><strong>Phone:</strong> ${escapeHtml(patient.phone)}</div>
                <div><strong>Age:</strong> ${escapeHtml(patient.age)}</div>
                <div><strong>Created by:</strong> ${escapeHtml(patient.created_by_clinician || 'Unknown')}</div>
                <div><strong>Current Status:</strong> ${escapeHtml(patient.patient_status)}</div>
            `;
            
            // Pre-select current approval status if exists
            if (patient.approval_status && patient.approval_status !== 'pending') {
                document.getElementById('approvalStatus').value = patient.approval_status;
            } else {
                // Reset to default if pending or null
                document.getElementById('approvalStatus').value = '';
            }
            
            document.getElementById('approvalModal').style.display = 'flex';
            console.log('Modal opened successfully');
        }

        function closeApprovalModal() {
            document.getElementById('approvalModal').style.display = 'none';
            document.getElementById('approvalForm').reset();
        }

        function escapeHtml(unsafe) {
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
        
        // Form submission validation
        document.getElementById('approvalForm')?.addEventListener('submit', function(e) {
            const approvalStatus = document.getElementById('approvalStatus').value;
            const assignmentId = document.getElementById('approvalAssignmentId').value;
            
            console.log('Form submitting with:', {
                approvalStatus: approvalStatus,
                assignmentId: assignmentId
            });
            
            if (!approvalStatus) {
                e.preventDefault();
                alert('Please select an approval decision.');
                return false;
            }
            
            if (!assignmentId) {
                e.preventDefault();
                alert('Error: Missing assignment information. Please refresh the page and try again.');
                return false;
            }
            
            // Confirm the action
            const action = approvalStatus === 'approved' ? 'approve' : 'reject';
            if (!confirm(`Are you sure you want to ${action} this patient?`)) {
                e.preventDefault();
                return false;
            }
            
            console.log('Form validation passed, submitting...');
            return true;
        });
    </script>

    <?php include 'includes/logout_modal.php'; ?>
</body>
</html>
