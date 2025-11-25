<?php
require_once 'config.php';
requireAuth();

header('Content-Type: application/json');

$user = getCurrentUser();
$role = $user['role'] ?? '';

// Only Clinical Instructors or Admin can use this endpoint
if (!in_array($role, ['Clinical Instructor', 'Admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
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

// Handle update assignment action
if ($action === 'update_assignment') {
    $assignmentId = $_POST['assignment_id'] ?? null;
    $status = $_POST['status'] ?? null;
    $notes = $_POST['notes'] ?? '';

    // Validate inputs
    if (empty($assignmentId) || empty($status)) {
        echo json_encode([
            'success' => false,
            'message' => 'Missing required parameters'
        ]);
        exit;
    }

    // Validate status
    if (!in_array($status, ['accepted', 'rejected'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid status value'
        ]);
        exit;
    }

    // Update the assignment status
    $result = updateAssignmentStatus($assignmentId, $user['id'], $status, $notes);

    if ($result) {
        $statusText = $status === 'accepted' ? 'accepted' : 'denied';
        echo json_encode([
            'success' => true,
            'message' => "Patient assignment has been {$statusText} successfully!"
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update assignment status. Please try again.'
        ]);
    }
    exit;
}

// Invalid action
echo json_encode([
    'success' => false,
    'message' => 'Invalid action'
]);
exit;
?>