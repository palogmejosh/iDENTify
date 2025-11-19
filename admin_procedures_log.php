<?php
require_once 'config.php';
requireAuth();

$user = getCurrentUser();
$role = $user['role'] ?? '';

// Redirect if not Admin
if ($role !== 'Admin') {
    header('Location: dashboard.php');
    exit;
}

// Get filter parameters
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$searchKeyword = $_GET['search'] ?? '';

// Check if this is the first load (no filters applied)
$isFirstLoad = empty($startDate) && empty($endDate) && empty($searchKeyword) && !isset($_GET['show_all']);

// If first load, default to today's date only
if ($isFirstLoad) {
    $startDate = date('Y-m-d');
    $endDate = date('Y-m-d');
}

// Build query to fetch procedure logs with related information
$sql = "SELECT 
    pl.id,
    pl.patient_id,
    pl.patient_name,
    pl.age,
    pl.sex,
    pl.procedure_selected,
    pl.procedure_details,
    pl.chair_number,
    pl.status,
    pl.remarks,
    pl.clinician_name,
    pl.logged_at,
    u_clinician.id as clinician_id,
    u_clinician.full_name as clinician_full_name,
    u_ci.full_name as clinical_instructor_name,
    DATE(pl.logged_at) as log_date
FROM procedure_logs pl
LEFT JOIN users u_clinician ON pl.clinician_id = u_clinician.id
LEFT JOIN patients p ON pl.patient_id = p.id
LEFT JOIN patient_assignments pa ON p.id = pa.patient_id AND pa.assignment_status IN ('accepted', 'completed')
LEFT JOIN users u_ci ON pa.clinical_instructor_id = u_ci.id
WHERE 1=1";

$params = [];

// Apply date filters
if (!empty($startDate)) {
    $sql .= " AND DATE(pl.logged_at) >= ?";
    $params[] = $startDate;
}

if (!empty($endDate)) {
    $sql .= " AND DATE(pl.logged_at) <= ?";
    $params[] = $endDate;
}

// Apply search filter
if (!empty($searchKeyword)) {
    $sql .= " AND (
        pl.patient_name LIKE ? OR
        pl.clinician_name LIKE ? OR
        u_ci.full_name LIKE ? OR
        pl.procedure_selected LIKE ?
    )";
    $searchTerm = "%$searchKeyword%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$sql .= " ORDER BY pl.logged_at DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $procedureLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching procedure logs: " . $e->getMessage());
    $procedureLogs = [];
}

$fullName = $user['full_name'] ?? 'Unknown User';
$profilePicture = $user['profile_picture'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>iDENTify - Procedures Log Report</title>
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

    <div class="flex ml-64 mt-16">

        <!-- Main Content -->
        <main class="flex-1 bg-gradient-to-br from-violet-50 to-purple-50 dark:from-gray-900 dark:to-violet-900 p-6 main-content overflow-y-auto min-h-screen">
            <div class="w-full">
                <!-- Header Section -->
                <div class="mb-6 no-print">
                    <h2 class="text-3xl font-bold bg-gradient-to-r from-violet-700 to-purple-700 bg-clip-text text-transparent dark:from-violet-300 dark:to-purple-300">
                        Dental Dispensary Procedures Log Report
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400 mt-2">
                        <?php if ($isFirstLoad): ?>
                            <i class="ri-calendar-check-line mr-1"></i>Showing <strong>today's procedures only</strong> (<?php echo date('F j, Y'); ?>)
                        <?php else: ?>
                            View and filter all procedure logs from clinicians
                        <?php endif; ?>
                    </p>
                </div>

                <!-- Filters Section -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-violet-200 dark:border-violet-700 p-6 mb-6 no-print">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">
                        <i class="ri-filter-line mr-2"></i>Filter Options
                    </h3>
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Start Date
                            </label>
                            <input type="date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>"
                                   class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-violet-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                End Date
                            </label>
                            <input type="date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>"
                                   class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-violet-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Search Keyword
                            </label>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($searchKeyword); ?>"
                                   placeholder="Patient, clinician, procedure..."
                                   class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-violet-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        <div class="md:col-span-3 flex justify-end space-x-3">
                            <a href="admin_procedures_log.php" class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-lg hover:bg-gray-400 dark:hover:bg-gray-500 transition-colors">
                                <i class="ri-refresh-line mr-2"></i>Today Only
                            </a>
                            <a href="admin_procedures_log.php?show_all=1" class="px-4 py-2 bg-blue-500 dark:bg-blue-600 text-white rounded-lg hover:bg-blue-600 dark:hover:bg-blue-700 transition-colors">
                                <i class="ri-calendar-line mr-2"></i>Show All Records
                            </a>
                            <button type="submit" class="px-4 py-2 bg-gradient-to-r from-violet-600 to-purple-600 text-white rounded-lg hover:from-violet-700 hover:to-purple-700 transition-all shadow-md">
                                <i class="ri-search-line mr-2"></i>Apply Filters
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Results Section -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-violet-200 dark:border-violet-700 p-6">
                    <div class="mb-4">
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">
                            <?php if ($isFirstLoad): ?>
                                <i class="ri-calendar-check-line text-blue-600 dark:text-blue-400 mr-2"></i>Today's Procedures
                            <?php else: ?>
                                Dental Dispensary Procedures Log Report
                            <?php endif; ?>
                            <span class="text-sm font-normal text-gray-600 dark:text-gray-400">
                                (<?php echo count($procedureLogs); ?> <?php echo count($procedureLogs) === 1 ? 'item' : 'items'; ?>)
                            </span>
                        </h3>
                    </div>

                    <!-- Table -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full border border-gray-300 dark:border-gray-600 text-sm">
                            <thead>
                                <tr class="bg-gray-100 dark:bg-gray-700">
                                    <th class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-left">No.</th>
                                    <th class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-left">Clinician</th>
                                    <th class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-left">C.I.</th>
                                    <th class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-left">Patient Name</th>
                                    <th class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-left">Age</th>
                                    <th class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-left">Sex</th>
                                    <th class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-left">Procedures</th>
                                    <th class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-left">Details</th>
                                    <th class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-left">Date</th>
                                    <th class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-left">Remarks</th>
                                    <th class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-left">Status</th>
                                    <th class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-left">Chair</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($procedureLogs)): ?>
                                    <tr>
                                        <td colspan="12" class="border border-gray-300 dark:border-gray-600 px-3 py-8 text-center text-gray-500 dark:text-gray-400">
                                            <i class="ri-inbox-line text-4xl mb-2"></i>
                                            <?php if ($isFirstLoad): ?>
                                                <p class="text-lg font-semibold">No procedures logged today yet</p>
                                                <p class="text-sm mt-2">Procedures logged by clinicians today will appear here automatically.</p>
                                                <p class="text-sm mt-2">
                                                    <a href="admin_procedures_log.php?show_all=1" class="text-blue-600 dark:text-blue-400 hover:underline">
                                                        <i class="ri-calendar-line"></i> View all historical records
                                                    </a>
                                                </p>
                                            <?php else: ?>
                                                <p class="text-lg font-semibold">No procedure logs found</p>
                                                <p class="text-sm mt-2">Try adjusting your filters or date range.</p>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($procedureLogs as $index => $log): ?>
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <td class="border border-gray-300 dark:border-gray-600 px-3 py-2"><?php echo $index + 1; ?></td>
                                            <td class="border border-gray-300 dark:border-gray-600 px-3 py-2"><?php echo htmlspecialchars($log['clinician_name']); ?></td>
                                            <td class="border border-gray-300 dark:border-gray-600 px-3 py-2"><?php echo htmlspecialchars($log['clinical_instructor_name'] ?? 'N/A'); ?></td>
                                            <td class="border border-gray-300 dark:border-gray-600 px-3 py-2"><?php echo htmlspecialchars($log['patient_name']); ?></td>
                                            <td class="border border-gray-300 dark:border-gray-600 px-3 py-2"><?php echo htmlspecialchars($log['age'] ?? '-'); ?></td>
                                            <td class="border border-gray-300 dark:border-gray-600 px-3 py-2"><?php echo htmlspecialchars($log['sex'] ?? '-'); ?></td>
                                            <td class="border border-gray-300 dark:border-gray-600 px-3 py-2">
                                                <div class="max-w-xs truncate" title="<?php echo htmlspecialchars($log['procedure_selected']); ?>">
                                                    <?php echo htmlspecialchars($log['procedure_selected']); ?>
                                                </div>
                                            </td>
                                            <td class="border border-gray-300 dark:border-gray-600 px-3 py-2">
                                                <div class="max-w-xs truncate" title="<?php echo htmlspecialchars($log['procedure_details'] ?? '-'); ?>">
                                                    <?php echo htmlspecialchars($log['procedure_details'] ?? '-'); ?>
                                                </div>
                                            </td>
                                            <td class="border border-gray-300 dark:border-gray-600 px-3 py-2 whitespace-nowrap">
                                                <?php echo date('d/m/Y', strtotime($log['logged_at'])); ?>
                                            </td>
                                            <td class="border border-gray-300 dark:border-gray-600 px-3 py-2">
                                                <div class="max-w-xs" title="<?php echo htmlspecialchars($log['remarks'] ?? '-'); ?>">
                                                    <?php echo htmlspecialchars($log['remarks'] ?? '-'); ?>
                                                </div>
                                            </td>
                                            <td class="border border-gray-300 dark:border-gray-600 px-3 py-2">
                                                <span class="px-2 py-1 bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 rounded text-xs">
                                                    <?php echo htmlspecialchars($log['status'] ?? 'Completed'); ?>
                                                </span>
                                            </td>
                                            <td class="border border-gray-300 dark:border-gray-600 px-3 py-2"><?php echo htmlspecialchars($log['chair_number'] ?? '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Logout Modal -->
    <?php include 'includes/logout_modal.php'; ?>

    <script>
        // Dark mode functionality
        const darkModeToggle = document.getElementById('darkModeToggle');
        const htmlElement = document.documentElement;

        // Check for saved dark mode preference
        if (localStorage.getItem('darkMode') === 'enabled') {
            htmlElement.classList.add('dark');
        }

        darkModeToggle.addEventListener('click', () => {
            htmlElement.classList.toggle('dark');
            
            if (htmlElement.classList.contains('dark')) {
                localStorage.setItem('darkMode', 'enabled');
            } else {
                localStorage.setItem('darkMode', 'disabled');
            }
        });

        // Logout modal functions
        function showLogoutModal() {
            document.getElementById('logoutModal').classList.remove('hidden');
        }

        function hideLogoutModal() {
            document.getElementById('logoutModal').classList.add('hidden');
        }

        function confirmLogout() {
            window.location.href = 'logout.php';
        }
    </script>
</body>
</html>
