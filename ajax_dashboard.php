<?php
require_once 'config.php';
requireAuth();

header('Content-Type: application/json');

// Get logged-in user
$currentUser = getCurrentUser();
$role = $currentUser['role'] ?? '';

// Get request parameters
$search = $_GET['search'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$statusFilter = $_GET['status'] ?? 'all';
$page = max(1, (int)($_GET['page'] ?? 1));
$itemsPerPage = 6;

global $pdo;

$sql = "SELECT p.*, u.full_name as clinician_name FROM patients p 
        LEFT JOIN users u ON p.created_by = u.id WHERE 1=1";
$params = [];

// Role-based filtering for patient list
if ($role === 'Clinician' && checkCreatedByColumnExists()) {
    $sql .= " AND p.created_by = ?";
    $params[] = $currentUser['id'];
} elseif ($role === 'COD' && checkCreatedByColumnExists()) {
    // COD sees patients created by Clinicians
    $sql = str_replace(
        "SELECT p.*, u.full_name as clinician_name FROM patients p LEFT JOIN users u ON p.created_by = u.id",
        "SELECT p.*, u.full_name as clinician_name, pa.assignment_status, ci.full_name as assigned_instructor FROM patients p 
         JOIN users u ON p.created_by = u.id AND u.role = 'Clinician' 
         LEFT JOIN patient_assignments pa ON p.id = pa.patient_id 
         LEFT JOIN users ci ON pa.clinical_instructor_id = ci.id",
        $sql
    );
} elseif ($role === 'Clinical Instructor' && checkCreatedByColumnExists()) {
    // Clinical Instructors see only patients explicitly assigned to them
    $sql = str_replace(
        "SELECT p.*, u.full_name as clinician_name FROM patients p LEFT JOIN users u ON p.created_by = u.id",
        "SELECT p.*, u.full_name as clinician_name, pa.assignment_status, pa.assigned_at, papp.approval_status FROM patients p 
         JOIN users u ON p.created_by = u.id 
         INNER JOIN patient_assignments pa ON p.id = pa.patient_id
         LEFT JOIN patient_approvals papp ON pa.id = papp.assignment_id",
        $sql
    );
    $sql .= " AND pa.clinical_instructor_id = ? AND pa.assignment_status IN ('accepted', 'completed')";
    $params[] = $currentUser['id'];
}

if (!empty($search)) {
    $sql .= " AND (CONCAT(p.first_name, ' ', p.last_name) LIKE ? OR p.email LIKE ?)";
    $searchTerm = "%$search%";
    array_push($params, $searchTerm, $searchTerm);
}

if (!empty($dateFrom)) {
    $sql .= " AND DATE(p.created_at) >= ?";
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $sql .= " AND DATE(p.created_at) <= ?";
    $params[] = $dateTo;
}

if ($statusFilter !== 'all') {
    $sql .= " AND p.status = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY p.created_at DESC";

// Create count query for pagination
$countSql = str_replace(
    "SELECT p.*, u.full_name as clinician_name",
    "SELECT COUNT(DISTINCT p.id) as total",
    $sql
);
$countSql = str_replace(" ORDER BY p.created_at DESC", "", $countSql);

try {
    // Get total count
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetch()['total'];
    $totalPages = ceil($totalRecords / $itemsPerPage);
    
    // Get paginated results
    $offset = ($page - 1) * $itemsPerPage;
    $sql .= " LIMIT ? OFFSET ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($params, [$itemsPerPage, $offset]));
    $patients = $stmt->fetchAll();
    
    // Generate table rows HTML
    $tableHTML = '';
    if (!empty($patients)) {
        foreach ($patients as $patient) {
            $statusClass = $patient['status'] === 'Approved' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 
                         ($patient['status'] === 'Pending' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : 
                          'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200');
            
            $tableHTML .= '<tr class="hover:bg-gray-50 dark:hover:bg-gray-700">';
            $tableHTML .= '<td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">';
            $tableHTML .= htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']);
            $tableHTML .= '</td>';
            $tableHTML .= '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">';
            $tableHTML .= '#' . str_pad($patient['id'], 4, '0', STR_PAD_LEFT);
            $tableHTML .= '</td>';
            $tableHTML .= '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">';
            $tableHTML .= date('M d, Y', strtotime($patient['created_at']));
            $tableHTML .= '</td>';
            $tableHTML .= '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">';
            $tableHTML .= htmlspecialchars($patient['clinician_name'] ?? 'Unassigned');
            $tableHTML .= '</td>';
            $tableHTML .= '<td class="px-6 py-4 whitespace-nowrap">';
            $tableHTML .= '<span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ' . $statusClass . '">';
            $statusDisplay = $patient['status'] === 'Disapproved' ? 'Declined' : $patient['status'];
            $tableHTML .= htmlspecialchars($statusDisplay);
            $tableHTML .= '</span>';
            $tableHTML .= '</td>';
            if ($role !== 'COD') {
                $tableHTML .= '<td class="px-6 py-4 whitespace-nowrap text-sm font-medium">';
                $tableHTML .= '<div class="flex space-x-2">';
                $tableHTML .= '<a href="view_patient.php?id=' . $patient['id'] . '" class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-200" title="View">';
                $tableHTML .= '<i class="ri-eye-line"></i>';
                $tableHTML .= '</a>';
                if ($role === 'Admin' || $role === 'Clinician') {
                    $tableHTML .= '<a href="edit_patient.php?id=' . $patient['id'] . '" class="text-green-600 dark:text-green-400 hover:text-green-900 dark:hover:text-green-200" title="Edit">';
                    $tableHTML .= '<i class="ri-edit-line"></i>';
                    $tableHTML .= '</a>';
                }
                if ($role === 'Admin') {
                    $tableHTML .= '<button onclick="deletePatient(' . $patient['id'] . ')" class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-200" title="Delete">';
                    $tableHTML .= '<i class="ri-delete-bin-line"></i>';
                    $tableHTML .= '</button>';
                }
                $tableHTML .= '</div>';
                $tableHTML .= '</td>';
            }
            $tableHTML .= '</tr>';
        }
    } else {
        $colspan = ($role === 'COD') ? '5' : '6';
        $tableHTML = '<tr><td colspan="' . $colspan . '" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">No patient records found.</td></tr>';
    }
    
    // Generate pagination HTML
    $paginationHTML = '';
    if ($totalPages > 1) {
        $paginationHTML .= '<div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">';
        $paginationHTML .= '<div class="flex flex-col sm:flex-row justify-between items-center space-y-3 sm:space-y-0">';
        $paginationHTML .= '<div class="text-sm text-gray-700 dark:text-gray-300">';
        $paginationHTML .= 'Showing ' . (($page - 1) * $itemsPerPage + 1) . ' to ' . min($page * $itemsPerPage, $totalRecords) . ' of ' . $totalRecords . ' results';
        $paginationHTML .= '</div>';
        $paginationHTML .= '<div class="flex items-center space-x-2">';
        
        // Previous button
        if ($page > 1) {
            $paginationHTML .= '<button onclick="loadDashboardPage(' . ($page - 1) . ')" class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300">';
            $paginationHTML .= '<i class="ri-arrow-left-line mr-1"></i>Previous';
            $paginationHTML .= '</button>';
        } else {
            $paginationHTML .= '<span class="px-3 py-2 text-sm bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md text-gray-400 dark:text-gray-600 cursor-not-allowed">';
            $paginationHTML .= '<i class="ri-arrow-left-line mr-1"></i>Previous';
            $paginationHTML .= '</span>';
        }
        
        // Page numbers
        $startPage = max(1, $page - 2);
        $endPage = min($totalPages, $page + 2);
        
        if ($startPage > 1) {
            $paginationHTML .= '<button onclick="loadDashboardPage(1)" class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300">1</button>';
            if ($startPage > 2) {
                $paginationHTML .= '<span class="px-2 text-gray-500 dark:text-gray-400">...</span>';
            }
        }
        
        for ($i = $startPage; $i <= $endPage; $i++) {
            if ($i == $page) {
                $paginationHTML .= '<span class="px-3 py-2 text-sm bg-blue-600 text-white border border-blue-600 rounded-md">' . $i . '</span>';
            } else {
                $paginationHTML .= '<button onclick="loadDashboardPage(' . $i . ')" class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300">' . $i . '</button>';
            }
        }
        
        if ($endPage < $totalPages) {
            if ($endPage < $totalPages - 1) {
                $paginationHTML .= '<span class="px-2 text-gray-500 dark:text-gray-400">...</span>';
            }
            $paginationHTML .= '<button onclick="loadDashboardPage(' . $totalPages . ')" class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300">' . $totalPages . '</button>';
        }
        
        // Next button
        if ($page < $totalPages) {
            $paginationHTML .= '<button onclick="loadDashboardPage(' . ($page + 1) . ')" class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300">';
            $paginationHTML .= 'Next<i class="ri-arrow-right-line ml-1"></i>';
            $paginationHTML .= '</button>';
        } else {
            $paginationHTML .= '<span class="px-3 py-2 text-sm bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md text-gray-400 dark:text-gray-600 cursor-not-allowed">';
            $paginationHTML .= 'Next<i class="ri-arrow-right-line ml-1"></i>';
            $paginationHTML .= '</span>';
        }
        
        $paginationHTML .= '</div>';
        $paginationHTML .= '</div>';
        $paginationHTML .= '</div>';
    }
    
    echo json_encode([
        'success' => true,
        'tableHTML' => $tableHTML,
        'paginationHTML' => $paginationHTML,
        'currentPage' => $page,
        'totalPages' => $totalPages,
        'totalRecords' => $totalRecords
    ]);
    
} catch (PDOException $e) {
    error_log("Error in AJAX dashboard: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred'
    ]);
}
?>