<?php
require_once 'config.php';
requireAuth();

$user = getCurrentUser();
$users = $_SESSION['users'] ?? [];
$message = '';

/* ---------- DELETE USER ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && $user['role'] === 'Admin') {
    $id = (int) $_POST['user_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: users.php?deleted=1");
        exit;
    } catch (PDOException $e) {
        $message = 'Delete failed: ' . $e->getMessage();
    }
}

/* ---------- EDIT USER ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id = (int) $_POST['user_id'];
    $fullName = $_POST['full_name'];
    $email = $_POST['email'];
    $role = $_POST['role'] ?? '';
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $password = $_POST['password'] ?? '';
    $accountStatus = $_POST['account_status'] ?? 'active';

    $fields = [$fullName, $email, $role, $isActive, $accountStatus, $id];
    $sql = "UPDATE users SET full_name = ?, email = ?, role = ?, is_active = ?, account_status = ?";
    if (!empty($password)) {
        $sql .= ", password = ?";
        $fields[] = password_hash($password, PASSWORD_DEFAULT);
    }
    $sql .= ", updated_at = NOW() WHERE id = ?";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($fields);
        header("Location: users.php?updated=1");
        exit;
    } catch (PDOException $e) {
        $message = 'Update failed: ' . $e->getMessage();
    }
}

/* ---------- ADD USER ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $result = addUser($_POST['username'], $_POST['fullName'], $_POST['email'], $_POST['password'], $_POST['role']);
    if ($result) {
        header("Location: users.php?added=1");
        exit;
    } else {
        $message = 'Failed to add user. Please try again.';
    }
}

// Handle success messages
if (isset($_GET['added']) && $_GET['added'] == 1) {
    $message = 'User added successfully!';
} elseif (isset($_GET['updated']) && $_GET['updated'] == 1) {
    $message = 'User updated successfully!';
} elseif (isset($_GET['deleted']) && $_GET['deleted'] == 1) {
    $message = 'User deleted successfully!';
}

// Get users from database with enhanced filtering
$roleFilter = $_GET['role'] ?? 'all';
$statusFilter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';
$filteredUsers = getUsers($roleFilter, $search, $statusFilter);

function getRoleBadgeClass($role)
{
    switch ($role) {
        case 'Admin':
            return 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200';
        case 'Clinician':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
        case 'Clinical Instructor':
            return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
        case 'COD':
            return 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';
    }
}

function getAccountStatusBadgeClass($accountStatus)
{
    return $accountStatus === 'active'
        ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
        : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
}

function getOnlineStatusBadgeClass($onlineStatus)
{
    return $onlineStatus === 'online'
        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200'
        : 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';
}

// Helper function to get user display name
function getUserDisplayName($user)
{
    return $user['full_name'] ?? $user['username'] ?? 'User';
}

$userDisplayName = getUserDisplayName($user);
$fullName = $user ? $user['full_name'] : 'Unknown User';
$profilePicture = $user['profile_picture'] ?? null;
$role = $user['role'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>iDENTify - System Users</title>
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

    <div class="flex ml-64 mt-16">

        <!-- Main Content -->
        <main
            class="flex-1 bg-gradient-to-br from-violet-50 to-purple-50 dark:from-gray-900 dark:to-violet-900 p-6 main-content min-h-screen">
            <div class="flex justify-between items-center mb-6">
                <h2
                    class="text-2xl font-bold bg-gradient-to-r from-violet-700 to-purple-700 bg-clip-text text-transparent dark:from-violet-300 dark:to-purple-300">
                    System Users</h2>
                <?php if ($role === 'Admin'): ?>
                    <button onclick="document.getElementById('addUserModal').style.display='flex'"
                        class="bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-700 hover:to-purple-700 text-white px-4 py-2 rounded-md flex items-center shadow-lg transition-all duration-200">
                        <i class="ri-add-line mr-2"></i>Add New User
                    </button>
                <?php endif; ?>
            </div>

            <!-- Notification/Alert Box -->
            <?php if ($message): ?>
                <div id="alertBox" class="mb-6">
                    <div
                        class="bg-gradient-to-r from-green-100 to-emerald-100 dark:from-green-900 dark:to-emerald-900 border border-green-400 dark:border-green-600 text-green-700 dark:text-green-200 px-4 py-3 rounded-lg shadow-lg relative">
                        <span id="alertMessage"><?php echo htmlspecialchars($message); ?></span>
                        <button onclick="hideAlert()"
                            class="absolute top-0 bottom-0 right-0 px-4 py-3 text-green-600 hover:text-green-800 dark:text-green-300 dark:hover:text-green-100">
                            <i class="ri-close-line"></i>
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Enhanced Filter Section -->
            <div
                class="bg-gradient-to-r from-white to-violet-50 dark:from-gray-800 dark:to-violet-900 rounded-lg shadow-lg border border-violet-200 dark:border-violet-700 p-6 mb-6">
                <h3
                    class="text-lg font-semibold bg-gradient-to-r from-violet-700 to-purple-700 bg-clip-text text-transparent dark:from-violet-300 dark:to-purple-300 mb-4">
                    Search & Filter Users</h3>
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-2">Search Users</label>
                        <input type="text" name="search" placeholder="Search by name, email, username..."
                            class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-violet-900 dark:text-white"
                            value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-2">Role</label>
                        <select name="role"
                            class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-violet-900 dark:text-white">
                            <option value="all" <?php echo ($roleFilter === 'all') ? 'selected' : ''; ?>>All Roles
                            </option>
                            <option value="Admin" <?php echo ($roleFilter === 'Admin') ? 'selected' : ''; ?>>Administrator
                            </option>
                            <option value="Clinician" <?php echo ($roleFilter === 'Clinician') ? 'selected' : ''; ?>>
                                Clinician</option>
                            <option value="Clinical Instructor" <?php echo ($roleFilter === 'Clinical Instructor') ? 'selected' : ''; ?>>Clinical Instructor</option>
                            <option value="COD" <?php echo ($roleFilter === 'COD') ? 'selected' : ''; ?>>COD (Coordinator
                                of Dental)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-2">Account
                            Status</label>
                        <select name="status"
                            class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-violet-900 dark:text-white">
                            <option value="all" <?php echo ($statusFilter === 'all') ? 'selected' : ''; ?>>All Status
                            </option>
                            <option value="active" <?php echo ($statusFilter === 'active') ? 'selected' : ''; ?>>Active
                            </option>
                            <option value="inactive" <?php echo ($statusFilter === 'inactive') ? 'selected' : ''; ?>>
                                Inactive</option>
                        </select>
                    </div>
                    <div class="flex space-x-2">
                        <button type="submit"
                            class="flex-1 bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-700 hover:to-purple-700 text-white px-4 py-2 rounded-md transition-all duration-200 shadow-lg">
                            <i class="ri-search-line mr-2"></i>Search
                        </button>
                        <a href="users.php"
                            class="bg-gradient-to-r from-gray-500 to-slate-500 hover:from-gray-600 hover:to-slate-600 text-white px-4 py-2 rounded-md flex items-center justify-center transition-all duration-200 shadow-lg">
                            <i class="ri-refresh-line"></i>
                        </a>
                    </div>
                </form>
            </div>

            <!-- Users Table -->
            <div
                class="bg-gradient-to-br from-white to-violet-25 dark:from-gray-800 dark:to-violet-900 rounded-lg shadow-lg border border-violet-200 dark:border-violet-700 overflow-hidden">
                <div
                    class="px-6 py-4 border-b border-violet-200 dark:border-violet-700 bg-gradient-to-r from-violet-50 to-purple-50 dark:from-violet-900 dark:to-purple-900">
                    <h3
                        class="text-lg font-semibold bg-gradient-to-r from-violet-700 to-purple-700 bg-clip-text text-transparent dark:from-violet-300 dark:to-purple-300">
                        User Records</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-violet-200 dark:divide-violet-700">
                        <thead
                            class="bg-gradient-to-r from-violet-100 to-purple-100 dark:from-violet-800 dark:to-purple-800">
                            <tr>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-900 dark:text-white uppercase tracking-wider">
                                    Username</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-900 dark:text-white uppercase tracking-wider">
                                    Full Name</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-900 dark:text-white uppercase tracking-wider">
                                    Email</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-900 dark:text-white uppercase tracking-wider">
                                    Role</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-900 dark:text-white uppercase tracking-wider">
                                    Account Status</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-900 dark:text-white uppercase tracking-wider">
                                    Online Status</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-900 dark:text-white uppercase tracking-wider">
                                    Created</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-900 dark:text-white uppercase tracking-wider">
                                    Actions</th>
                            </tr>
                        </thead>
                        <tbody id="usersTableBody"
                            class="bg-gradient-to-b from-white to-violet-25 dark:from-gray-800 dark:to-violet-900 divide-y divide-violet-200 dark:divide-violet-700">
                            <?php if (!empty($filteredUsers)): ?>
                                <?php foreach ($filteredUsers as $userData): ?>
                                    <tr
                                        class="hover:bg-gradient-to-r hover:from-violet-50 hover:to-purple-50 dark:hover:from-violet-900 dark:hover:to-purple-900">
                                        <td
                                            class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                            <?= htmlspecialchars($userData['username']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                            <?= htmlspecialchars($userData['full_name']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                            <?= htmlspecialchars($userData['email']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span
                                                class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= getRoleBadgeClass($userData['role']) ?>">
                                                <?= htmlspecialchars($userData['role']) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span
                                                class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= getAccountStatusBadgeClass($userData['account_status']) ?>">
                                                <?= htmlspecialchars(ucfirst($userData['account_status'])) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span
                                                class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= getOnlineStatusBadgeClass($userData['online_status']) ?>">
                                                <i
                                                    class="ri-<?= $userData['online_status'] === 'online' ? 'checkbox-blank-circle-fill' : 'checkbox-blank-circle-line' ?> mr-1"></i>
                                                <?= htmlspecialchars(ucfirst($userData['online_status'])) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                            <?= isset($userData['created_at']) ? date('M d, Y', strtotime($userData['created_at'])) : 'N/A' ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex space-x-2">
                                                <button onclick="openViewModal(<?= htmlspecialchars(json_encode($userData)) ?>)"
                                                    class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-200"
                                                    title="View">
                                                    <i class="ri-eye-line"></i>
                                                </button>

                                                <?php if ($role === 'Admin'): ?>
                                                    <button onclick="openEditModal(<?= htmlspecialchars(json_encode($userData)) ?>)"
                                                        class="text-green-600 dark:text-green-400 hover:text-green-900 dark:hover:text-green-200"
                                                        title="Edit">
                                                        <i class="ri-edit-line"></i>
                                                    </button>

                                                    <button
                                                        onclick="deleteUser(<?= $userData['id'] ?>, '<?= htmlspecialchars($userData['username']) ?>')"
                                                        class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-200"
                                                        title="Delete">
                                                        <i class="ri-delete-bin-line"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="px-6 py-4 text-center text-gray-700 dark:text-gray-300">
                                        No users found matching your criteria.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Floating Add Button (Mobile) -->
            <?php if ($role === 'Admin'): ?>
                <div class="fixed bottom-6 right-6 md:hidden">
                    <button onclick="document.getElementById('addUserModal').style.display='flex'"
                        class="bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-700 hover:to-purple-700 text-white p-4 rounded-full shadow-lg transition-all duration-200">
                        <i class="ri-add-line text-xl"></i>
                    </button>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Add User Modal -->
    <div id="addUserModal" class="fixed inset-0 bg-black bg-opacity-50 hidden justify-center items-center px-4 z-50">
        <div
            class="bg-gradient-to-br from-white to-violet-50 dark:from-gray-800 dark:to-violet-900 p-5 rounded-lg shadow-xl border border-violet-200 dark:border-violet-700 w-full max-w-md mx-auto modal-fade max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3
                    class="text-lg font-semibold bg-gradient-to-r from-violet-700 to-purple-700 bg-clip-text text-transparent dark:from-violet-300 dark:to-purple-300">
                    Add New User</h3>
                <button onclick="closeAddModal()"
                    class="text-violet-400 hover:text-gray-700 dark:text-gray-300 dark:hover:text-violet-300">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>
            <form id="addUserForm" method="POST">
                <input type="hidden" name="action" value="add">
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">
                            Username <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="username" required class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md
                                      focus:outline-none focus:ring-2 focus:ring-violet-500
                                      dark:bg-violet-900 dark:text-white text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">
                            Full Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="fullName" required class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md
                                      focus:outline-none focus:ring-2 focus:ring-violet-500
                                      dark:bg-violet-900 dark:text-white text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">
                            Email <span class="text-red-500">*</span>
                        </label>
                        <input type="email" name="email" required class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md
                                      focus:outline-none focus:ring-2 focus:ring-violet-500
                                      dark:bg-violet-900 dark:text-white text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">
                            Password <span class="text-red-500">*</span>
                        </label>
                        <input type="password" name="password" required class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md
                                      focus:outline-none focus:ring-2 focus:ring-violet-500
                                      dark:bg-violet-900 dark:text-white text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">
                            Role <span class="text-red-500">*</span>
                        </label>
                        <select name="role" required class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md
                                       focus:outline-none focus:ring-2 focus:ring-violet-500
                                       dark:bg-violet-900 dark:text-white text-sm">
                            <option value="">Select Role</option>
                            <option value="Clinician">Clinician</option>
                            <option value="Clinical Instructor">Clinical Instructor</option>
                            <option value="COD">COD (Coordinator of Dental)</option>
                            <option value="Admin">Administrator</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end space-x-2 mt-5">
                    <button type="button" onclick="closeAddModal()"
                        class="px-3 py-1.5 text-sm text-gray-600 dark:text-gray-300
                                   hover:text-gray-800 dark:hover:text-gray-100 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-800">
                        Cancel
                    </button>
                    <button type="submit"
                        class="bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-700 hover:to-purple-700 text-white px-4 py-1.5 rounded-md text-sm shadow-lg transition-all duration-200">
                        Add User
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editUserModal" class="fixed inset-0 bg-black bg-opacity-50 hidden justify-center items-center px-4 z-50">
        <div
            class="bg-gradient-to-br from-white to-violet-50 dark:from-gray-800 dark:to-violet-900 p-5 rounded-lg shadow-xl border border-violet-200 dark:border-violet-700 w-full max-w-md mx-auto modal-fade max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3
                    class="text-lg font-semibold bg-gradient-to-r from-violet-700 to-purple-700 bg-clip-text text-transparent dark:from-violet-300 dark:to-purple-300">
                    Edit User</h3>
                <button onclick="closeEditModal()"
                    class="text-violet-400 hover:text-gray-700 dark:text-gray-300 dark:hover:text-violet-300">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>
            <form id="editUserForm" method="POST" autocomplete="off">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" id="editUserId" name="user_id">

                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">Username</label>
                        <input type="text" id="editUsername" readonly class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md
                                      bg-violet-100 dark:bg-violet-800 dark:text-violet-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">
                            Full Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="editFullName" name="full_name" required class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md
                                      focus:outline-none focus:ring-2 focus:ring-violet-500
                                      dark:bg-violet-900 dark:text-white text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">
                            Email <span class="text-red-500">*</span>
                        </label>
                        <input type="email" id="editEmail" name="email" required class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md
                                      focus:outline-none focus:ring-2 focus:ring-violet-500
                                      dark:bg-violet-900 dark:text-white text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">
                            New Password (leave blank to keep current)
                        </label>
                        <input type="password" name="password" class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md
                                      focus:outline-none focus:ring-2 focus:ring-violet-500
                                      dark:bg-violet-900 dark:text-white text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">
                            Role <span class="text-red-500">*</span>
                        </label>
                        <select name="role" id="editRole" required class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md
                                       focus:outline-none focus:ring-2 focus:ring-violet-500
                                       dark:bg-violet-900 dark:text-white text-sm">
                            <option value="Clinician">Clinician</option>
                            <option value="Clinical Instructor">Clinical Instructor</option>
                            <option value="COD">COD (Coordinator of Dental)</option>
                            <option value="Admin">Administrator</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">Account
                            Status</label>
                        <select name="account_status" id="editAccountStatus" class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md
                                       focus:outline-none focus:ring-2 focus:ring-violet-500
                                       dark:bg-violet-900 dark:text-white text-sm" onchange="syncIsActive()">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="flex items-center">
                        <input type="checkbox" name="is_active" id="editIsActive" value="1" onchange="syncStatus()"
                            class="mr-2">
                        <label for="editIsActive" class="text-sm text-gray-900 dark:text-white">Active User</label>
                    </div>
                </div>
                <div class="flex justify-end space-x-2 mt-5">
                    <button type="button" onclick="closeEditModal()"
                        class="px-3 py-1.5 text-sm text-violet-600 dark:text-violet-300
                                   hover:text-violet-800 dark:hover:text-violet-100 border border-violet-300 dark:border-violet-600 rounded-md hover:bg-violet-50 dark:hover:bg-violet-800">
                        Cancel
                    </button>
                    <button type="submit"
                        class="bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-700 hover:to-purple-700 text-white px-4 py-1.5 rounded-md text-sm shadow-lg transition-all duration-200">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- View User Modal -->
    <div id="viewUserModal" class="fixed inset-0 bg-black bg-opacity-50 hidden justify-center items-center px-4 z-50">
        <div
            class="bg-gradient-to-br from-white to-violet-50 dark:from-gray-800 dark:to-violet-900 p-5 rounded-lg shadow-xl border border-violet-200 dark:border-violet-700 w-full max-w-md mx-auto modal-fade">
            <div class="flex justify-between items-center mb-4">
                <h3
                    class="text-lg font-semibold bg-gradient-to-r from-violet-700 to-purple-700 bg-clip-text text-transparent dark:from-violet-300 dark:to-purple-300">
                    User Details</h3>
                <button onclick="closeViewModal()"
                    class="text-violet-400 hover:text-gray-700 dark:text-gray-300 dark:hover:text-violet-300">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>
            <div id="viewUserContent" class="space-y-3 text-sm text-gray-900 dark:text-white">
                <!-- Populated by JS -->
            </div>
            <div class="flex justify-end mt-4">
                <button type="button" onclick="closeViewModal()"
                    class="bg-gradient-to-r from-violet-500 to-purple-500 hover:from-violet-600 hover:to-purple-600 text-white px-4 py-2 rounded-md text-sm shadow-lg transition-all duration-200">
                    Close
                </button>
            </div>
        </div>
    </div>

    <?php include 'includes/logout_modal.php'; ?>
    <?php include 'includes/delete_modal.php'; ?>

    <!-- ?? Dark-mode toggle (synced across pages) -->
    <script>
            (() => {
                const html = document.documentElement;
                const btn = document.getElementById('darkModeToggle');
                const key = 'darkMode';
                const isDark = localStorage.getItem(key) === 'true';

                // apply on load
                if (isDark) html.classList.add('dark');

                btn.addEventListener('click', () => {
                    html.classList.toggle('dark');
                    localStorage.setItem(key, html.classList.contains('dark'));
                });
            })();
    </script>

    <script>

        /* ---------- Alert Box Auto-hide ---------- */
        function hideAlert() {
            document.getElementById('alertBox')?.remove();
        }
        setTimeout(hideAlert, 4000);

        /* ---------- Modal Helpers ---------- */
        function closeAddModal() { document.getElementById('addUserModal').style.display = 'none'; }
        function closeEditModal() { document.getElementById('editUserModal').style.display = 'none'; }
        function closeViewModal() { document.getElementById('viewUserModal').style.display = 'none'; }

        function openEditModal(user) {
            document.getElementById('editUserId').value = user.id;
            document.getElementById('editUsername').value = user.username;
            document.getElementById('editFullName').value = user.full_name;
            document.getElementById('editEmail').value = user.email;
            document.getElementById('editRole').value = user.role;
            document.getElementById('editAccountStatus').value = user.account_status;
            document.getElementById('editIsActive').checked = (user.account_status === 'active');
            document.getElementById('editUserModal').style.display = 'flex';
        }

        function openViewModal(user) {
            const c = document.getElementById('viewUserContent');
            const onlineIcon = user.online_status === 'online' ? '??' : '??';
            c.innerHTML = `
                <p><strong>Username:</strong> ${escapeHtml(user.username)}</p>
                <p><strong>Full Name:</strong> ${escapeHtml(user.full_name)}</p>
                <p><strong>Email:</strong> ${escapeHtml(user.email)}</p>
                <p><strong>Role:</strong> ${escapeHtml(user.role)}</p>
                <p><strong>Account Status:</strong> ${escapeHtml(user.account_status)}</p>
                <p><strong>Online Status:</strong> ${onlineIcon} ${escapeHtml(user.online_status)}</p>
                <p><strong>Created:</strong> ${user.created_at ? new Date(user.created_at).toLocaleDateString() : 'N/A'}</p>
            `;
            document.getElementById('viewUserModal').style.display = 'flex';
        }

        function deleteUser(id, username) {
            showDeleteModal(
                `Are you sure you want to delete user <strong>"${escapeHtml(username)}"</strong>?<br><br><span class="text-red-600 dark:text-red-400 text-sm">?? This action cannot be undone and will permanently remove all user data.</span>`,
                function () {
                    const f = document.createElement('form');
                    f.method = 'POST';
                    f.innerHTML = `
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="user_id" value="${id}">
                    `;
                    document.body.appendChild(f);
                    f.submit();
                }
            );
        }

        function syncIsActive() {
            const st = document.getElementById('editAccountStatus');
            document.getElementById('editIsActive').checked = (st.value === 'active');
        }
        function syncStatus() {
            const chk = document.getElementById('editIsActive');
            document.getElementById('editAccountStatus').value = chk.checked ? 'active' : 'inactive';
        }

        function escapeHtml(unsafe) {
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
    </script>
</body>

</html>