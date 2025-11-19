<?php
require_once 'config.php';

// Set JSON response header
header('Content-Type: application/json');

// Enable CORS if needed
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Ensure user is authenticated and is COD
requireAuth();
$user = getCurrentUser();
if (!$user || $user['role'] !== 'COD') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied. COD role required.']);
    exit;
}

// Get the requested action
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_online_ci':
        handleGetOnlineCI();
        break;
    
    case 'get_all_ci':
        handleGetAllCI();
        break;
    
    case 'add_ci_to_pool':
        handleAddCIToPool();
        break;
    
case 'auto_assign':
        handleAutoAssign();
        break;
    
    case 'auto_assign_procedure':
        handleAutoAssignProcedure();
        break;
    
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action specified.']);
        break;
}

// Get online Clinical Instructors
function handleGetOnlineCI() {
    try {
        $onlineCIs = getOnlineClinicalInstructors();
        echo json_encode([
            'success' => true,
            'data' => $onlineCIs,
            'count' => count($onlineCIs)
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to fetch online CIs: ' . $e->getMessage()]);
    }
}

// Get all Clinical Instructors with counts
function handleGetAllCI() {
    try {
        $allCIs = getAllClinicalInstructorsWithCounts();
        echo json_encode([
            'success' => true,
            'data' => $allCIs,
            'count' => count($allCIs)
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to fetch all CIs: ' . $e->getMessage()]);
    }
}

// Add CI to assignment pool
function handleAddCIToPool() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        return;
    }
    
    $ciId = (int) $_POST['ci_id'] ?? 0;
    
    if (!$ciId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'CI ID is required.']);
        return;
    }
    
    try {
        $result = addCIToAssignmentPool($ciId);
        echo json_encode($result);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to add CI to pool: ' . $e->getMessage()]);
    }
}

// Auto assign patient
function handleAutoAssign() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        return;
    }
    
    global $user;
    
    $patientId = (int) $_POST['patient_id'] ?? 0;
    $notes = $_POST['notes'] ?? '';
    $treatmentHint = $_POST['treatment_hint'] ?? '';
    
    if (!$patientId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Patient ID is required.']);
        return;
    }
    
    try {
        $result = autoAssignPatientToBestClinicalInstructor($patientId, $user['id'], $notes, $treatmentHint);
        echo json_encode($result);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to auto assign patient: ' . $e->getMessage()]);
    }
}

// Auto assign procedure
function handleAutoAssignProcedure() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        return;
    }
    
    global $user;
    
    $procedureLogId = (int) $_POST['procedure_log_id'] ?? 0;
    $notes = $_POST['notes'] ?? '';
    $procedureDetails = $_POST['procedure_details'] ?? '';
    
    if (!$procedureLogId) {        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Procedure log ID is required.']);
        return;
    }
    
    try {
        $result = autoAssignProcedureToBestCI($procedureLogId, $user['id'], $procedureDetails, $notes);
        echo json_encode($result);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to auto assign procedure: ' . $e->getMessage()]);
    }
}
}

?>