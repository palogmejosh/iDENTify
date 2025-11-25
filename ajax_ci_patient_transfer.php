<?php
require_once 'config.php';
requireAuth();

header('Content-Type: application/json');

$user = getCurrentUser();
$role = $user['role'] ?? '';

// Only Clinical Instructors or Admin can use this endpoint
if (!in_array($role, ['Clinical Instructor', 'Admin'])) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access. Only Clinical Instructors or Admin can manage patient transfers.'
    ]);
    exit;
}

// Handle GET request for available CIs
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_available_cis') {
    $ciId = $user['id'];
    $availableCIs = getAvailableCIsForTransfer($ciId);

    echo json_encode([
        'success' => true,
        'cis' => $availableCIs
    ]);
    exit;
}

// Check if action is set
if (!isset($_POST['action'])) {
    echo json_encode([
        'success' => false,
        'message' => 'No action specified'
    ]);
    exit;
}

$action = $_POST['action'];
$ciId = $user['id'];

// Handle create transfer request
if ($action === 'create_transfer') {
    $patientId = $_POST['patient_id'] ?? null;
    $assignmentId = $_POST['assignment_id'] ?? null;
    $toCIId = $_POST['to_ci_id'] ?? null;
    $transferReason = $_POST['transfer_reason'] ?? '';

    // Validate inputs
    if (empty($patientId) || empty($assignmentId) || empty($toCIId)) {
        echo json_encode([
            'success' => false,
            'message' => 'Missing required parameters. Patient ID, Assignment ID, and Target CI are required.'
        ]);
        exit;
    }

    // Validate target CI is not the same as current CI
    if ($toCIId == $ciId) {
        echo json_encode([
            'success' => false,
            'message' => 'You cannot transfer a patient to yourself.'
        ]);
        exit;
    }

    // Create transfer request
    $result = createPatientTransferRequest($patientId, $assignmentId, $ciId, $toCIId, $transferReason);

    echo json_encode($result);
    exit;
}

// Handle respond to transfer request (accept/reject)
if ($action === 'respond_transfer') {
    $transferId = $_POST['transfer_id'] ?? null;
    $response = $_POST['response'] ?? null; // 'accept' or 'reject'
    $responseNotes = $_POST['response_notes'] ?? '';

    // Validate inputs
    if (empty($transferId) || empty($response)) {
        echo json_encode([
            'success' => false,
            'message' => 'Missing required parameters. Transfer ID and response are required.'
        ]);
        exit;
    }

    // Validate response value
    if (!in_array($response, ['accept', 'reject'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid response. Must be accept or reject.'
        ]);
        exit;
    }

    // Respond to transfer request
    $result = respondToTransferRequest($transferId, $ciId, $response, $responseNotes);

    echo json_encode($result);
    exit;
}

// Handle cancel transfer request
if ($action === 'cancel_transfer') {
    $transferId = $_POST['transfer_id'] ?? null;

    // Validate inputs
    if (empty($transferId)) {
        echo json_encode([
            'success' => false,
            'message' => 'Missing required parameter. Transfer ID is required.'
        ]);
        exit;
    }

    // Cancel transfer request
    $result = cancelTransferRequest($transferId, $ciId);

    echo json_encode($result);
    exit;
}

// Invalid action
echo json_encode([
    'success' => false,
    'message' => 'Invalid action specified'
]);
exit;
?>
