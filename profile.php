<?php
require_once 'config.php';
requireAuth();

$user = getCurrentUser();
$role = $user['role'] ?? '';
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'upload_picture' && isset($_FILES['profile_picture'])) {
            $result = uploadProfilePicture($_FILES['profile_picture'], $user['id']);
            if ($result['success']) {
                $message = $result['message'];
                // Refresh user data to show new profile picture
                $user = getCurrentUser();
            } else {
                $error = $result['message'];
            }
        } elseif ($_POST['action'] === 'delete_picture') {
            if (deleteProfilePicture($user['id'])) {
                $message = 'Profile picture deleted successfully.';
                // Refresh user data
                $user = getCurrentUser();
            } else {
                $error = 'Failed to delete profile picture.';
            }
        } elseif ($_POST['action'] === 'update_profile') {
            $newFullName = trim($_POST['full_name'] ?? '');
            $newEmail = trim($_POST['email'] ?? '');
            $newUsername = trim($_POST['username'] ?? '');
            $currentPass = $_POST['current_password'] ?? '';
            
            // Validate current password - handle both hashed and plain text
            $isPasswordCorrect = false;
            if (strlen($user['password']) === 60 && substr($user['password'], 0, 4) === '$2y$') {
                $isPasswordCorrect = password_verify($currentPass, $user['password']);
            } else {
                $isPasswordCorrect = ($currentPass === $user['password']);
            }
            
            if (!$isPasswordCorrect) {
                $error = 'Current password is incorrect.';
            } else {
                $updateFields = [];
                $updateValues = [];
                $hasChanges = false;
                
                // Validate and prepare full name update
                if ($newFullName !== $user['full_name']) {
                    if (empty($newFullName)) {
                        $error = 'Full name cannot be empty.';
                    } elseif (strlen($newFullName) < 2) {
                        $error = 'Full name must be at least 2 characters long.';
                    } elseif (strlen($newFullName) > 100) {
                        $error = 'Full name cannot exceed 100 characters.';
                    } elseif (!preg_match('/^[a-zA-Z\s.-]+$/', $newFullName)) {
                        $error = 'Full name can only contain letters, spaces, dots, and hyphens.';
                    } else {
                        $updateFields[] = 'full_name = ?';
                        $updateValues[] = $newFullName;
                        $hasChanges = true;
                    }
                }
                
                // Validate and prepare email update
                if (empty($error) && $newEmail !== $user['email']) {
                    if (empty($newEmail)) {
                        $error = 'Email cannot be empty.';
                    } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                        $error = 'Please enter a valid email address.';
                    } else {
                        // Check if email is already taken
                        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                        $stmt->execute([$newEmail, $user['id']]);
                        if ($stmt->fetch()) {
                            $error = 'This email address is already in use by another user.';
                        } else {
                            $updateFields[] = 'email = ?';
                            $updateValues[] = $newEmail;
                            $hasChanges = true;
                        }
                    }
                }
                
                // Validate and prepare procedure details update (Clinical Instructors only)
                if (empty($error) && $role === 'Clinical Instructor') {
                    $newSpecialtyHint = trim($_POST['specialty_hint'] ?? '');
                    if ($newSpecialtyHint !== ($user['specialty_hint'] ?? '')) {
                        if (strlen($newSpecialtyHint) > 255) {
                            $error = 'Procedure details cannot exceed 255 characters.';
                        } else {
                            $updateFields[] = 'specialty_hint = ?';
                            $updateValues[] = $newSpecialtyHint ?: null;
                            $hasChanges = true;
                        }
                    }
                }
                
                // Validate and prepare username update
                if (empty($error) && $newUsername !== $user['username']) {
                    if (empty($newUsername)) {
                        $error = 'Username cannot be empty.';
                    } elseif (strlen($newUsername) < 3) {
                        $error = 'Username must be at least 3 characters long.';
                    } elseif (strlen($newUsername) > 50) {
                        $error = 'Username cannot exceed 50 characters.';
                    } elseif (!preg_match('/^[a-zA-Z0-9._-]+$/', $newUsername)) {
                        $error = 'Username can only contain letters, numbers, dots, underscores, and hyphens.';
                    } else {
                        // Check if username is already taken
                        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                        $stmt->execute([$newUsername, $user['id']]);
                        if ($stmt->fetch()) {
                            $error = 'This username is already taken.';
                        } else {
                            $updateFields[] = 'username = ?';
                            $updateValues[] = $newUsername;
                            $hasChanges = true;
                        }
                    }
                }
                
                // Execute update if no errors and there are changes
                if (empty($error)) {
                    if ($hasChanges) {
                        try {
                            $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
                            $updateValues[] = $user['id'];
                            $stmt = $pdo->prepare($sql);
                            $result = $stmt->execute($updateValues);
                            
                            if ($result) {
                                $message = 'Profile information updated successfully.';
                                // Refresh user data
                                $user = getCurrentUser();
                            } else {
                                $error = 'Failed to update profile information.';
                            }
                        } catch (PDOException $e) {
                            $error = 'Database error: ' . $e->getMessage();
                        }
                    } else {
                        $error = 'No changes were made to your profile information.';
                    }
                }
            }
        }
    }
}

$fullName = $user['full_name'] ?? 'Unknown User';
$profilePicture = $user['profile_picture'] ?? null;
$userActions = getUserRoleActions($role);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>iDENTify - My Profile</title>
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
        .profile-picture-preview {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #e5e7eb;
            background-color: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: #6b7280;
            position: relative;
        }
        
        .profile-picture-preview.cursor-pointer {
            position: relative;
            transition: all 0.3s ease;
        }
        
        .profile-picture-preview.cursor-pointer:hover {
            transform: scale(1.05);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }
        .modal-fade {
            animation: fadeIn 0.3s ease-in-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .role-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 0.875rem;
        }
        .role-admin { background-color: #fef3c7; color: #92400e; }
        .role-clinician { background-color: #dbeafe; color: #1e40af; }
        .role-instructor { background-color: #d1fae5; color: #065f46; }
        .role-cod { background-color: #f3e8ff; color: #7c2d12; }
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
        
        /* Profile Viewer Modal Styles */
        .profile-viewer-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background-color: rgba(0, 0, 0, 0.9);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            padding: 10px;
            overflow: auto;
        }
        
        .profile-viewer-content {
            position: relative;
            width: 100%;
            max-width: 600px;
            max-height: calc(100vh - 20px);
            background: white;
            border-radius: 12px;
            overflow: auto;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            margin: auto;
        }
        
        .dark .profile-viewer-content {
            background: #1f2937;
        }
        
        .profile-viewer-header {
            padding: 16px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .profile-viewer-header h3 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .profile-viewer-close {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            transition: background-color 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
        }
        
        .profile-viewer-close:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        .profile-viewer-body {
            padding: 40px 20px;
            text-align: center;
        }
        
        .profile-viewer-image {
            max-width: 100%;
            max-height: 500px;
            width: auto;
            height: auto;
            border-radius: 12px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            object-fit: contain;
        }
        
        .profile-viewer-info {
            margin-top: 20px;
            color: #6b7280;
        }
        
        .dark .profile-viewer-info {
            color: #9ca3af;
        }
        
        
        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            .profile-viewer-modal {
                padding: 5px;
            }
            
            .profile-viewer-content {
                max-width: 100%;
                max-height: calc(100vh - 10px);
                border-radius: 8px;
            }
            
            .profile-viewer-header {
                padding: 12px 15px;
            }
            
            .profile-viewer-header h3 {
                font-size: 1rem;
            }
            
            .profile-viewer-body {
                padding: 30px 15px;
            }
            
            .profile-viewer-image {
                max-height: 400px;
                border-radius: 8px;
            }
        }
        
        @media (max-width: 480px) {
            .profile-viewer-header h3 {
                font-size: 0.9rem;
            }
            
            .profile-viewer-body {
                padding: 25px 10px;
            }
            
            .profile-viewer-image {
                max-height: 350px;
            }
            
            .profile-viewer-info p {
                font-size: 0.9rem;
            }
        }
        
        /* Landscape mobile orientation */
        @media (max-height: 600px) and (orientation: landscape) {
            .profile-viewer-content {
                max-height: calc(100vh - 10px);
            }
            
            .profile-viewer-body {
                padding: 20px;
            }
            
            .profile-viewer-image {
                max-height: 300px;
            }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-violet-25 via-white to-purple-25 dark:bg-gradient-to-br dark:from-violet-950 dark:via-gray-900 dark:to-purple-950 transition-colors duration-200">

<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<!-- Main Content -->
<main class="ml-64 mt-[64px] min-h-screen bg-gradient-to-br from-violet-50 to-purple-50 dark:from-gray-900 dark:to-violet-900 p-6">
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
            
            <?php if ($error): ?>
                <div id="errorBox" class="mb-6">
                    <div class="bg-gradient-to-r from-red-100 to-pink-100 dark:from-red-900 dark:to-pink-900 border border-red-400 dark:border-red-600 text-red-700 dark:text-red-200 px-4 py-3 rounded-lg shadow-lg relative">
                        <span id="errorMessage"><?php echo htmlspecialchars($error); ?></span>
                        <button onclick="hideError()" class="absolute top-0 bottom-0 right-0 px-4 py-3 text-red-600 hover:text-red-800 dark:text-red-300 dark:hover:text-red-100">
                            <i class="ri-close-line"></i>
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <div class="max-w-4xl mx-auto">
                <!-- Profile Header -->
                <div class="bg-gradient-to-br from-white to-violet-50 dark:from-gray-800 dark:to-violet-900 rounded-lg shadow-xl border border-violet-200 dark:border-violet-700 p-8 mb-8">
                    <div class="flex flex-col md:flex-row items-center md:items-start space-y-6 md:space-y-0 md:space-x-8">
                        <!-- Profile Picture -->
                        <div class="flex flex-col items-center">
                            <div class="profile-picture-preview dark:border-violet-600 dark:bg-violet-700 <?php echo $profilePicture ? 'cursor-pointer hover:opacity-80 transition-opacity duration-200' : ''; ?>" 
                                 <?php echo $profilePicture ? 'onclick="openProfileViewer()" title="Click to view larger image"' : ''; ?>>
                                <?php if ($profilePicture && file_exists(__DIR__ . '/' . $profilePicture)): ?>
                                    <img src="<?php echo htmlspecialchars($profilePicture); ?>" 
                                         alt="Profile Picture" 
                                         class="profile-picture-preview">
                                <?php else: ?>
                                    <i class="ri-user-3-line text-5xl text-violet-400"></i>
                                <?php endif; ?>
                            </div>
                            <?php if ($profilePicture): ?>
                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-2 text-center">
                                <i class="ri-information-line mr-1"></i>Click image to view larger
                            </p>
                            <?php endif; ?>
                            <div class="mt-4 flex flex-wrap justify-center gap-2">
                                <button onclick="document.getElementById('uploadModal').style.display='flex'" 
                                        class="bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-700 hover:to-purple-700 text-white px-4 py-2 rounded-md text-sm shadow-lg transition-all duration-200">
                                    <i class="ri-upload-line mr-1"></i>
                                    <?php echo $profilePicture ? 'Change' : 'Upload'; ?>
                                </button>
                                <?php if ($profilePicture): ?>
                                <form id="deletePictureForm" method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="delete_picture">
                                    <button type="button" onclick="deleteProfilePicture()" class="bg-gradient-to-r from-red-500 to-pink-500 hover:from-red-600 hover:to-pink-600 text-white px-4 py-2 rounded-md text-sm shadow-lg transition-all duration-200">
                                        <i class="ri-delete-bin-line mr-1"></i>Delete
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- User Info -->
                        <div class="flex-1 text-center md:text-left">
                            <div class="flex items-center justify-center md:justify-start mb-2">
                                <h1 class="text-3xl font-bold bg-gradient-to-r from-violet-700 to-purple-700 bg-clip-text text-transparent dark:from-violet-300 dark:to-purple-300">
                                    <?php echo htmlspecialchars($fullName); ?>
                                </h1>
                                <button onclick="document.getElementById('editProfileModal').style.display='flex'" 
                                        class="ml-3 p-2 text-violet-500 hover:text-violet-700 dark:text-violet-400 dark:hover:text-violet-300 transition-colors" 
                                        title="Edit Profile Information">
                                    <i class="ri-edit-line text-lg"></i>
                                </button>
                            </div>
                            <div class="mb-4">
                                <span class="role-badge <?php 
                                    echo $role === 'Admin' ? 'role-admin' : 
                                        ($role === 'Clinician' ? 'role-clinician' : 
                                        ($role === 'Clinical Instructor' ? 'role-instructor' : 'role-cod')); 
                                ?>">
                                    <i class="ri-shield-user-line mr-2"></i>
                                    <?php echo htmlspecialchars($role); ?>
                                </span>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                <div class="flex items-center justify-between bg-gradient-to-r from-violet-50 to-purple-50 dark:from-violet-800 dark:to-purple-800 p-3 rounded-lg border border-violet-200 dark:border-violet-600">
                                    <div class="flex items-center">
                                        <i class="ri-user-line text-gray-700 dark:text-gray-300 mr-2"></i>
                                        <span class="text-gray-900 dark:text-white">Username:</span>
                                        <span class="ml-2 font-medium text-gray-900 dark:text-white">
                                            <?php echo htmlspecialchars($user['username']); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between bg-gradient-to-r from-violet-50 to-purple-50 dark:from-violet-800 dark:to-purple-800 p-3 rounded-lg border border-violet-200 dark:border-violet-600">
                                    <div class="flex items-center">
                                        <i class="ri-mail-line text-gray-700 dark:text-gray-300 mr-2"></i>
                                        <span class="text-gray-900 dark:text-white">Email:</span>
                                        <span class="ml-2 font-medium text-gray-900 dark:text-white">
                                            <?php echo htmlspecialchars($user['email']); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="flex items-center bg-gradient-to-r from-violet-50 to-purple-50 dark:from-violet-800 dark:to-purple-800 p-3 rounded-lg border border-violet-200 dark:border-violet-600">
                                    <i class="ri-calendar-line text-gray-700 dark:text-gray-300 mr-2"></i>
                                    <span class="text-gray-900 dark:text-white">Member Since:</span>
                                    <span class="ml-2 font-medium text-gray-900 dark:text-white">
                                        <?php echo date('F d, Y', strtotime($user['created_at'])); ?>
                                    </span>
                                </div>
                                <div class="flex items-center bg-gradient-to-r from-violet-50 to-purple-50 dark:from-violet-800 dark:to-purple-800 p-3 rounded-lg border border-violet-200 dark:border-violet-600">
                                    <i class="ri-checkbox-circle-line text-gray-700 dark:text-gray-300 mr-2"></i>
                                    <span class="text-gray-900 dark:text-white">Status:</span>
                                    <span class="ml-2">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                            Active
                                        </span>
                                    </span>
                                </div>
                                <?php if ($role === 'Clinical Instructor'): ?>
                                <div class="md:col-span-2 flex items-start bg-gradient-to-r from-violet-100 to-purple-100 dark:from-violet-700 dark:to-purple-700 p-3 rounded-lg border border-violet-300 dark:border-violet-500">
                                    <i class="ri-stethoscope-line text-gray-700 dark:text-gray-300 mr-2 mt-0.5"></i>
                                    <div class="flex-1">
                                        <span class="text-gray-900 dark:text-white font-medium">Procedure Details:</span>
                                        <span class="ml-2 font-medium text-gray-900 dark:text-white">
                                            <?php echo !empty($user['specialty_hint']) ? htmlspecialchars($user['specialty_hint']) : 'Not specified'; ?>
                                        </span>
                                        <?php if (empty($user['specialty_hint'])): ?>
                                            <span class="block text-xs text-violet-600 dark:text-violet-300 mt-1">
                                                Click "Edit Profile Info" to add your procedure details
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Role Permissions -->
                <div class="bg-gradient-to-br from-white to-violet-50 dark:from-gray-800 dark:to-violet-900 rounded-lg shadow-xl border border-violet-200 dark:border-violet-700 p-8 mb-8">
                    <h2 class="text-2xl font-bold bg-gradient-to-r from-violet-700 to-purple-700 bg-clip-text text-transparent dark:from-violet-300 dark:to-purple-300 mb-6">
                        <i class="ri-lock-unlock-line mr-2"></i>Your Role Permissions
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php foreach ($userActions as $action): ?>
                        <div class="flex items-center p-4 bg-gradient-to-r from-violet-50 to-purple-50 dark:from-violet-800 dark:to-purple-800 rounded-lg border border-violet-200 dark:border-violet-600">
                            <i class="ri-check-line text-green-500 mr-3 text-lg"></i>
                            <span class="text-gray-900 dark:text-white"><?php echo htmlspecialchars($action); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-gradient-to-br from-white to-violet-50 dark:from-gray-800 dark:to-violet-900 rounded-lg shadow-xl border border-violet-200 dark:border-violet-700 p-8">
                    <h2 class="text-2xl font-bold bg-gradient-to-r from-violet-700 to-purple-700 bg-clip-text text-transparent dark:from-violet-300 dark:to-purple-300 mb-6">
                        <i class="ri-lightning-line mr-2"></i>Quick Actions
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <button onclick="document.getElementById('editProfileModal').style.display='flex'" 
                                class="flex items-center p-4 bg-gradient-to-r from-green-50 to-emerald-50 dark:from-green-900 dark:to-emerald-900 text-green-700 dark:text-green-300 rounded-lg hover:from-green-100 hover:to-emerald-100 dark:hover:from-green-800 dark:hover:to-emerald-800 transition-all duration-200 border border-green-200 dark:border-green-700">
                            <i class="ri-edit-line mr-3 text-xl"></i>
                            <span class="font-medium">Edit Profile Info</span>
                        </button>
                        
                        <a href="settings.php" class="flex items-center p-4 bg-gradient-to-r from-violet-50 to-purple-50 dark:from-violet-900 dark:to-purple-900 text-gray-900 dark:text-white rounded-lg hover:from-violet-100 hover:to-purple-100 dark:hover:from-violet-800 dark:hover:to-purple-800 transition-all duration-200 border border-violet-200 dark:border-violet-700">
                            <i class="ri-lock-password-line mr-3 text-xl"></i>
                            <span class="font-medium">Change Password</span>
                        </a>
                        
                        <?php if ($role !== 'Clinical Instructor'): ?>
                        <a href="patients.php" class="flex items-center p-4 bg-gradient-to-r from-orange-50 to-amber-50 dark:from-orange-900 dark:to-amber-900 text-orange-700 dark:text-orange-300 rounded-lg hover:from-orange-100 hover:to-amber-100 dark:hover:from-orange-800 dark:hover:to-amber-800 transition-all duration-200 border border-orange-200 dark:border-orange-700">
                            <i class="ri-user-add-line mr-3 text-xl"></i>
                            <span class="font-medium">Add New Patient</span>
                        </a>
                        <?php else: ?>
                        <a href="dashboard.php" class="flex items-center p-4 bg-gradient-to-r from-purple-50 to-indigo-50 dark:from-purple-900 dark:to-indigo-900 text-purple-700 dark:text-purple-300 rounded-lg hover:from-purple-100 hover:to-indigo-100 dark:hover:from-purple-800 dark:hover:to-indigo-800 transition-all duration-200 border border-purple-200 dark:border-purple-700">
                            <i class="ri-dashboard-line mr-3 text-xl"></i>
                            <span class="font-medium">View Dashboard</span>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>

    <!-- Upload Profile Picture Modal -->
    <div id="uploadModal" class="fixed inset-0 bg-black bg-opacity-50 hidden justify-center items-center px-4 z-50">
        <div class="bg-gradient-to-br from-white to-violet-50 dark:from-gray-800 dark:to-violet-900 p-6 rounded-lg shadow-xl border border-violet-200 dark:border-violet-700 w-full max-w-md mx-auto modal-fade">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold bg-gradient-to-r from-violet-700 to-purple-700 bg-clip-text text-transparent dark:from-violet-300 dark:to-purple-300">Upload Profile Picture</h3>
                <button onclick="document.getElementById('uploadModal').style.display='none'" class="text-violet-400 hover:text-gray-700 dark:text-gray-300 dark:hover:text-violet-300">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_picture">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-2">
                            Select Image (JPG, PNG, GIF, WebP - Max 5MB)
                        </label>
                        <input type="file" name="profile_picture" accept="image/*" required
                               class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-violet-900 dark:text-white text-sm">
                    </div>
                    
                    <div class="bg-gradient-to-r from-violet-50 to-purple-50 dark:from-violet-800 dark:to-purple-800 p-4 rounded-md border border-violet-200 dark:border-violet-600">
                        <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-2">Requirements:</h4>
                        <ul class="text-xs text-gray-900 dark:text-white space-y-1">
                            <li>• Maximum file size: 5MB</li>
                            <li>• Supported formats: JPG, PNG, GIF, WebP</li>
                            <li>• Recommended: Square images for best results</li>
                        </ul>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-2 mt-6">
                    <button type="button" onclick="document.getElementById('uploadModal').style.display='none'"
                            class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-gray-100 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-800">
                        Cancel
                    </button>
                    <button type="submit"
                            class="bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-700 hover:to-purple-700 text-white px-4 py-2 rounded-md text-sm shadow-lg transition-all duration-200">
                        Upload Picture
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Profile Information Modal -->
    <div id="editProfileModal" class="fixed inset-0 bg-black bg-opacity-50 hidden justify-center items-center px-4 z-50">
        <div class="bg-gradient-to-br from-white to-violet-50 dark:from-gray-800 dark:to-violet-900 p-6 rounded-lg shadow-xl border border-violet-200 dark:border-violet-700 w-full max-w-lg mx-auto modal-fade max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold bg-gradient-to-r from-violet-700 to-purple-700 bg-clip-text text-transparent dark:from-violet-300 dark:to-purple-300">Edit Profile Information</h3>
                <button onclick="document.getElementById('editProfileModal').style.display='none'" class="text-violet-400 hover:text-gray-700 dark:text-gray-300 dark:hover:text-violet-300">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="update_profile">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-2">
                            <i class="ri-user-line mr-1"></i>Full Name
                        </label>
                        <input type="text" name="full_name" required maxlength="100" 
                               value="<?= htmlspecialchars($user['full_name']) ?>"
                               class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-violet-900 dark:text-white text-sm">
                        <p class="mt-1 text-xs text-gray-700 dark:text-gray-300">Letters, spaces, dots, and hyphens only (2-100 characters)</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-2">
                            <i class="ri-mail-line mr-1"></i>Email Address
                        </label>
                        <input type="email" name="email" required maxlength="100" 
                               value="<?= htmlspecialchars($user['email']) ?>"
                               class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-violet-900 dark:text-white text-sm">
                        <p class="mt-1 text-xs text-gray-700 dark:text-gray-300">Must be a valid email address and unique in the system</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-2">
                            <i class="ri-at-line mr-1"></i>Username
                        </label>
                        <input type="text" name="username" required maxlength="50" 
                               value="<?= htmlspecialchars($user['username']) ?>"
                               class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-violet-900 dark:text-white text-sm">
                        <p class="mt-1 text-xs text-gray-700 dark:text-gray-300">Letters, numbers, dots, underscores, and hyphens only (3-50 characters)</p>
                    </div>
                    
                    <?php if ($role === 'Clinical Instructor'): ?>
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-2">
                            <i class="ri-stethoscope-line mr-1"></i>Procedure Details
                        </label>
                        <input type="text" name="specialty_hint" maxlength="255" 
                               value="<?= htmlspecialchars($user['specialty_hint'] ?? '') ?>"
                               placeholder="e.g., Orthodontics, Oral Surgery, Periodontics, General Dentistry"
                               class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-violet-900 dark:text-white text-sm">
                        <p class="mt-1 text-xs text-gray-700 dark:text-gray-300">Help COD assign appropriate patients to you based on your procedure details (optional)</p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="bg-gradient-to-r from-amber-50 to-yellow-50 dark:from-amber-900 dark:to-yellow-900 p-4 rounded-md border border-amber-200 dark:border-amber-700">
                        <div class="flex">
                            <i class="ri-information-line text-amber-600 dark:text-amber-300 mr-2 mt-0.5"></i>
                            <div>
                                <h4 class="text-sm font-medium text-amber-800 dark:text-amber-200">Security Verification Required</h4>
                                <p class="text-sm text-amber-700 dark:text-amber-300 mt-1">Enter your current password to confirm these changes.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-2">
                            <i class="ri-lock-line mr-1"></i>Current Password
                        </label>
                        <input type="password" name="current_password" required
                               placeholder="Enter your current password to confirm changes"
                               class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-violet-900 dark:text-white text-sm">
                    </div>
                </div>
                
                <div class="flex justify-end space-x-2 mt-6">
                    <button type="button" onclick="document.getElementById('editProfileModal').style.display='none'"
                            class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-gray-100 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-800">
                        Cancel
                    </button>
                    <button type="submit"
                            class="bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white px-4 py-2 rounded-md text-sm shadow-lg transition-all duration-200">
                        <i class="ri-save-line mr-1"></i>Update Profile
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Profile Viewer Modal -->
    <div id="profileViewerModal" class="profile-viewer-modal" role="dialog" aria-modal="true" aria-labelledby="profileViewerTitle">
        <div class="profile-viewer-content">
            <div class="profile-viewer-header">
                <h3 id="profileViewerTitle"><i class="ri-user-3-line mr-2"></i>Profile Picture</h3>
                <button class="profile-viewer-close" onclick="closeProfileViewer()" aria-label="Close profile viewer">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <div class="profile-viewer-body">
                <?php if ($profilePicture && file_exists(__DIR__ . '/' . $profilePicture)): ?>
                    <div class="w-full flex justify-center mb-6">
                        <img id="profileViewerImage" 
                             src="<?php echo htmlspecialchars($profilePicture); ?>" 
                             alt="<?php echo htmlspecialchars($fullName); ?>'s Profile Picture" 
                             class="profile-viewer-image">
                    </div>
                    <div class="profile-viewer-info">
                        <p class="font-bold text-xl text-gray-900 dark:text-white mb-2"><?php echo htmlspecialchars($fullName); ?></p>
                        <p class="text-base opacity-75"><?php echo htmlspecialchars($role); ?></p>
                    </div>
                <?php else: ?>
                    <div class="text-center text-gray-500 dark:text-gray-400">
                        <i class="ri-image-line text-6xl mb-4"></i>
                        <p>No profile picture available</p>
                    </div>
                <?php endif; ?>
            </div>
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
            const alertBox = document.getElementById('alertBox');
            if (alertBox) alertBox.style.display = 'none';
        }

        function hideError() {
            const errorBox = document.getElementById('errorBox');
            if (errorBox) errorBox.style.display = 'none';
        }

        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            hideAlert();
            hideError();
        }, 5000);

        // Close modals when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('fixed') && e.target.classList.contains('inset-0')) {
                e.target.style.display = 'none';
            }
        });
        
        // Real-time validation for edit profile form
        const fullNameInput = document.querySelector('#editProfileModal input[name="full_name"]');
        const emailInput = document.querySelector('#editProfileModal input[name="email"]');
        const usernameInput = document.querySelector('#editProfileModal input[name="username"]');
        
        if (fullNameInput) {
            fullNameInput.addEventListener('input', function() {
                const value = this.value;
                const isValid = /^[a-zA-Z\s.-]*$/.test(value);
                
                if (!isValid && value.length > 0) {
                    this.style.borderColor = '#ef4444';
                    this.style.backgroundColor = '#fef2f2';
                } else {
                    this.style.borderColor = '';
                    this.style.backgroundColor = '';
                }
            });
        }
        
        if (usernameInput) {
            usernameInput.addEventListener('input', function() {
                const value = this.value;
                const isValid = /^[a-zA-Z0-9._-]*$/.test(value);
                
                if (!isValid && value.length > 0) {
                    this.style.borderColor = '#ef4444';
                    this.style.backgroundColor = '#fef2f2';
                } else {
                    this.style.borderColor = '';
                    this.style.backgroundColor = '';
                }
            });
        }
        
        if (emailInput) {
            emailInput.addEventListener('input', function() {
                const value = this.value;
                const isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
                
                if (!isValid && value.length > 0) {
                    this.style.borderColor = '#ef4444';
                    this.style.backgroundColor = '#fef2f2';
                } else {
                    this.style.borderColor = '#22c55e';
                    this.style.backgroundColor = '#f0fdf4';
                }
            });
        }
        
        // Profile Viewer Functions
        function openProfileViewer() {
            const modal = document.getElementById('profileViewerModal');
            if (modal) {
                modal.style.display = 'flex';
                document.body.style.overflow = 'hidden'; // Prevent background scrolling
                document.documentElement.style.overflow = 'hidden'; // Also prevent html scrolling
                
                // Add fade-in animation
                setTimeout(() => {
                    modal.style.opacity = '1';
                }, 10);
                
                // Focus management for accessibility
                const closeButton = modal.querySelector('.profile-viewer-close');
                if (closeButton) {
                    closeButton.focus();
                }
            }
        }
        
        function closeProfileViewer() {
            const modal = document.getElementById('profileViewerModal');
            if (modal) {
                modal.style.opacity = '0';
                setTimeout(() => {
                    modal.style.display = 'none';
                }, 300); // Wait for fade out animation
                document.body.style.overflow = 'auto'; // Restore scrolling
                document.documentElement.style.overflow = 'auto'; // Restore html scrolling
            }
        }
        
        
        // Close profile viewer when clicking outside the modal content
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('profile-viewer-modal')) {
                closeProfileViewer();
            }
        });
        
        // Close profile viewer with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeProfileViewer();
            }
        });
        
        // Add smooth opacity transition
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('profileViewerModal');
            if (modal) {
                modal.style.transition = 'opacity 0.3s ease-in-out';
                modal.style.opacity = '0';
            }
        });
        
        // Logout confirmation modal functions
        function showLogoutModal() {
            document.getElementById('logoutModal').style.display = 'flex';
        }
        
        function hideLogoutModal() {
            document.getElementById('logoutModal').style.display = 'none';
        }
        
        function confirmLogout() {
            window.location.href = 'logout.php';
        }
        
        // Profile picture delete function
        function deleteProfilePicture() {
            showDeleteModal(
                `Are you sure you want to delete your profile picture?<br><br><span class="text-red-600 dark:text-red-400 text-sm">?? This action will remove your current profile picture and cannot be undone.</span>`,
                function() {
                    document.getElementById('deletePictureForm').submit();
                }
            );
        }
    </script>
    
    <!-- Logout Confirmation Modal -->
    <div id="logoutModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50" style="display: none;">
        <div class="bg-gradient-to-br from-white to-violet-50 dark:from-gray-800 dark:to-violet-900 p-6 rounded-lg shadow-xl border border-violet-200 dark:border-violet-700 max-w-sm mx-4">
            <div class="flex items-center mb-4">
                <i class="ri-logout-line text-red-500 text-2xl mr-3"></i>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Confirm Logout</h3>
            </div>
            <p class="text-gray-700 dark:text-gray-300 mb-6">Are you sure you want to logout from your account?</p>
            <div class="flex justify-end space-x-3">
                <button onclick="hideLogoutModal()" class="px-4 py-2 text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-gray-100 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-200">
                    Cancel
                </button>
                <button onclick="confirmLogout()" class="px-4 py-2 bg-gradient-to-r from-red-500 to-pink-500 hover:from-red-600 hover:to-pink-600 text-white rounded-md transition-all duration-200 shadow-lg">
                    Logout
                </button>
            </div>
        </div>
    </div>
    
    <?php include 'includes/delete_modal.php'; ?>
</body>
</html>
