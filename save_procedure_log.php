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
    $patientId = isset($_POST['patient_id']) ? (int) $_POST['patient_id'] : 0;
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
        // Get the ID of the newly created procedure log
        $procedureLogId = $pdo->lastInsertId();

        // First, check if there are Clinical Instructors currently online
        $onlineCICheck = $pdo->prepare("
            SELECT u.id, u.full_name
            FROM users u
            WHERE u.role = 'Clinical Instructor'
            AND u.account_status = 'active'
            AND u.connection_status = 'online'
            AND (u.last_activity IS NULL OR u.last_activity >= DATE_SUB(NOW(), INTERVAL 5 MINUTE))
            ORDER BY u.full_name
            LIMIT 1
        ");
        $onlineCICheck->execute();
        $onlineCI = $onlineCICheck->fetch();

        // If there's an online CI, manually create the assignment and progress note
        if ($onlineCI) {
            try {
                // Start transaction for consistent updates
                $pdo->beginTransaction();

                // Create the assignment record
                $assignmentStmt = $pdo->prepare("
                    INSERT INTO procedure_assignments 
                    (procedure_log_id, cod_user_id, clinical_instructor_id, assignment_status, notes, assigned_at, created_at, updated_at)
                    VALUES (?, ?, ?, 'pending', 'Auto-assigned on submit', NOW(), NOW(), NOW())
                ");

                $codId = null;
                // Try to get a COD user for attribution
                $codCheck = $pdo->prepare("SELECT id FROM users WHERE role = 'COD' AND account_status = 'active' LIMIT 1");
                $codCheck->execute();
                $codUser = $codCheck->fetch();
                if ($codUser) {
                    $codId = $codUser['id'];
                }

                $assignmentStmt->execute([$procedureLogId, $codId, $onlineCI['id']]);

                $pdo->commit();
                error_log("Created auto-assignment for procedure log ID $procedureLogId to CI {$onlineCI['full_name']}");

            } catch (PDOException $e) {
                $pdo->rollBack();
                // Log error but don't fail the entire operation
                error_log("Error creating auto-assignment: " . $e->getMessage());
            }
        }

        header('Location: patients.php?log_success=1');
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
