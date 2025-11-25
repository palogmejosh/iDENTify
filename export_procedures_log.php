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

// Get filter parameters (same as admin_procedures_log.php)
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

// If first load (no filters), default to today's date only
$isFirstLoad = empty($startDate) && empty($endDate) && empty($exactDate) && empty($searchKeyword) 
    && empty($filterClinician) && empty($filterCI) && empty($filterPatientName) && empty($filterAge) 
    && empty($filterSex) && empty($filterStatus) && empty($filterPatientStatus) && empty($filterChair) 
    && !isset($_GET['show_all']);

if ($isFirstLoad) {
    $startDate = date('Y-m-d');
    $endDate = date('Y-m-d');
}

// Build query to fetch procedure logs (same logic as admin_procedures_log.php)
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
LEFT JOIN procedure_assignments pra ON pl.id = pra.procedure_log_id 
    AND pra.assignment_status = 'accepted'
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
LEFT JOIN progress_notes pn ON pl.id = pn.procedure_log_id
WHERE 1=1";

$params = [];

// Apply date filters
if (!empty($exactDate)) {
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
    error_log("Error fetching procedure logs for CSV: " . $e->getMessage());
    die("Error generating CSV export. Please try again.");
}

// Set headers for CSV download
$filename = 'procedures_log_' . date('Y-m-d_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Open output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// CSV Headers
$headers = [
    'No.',
    'Clinician',
    'Clinical Instructor',
    'Patient Name',
    'Age',
    'Sex',
    'Procedure',
    'Procedure Details',
    'Date',
    'Remarks',
    'Status',
    'Patient Status',
    'Chair Number'
];

fputcsv($output, $headers);

// Add data rows
$rowNumber = 1;
foreach ($procedureLogs as $log) {
    $row = [
        $rowNumber++,
        $log['clinician_name'] ?? '',
        $log['clinical_instructor_name'] ?? 'N/A',
        $log['patient_name'] ?? '',
        $log['age'] ?? '-',
        $log['sex'] ?? '-',
        $log['procedure_selected'] ?? '',
        $log['procedure_details'] ?? '-',
        date('d/m/Y', strtotime($log['logged_at'])),
        $log['remarks'] ?? '-',
        $log['status'] ?? 'Completed',
        $log['patient_status'] ?? 'Active',
        $log['chair_number'] ?? '-'
    ];
    
    fputcsv($output, $row);
}

fclose($output);
exit;

