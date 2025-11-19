<?php
require_once 'config.php';
requireAuth();

header('Content-Type: application/json');

$user = getCurrentUser();
$role = $user['role'] ?? '';

// Only Clinicians can access
if ($role !== 'Clinician') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$patientId = isset($_GET['patient_id']) && is_numeric($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

if (!$patientId) {
    echo json_encode(['success' => false, 'message' => 'Invalid patient ID']);
    exit;
}

try {
    // Verify patient belongs to this clinician
    $checkStmt = $pdo->prepare("SELECT id FROM patients WHERE id = ? AND created_by = ?");
    $checkStmt->execute([$patientId, $user['id']]);
    
    if (!$checkStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Patient not found or unauthorized']);
        exit;
    }
    
    // Fetch all unique remarks from progress_notes for this patient
    // Order by most recent first
    $stmt = $pdo->prepare("
        SELECT 
            id,
            date,
            tooth,
            progress,
            remarks
        FROM progress_notes 
        WHERE patient_id = ? 
        AND remarks IS NOT NULL 
        AND remarks != ''
        ORDER BY date DESC, id DESC
    ");
    
    $stmt->execute([$patientId]);
    $progressNotes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $remarks = [];
    foreach ($progressNotes as $note) {
        // Create a display string with context
        $display = '';
        
        // Add date if available
        if (!empty($note['date'])) {
            $display .= date('m/d/Y', strtotime($note['date'])) . ' - ';
        }
        
        // Add tooth if available
        if (!empty($note['tooth'])) {
            $display .= 'Tooth ' . $note['tooth'] . ' - ';
        }
        
        // Add the remark (truncate if too long)
        $remarkText = $note['remarks'];
        if (strlen($remarkText) > 80) {
            $remarkText = substr($remarkText, 0, 77) . '...';
        }
        $display .= $remarkText;
        
        $remarks[] = [
            'id' => $note['id'],
            'remarks' => $note['remarks'], // Full text for value
            'display' => $display, // Formatted for dropdown display
            'date' => $note['date'] ?? null,
            'tooth' => $note['tooth'] ?? null
        ];
    }
    
    echo json_encode([
        'success' => true,
        'remarks' => $remarks,
        'count' => count($remarks)
    ]);
    
} catch (PDOException $e) {
    error_log("Error fetching patient remarks: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
}
