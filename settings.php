<?php
require_once 'config.php';
requireAuth();

$user        = getCurrentUser();
$message     = '';
$error       = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPass = $_POST['current_password'] ?? '';
    $newPass     = $_POST['new_password']     ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    // validate current password - handle both hashed and plain text
    $isPasswordCorrect = false;
    if (strlen($user['password']) === 60 && substr($user['password'], 0, 4) === '$2y$') {
        // Password is hashed (bcrypt)
        $isPasswordCorrect = password_verify($currentPass, $user['password']);
    } else {
        // Password is plain text
        $isPasswordCorrect = ($currentPass === $user['password']);
    }
    
    if (!$isPasswordCorrect) {
        $error = 'Current password is incorrect.';
    } elseif ($newPass !== $confirmPass) {
        $error = 'New passwords do not match.';
    } elseif (strlen($newPass) < 6) {
        $error = 'New password must be at least 6 characters.';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([password_hash($newPass, PASSWORD_DEFAULT), $user['id']]);
            $message = 'Password updated successfully.';
            // Refresh user data
            $user = getCurrentUser();
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

$fullName = $user['full_name'] ?? $user['username'];
$profilePicture = $user['profile_picture'] ?? null;
$role        = $user['role'] ?? '';

// Debug mode - remove this in production
$debug = isset($_GET['debug']) && $_GET['debug'] === '1';
if ($debug) {
    echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px 0; border-left: 4px solid #007bff;'>";
    echo "<strong>Debug Info:</strong><br>";
    echo "User ID: " . $user['id'] . "<br>";
    echo "Username: " . htmlspecialchars($user['username']) . "<br>";
    echo "Full Name: " . htmlspecialchars($user['full_name']) . "<br>";
    echo "Password Type: " . (strlen($user['password']) === 60 && substr($user['password'], 0, 4) === '$2y$' ? 'Hashed (bcrypt)' : 'Plain text') . "<br>";
    echo "Password Length: " . strlen($user['password']) . "<br>";
    echo "</div>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>iDENTify – Settings</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.0.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="dark-mode-override.css">
    <link rel="stylesheet" href="styles.css">
    <script>
        tailwind.config = { darkMode: 'class' };
    </script>
    <style>
        .modal-fade { animation: fadeIn 0.3s ease-in-out; }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to   { opacity: 1; transform: translateY(0); }
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

<!-- ================== MAIN CONTENT ================== -->
<main class="ml-64 mt-[64px] min-h-screen bg-gradient-to-br from-violet-50 to-purple-50 dark:from-gray-900 dark:to-violet-900 p-6">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold bg-gradient-to-r from-violet-700 to-purple-700 bg-clip-text text-transparent dark:from-violet-300 dark:to-purple-300">Security Settings</h2>
            <p class="text-gray-700 dark:text-gray-300">Manage your password and security preferences</p>
        </div>

        <!-- Alerts -->
        <?php if ($message): ?>
            <div class="bg-gradient-to-r from-green-100 to-emerald-100 dark:from-green-900 dark:to-emerald-900 border border-green-400 dark:border-green-600 text-green-700 dark:text-green-200 px-4 py-3 rounded-lg shadow-lg mb-4">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="bg-gradient-to-r from-red-100 to-pink-100 dark:from-red-900 dark:to-pink-900 border border-red-400 dark:border-red-600 text-red-700 dark:text-red-200 px-4 py-3 rounded-lg shadow-lg mb-4">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Password change card -->
        <div class="bg-gradient-to-br from-white to-violet-50 dark:from-gray-800 dark:to-violet-900 rounded-lg shadow-xl border border-violet-200 dark:border-violet-700 p-6 max-w-md">
            <h3 class="text-lg font-semibold bg-gradient-to-r from-violet-700 to-purple-700 bg-clip-text text-transparent dark:from-violet-300 dark:to-purple-300 mb-4">
                <i class="ri-lock-password-line mr-2"></i>Change Password
            </h3>
            <div class="bg-gradient-to-r from-violet-50 to-purple-50 dark:from-violet-900 dark:to-purple-900 p-4 rounded-md border border-violet-200 dark:border-violet-700 mb-4">
                <div class="flex">
                    <i class="ri-information-line text-violet-600 dark:text-violet-300 mr-2 mt-0.5"></i>
                    <div>
                        <p class="text-sm text-gray-900 dark:text-white">
                            To update your personal information (name, email, username), please visit your 
                            <a href="profile.php" class="font-medium underline hover:text-violet-900 dark:hover:text-violet-100">Profile page</a>.
                        </p>
                    </div>
                </div>
            </div>
            <form method="POST">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white">Current Password</label>
                        <input type="password" name="current_password" required
                               class="mt-1 w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md bg-violet-25 dark:bg-violet-900 dark:text-white focus:ring-violet-500 focus:border-violet-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white">New Password</label>
                        <input type="password" name="new_password" required
                               class="mt-1 w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md bg-violet-25 dark:bg-violet-900 dark:text-white focus:ring-violet-500 focus:border-violet-500">
                        <p class="mt-1 text-xs text-gray-700 dark:text-gray-300">Minimum 6 characters required</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white">Confirm New Password</label>
                        <input type="password" name="confirm_password" required
                               class="mt-1 w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md bg-violet-25 dark:bg-violet-900 dark:text-white focus:ring-violet-500 focus:border-violet-500">
                    </div>

                    <button type="submit"
                            class="w-full bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-700 hover:to-purple-700 text-white font-medium py-2 px-4 rounded-md shadow-lg transition-all duration-200">
                        <i class="ri-save-line mr-2"></i>Update Password
                    </button>
                </div>
            </form>
        </div>
    </main>

<!-- ================== DARK-MODE SCRIPT ================== -->
<script>
(() => {
    const html   = document.documentElement;
    const btn    = document.getElementById('darkModeToggle');
    const key    = 'darkMode';
    const isDark = localStorage.getItem(key) === 'true';

    if (isDark) html.classList.add('dark');

    btn.addEventListener('click', () => {
        html.classList.toggle('dark');
        localStorage.setItem(key, html.classList.contains('dark'));
    });
})();

// Form handling and validation
document.addEventListener('DOMContentLoaded', function() {
    // Clear form fields after successful submission
    const hasMessage = <?php echo $message ? 'true' : 'false'; ?>;
    if (hasMessage) {
        // Clear all password fields
        document.querySelectorAll('input[type="password"]').forEach(input => {
            input.value = '';
        });
        // Clear the name input field
        const nameInput = document.querySelector('input[name="full_name"]');
        if (nameInput) nameInput.value = '';
    }
    
    // Real-time validation for full name
    const fullNameInput = document.querySelector('input[name="full_name"]');
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
    
    // Confirm password matching for password change
    const newPassInput = document.querySelector('input[name="new_password"]');
    const confirmPassInput = document.querySelector('input[name="confirm_password"]');
    
    if (newPassInput && confirmPassInput) {
        function checkPasswordMatch() {
            const newPass = newPassInput.value;
            const confirmPass = confirmPassInput.value;
            
            if (confirmPass.length > 0) {
                if (newPass === confirmPass) {
                    confirmPassInput.style.borderColor = '#22c55e';
                    confirmPassInput.style.backgroundColor = '#f0fdf4';
                } else {
                    confirmPassInput.style.borderColor = '#ef4444';
                    confirmPassInput.style.backgroundColor = '#fef2f2';
                }
            } else {
                confirmPassInput.style.borderColor = '';
                confirmPassInput.style.backgroundColor = '';
            }
        }
        
        newPassInput.addEventListener('input', checkPasswordMatch);
        confirmPassInput.addEventListener('input', checkPasswordMatch);
    }
    
    // Auto-hide alerts after 5 seconds
    setTimeout(() => {
        const alerts = document.querySelectorAll('.bg-green-100, .bg-red-100');
        alerts.forEach(alert => {
            if (alert.parentElement) {
                alert.parentElement.style.opacity = '0';
                alert.parentElement.style.transition = 'opacity 0.5s';
                setTimeout(() => {
                    if (alert.parentElement) {
                        alert.parentElement.style.display = 'none';
                    }
                }, 500);
            }
        });
    }, 5000);
});
</script>

<?php include 'includes/logout_modal.php'; ?>
</body>
</html>
