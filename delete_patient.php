<?php
require_once 'config.php';
requireAuth();

header('Content-Type: application/json');

$user = getCurrentUser();
$role = $user['role'] ?? '';

// Only Admin users can delete patients
if ($role !== 'Admin') {
    echo json_encode(['success' => false, 'error' => 'Access denied. Only administrators can delete patients.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patientId = (int) ($_POST['patient_id'] ?? 0);
    
    if ($patientId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid patient ID.']);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Get patient name before deletion for confirmation message
        $nameStmt = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) as full_name FROM patients WHERE id = ?");
        $nameStmt->execute([$patientId]);
        $patientData = $nameStmt->fetch();
        
        if (!$patientData) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => 'Patient not found.']);
            exit;
        }
        
        $patientName = $patientData['full_name'];
        
        // Delete related records first (foreign key constraints)
        
        // 1. Delete patient approvals (if any)
        $pdo->prepare("DELETE FROM patient_approvals WHERE assignment_id IN (SELECT id FROM patient_assignments WHERE patient_id = ?)")->execute([$patientId]);
        
        // 2. Delete patient transfers (if any)
        $pdo->prepare("DELETE FROM patient_transfers WHERE patient_id = ?")->execute([$patientId]);
        
        // 3. Delete patient assignments (if any)
        $pdo->prepare("DELETE FROM patient_assignments WHERE patient_id = ?")->execute([$patientId]);
        
        // 4. Delete procedure logs (if any)
        $pdo->prepare("DELETE FROM procedure_logs WHERE patient_id = ?")->execute([$patientId]);
        
        // 5. Delete patient PIR records (if any)
        $pdo->prepare("DELETE FROM patient_pir WHERE patient_id = ?")->execute([$patientId]);
        
        // 6. Delete informed consent records (if any)
        $pdo->prepare("DELETE FROM informed_consent WHERE patient_id = ?")->execute([$patientId]);
        
        // 7. Finally, delete the patient record
        $stmt = $pdo->prepare("DELETE FROM patients WHERE id = ?");
        $result = $stmt->execute([$patientId]);
        
        if ($result) {
            $pdo->commit();
            echo json_encode([
                'success' => true, 
                'message' => "Patient \"$patientName\" and all associated records have been permanently deleted.",
                'patient_name' => $patientName
            ]);
        } else {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => 'Failed to delete patient. Please try again.']);
        }
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error deleting patient: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Database error occurred while deleting patient.']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
}
?>
