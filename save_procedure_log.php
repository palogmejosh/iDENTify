<?php
require_once 'config.php';
requireAuth();

$user = getCurrentUser();
$role = $user['role'] ?? '';

// Only Clinicians and Admin can save procedure logs
if (!in_array($role, ['Clinician', 'Admin'])) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: clinician_log_procedure.php');
    exit;
}

try {
    // Get form data
    $patientId = isset($_POST['patient_id']) ? (int)$_POST['patient_id'] : 0;
    $procedureSelectedJson = $_POST['procedure_selected'] ?? '';
    $clinicianName = $_POST['clinician_name'] ?? '';
    $procedureDetails = trim($_POST['procedure_details'] ?? '');
    $chairNumber = trim($_POST['chair_number'] ?? '');
    $remarks = trim($_POST['remarks'] ?? ''); // Get selected remark
    
    // Validate required fields
    if (!$patientId || empty($procedureSelectedJson) || empty($clinicianName)) {
        header('Location: clinician_log_procedure.php?error=' . urlencode('All fields are required'));
        exit;
    }
    
    // Verify that the patient exists (Admin sees all, Clinician sees only their patients)
    if ($role === 'Admin') {
        $stmt = $pdo->prepare("
            SELECT 
                p.id, 
                p.first_name, 
                p.last_name, 
                p.middle_initial,
                p.age, 
                p.gender, 
                p.treatment_hint
            FROM patients p
            WHERE p.id = ?
        ");
        $stmt->execute([$patientId]);
    } else {
        $stmt = $pdo->prepare("
            SELECT 
                p.id, 
                p.first_name, 
                p.last_name, 
                p.middle_initial,
                p.age, 
                p.gender, 
                p.treatment_hint
            FROM patients p
            WHERE p.id = ? AND p.created_by = ?
        ");
        $stmt->execute([$patientId, $user['id']]);
    }
    $patient = $stmt->fetch();
    
    if (!$patient) {
        header('Location: clinician_log_procedure.php?error=' . urlencode('Patient not found or unauthorized'));
        exit;
    }
    
    // Parse procedure data
    $procedureData = json_decode($procedureSelectedJson, true);
    if (!$procedureData) {
        header('Location: clinician_log_procedure.php?error=' . urlencode('Invalid procedure data'));
        exit;
    }
    
    // Build patient full name
    $patientFullName = trim($patient['first_name']);
    if (!empty($patient['middle_initial'])) {
        $patientFullName .= ' ' . $patient['middle_initial'] . '.';
    }
    $patientFullName .= ' ' . $patient['last_name'];
    
    // Format procedure selected for storage
    $procedureDisplay = $procedureData['plan'] ?? '';
    if (!empty($procedureData['tooth'])) {
        $procedureDisplay .= ' (Tooth: ' . $procedureData['tooth'] . ')';
    }
    if (!empty($procedureData['diagnosis'])) {
        $procedureDisplay .= ' - ' . $procedureData['diagnosis'];
    }
    if (!empty($procedureData['sequence'])) {
        $procedureDisplay .= ' [' . $procedureData['sequence'] . ']';
    }
    
    // Insert procedure log
    $stmt = $pdo->prepare("
        INSERT INTO procedure_logs (
            patient_id,
            clinician_id,
            patient_name,
            age,
            sex,
            procedure_selected,
            procedure_details,
            chair_number,
            clinician_name,
            remarks,
            logged_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    // Use custom procedure details if provided, otherwise use patient's procedure details from database
    $finalProcedureDetails = !empty($procedureDetails) ? $procedureDetails : ($patient['treatment_hint'] ?? null);
    
    $result = $stmt->execute([
        $patientId,
        $user['id'],
        $patientFullName,
        $patient['age'] ?? null,
        $patient['gender'] ?? null,
        $procedureDisplay,
        $finalProcedureDetails,
        !empty($chairNumber) ? $chairNumber : null,
        $clinicianName,
        !empty($remarks) ? $remarks : null // Save selected remark
    ]);
    
    if ($result) {
        header('Location: clinician_log_procedure.php?success=1');
        exit;
    } else {
        header('Location: clinician_log_procedure.php?error=' . urlencode('Failed to save procedure log'));
        exit;
    }
    
} catch (PDOException $e) {
    error_log("Error saving procedure log: " . $e->getMessage());
    header('Location: clinician_log_procedure.php?error=' . urlencode('Database error occurred'));
    exit;
}
