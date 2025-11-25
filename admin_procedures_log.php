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
$exactDate = $_GET['exact_date'] ?? '';
$searchKeyword = $_GET['search'] ?? '';
$filterClinician = $_GET['filter_clinician'] ?? '';
$filterCI = $_GET['filter_ci'] ?? '';
$filterPatientName = $_GET['filter_patient_name'] ?? '';
$filterAge = $_GET['filter_age'] ?? '';
$filterSex = $_GET['filter_sex'] ?? '';
$filterStatus = $_GET['filter_status'] ?? '';
$filterPatientStatus = $_GET['filter_patient_status'] ?? '';
$filterChair = $_GET['filter_chair'] ?? '';

// Check if this is the first load (no filters applied)
$isFirstLoad = empty($startDate) && empty($endDate) && empty($exactDate) && empty($searchKeyword) 
    && empty($filterClinician) && empty($filterCI) && empty($filterPatientName) && empty($filterAge) 
    && empty($filterSex) && empty($filterStatus) && empty($filterPatientStatus) && empty($filterChair) 
    && !isset($_GET['show_all']);

// If first load, default to today's date only
if ($isFirstLoad) {
    $startDate = date('Y-m-d');
    $endDate = date('Y-m-d');
}

// Get unique values for filter dropdowns
try {
    // Get all clinicians
    $cliniciansStmt = $pdo->prepare("SELECT DISTINCT pl.clinician_name FROM procedure_logs pl WHERE pl.clinician_name IS NOT NULL AND pl.clinician_name != '' ORDER BY pl.clinician_name ASC");
    $cliniciansStmt->execute();
    $allClinicians = $cliniciansStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Add "Administrator" to the list if not already present
    if (!in_array('Administrator', $allClinicians)) {
        $allClinicians[] = 'Administrator';
        sort($allClinicians); // Re-sort to maintain alphabetical order
    }
    
    // Get all clinical instructors
    $ciStmt = $pdo->prepare("
        SELECT DISTINCT COALESCE(u_ci_proc.full_name, u_ci_patient.full_name) as ci_name
        FROM procedure_logs pl
        LEFT JOIN patients p ON pl.patient_id = p.id
        LEFT JOIN procedure_assignments pra ON pl.id = pra.procedure_log_id AND pra.assignment_status = 'accepted'
        LEFT JOIN users u_ci_proc ON pra.clinical_instructor_id = u_ci_proc.id
        LEFT JOIN (
            SELECT pa1.patient_id, pa1.clinical_instructor_id
            FROM patient_assignments pa1
            INNER JOIN (
                SELECT patient_id, MAX(assigned_at) as max_assigned_at
                FROM patient_assignments
                WHERE assignment_status IN ('accepted', 'completed')
                GROUP BY patient_id
            ) pa2 ON pa1.patient_id = pa2.patient_id 
                AND pa1.assigned_at = pa2.max_assigned_at
                AND pa1.assignment_status IN ('accepted', 'completed')
        ) pa_latest ON p.id = pa_latest.patient_id AND pra.id IS NULL
        LEFT JOIN users u_ci_patient ON pa_latest.clinical_instructor_id = u_ci_patient.id
        WHERE COALESCE(u_ci_proc.full_name, u_ci_patient.full_name) IS NOT NULL
        ORDER BY ci_name ASC
    ");
    $ciStmt->execute();
    $allCIs = $ciStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get unique sex values
    $sexStmt = $pdo->prepare("SELECT DISTINCT pl.sex FROM procedure_logs pl WHERE pl.sex IS NOT NULL AND pl.sex != '' ORDER BY pl.sex ASC");
    $sexStmt->execute();
    $allSexes = $sexStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get unique status values
    $statusStmt = $pdo->prepare("SELECT DISTINCT pl.status FROM procedure_logs pl WHERE pl.status IS NOT NULL AND pl.status != '' ORDER BY pl.status ASC");
    $statusStmt->execute();
    $allStatuses = $statusStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get unique chair numbers
    $chairStmt = $pdo->prepare("SELECT DISTINCT pl.chair_number FROM procedure_logs pl WHERE pl.chair_number IS NOT NULL AND pl.chair_number != '' ORDER BY CAST(pl.chair_number AS UNSIGNED), pl.chair_number ASC");
    $chairStmt->execute();
    $allChairs = $chairStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Error fetching filter options: " . $e->getMessage());
    $allClinicians = [];
    $allCIs = [];
    $allSexes = [];
    $allStatuses = [];
    $allChairs = [];
}

// Build query to fetch procedure logs with related information
// Priority: Use procedure_assignments to get the CI who accepted the procedure
// This prevents duplicates when a patient has multiple patient assignments
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
    COALESCE(pn.remarks, pl.remarks) as remarks,
    pl.clinician_name,
    pl.logged_at,
    u_clinician.id as clinician_id,
    u_clinician.full_name as clinician_full_name,
    COALESCE(u_ci_proc.full_name, u_ci_patient.full_name) as clinical_instructor_name,
    DATE(pl.logged_at) as log_date,
    (
        SELECT CASE 
            WHEN MAX(pn_sub.date) IS NULL THEN 'Active'
            WHEN MAX(pn_sub.date) >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR) THEN 'Active'
            ELSE 'Inactive'
        END
        FROM progress_notes pn_sub
        WHERE pn_sub.patient_id = p.id
    ) AS patient_status
FROM procedure_logs pl
LEFT JOIN users u_clinician ON pl.clinician_id = u_clinician.id
LEFT JOIN patients p ON pl.patient_id = p.id
-- Primary: Get CI from procedure_assignments (the CI who accepted this specific procedure)
LEFT JOIN procedure_assignments pra ON pl.id = pra.procedure_log_id 
    AND pra.assignment_status = 'accepted'
LEFT JOIN users u_ci_proc ON pra.clinical_instructor_id = u_ci_proc.id
-- Fallback: Get CI from patient_assignments (only if no procedure assignment exists)
-- Get the most recent accepted/completed patient assignment using a correlated subquery
LEFT JOIN (
    SELECT pa1.patient_id, pa1.clinical_instructor_id
    FROM patient_assignments pa1
    INNER JOIN (
        SELECT patient_id, MAX(assigned_at) as max_assigned_at
        FROM patient_assignments
        WHERE assignment_status IN ('accepted', 'completed')
        GROUP BY patient_id
    ) pa2 ON pa1.patient_id = pa2.patient_id 
        AND pa1.assigned_at = pa2.max_assigned_at
        AND pa1.assignment_status IN ('accepted', 'completed')
) pa_latest ON p.id = pa_latest.patient_id AND pra.id IS NULL
LEFT JOIN users u_ci_patient ON pa_latest.clinical_instructor_id = u_ci_patient.id
LEFT JOIN progress_notes pn ON pl.id = pn.procedure_log_id
WHERE 1=1";

$params = [];

// Apply date filters
if (!empty($exactDate)) {
    // Exact date filter takes priority over date range
    $sql .= " AND DATE(pl.logged_at) = ?";
    $params[] = $exactDate;
} else {
    if (!empty($startDate)) {
        $sql .= " AND DATE(pl.logged_at) >= ?";
        $params[] = $startDate;
    }

    if (!empty($endDate)) {
        $sql .= " AND DATE(pl.logged_at) <= ?";
        $params[] = $endDate;
    }
}

// Apply advanced filters
if (!empty($filterClinician)) {
    $sql .= " AND pl.clinician_name = ?";
    $params[] = $filterClinician;
}

if (!empty($filterCI)) {
    $sql .= " AND COALESCE(u_ci_proc.full_name, u_ci_patient.full_name) = ?";
    $params[] = $filterCI;
}

if (!empty($filterPatientName)) {
    $sql .= " AND pl.patient_name LIKE ?";
    $params[] = "%$filterPatientName%";
}

if (!empty($filterAge)) {
    $sql .= " AND pl.age = ?";
    $params[] = (int)$filterAge;
}

if (!empty($filterSex)) {
    $sql .= " AND pl.sex = ?";
    $params[] = $filterSex;
}

if (!empty($filterStatus)) {
    $sql .= " AND pl.status = ?";
    $params[] = $filterStatus;
}

if (!empty($filterPatientStatus)) {
    // Filter by patient status requires a subquery or HAVING clause
    // We'll use a subquery approach
    if ($filterPatientStatus === 'Active') {
        $sql .= " AND (
            SELECT CASE 
                WHEN MAX(pn_filter.date) IS NULL THEN 'Active'
                WHEN MAX(pn_filter.date) >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR) THEN 'Active'
                ELSE 'Inactive'
            END
            FROM progress_notes pn_filter
            WHERE pn_filter.patient_id = p.id
        ) = 'Active'";
    } else {
        $sql .= " AND (
            SELECT CASE 
                WHEN MAX(pn_filter.date) IS NULL THEN 'Active'
                WHEN MAX(pn_filter.date) >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR) THEN 'Active'
                ELSE 'Inactive'
            END
            FROM progress_notes pn_filter
            WHERE pn_filter.patient_id = p.id
        ) = 'Inactive'";
    }
}

if (!empty($filterChair)) {
    $sql .= " AND pl.chair_number = ?";
    $params[] = $filterChair;
}

// Apply search filter (general search)
if (!empty($searchKeyword)) {
    $sql .= " AND (
        pl.patient_name LIKE ? OR
        pl.clinician_name LIKE ? OR
        COALESCE(u_ci_proc.full_name, u_ci_patient.full_name) LIKE ? OR
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
            class="flex-1 bg-gradient-to-br from-violet-50 to-purple-50 dark:from-gray-900 dark:to-violet-900 p-6 main-content overflow-y-auto min-h-screen">
            <div class="w-full">
                <!-- Header Section -->
                <div class="mb-6 no-print">
                    <h2
                        class="text-3xl font-bold bg-gradient-to-r from-violet-700 to-purple-700 bg-clip-text text-transparent dark:from-violet-300 dark:to-purple-300">
                        Dental Dispensary Procedures Log Report
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400 mt-2">
                        <?php if ($isFirstLoad): ?>
                            <i class="ri-calendar-check-line mr-1"></i>Showing <strong>today's procedures only</strong>
                            (<?php echo date('F j, Y'); ?>)
                        <?php else: ?>
                            View and filter all procedure logs from clinicians
                        <?php endif; ?>
                    </p>
                </div>

                <!-- Advanced Filters Section -->
                <div
                    class="bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-violet-200 dark:border-violet-700 p-6 mb-6 no-print">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">
                            <i class="ri-filter-3-line mr-2"></i>Advanced Filter Options
                        </h3>
                        <button type="button" onclick="toggleAdvancedFilters()" id="toggleFiltersBtn"
                            class="text-sm text-violet-600 dark:text-violet-400 hover:text-violet-800 dark:hover:text-violet-300">
                            <i class="ri-arrow-down-s-line mr-1" id="toggleFiltersIcon"></i>
                            <span id="toggleFiltersText">Show Filters</span>
                        </button>
                    </div>
                    <form method="GET" id="advancedFiltersForm" class="hidden">
                        <?php if (isset($_GET['show_all'])): ?>
                            <input type="hidden" name="show_all" value="1">
                        <?php endif; ?>
                        <!-- Date Filters Row -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    <i class="ri-calendar-line mr-1"></i>Exact Date
                                </label>
                                <input type="date" name="exact_date" value="<?php echo htmlspecialchars($exactDate); ?>"
                                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-violet-500 dark:bg-gray-700 dark:text-white"
                                    onchange="document.querySelector('[name=start_date]').value=''; document.querySelector('[name=end_date]').value='';">
                                <small class="text-xs text-gray-500 dark:text-gray-400 mt-1 block">Overrides date range</small>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    <i class="ri-calendar-check-line mr-1"></i>Start Date
                                </label>
                                <input type="date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>"
                                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-violet-500 dark:bg-gray-700 dark:text-white"
                                    onchange="document.querySelector('[name=exact_date]').value='';">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    <i class="ri-calendar-close-line mr-1"></i>End Date
                                </label>
                                <input type="date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>"
                                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-violet-500 dark:bg-gray-700 dark:text-white"
                                    onchange="document.querySelector('[name=exact_date]').value='';">
                            </div>
                        </div>

                        <!-- People Filters Row -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    <i class="ri-user-line mr-1"></i>Clinician
                                </label>
                                <select name="filter_clinician"
                                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-violet-500 dark:bg-gray-700 dark:text-white">
                                    <option value="">All Clinicians</option>
                                    <?php foreach ($allClinicians as $clinician): ?>
                                        <option value="<?php echo htmlspecialchars($clinician); ?>"
                                            <?php echo ($filterClinician === $clinician) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($clinician); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    <i class="ri-user-star-line mr-1"></i>Clinical Instructor
                                </label>
                                <select name="filter_ci"
                                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-violet-500 dark:bg-gray-700 dark:text-white">
                                    <option value="">All Clinical Instructors</option>
                                    <?php foreach ($allCIs as $ci): ?>
                                        <option value="<?php echo htmlspecialchars($ci); ?>"
                                            <?php echo ($filterCI === $ci) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($ci); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    <i class="ri-user-heart-line mr-1"></i>Patient Name
                                </label>
                                <input type="text" name="filter_patient_name" value="<?php echo htmlspecialchars($filterPatientName); ?>"
                                    placeholder="Enter patient name..."
                                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-violet-500 dark:bg-gray-700 dark:text-white">
                            </div>
                        </div>

                        <!-- Patient Details Filters Row -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    <i class="ri-number-1 mr-1"></i>Age
                                </label>
                                <input type="number" name="filter_age" value="<?php echo htmlspecialchars($filterAge); ?>"
                                    placeholder="Exact age..."
                                    min="0" max="150"
                                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-violet-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    <i class="ri-genderless-line mr-1"></i>Sex
                                </label>
                                <select name="filter_sex"
                                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-violet-500 dark:bg-gray-700 dark:text-white">
                                    <option value="">All</option>
                                    <?php foreach ($allSexes as $sex): ?>
                                        <option value="<?php echo htmlspecialchars($sex); ?>"
                                            <?php echo ($filterSex === $sex) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($sex); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    <i class="ri-chair-line mr-1"></i>Chair Number
                                </label>
                                <select name="filter_chair"
                                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-violet-500 dark:bg-gray-700 dark:text-white">
                                    <option value="">All Chairs</option>
                                    <?php foreach ($allChairs as $chair): ?>
                                        <option value="<?php echo htmlspecialchars($chair); ?>"
                                            <?php echo ($filterChair === $chair) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($chair); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Status Filters Row -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    <i class="ri-checkbox-circle-line mr-1"></i>Procedure Status
                                </label>
                                <select name="filter_status"
                                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-violet-500 dark:bg-gray-700 dark:text-white">
                                    <option value="">All Statuses</option>
                                    <?php foreach ($allStatuses as $status): ?>
                                        <option value="<?php echo htmlspecialchars($status); ?>"
                                            <?php echo ($filterStatus === $status) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($status); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    <i class="ri-heart-pulse-line mr-1"></i>Patient Status
                                </label>
                                <select name="filter_patient_status"
                                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-violet-500 dark:bg-gray-700 dark:text-white">
                                    <option value="">All Patient Statuses</option>
                                    <option value="Active" <?php echo ($filterPatientStatus === 'Active') ? 'selected' : ''; ?>>Active</option>
                                    <option value="Inactive" <?php echo ($filterPatientStatus === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    <i class="ri-search-line mr-1"></i>General Search
                                </label>
                                <input type="text" name="search" value="<?php echo htmlspecialchars($searchKeyword); ?>"
                                    placeholder="Search patient, clinician, procedure..."
                                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-violet-500 dark:bg-gray-700 dark:text-white">
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex flex-wrap justify-between items-center gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <div class="flex space-x-2">
                                <a href="admin_procedures_log.php"
                                    class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-lg hover:bg-gray-400 dark:hover:bg-gray-500 transition-colors text-sm">
                                    <i class="ri-refresh-line mr-2"></i>Today Only
                                </a>
                                <a href="admin_procedures_log.php?show_all=1"
                                    class="px-4 py-2 bg-blue-500 dark:bg-blue-600 text-white rounded-lg hover:bg-blue-600 dark:hover:bg-blue-700 transition-colors text-sm">
                                    <i class="ri-calendar-line mr-2"></i>Show All Records
                                </a>
                                <button type="button" onclick="clearAllFilters()"
                                    class="px-4 py-2 bg-yellow-500 dark:bg-yellow-600 text-white rounded-lg hover:bg-yellow-600 dark:hover:bg-yellow-700 transition-colors text-sm">
                                    <i class="ri-close-circle-line mr-2"></i>Clear All
                                </button>
                            </div>
                            <button type="submit"
                                class="px-6 py-2 bg-gradient-to-r from-violet-600 to-purple-600 text-white rounded-lg hover:from-violet-700 hover:to-purple-700 transition-all shadow-md text-sm">
                                <i class="ri-search-line mr-2"></i>Apply Filters
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Results Section -->
                <div
                    class="bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-violet-200 dark:border-violet-700 p-6">
                    <div class="mb-4 flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">
                            <?php if ($isFirstLoad): ?>
                                <i class="ri-calendar-check-line text-blue-600 dark:text-blue-400 mr-2"></i>Today's
                                Procedures
                            <?php else: ?>
                                Dental Dispensary Procedures Log Report
                            <?php endif; ?>
                            <span class="text-sm font-normal text-gray-600 dark:text-gray-400">
                                (<?php echo count($procedureLogs); ?>
                                <?php echo count($procedureLogs) === 1 ? 'item' : 'items'; ?>)
                            </span>
                        </h3>
                        <a href="<?php 
                            // Build export URL with all current filter parameters
                            $exportParams = [];
                            if (!empty($startDate)) $exportParams['start_date'] = $startDate;
                            if (!empty($endDate)) $exportParams['end_date'] = $endDate;
                            if (!empty($exactDate)) $exportParams['exact_date'] = $exactDate;
                            if (!empty($searchKeyword)) $exportParams['search'] = $searchKeyword;
                            if (!empty($filterClinician)) $exportParams['filter_clinician'] = $filterClinician;
                            if (!empty($filterCI)) $exportParams['filter_ci'] = $filterCI;
                            if (!empty($filterPatientName)) $exportParams['filter_patient_name'] = $filterPatientName;
                            if (!empty($filterAge)) $exportParams['filter_age'] = $filterAge;
                            if (!empty($filterSex)) $exportParams['filter_sex'] = $filterSex;
                            if (!empty($filterStatus)) $exportParams['filter_status'] = $filterStatus;
                            if (!empty($filterPatientStatus)) $exportParams['filter_patient_status'] = $filterPatientStatus;
                            if (!empty($filterChair)) $exportParams['filter_chair'] = $filterChair;
                            if (isset($_GET['show_all'])) $exportParams['show_all'] = 1;
                            
                            echo 'export_procedures_log.php?' . http_build_query($exportParams);
                        ?>" 
                           class="px-4 py-2 bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white rounded-lg shadow-md transition-all flex items-center text-sm no-print">
                            <i class="ri-file-download-line mr-2"></i>Export to CSV
                        </a>
                    </div>

                    <!-- Table -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full border border-gray-300 dark:border-gray-600 text-sm">
                            <thead>
                                <tr class="bg-gray-100 dark:bg-gray-700">
                                    <th class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-left">No.</th>
                                    <th class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-left">
                                        Clinician</th>
                                    <th class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-left">C.I.
                                    </th>
                                    <th class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-left">Patient
                                        Name</th>
                                    <th class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-left">Age</th>
                                    <th class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-left">Sex</th>
                                    <th class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-left">
                                        Procedures</th>
                                    <th class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-left">Details
                                    </th>
                                    <th class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-left">Date
                                    </th>
                                    <th class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-left">Remarks
                                    </th>
                                    <th class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-left">Status
                                    </th>
                                    <th class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-left">Patient Status
                                    </th>
                                    <th class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-left">Chair
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($procedureLogs)): ?>
                                    <tr>
                                        <td colspan="13"
                                            class="border border-gray-300 dark:border-gray-600 px-3 py-8 text-center text-gray-500 dark:text-gray-400">
                                            <i class="ri-inbox-line text-4xl mb-2"></i>
                                            <?php if ($isFirstLoad): ?>
                                                <p class="text-lg font-semibold">No procedures logged today yet</p>
                                                <p class="text-sm mt-2">Procedures logged by clinicians today will appear here
                                                    automatically.</p>
                                                <p class="text-sm mt-2">
                                                    <a href="admin_procedures_log.php?show_all=1"
                                                        class="text-blue-600 dark:text-blue-400 hover:underline">
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
                                            <td class="border border-gray-300 dark:border-gray-600 px-3 py-2">
                                                <?php echo $index + 1; ?></td>
                                            <td class="border border-gray-300 dark:border-gray-600 px-3 py-2">
                                                <?php echo htmlspecialchars($log['clinician_name']); ?></td>
                                            <td class="border border-gray-300 dark:border-gray-600 px-3 py-2">
                                                <?php echo htmlspecialchars($log['clinical_instructor_name'] ?? 'N/A'); ?></td>
                                            <td class="border border-gray-300 dark:border-gray-600 px-3 py-2">
                                                <?php echo htmlspecialchars($log['patient_name']); ?></td>
                                            <td class="border border-gray-300 dark:border-gray-600 px-3 py-2">
                                                <?php echo htmlspecialchars($log['age'] ?? '-'); ?></td>
                                            <td class="border border-gray-300 dark:border-gray-600 px-3 py-2">
                                                <?php echo htmlspecialchars($log['sex'] ?? '-'); ?></td>
                                            <td class="border border-gray-300 dark:border-gray-600 px-3 py-2">
                                                <div class="max-w-xs truncate"
                                                    title="<?php echo htmlspecialchars($log['procedure_selected']); ?>">
                                                    <?php echo htmlspecialchars($log['procedure_selected']); ?>
                                                </div>
                                            </td>
                                            <td class="border border-gray-300 dark:border-gray-600 px-3 py-2">
                                                <div class="max-w-xs truncate"
                                                    title="<?php echo htmlspecialchars($log['procedure_details'] ?? '-'); ?>">
                                                    <?php echo htmlspecialchars($log['procedure_details'] ?? '-'); ?>
                                                </div>
                                            </td>
                                            <td class="border border-gray-300 dark:border-gray-600 px-3 py-2 whitespace-nowrap">
                                                <?php echo date('d/m/Y', strtotime($log['logged_at'])); ?>
                                            </td>
                                            <td class="border border-gray-300 dark:border-gray-600 px-3 py-2">
                                                <div class="max-w-xs"
                                                    title="<?php echo htmlspecialchars($log['remarks'] ?? '-'); ?>">
                                                    <?php echo htmlspecialchars($log['remarks'] ?? '-'); ?>
                                                </div>
                                            </td>
                                            <td class="border border-gray-300 dark:border-gray-600 px-3 py-2">
                                                <span
                                                    class="px-2 py-1 bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 rounded text-xs">
                                                    <?php echo htmlspecialchars($log['status'] ?? 'Completed'); ?>
                                                </span>
                                            </td>
                                            <td class="border border-gray-300 dark:border-gray-600 px-3 py-2">
                                                <?php
                                                $patientStatus = $log['patient_status'] ?? 'Active';
                                                $statusClass = $patientStatus === 'Active'
                                                    ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                    : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
                                                ?>
                                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $statusClass; ?>">
                                                    <?php echo htmlspecialchars($patientStatus); ?>
                                                </span>
                                            </td>
                                            <td class="border border-gray-300 dark:border-gray-600 px-3 py-2">
                                                <?php echo htmlspecialchars($log['chair_number'] ?? '-'); ?></td>
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

        // Advanced filters functionality
        function toggleAdvancedFilters() {
            const form = document.getElementById('advancedFiltersForm');
            const btn = document.getElementById('toggleFiltersBtn');
            const icon = document.getElementById('toggleFiltersIcon');
            const text = document.getElementById('toggleFiltersText');
            
            if (form.classList.contains('hidden')) {
                form.classList.remove('hidden');
                icon.classList.remove('ri-arrow-down-s-line');
                icon.classList.add('ri-arrow-up-s-line');
                text.textContent = 'Hide Filters';
            } else {
                form.classList.add('hidden');
                icon.classList.remove('ri-arrow-up-s-line');
                icon.classList.add('ri-arrow-down-s-line');
                text.textContent = 'Show Filters';
            }
        }

        function clearAllFilters() {
            // Clear all form inputs
            const form = document.getElementById('advancedFiltersForm');
            const inputs = form.querySelectorAll('input, select');
            inputs.forEach(input => {
                if (input.type === 'text' || input.type === 'number' || input.type === 'date') {
                    input.value = '';
                } else if (input.tagName === 'SELECT') {
                    input.selectedIndex = 0;
                }
            });
            
            // Submit form to apply cleared filters
            form.submit();
        }

        // Show filters if any are active
        document.addEventListener('DOMContentLoaded', function() {
            const hasActiveFilters = <?php 
                echo (!empty($exactDate) || !empty($startDate) || !empty($endDate) || 
                      !empty($filterClinician) || !empty($filterCI) || !empty($filterPatientName) || 
                      !empty($filterAge) || !empty($filterSex) || !empty($filterStatus) || 
                      !empty($filterPatientStatus) || !empty($filterChair) || !empty($searchKeyword)) ? 'true' : 'false'; 
            ?>;
            
            if (hasActiveFilters) {
                toggleAdvancedFilters();
            }
        });
    </script>
</body>

</html>