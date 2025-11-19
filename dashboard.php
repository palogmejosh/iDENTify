<?php
require_once 'config.php';
requireAuth();

// Get logged-in user
$currentUser = getCurrentUser();
$user = $currentUser; // Alias for sidebar compatibility
$fullName = $currentUser ? $currentUser['full_name'] : 'Unknown User';
$role = $currentUser['role'] ?? '';
$profilePicture = $currentUser['profile_picture'] ?? null;

// Fetch enhanced statistics using PDO
global $pdo;

// Count total patients (filtered for Clinicians and specialized for COD)
$totalPatients = 0;
try {
    if ($role === 'Clinician' && checkCreatedByColumnExists()) {
        $stmt = $pdo->prepare("SELECT COUNT(*) AS count FROM patients WHERE created_by = ?");
        $stmt->execute([$currentUser['id']]);
    } elseif ($role === 'COD' && checkCreatedByColumnExists()) {
        // COD sees all patients created by Clinicians
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) AS count FROM patients p 
             JOIN users u ON p.created_by = u.id 
             WHERE u.role = 'Clinician'"
        );
        $stmt->execute();
} elseif ($role === 'Clinical Instructor' && checkCreatedByColumnExists()) {
        // Clinical Instructors see only patients explicitly assigned to them (including completed)
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) AS count FROM patients p 
             INNER JOIN patient_assignments pa ON p.id = pa.patient_id 
             WHERE pa.clinical_instructor_id = ? AND pa.assignment_status IN ('accepted', 'completed')"
        );
        $stmt->execute([$currentUser['id']]);
    } else {
        $stmt = $pdo->query("SELECT COUNT(*) AS count FROM patients");
    }
    $row = $stmt->fetch();
    $totalPatients = $row['count'] ?? 0;
} catch (PDOException $e) {
    error_log("Error fetching patient count: " . $e->getMessage());
}

// Count total records (PIR + Health + Dental + Consent)
$totalRecords = 0;
try {
    $stmt = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM patient_pir) + 
            (SELECT COUNT(*) FROM patient_health) + 
            (SELECT COUNT(*) FROM dental_examination) + 
            (SELECT COUNT(*) FROM informed_consent) AS total_count
    ");
    $row = $stmt->fetch();
    $totalRecords = $row['total_count'] ?? 0;
} catch (PDOException $e) {
    error_log("Error fetching total records: " . $e->getMessage());
}

// Count today's submissions (filtered for Clinicians and specialized for COD)
$todaySubmissions = 0;
try {
    if ($role === 'Clinician' && checkCreatedByColumnExists()) {
        $stmt = $pdo->prepare("SELECT COUNT(*) AS count FROM patients WHERE DATE(created_at) = CURDATE() AND created_by = ?");
        $stmt->execute([$currentUser['id']]);
    } elseif ($role === 'COD' && checkCreatedByColumnExists()) {
        // COD sees today's submissions from all Clinicians
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) AS count FROM patients p 
             JOIN users u ON p.created_by = u.id 
             WHERE DATE(p.created_at) = CURDATE() AND u.role = 'Clinician'"
        );
        $stmt->execute();
} elseif ($role === 'Clinical Instructor' && checkCreatedByColumnExists()) {
        // Clinical Instructors see today's new assignments and completed work
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) AS count FROM patient_assignments pa 
             WHERE DATE(pa.assigned_at) = CURDATE() 
             AND pa.clinical_instructor_id = ? 
             AND pa.assignment_status IN ('accepted', 'completed')"
        );
        $stmt->execute([$currentUser['id']]);
    } else {
        $stmt = $pdo->query("SELECT COUNT(*) AS count FROM patients WHERE DATE(created_at) = CURDATE()");
    }
    $row = $stmt->fetch();
    $todaySubmissions = $row['count'] ?? 0;
} catch (PDOException $e) {
    error_log("Error fetching today's submissions: " . $e->getMessage());
}

// Count active medical alerts/pending items (specialized by role)
$activeAlerts = 0;
try {
    if ($role === 'Clinician' && checkCreatedByColumnExists()) {
        $stmt = $pdo->prepare("SELECT COUNT(*) AS count FROM patients WHERE status = 'Pending' AND created_by = ?");
        $stmt->execute([$currentUser['id']]);
    } elseif ($role === 'COD' && checkCreatedByColumnExists()) {
        // COD sees pending assignments (patients needing Clinical Instructor assignment)
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) AS count FROM patients p 
             JOIN users u ON p.created_by = u.id 
             LEFT JOIN patient_assignments pa ON p.id = pa.patient_id 
             WHERE u.role = 'Clinician' 
             AND (pa.assignment_status IS NULL OR pa.assignment_status = 'pending')"
        );
        $stmt->execute();
} elseif ($role === 'Clinical Instructor' && checkCreatedByColumnExists()) {
        // Clinical Instructors see patients needing attention (pending status)
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) AS count FROM patients p
             INNER JOIN patient_assignments pa ON p.id = pa.patient_id
             WHERE pa.clinical_instructor_id = ? 
             AND p.status = 'Pending'
             AND pa.assignment_status IN ('accepted', 'completed')"
        );
        $stmt->execute([$currentUser['id']]);
    } else {
        $stmt = $pdo->query("SELECT COUNT(*) AS count FROM patients WHERE status = 'Pending'");
    }
    $row = $stmt->fetch();
    $activeAlerts = $row['count'] ?? 0;
} catch (PDOException $e) {
    error_log("Error fetching active alerts: " . $e->getMessage());
}

// Get recent patients with filters - Use the same function as patients.php for consistency
$search = $_GET['search'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$statusFilter = $_GET['status'] ?? 'all';

// Pagination parameters
$page = max(1, (int)($_GET['page'] ?? 1));
$itemsPerPage = 6;

// Use the getPatients() function from config.php (same as patients.php)
// This ensures consistency between dashboard and patients page
$allFilteredPatients = getPatients($search, $statusFilter, $dateFrom, $dateTo, $role, $currentUser['id'] ?? null);
$totalRecentPatients = count($allFilteredPatients);
$totalPages = ceil($totalRecentPatients / $itemsPerPage);

// Get paginated results
$offset = ($page - 1) * $itemsPerPage;
$recentPatients = array_slice($allFilteredPatients, $offset, $itemsPerPage);

// Get monthly statistics for chart
$monthlyStats = [];
try {
    $stmt = $pdo->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as count
        FROM patients 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month ASC
    ");
    $monthlyStats = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching monthly stats: " . $e->getMessage());
}

// Get actual patient status distribution for chart
$statusApproved = 0;
$statusPending = 0;
$statusDisapproved = 0;

try {
    if ($role === 'Clinician' && checkCreatedByColumnExists()) {
        // Clinicians see only their own patients' status distribution
        $stmt = $pdo->prepare("
            SELECT 
                p.status,
                COUNT(*) as count
            FROM patients p
            WHERE p.created_by = ?
            GROUP BY p.status
        ");
        $stmt->execute([$currentUser['id']]);
    } elseif ($role === 'COD' && checkCreatedByColumnExists()) {
        // COD sees patients created by Clinicians
        $stmt = $pdo->prepare("
            SELECT 
                p.status,
                COUNT(*) as count
            FROM patients p
            JOIN users u ON p.created_by = u.id
            WHERE u.role = 'Clinician'
            GROUP BY p.status
        ");
        $stmt->execute();
    } elseif ($role === 'Clinical Instructor' && checkCreatedByColumnExists()) {
        // Clinical Instructors see assigned patients' status distribution (including completed)
        $stmt = $pdo->prepare("
            SELECT 
                p.status,
                COUNT(*) as count
            FROM patients p
            INNER JOIN patient_assignments pa ON p.id = pa.patient_id
            WHERE pa.clinical_instructor_id = ? AND pa.assignment_status IN ('accepted', 'completed')
            GROUP BY p.status
        ");
        $stmt->execute([$currentUser['id']]);
    } else {
        // Admin sees all patients' status distribution
        $stmt = $pdo->query("
            SELECT 
                p.status,
                COUNT(*) as count
            FROM patients p
            GROUP BY p.status
        ");
    }
    
    $statusCounts = $stmt->fetchAll();
    
    foreach ($statusCounts as $row) {
        switch ($row['status']) {
            case 'Approved':
                $statusApproved = $row['count'];
                break;
            case 'Pending':
                $statusPending = $row['count'];
                break;
            case 'Disapproved':
                $statusDisapproved = $row['count'];
                break;
        }
    }
} catch (PDOException $e) {
    error_log("Error fetching status distribution: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>iDENTify - Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.0.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="dark-mode-override.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="styles.css">
    <script>
        // Dark mode configuration
        tailwind.config = {
            darkMode: 'class',
        }
    </script>
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
</head>
<body class="bg-gradient-to-br from-violet-25 via-white to-purple-25 dark:bg-gradient-to-br dark:from-violet-950 dark:via-gray-900 dark:to-purple-950 transition-colors duration-200">

<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<!-- Main Content -->
<main class="ml-64 mt-[64px] p-6 min-h-screen bg-gradient-to-br from-violet-50 to-purple-50 dark:from-gray-900 dark:to-violet-900">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold bg-gradient-to-r from-violet-700 to-purple-700 bg-clip-text text-transparent dark:from-violet-300 dark:to-purple-300">Dashboard</h2>
                <?php if ($role !== 'Clinical Instructor' && $role !== 'COD'): ?>
                <a href="patients.php" class="bg-gradient-to-r from-violet-500 to-purple-600 hover:from-violet-600 hover:to-purple-700 text-white px-4 py-2 rounded-md flex items-center shadow-lg">
                    <i class="ri-add-line mr-2"></i>Add New Patient
                </a>
                <?php endif; ?>
            </div>

            <!-- Notification/Alert Box -->
            <div id="alertBox" class="mb-6 hidden">
                <div class="bg-gradient-to-r from-green-100 to-emerald-100 dark:from-green-900 dark:to-emerald-900 border border-green-400 dark:border-green-600 text-green-700 dark:text-green-200 px-4 py-3 rounded-lg shadow-lg relative">
                    <span id="alertMessage"></span>
                    <button onclick="hideAlert()" class="absolute top-0 bottom-0 right-0 px-4 py-3 text-green-600 hover:text-green-800 dark:text-green-300 dark:hover:text-green-100">
                        <i class="ri-close-line"></i>
                    </button>
                </div>
            </div>

            <!-- Clinical Instructor Procedure Details Reminder -->
            <?php if ($role === 'Clinical Instructor'): ?>
            <div class="mb-6">
                <div class="bg-gradient-to-r from-violet-100 to-purple-100 dark:from-violet-900 dark:to-purple-900 border border-violet-400 dark:border-violet-600 text-gray-900 dark:text-white px-4 py-3 rounded-lg shadow-lg">
                    <div class="flex items-center">
                        <i class="ri-stethoscope-line text-violet-500 dark:text-violet-400 mr-3 text-lg"></i>
                        <div class="flex-1">
                            <h3 class="font-medium text-gray-900 dark:text-white">Your Procedure Details</h3>
                            <p class="text-sm">
                                <?php if (!empty($currentUser['specialty_hint'])): ?>
                                    You're accepting patients for: <strong><?php echo htmlspecialchars($currentUser['specialty_hint']); ?></strong>
                                <?php else: ?>
                                    <span class="font-medium">Not specified</span> - COD will have difficulty assigning appropriate patients to you.
                                <?php endif; ?>
                            </p>
                        </div>
                        <a href="profile.php" class="ml-4 bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-700 hover:to-purple-700 text-white px-3 py-1 rounded text-sm shadow-lg transition-all duration-200">
                            <?php echo empty($currentUser['specialty_hint']) ? 'Set Procedure Details' : 'Update'; ?>
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Pending Transfer Requests Notification -->
            <?php 
            $transferCounts = getTransferRequestCounts($currentUser['id']);
            if ($transferCounts['incoming_pending'] > 0): 
            ?>
            <div class="mb-6">
                <div class="bg-gradient-to-r from-yellow-100 to-amber-100 dark:from-yellow-900 dark:to-amber-900 border border-yellow-400 dark:border-yellow-600 text-gray-900 dark:text-white px-4 py-3 rounded-lg shadow-lg">
                    <div class="flex items-center">
                        <i class="ri-exchange-line text-yellow-600 dark:text-yellow-400 mr-3 text-lg"></i>
                        <div class="flex-1">
                            <h3 class="font-medium text-gray-900 dark:text-white">Pending Transfer Requests</h3>
                            <p class="text-sm">
                                You have <strong><?php echo $transferCounts['incoming_pending']; ?></strong> pending patient transfer request<?php echo ($transferCounts['incoming_pending'] > 1) ? 's' : ''; ?> waiting for your review.
                            </p>
                        </div>
                        <a href="ci_patient_transfers.php" class="ml-4 bg-gradient-to-r from-yellow-600 to-amber-600 hover:from-yellow-700 hover:to-amber-700 text-white px-3 py-1 rounded text-sm shadow-lg transition-all duration-200">
                            View Requests
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8 stat-cards">
                <!-- Total Patients -->
                <div class="bg-gradient-to-br from-white to-violet-25 dark:from-gray-800 dark:to-violet-900 rounded-lg shadow-xl border border-violet-200 dark:border-violet-700 p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-gradient-to-r from-violet-100 to-purple-100 dark:from-violet-800 dark:to-purple-800 text-violet-600 dark:text-violet-300">
                            <i class="ri-user-line text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Total Patients</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo $totalPatients; ?></p>
                        </div>
                    </div>
                </div>

                <!-- Total Records -->
                <div class="bg-gradient-to-br from-white to-violet-25 dark:from-gray-800 dark:to-violet-900 rounded-lg shadow-xl border border-violet-200 dark:border-violet-700 p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-gradient-to-r from-green-100 to-emerald-100 dark:from-green-800 dark:to-emerald-800 text-green-600 dark:text-green-300">
                            <i class="ri-file-list-line text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Total Records</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo $totalRecords; ?></p>
                        </div>
                    </div>
                </div>

                <!-- Today's Submissions -->
                <div class="bg-gradient-to-br from-white to-violet-25 dark:from-gray-800 dark:to-violet-900 rounded-lg shadow-xl border border-violet-200 dark:border-violet-700 p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-gradient-to-r from-yellow-100 to-amber-100 dark:from-yellow-800 dark:to-amber-800 text-yellow-600 dark:text-yellow-300">
                            <i class="ri-calendar-line text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Today's Submissions</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo $todaySubmissions; ?></p>
                        </div>
                    </div>
                </div>

                <!-- Active Medical Alerts -->
                <div class="bg-gradient-to-br from-white to-violet-25 dark:from-gray-800 dark:to-violet-900 rounded-lg shadow-xl border border-violet-200 dark:border-violet-700 p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-gradient-to-r from-red-100 to-pink-100 dark:from-red-800 dark:to-pink-800 text-red-600 dark:text-red-300">
                            <i class="ri-alert-line text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Active Medical Alerts</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo $activeAlerts; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Monthly Submissions Chart -->
                <div class="bg-gradient-to-br from-white to-violet-25 dark:from-gray-800 dark:to-violet-900 rounded-lg shadow-xl border border-violet-200 dark:border-violet-700 p-6">
                    <h3 class="text-lg font-semibold bg-gradient-to-r from-violet-700 to-purple-700 bg-clip-text text-transparent dark:from-violet-300 dark:to-purple-300 mb-4">Monthly Submissions</h3>
                    <div class="h-64 relative">
                        <canvas id="monthlyChart"></canvas>
                    </div>
                </div>

                <!-- Status Distribution -->
                <div class="bg-gradient-to-br from-white to-violet-25 dark:from-gray-800 dark:to-violet-900 rounded-lg shadow-xl border border-violet-200 dark:border-violet-700 p-6">
                    <h3 class="text-lg font-semibold bg-gradient-to-r from-violet-700 to-purple-700 bg-clip-text text-transparent dark:from-violet-300 dark:to-purple-300 mb-4">Patient Status Distribution</h3>
                    <div class="h-64 relative">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Search and Filter Form -->
            <div class="bg-gradient-to-r from-white to-violet-50 dark:from-gray-800 dark:to-violet-900 rounded-lg shadow-lg border border-violet-200 dark:border-violet-700 p-6 mb-6">
                <h3 class="text-lg font-semibold bg-gradient-to-r from-violet-700 to-purple-700 bg-clip-text text-transparent dark:from-violet-300 dark:to-purple-300 mb-4">Search & Filter Records</h3>
                <form method="GET" onsubmit="return updateDashboardFilters()" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-2">Patient Name</label>
                        <input type="text" name="search" placeholder="Search by name or email..." 
                               class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-violet-900 dark:text-white" 
                               value="<?php echo htmlspecialchars($search); ?>">
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
                        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-2">Status</label>
                        <select name="status" class="w-full px-3 py-2 border border-violet-300 dark:border-violet-600 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500 dark:bg-violet-900 dark:text-white">
                            <option value="all" <?php echo ($statusFilter === 'all') ? 'selected' : ''; ?>>All Status</option>
                            <option value="Approved" <?php echo ($statusFilter === 'Approved') ? 'selected' : ''; ?>>Approved</option>
                            <option value="Pending" <?php echo ($statusFilter === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="Disapproved" <?php echo ($statusFilter === 'Disapproved') ? 'selected' : ''; ?>>Declined</option>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="w-full bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-700 hover:to-purple-700 text-white px-4 py-2 rounded-md transition-all duration-200 shadow-lg">
                            <i class="ri-search-line mr-2"></i>Search
                        </button>
                    </div>
                </form>
            </div>

            <!-- Recent Patients Table -->
            <div class="bg-gradient-to-br from-white to-violet-25 dark:from-gray-800 dark:to-violet-900 rounded-lg shadow-lg border border-violet-200 dark:border-violet-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-violet-200 dark:border-violet-700 bg-gradient-to-r from-violet-50 to-purple-50 dark:from-violet-900 dark:to-purple-900">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-semibold bg-gradient-to-r from-violet-700 to-purple-700 bg-clip-text text-transparent dark:from-violet-300 dark:to-purple-300">Recent Patient Records</h3>
                        <div id="dashboardHeaderInfo" class="flex items-center space-x-4">
                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                Total: <span class="total-records"><?php echo $totalRecentPatients; ?></span>
                            </span>
                            <span class="page-info text-sm text-gray-700 dark:text-gray-300" <?php echo ($totalPages <= 1) ? 'style="display:none;"' : ''; ?>>
                                (Page <?php echo $page; ?> of <?php echo $totalPages; ?>)
                            </span>
                        </div>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table id="recentPatientsTable" class="min-w-full divide-y divide-violet-200 dark:divide-violet-700">
                        <thead class="bg-gradient-to-r from-violet-100 to-purple-100 dark:from-violet-800 dark:to-purple-800">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">Patient Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">ID Number</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">Date Created</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">Assigned Clinician</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">Status</th>
                                <?php if ($role !== 'COD'): ?>
                                <th class="px-6 py-3 text-left text-xs font-medium text-black dark:text-white uppercase tracking-wider">Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody class="bg-gradient-to-b from-white to-violet-25 dark:from-gray-800 dark:to-violet-900 divide-y divide-violet-200 dark:divide-violet-700">
                            <?php if (!empty($recentPatients)): ?>
                                <?php foreach ($recentPatients as $patient): ?>
                                    <tr class="hover:bg-gradient-to-r hover:from-violet-50 hover:to-purple-50 dark:hover:from-violet-900 dark:hover:to-purple-900">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                            <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                            #<?php echo str_pad($patient['id'], 4, '0', STR_PAD_LEFT); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                            <?php echo date('M d, Y', strtotime($patient['created_at'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                            <?php echo htmlspecialchars($patient['created_by_name'] ?? 'Unassigned'); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                                <?php 
                                                echo $patient['status'] === 'Approved' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 
                                                     ($patient['status'] === 'Pending' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : 
                                                      'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'); 
                                                ?>">
                                                <?php echo htmlspecialchars($patient['status'] === 'Disapproved' ? 'Declined' : $patient['status']); ?>
                                            </span>
                                        </td>
                                        <?php if ($role !== 'COD'): ?>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex space-x-2">
                                                <a href="view_patient.php?id=<?php echo $patient['id']; ?>" 
                                                   class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-200" title="View">
                                                    <i class="ri-eye-line"></i>
                                                </a>
                                                <?php if ($role === 'Admin' || $role === 'Clinician'): ?>
                                                    <a href="edit_patient.php?id=<?php echo $patient['id']; ?>" 
                                                       class="text-green-600 dark:text-green-400 hover:text-green-900 dark:hover:text-green-200" title="Edit">
                                                        <i class="ri-edit-line"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if ($role === 'Admin'): ?>
                                                    <button onclick="deletePatient(<?php echo $patient['id']; ?>)" 
                                                            class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-200" title="Delete">
                                                        <i class="ri-delete-bin-line"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?php echo ($role === 'COD') ? '5' : '6'; ?>" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                        No patient records found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination Controls -->
                <div id="recentPatientsPagination">
                <?php if ($totalPages > 1): ?>
                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex flex-col sm:flex-row justify-between items-center space-y-3 sm:space-y-0">
                        <!-- Results Info -->
                        <div class="text-sm text-gray-700 dark:text-gray-300">
                            Showing <?php echo (($page - 1) * $itemsPerPage + 1); ?> to <?php echo min($page * $itemsPerPage, $totalRecentPatients); ?> of <?php echo $totalRecentPatients; ?> results
                        </div>
                        
                        <!-- Pagination Buttons -->
                        <div class="flex items-center space-x-2">
                            <?php if ($page > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                                   class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300">
                                    <i class="ri-arrow-left-line mr-1"></i>Previous
                                </a>
                            <?php else: ?>
                                <span class="px-3 py-2 text-sm bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md text-gray-400 dark:text-gray-600 cursor-not-allowed">
                                    <i class="ri-arrow-left-line mr-1"></i>Previous
                                </span>
                            <?php endif; ?>
                            
                            <!-- Page Numbers -->
                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            
                            if ($startPage > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" 
                                   class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300">1</a>
                                <?php if ($startPage > 2): ?>
                                    <span class="px-2 text-gray-500 dark:text-gray-400">...</span>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <?php if ($i == $page): ?>
                                    <span class="px-3 py-2 text-sm bg-blue-600 text-white border border-blue-600 rounded-md"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                       class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($endPage < $totalPages): ?>
                                <?php if ($endPage < $totalPages - 1): ?>
                                    <span class="px-2 text-gray-500 dark:text-gray-400">...</span>
                                <?php endif; ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $totalPages])); ?>" 
                                   class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300"><?php echo $totalPages; ?></a>
                            <?php endif; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                                   class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300">
                                    Next<i class="ri-arrow-right-line ml-1"></i>
                                </a>
                            <?php else: ?>
                                <span class="px-3 py-2 text-sm bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md text-gray-400 dark:text-gray-600 cursor-not-allowed">
                                    Next<i class="ri-arrow-right-line ml-1"></i>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                </div>
            </div>

            <!-- Floating Add Button (Mobile) -->
            <?php if ($role !== 'Clinical Instructor' && $role !== 'COD'): ?>
            <div class="fixed bottom-6 right-6 md:hidden">
                <a href="patients.php" class="bg-blue-600 hover:bg-blue-700 text-white p-4 rounded-full shadow-lg">
                    <i class="ri-add-line text-xl"></i>
                </a>
            </div>
            <?php endif; ?>
</main>

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
            
            // Reinitialize charts with new colors when dark mode toggles
            setTimeout(initializeCharts, 100);
        });

        // Alert functions
        function showAlert(message, type = 'success') {
            const alertBox = document.getElementById('alertBox');
            const alertMessage = document.getElementById('alertMessage');
            
            alertBox.className = `mb-6 ${type === 'success' ? 'bg-green-100 dark:bg-green-900 border-green-400 dark:border-green-600 text-green-700 dark:text-green-200' : 
                                            'bg-red-100 dark:bg-red-900 border-red-400 dark:border-red-600 text-red-700 dark:text-red-200'} border px-4 py-3 rounded relative`;
            alertMessage.textContent = message;
            alertBox.classList.remove('hidden');
        }

        function hideAlert() {
            document.getElementById('alertBox').classList.add('hidden');
        }

        // Action functions
        function generateReport(patientId) {
            showAlert('Generating report for patient ID: ' + patientId);
            // Add actual report generation logic here
        }

        function deletePatient(patientId) {
            // Get patient name from the table row for a more personalized message
            const row = document.querySelector(`button[onclick*="deletePatient(${patientId})"]`).closest('tr');
            const patientName = row ? row.querySelector('td:nth-child(2)').textContent.trim() : 'this patient';
            
            showDeleteModal(
                `Are you sure you want to delete <strong>${patientName}</strong>?<br><br><span class="text-red-600 dark:text-red-400 text-sm">⚠️ This action cannot be undone and will permanently remove all patient data including medical records.</span>`,
                function() {
                    // TODO: Implement actual delete logic here
                    // This should make an AJAX call to delete the patient from the database
                    showAlert('Patient "' + patientName + '" deleted successfully');
                    
                    // Remove the row from the table
                    if (row) {
                        row.remove();
                    }
                    
                    // Optionally reload the page or refresh the table
                    // location.reload();
                }
            );
        }

        // Store chart instances globally
        let monthlyChartInstance = null;
        let statusChartInstance = null;
        
        // AJAX Pagination Functions
        let currentFilters = {
            search: '<?php echo addslashes($search); ?>',
            date_from: '<?php echo addslashes($dateFrom); ?>',
            date_to: '<?php echo addslashes($dateTo); ?>',
            status: '<?php echo addslashes($statusFilter); ?>'
        };

        function loadDashboardPage(page) {
            // Show loading state
            const tableBody = document.querySelector('#recentPatientsTable tbody');
            const paginationContainer = document.querySelector('#recentPatientsPagination');
            const userRole = '<?php echo $role; ?>';
            const colspan = userRole === 'COD' ? '5' : '6';
            
            if (tableBody) {
                tableBody.innerHTML = '<tr><td colspan="' + colspan + '" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400"><i class="ri-loader-2-line animate-spin mr-2"></i>Loading...</td></tr>';
            }
            
            // Build query parameters
            const params = new URLSearchParams({
                page: page,
                ...currentFilters
            });
            
            // Make AJAX request
            fetch(`ajax_dashboard.php?${params.toString()}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update table content
                        if (tableBody) {
                            tableBody.innerHTML = data.tableHTML;
                        }
                        
                        // Update pagination
                        if (paginationContainer) {
                            paginationContainer.innerHTML = data.paginationHTML;
                        }
                        
                        // Update header info
                        const headerInfo = document.querySelector('#dashboardHeaderInfo');
                        if (headerInfo && data.totalRecords) {
                            const totalSpan = headerInfo.querySelector('.total-records');
                            const pageSpan = headerInfo.querySelector('.page-info');
                            
                            if (totalSpan) {
                                totalSpan.textContent = data.totalRecords;
                            }
                            
                            if (pageSpan) {
                                if (data.totalPages > 1) {
                                    pageSpan.textContent = `(Page ${data.currentPage} of ${data.totalPages})`;
                                    pageSpan.style.display = 'inline';
                                } else {
                                    pageSpan.style.display = 'none';
                                }
                            }
                        }
                        
                        // Smooth scroll to table
                        document.querySelector('#recentPatientsTable').scrollIntoView({
                            behavior: 'smooth',
                            block: 'nearest'
                        });
                        
                    } else {
                        console.error('Error loading dashboard page:', data.error);
                        showAlert('Error loading page: ' + (data.error || 'Unknown error'), 'error');
                    }
                })
                .catch(error => {
                    console.error('Network error:', error);
                    showAlert('Network error occurred. Please try again.', 'error');
                    
                    // Restore table content on error
                    const userRole = '<?php echo $role; ?>';
                    const colspan = userRole === 'COD' ? '5' : '6';
                    if (tableBody) {
                        tableBody.innerHTML = '<tr><td colspan="' + colspan + '" class="px-6 py-4 text-center text-red-500 dark:text-red-400">Error loading data. Please refresh the page.</td></tr>';
                    }
                });
        }

        // Update filters when search form is submitted
        function updateDashboardFilters() {
            const form = document.querySelector('form[method="GET"]');
            if (form) {
                const formData = new FormData(form);
                currentFilters = {
                    search: formData.get('search') || '',
                    date_from: formData.get('date_from') || '',
                    date_to: formData.get('date_to') || '',
                    status: formData.get('status') || 'all'
                };
                
                // Load first page with new filters
                loadDashboardPage(1);
                return false; // Prevent form submission
            }
        }

        // Chart initialization - wait for DOM to be ready
        function initializeCharts() {
            console.log('Initializing charts...');
            
            // Check if Chart.js is loaded
            if (typeof Chart === 'undefined') {
                console.error('Chart.js library not loaded! Retrying in 1 second...');
                setTimeout(initializeCharts, 1000);
                return;
            }
            
            // Destroy existing chart instances if they exist
            if (monthlyChartInstance) {
                monthlyChartInstance.destroy();
            }
            if (statusChartInstance) {
                statusChartInstance.destroy();
            }
            
            // Detect dark mode
            const isDarkMode = document.documentElement.classList.contains('dark');
            const textColor = isDarkMode ? '#FFFFFF' : '#374151';
            const gridColor = isDarkMode ? 'rgba(255, 255, 255, 0.1)' : 'rgba(156, 163, 175, 0.3)';
            
            // Monthly chart
            const monthlyCanvas = document.getElementById('monthlyChart');
            if (!monthlyCanvas) {
                console.error('Monthly chart canvas not found!');
                return;
            }
            const ctx1 = monthlyCanvas.getContext('2d');
            monthlyChartInstance = new Chart(ctx1, {
            type: 'bar',
            data: {
                labels: [<?php 
                    $labels = [];
                    foreach ($monthlyStats as $stat) {
                        $labels[] = "'" . date('M Y', strtotime($stat['month'] . '-01')) . "'";
                    }
                    echo implode(', ', $labels);
                ?>],
                datasets: [{
                    label: 'Submissions',
                    data: [<?php 
                        $data = [];
                        foreach ($monthlyStats as $stat) {
                            $data[] = $stat['count'];
                        }
                        echo implode(', ', $data);
                    ?>],
                    backgroundColor: 'rgba(59, 130, 246, 0.8)',
                    borderColor: 'rgba(59, 130, 246, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: gridColor
                        },
                        ticks: {
                            color: textColor,
                            font: {
                                size: 12
                            }
                        }
                    },
                    x: {
                        grid: {
                            color: gridColor
                        },
                        ticks: {
                            color: textColor,
                            font: {
                                size: 12
                            }
                        }
                    }
                }
            }
        });

            // Status distribution chart
            const statusApproved = <?php echo (int)$statusApproved; ?>;
            const statusPending = <?php echo (int)$statusPending; ?>;
            const statusDisapproved = <?php echo (int)$statusDisapproved; ?>;
            
            console.log('Status counts - Approved:', statusApproved, 'Pending:', statusPending, 'Disapproved:', statusDisapproved);
            
            const statusCanvas = document.getElementById('statusChart');
            if (!statusCanvas) {
                console.error('Status chart canvas not found!');
                return;
            }
            
            // Check if there's data to display
            const totalStatus = statusApproved + statusPending + statusDisapproved;
            if (totalStatus === 0) {
                console.log('No patient data to display in status chart');
                // Display a message instead of empty chart
                const chartContainer = statusCanvas.parentElement;
                chartContainer.innerHTML = '<div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #9CA3AF;"><div style="text-align: center;"><i class="ri-pie-chart-line" style="font-size: 3rem; display: block; margin-bottom: 1rem;"></i><p>No patient data available</p><p style="font-size: 0.875rem; margin-top: 0.5rem;">Add some patients to see the status distribution</p></div></div>';
                return;
            }
            
            const ctx2 = statusCanvas.getContext('2d');
            statusChartInstance = new Chart(ctx2, {
            type: 'doughnut',
            data: {
                labels: ['Approved', 'Pending', 'Declined'],
                datasets: [{
                    data: [
                        statusApproved, // Approved (actual count from database)
                        statusPending, // Pending (actual count from database)
                        statusDisapproved // Disapproved (actual count from database)
                    ],
                    backgroundColor: [
                        'rgba(34, 197, 94, 0.8)',
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(239, 68, 68, 0.8)'
                    ],
                    borderColor: [
                        'rgba(34, 197, 94, 1)',
                        'rgba(245, 158, 11, 1)',
                        'rgba(239, 68, 68, 1)'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: textColor,
                            font: {
                                size: 12
                            },
                            padding: 10
                        }
                    }
                }
            }
            });
            
            console.log('Charts initialized successfully!');
        }
        
        // Initialize charts when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initializeCharts);
        } else {
            // DOM is already ready, call the function directly
            initializeCharts();
        }

        // Auto-refresh functionality
        setInterval(() => {
            // You can add AJAX calls here to refresh data periodically
        }, 30000); // Refresh every 30 seconds
    </script>

    <?php include 'includes/logout_modal.php'; ?>
    <?php include 'includes/delete_modal.php'; ?>
</body>
</html>
