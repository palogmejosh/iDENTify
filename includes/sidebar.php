<!-- Sidebar -->
<nav class="fixed left-0 top-16 w-64 bg-gradient-to-b from-white to-violet-50 dark:bg-black shadow-lg h-[calc(100vh-64px)] sidebar border-r-2 border-violet-100 dark:border-gray-900 overflow-y-auto z-40">
    <div class="p-6">
        <ul class="space-y-2">
            <li>
                <a href="dashboard.php" class="sidebar-link flex items-center px-4 py-2 text-gray-900 dark:text-white rounded-md hover:bg-violet-100 dark:hover:bg-gray-800 <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'sidebar-active bg-gradient-to-r from-violet-100 to-purple-100 dark:bg-gray-800 border-l-4 border-violet-500' : ''; ?>">
                    <i class="ri-dashboard-line mr-3"></i>Dashboard
                </a>
            </li>
            <li>
                <a href="patients.php" class="sidebar-link flex items-center px-4 py-2 text-gray-900 dark:text-white rounded-md hover:bg-violet-100 dark:hover:bg-gray-800 <?php echo (basename($_SERVER['PHP_SELF']) == 'patients.php') ? 'sidebar-active bg-gradient-to-r from-violet-100 to-purple-100 dark:bg-gray-800 border-l-4 border-violet-500' : ''; ?>">
                    <i class="ri-user-line mr-3"></i><?php echo ($role === 'COD') ? 'All Patients' : 'Patients'; ?>
                </a>
            </li>
            <?php if ($role === 'Clinician' || $role === 'Admin'): ?>
            <li>
                <a href="clinician_log_procedure.php" class="sidebar-link flex items-center px-4 py-2 text-gray-900 dark:text-white rounded-md hover:bg-violet-100 dark:hover:bg-gray-800 <?php echo (basename($_SERVER['PHP_SELF']) == 'clinician_log_procedure.php') ? 'sidebar-active bg-gradient-to-r from-violet-100 to-purple-100 dark:bg-gray-800 border-l-4 border-violet-500' : ''; ?>">
                    <i class="ri-file-list-3-line mr-3"></i>Log Procedure
                </a>
            </li>
            <?php endif; ?>
            <?php if ($role === 'COD'): ?>
            <li>
                <a href="cod_patients.php" class="sidebar-link flex items-center px-4 py-2 text-gray-900 dark:text-white rounded-md hover:bg-violet-100 dark:hover:bg-gray-800 <?php echo (basename($_SERVER['PHP_SELF']) == 'cod_patients.php') ? 'sidebar-active bg-gradient-to-r from-violet-100 to-purple-100 dark:bg-gray-800 border-l-4 border-violet-500' : ''; ?>">
                    <i class="ri-user-settings-line mr-3"></i>Patient Assignments
                </a>
            </li>
            <li>
                <a href="cod_log_procedures_assignment.php" class="sidebar-link flex items-center px-4 py-2 text-gray-900 dark:text-white rounded-md hover:bg-violet-100 dark:hover:bg-gray-800 <?php echo (basename($_SERVER['PHP_SELF']) == 'cod_log_procedures_assignment.php') ? 'sidebar-active bg-gradient-to-r from-violet-100 to-purple-100 dark:bg-gray-800 border-l-4 border-violet-500' : ''; ?>">
                    <i class="ri-file-list-3-line mr-3"></i>Log Procedures Assignment
                </a>
            </li>
            <?php endif; ?>
            <?php if ($role === 'Clinical Instructor'): ?>
            <li>
                <a href="ci_patient_assignments.php" class="sidebar-link flex items-center px-4 py-2 text-gray-900 dark:text-white rounded-md hover:bg-violet-100 dark:hover:bg-gray-800 <?php echo (basename($_SERVER['PHP_SELF']) == 'ci_patient_assignments.php') ? 'sidebar-active bg-gradient-to-r from-violet-100 to-purple-100 dark:bg-gray-800 border-l-4 border-violet-500' : ''; ?>">
                    <i class="ri-user-add-line mr-3"></i>Patient Assignments
                </a>
            </li>
            <li>
                <a href="ci_patient_transfers.php" class="sidebar-link flex items-center px-4 py-2 text-gray-900 dark:text-white rounded-md hover:bg-violet-100 dark:hover:bg-gray-800 <?php echo (basename($_SERVER['PHP_SELF']) == 'ci_patient_transfers.php') ? 'sidebar-active bg-gradient-to-r from-violet-100 to-purple-100 dark:bg-gray-800 border-l-4 border-violet-500' : ''; ?>">
                    <i class="ri-exchange-line mr-3"></i>Patient Transfers
                    <?php 
                    if (function_exists('getTransferRequestCounts')) {
                        $transferCounts = getTransferRequestCounts($user['id']);
                        if ($transferCounts['incoming_pending'] > 0): 
                    ?>
                        <span class="ml-auto bg-red-500 text-white text-xs rounded-full px-2 py-1">
                            <?php echo $transferCounts['incoming_pending']; ?>
                        </span>
                    <?php 
                        endif;
                    }
                    ?>
                </a>
            </li>
            <?php endif; ?>
            <?php if ($role === 'Admin'): ?>
            <li>
                <a href="users.php" class="sidebar-link flex items-center px-4 py-2 text-gray-900 dark:text-white rounded-md hover:bg-violet-100 dark:hover:bg-gray-800 <?php echo (basename($_SERVER['PHP_SELF']) == 'users.php') ? 'sidebar-active bg-gradient-to-r from-violet-100 to-purple-100 dark:bg-gray-800 border-l-4 border-violet-500' : ''; ?>">
                    <i class="ri-group-line mr-3"></i>System Users
                </a>
            </li>
            <li>
                <a href="admin_procedures_log.php" class="sidebar-link flex items-center px-4 py-2 text-gray-900 dark:text-white rounded-md hover:bg-violet-100 dark:hover:bg-gray-800 <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_procedures_log.php') ? 'sidebar-active bg-gradient-to-r from-violet-100 to-purple-100 dark:bg-gray-800 border-l-4 border-violet-500' : ''; ?>">
                    <i class="ri-file-list-line mr-3"></i>Procedures Log
                </a>
            </li>
            <?php endif; ?>
            <li>
                <a href="profile.php" class="sidebar-link flex items-center px-4 py-2 text-gray-900 dark:text-white rounded-md hover:bg-violet-100 dark:hover:bg-gray-800 <?php echo (basename($_SERVER['PHP_SELF']) == 'profile.php') ? 'sidebar-active bg-gradient-to-r from-violet-100 to-purple-100 dark:bg-gray-800 border-l-4 border-violet-500' : ''; ?>">
                    <i class="ri-user-3-line mr-3"></i>My Profile
                </a>
            </li>
            <li>
                <a href="settings.php" class="sidebar-link flex items-center px-4 py-2 text-gray-900 dark:text-white rounded-md hover:bg-violet-100 dark:hover:bg-gray-800 <?php echo (basename($_SERVER['PHP_SELF']) == 'settings.php') ? 'sidebar-active bg-gradient-to-r from-violet-100 to-purple-100 dark:bg-gray-800 border-l-4 border-violet-500' : ''; ?>">
                    <i class="ri-settings-3-line mr-3"></i>Settings
                </a>
            </li>
        </ul>
    </div>
</nav>
