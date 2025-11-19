<?php
require_once 'config.php';
requireAuth();

header('Content-Type: application/json');

$user = getCurrentUser();
$role = $user['role'] ?? '';

// Only Clinicians can access this endpoint
if ($role !== 'Clinician') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$patientId = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

if (!$patientId) {
    echo json_encode(['success' => false, 'error' => 'Invalid patient ID']);
    exit;
}

try {
    // Verify that the patient belongs to this clinician
    $stmt = $pdo->prepare("SELECT id FROM patients WHERE id = ? AND created_by = ?");
    $stmt->execute([$patientId, $user['id']]);
    $patient = $stmt->fetch();
    
    if (!$patient) {
        echo json_encode(['success' => false, 'error' => 'Patient not found or unauthorized']);
        exit;
    }
    
    // Fetch dental examination record for this patient
    $stmt = $pdo->prepare("SELECT assessment_plan_json FROM dental_examination WHERE patient_id = ?");
    $stmt->execute([$patientId]);
    $examination = $stmt->fetch();
    
    if (!$examination || empty($examination['assessment_plan_json'])) {
        echo json_encode(['success' => true, 'plans' => []]);
        exit;
    }
    
    // Parse the assessment plan JSON
    $assessmentPlans = json_decode($examination['assessment_plan_json'], true);
    
    if (!$assessmentPlans || !is_array($assessmentPlans)) {
        echo json_encode(['success' => true, 'plans' => []]);
        exit;
    }
    
    // Extract treatment plans (filter out empty entries)
    $treatmentPlans = [];
    foreach ($assessmentPlans as $index => $plan) {
        $treatmentPlan = $plan['plan'] ?? '';
        $tooth = $plan['tooth'] ?? '';
        $diagnosis = $plan['diagnosis'] ?? '';
        $sequence = $plan['sequence'] ?? '';
        
        // Only include if treatment plan is not empty
        if (!empty(trim($treatmentPlan))) {
            $displayText = $treatmentPlan;
            
            // Add additional context if available
            if (!empty($tooth)) {
                $displayText .= " (Tooth: $tooth)";
            }
            if (!empty($diagnosis)) {
                $displayText .= " - $diagnosis";
            }
            
            $treatmentPlans[] = [
                'sequence' => $sequence,
                'tooth' => $tooth,
                'diagnosis' => $diagnosis,
                'plan' => $treatmentPlan,
                'display' => $displayText
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'plans' => $treatmentPlans
    ]);
    
} catch (PDOException $e) {
    error_log("Error fetching treatment plans: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
