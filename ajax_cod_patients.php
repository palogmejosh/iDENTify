<?php
require_once 'config.php';
requireAuth();

header('Content-Type: application/json');

$user = getCurrentUser();
$role = $user['role'] ?? '';

// Redirect if not COD or Admin
if (!in_array($role, ['COD', 'Admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Get request parameters
$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? 'all';
$assignmentStatusFilter = $_GET['assignment_status'] ?? 'all';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$itemsPerPage = 6;

// Get patients created by Clinicians for COD oversight (with pagination)
$codPatientsResult = getCODPatients($search, $statusFilter, $dateFrom, $dateTo, $assignmentStatusFilter, $page, $itemsPerPage);
$codPatients = $codPatientsResult['patients'];
$totalItems = $codPatientsResult['total'];
$totalPages = ceil($totalItems / $itemsPerPage);

try {
    // Generate table rows HTML
    $tableHTML = '';
    if (!empty($codPatients)) {
        foreach ($codPatients as $patient) {
            $tableHTML .= '<tr class="hover:bg-gray-50 dark:hover:bg-gray-700">';

            // Patient Name
            $tableHTML .= '<td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">';
            $tableHTML .= htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']);
            $tableHTML .= '<div class="text-xs text-gray-500">' . htmlspecialchars($patient['email']) . '</div>';
            $tableHTML .= '</td>';

            // Procedure Details
            $tableHTML .= '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">';
            if (!empty($patient['treatment_hint'])) {
                $tableHTML .= '<span class="treatment-hint-badge inline-flex items-center px-3 py-1 text-xs font-semibold rounded-full bg-gradient-to-r from-purple-100 to-purple-200 text-purple-800 dark:from-purple-900 dark:to-purple-800 dark:text-purple-200 border border-purple-300 dark:border-purple-600" title="Procedure Details: ' . htmlspecialchars($patient['treatment_hint']) . '">';
                $tableHTML .= '<i class="ri-heart-pulse-line mr-1"></i>';
                $tableHTML .= htmlspecialchars($patient['treatment_hint']);
                $tableHTML .= '</span>';
            } else {
                $tableHTML .= '<span class="inline-flex items-center px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400">';
                $tableHTML .= '<i class="ri-question-mark mr-1"></i>';
                $tableHTML .= 'Not specified';
                $tableHTML .= '</span>';
            }
            $tableHTML .= '</td>';

            // Created By
            $tableHTML .= '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">';
            $tableHTML .= htmlspecialchars($patient['created_by_clinician'] ?? 'N/A');
            $tableHTML .= '</td>';

            // Date Created
            $tableHTML .= '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">';
            $tableHTML .= date('M d, Y', strtotime($patient['created_at']));
            $tableHTML .= '</td>';

            // Patient Status
            $statusClass = $patient['patient_status'] === 'Approved' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' :
                ($patient['patient_status'] === 'Pending' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' :
                    'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200');
            $statusDisplay = $patient['patient_status'] === 'Disapproved' ? 'Declined' : $patient['patient_status'];
            $tableHTML .= '<td class="px-6 py-4 whitespace-nowrap">';
            $tableHTML .= '<span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ' . $statusClass . '">';
            $tableHTML .= htmlspecialchars($statusDisplay);
            $tableHTML .= '</span>';
            $tableHTML .= '</td>';

            // Assignment Status
            $assignmentStatus = $patient['assignment_status'] ?? 'unassigned';
            $assignmentClass = $assignmentStatus === 'completed' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' :
                ($assignmentStatus === 'accepted' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' :
                    ($assignmentStatus === 'pending' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' :
                        'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'));
            $tableHTML .= '<td class="px-6 py-4 whitespace-nowrap">';
            $tableHTML .= '<span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ' . $assignmentClass . '">';
            $tableHTML .= htmlspecialchars(ucfirst($assignmentStatus));
            $tableHTML .= '</span>';
            $tableHTML .= '</td>';

            // Assigned Instructor
            $tableHTML .= '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">';
            $tableHTML .= htmlspecialchars($patient['assigned_clinical_instructor'] ?? 'Auto-assigning...');
            if (!empty($patient['assigned_at'])) {
                $tableHTML .= '<div class="text-xs text-gray-400">';
                $tableHTML .= '<i class="ri-time-line mr-1"></i>' . date('M d, Y H:i', strtotime($patient['assigned_at']));
                $tableHTML .= '</div>';
            }
            if (!empty($patient['assignment_notes']) && strpos($patient['assignment_notes'], 'Auto-assigned') !== false) {
                $tableHTML .= '<div class="text-xs text-blue-600 dark:text-blue-400">';
                $tableHTML .= '<i class="ri-magic-line mr-1"></i>Auto-assigned';
                $tableHTML .= '</div>';
            }
            $tableHTML .= '</td>';

            // Actions Column
            $tableHTML .= '<td class="px-6 py-4 whitespace-nowrap text-sm">';
            if ($assignmentStatus === 'unassigned' || empty($patient['assigned_clinical_instructor'])) {
                $patientJson = htmlspecialchars(json_encode($patient), ENT_QUOTES, 'UTF-8');
                $tableHTML .= '<button onclick=\'openAssignModal(' . $patientJson . ')\' ';
                $tableHTML .= 'class="inline-flex items-center px-3 py-1.5 bg-gradient-to-r from-blue-600 to-cyan-600 hover:from-blue-700 hover:to-cyan-700 text-white text-xs font-medium rounded-md transition-all duration-200 shadow-md hover:shadow-lg" ';
                $tableHTML .= 'title="Manually assign this patient to a Clinical Instructor">';
                $tableHTML .= '<i class="ri-user-add-line mr-1"></i>Assign';
                $tableHTML .= '</button>';
            } elseif ($assignmentStatus === 'pending' || $assignmentStatus === 'accepted') {
                $patientJson = htmlspecialchars(json_encode($patient), ENT_QUOTES, 'UTF-8');
                $tableHTML .= '<button onclick=\'openAssignModal(' . $patientJson . ')\' ';
                $tableHTML .= 'class="inline-flex items-center px-3 py-1.5 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white text-xs font-medium rounded-md transition-all duration-200 shadow-md hover:shadow-lg" ';
                $tableHTML .= 'title="Reassign this patient to a different Clinical Instructor">';
                $tableHTML .= '<i class="ri-refresh-line mr-1"></i>Reassign';
                $tableHTML .= '</button>';
            } else {
                $tableHTML .= '<span class="inline-flex items-center px-3 py-1.5 bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-400 text-xs font-medium rounded-md">';
                $tableHTML .= '<i class="ri-check-line mr-1"></i>Completed';
                $tableHTML .= '</span>';
            }
            $tableHTML .= '</td>';

            $tableHTML .= '</tr>';
        }
    } else {
        $tableHTML = '<tr><td colspan="8" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">No patients found matching your criteria.</td></tr>';
    }

    // Generate pagination HTML
    $paginationHTML = '';
    if ($totalPages > 1) {
        $paginationHTML .= '<div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">';
        $paginationHTML .= '<div class="flex flex-col sm:flex-row justify-between items-center space-y-3 sm:space-y-0">';
        $paginationHTML .= '<div class="text-sm text-gray-700 dark:text-gray-300">';
        $paginationHTML .= 'Showing ' . (($page - 1) * $itemsPerPage + 1) . ' to ' . min($page * $itemsPerPage, $totalItems) . ' of ' . $totalItems . ' results';
        $paginationHTML .= '</div>';
        $paginationHTML .= '<div class="flex items-center space-x-2">';

        // Previous button
        if ($page > 1) {
            $paginationHTML .= '<button onclick="loadCODPage(' . ($page - 1) . ')" class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300">';
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
            $paginationHTML .= '<button onclick="loadCODPage(1)" class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300">1</button>';
            if ($startPage > 2) {
                $paginationHTML .= '<span class="px-2 text-gray-500 dark:text-gray-400">...</span>';
            }
        }

        for ($i = $startPage; $i <= $endPage; $i++) {
            if ($i == $page) {
                $paginationHTML .= '<span class="px-3 py-2 text-sm bg-blue-600 text-white border border-blue-600 rounded-md">' . $i . '</span>';
            } else {
                $paginationHTML .= '<button onclick="loadCODPage(' . $i . ')" class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300">' . $i . '</button>';
            }
        }

        if ($endPage < $totalPages) {
            if ($endPage < $totalPages - 1) {
                $paginationHTML .= '<span class="px-2 text-gray-500 dark:text-gray-400">...</span>';
            }
            $paginationHTML .= '<button onclick="loadCODPage(' . $totalPages . ')" class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300">' . $totalPages . '</button>';
        }

        // Next button
        if ($page < $totalPages) {
            $paginationHTML .= '<button onclick="loadCODPage(' . ($page + 1) . ')" class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300">';
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
        'totalRecords' => $totalItems
    ]);

} catch (Exception $e) {
    error_log("Error in AJAX COD patients: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error occurred while loading data'
    ]);
}
?>