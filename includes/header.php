<!-- Header -->
<header class="fixed top-0 left-0 right-0 h-16 bg-gradient-to-r from-violet-50 to-purple-50 dark:bg-black shadow-lg border-b-2 border-violet-200 dark:border-gray-800 z-50">
    <div class="flex items-center justify-between px-6 h-full">
        <h1 class="text-xl font-bold bg-gradient-to-r from-violet-600 via-purple-600 to-indigo-600 bg-clip-text text-transparent">iDENTify</h1>
        <div class="flex items-center space-x-4">
            <!-- Profile Picture -->
            <a href="profile.php" class="flex items-center space-x-3 hover:opacity-80 transition-opacity" title="Go to My Profile">
                <?php if ($profilePicture && file_exists(__DIR__ . '/../' . $profilePicture)): ?>
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
            </button>
        </div>
    </div>
</header>

<style>
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
