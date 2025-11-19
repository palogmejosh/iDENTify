<?php
session_start();

// Database configuration
$host = 'localhost';
$dbname = 'identify_db';
$username = 'root';
$password = '';

// Global PDO connection
$pdo = null;

// Initialize database connection
function initDatabase() {
    global $pdo, $host, $dbname, $username, $password;

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return true;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        die("Database connection error.");
    }
}

// Initialize DB on load
initDatabase();

// Online/Offline Status Functions
function updateUserActivity($userId) {
    global $pdo;
    
    if (!$pdo || !$userId) return false;
    
    try {
        // Update both last_activity and connection_status to keep user online during active session
        $stmt = $pdo->prepare("UPDATE users SET last_activity = NOW(), connection_status = 'online' WHERE id = ?");
        return $stmt->execute([$userId]);
    } catch (PDOException $e) {
        error_log("Error updating user activity: " . $e->getMessage());
        return false;
    }
}

function setUserOnlineStatus($userId, $status = 'online') {
    global $pdo;
    
    if (!$pdo || !$userId) return false;
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET connection_status = ?, last_activity = NOW() WHERE id = ?");
        return $stmt->execute([$status, $userId]);
    } catch (PDOException $e) {
        error_log("Error setting user online status: " . $e->getMessage());
        return false;
    }
}

function isUserOnline($userId, $timeoutMinutes = 5) {
    global $pdo;
    
    if (!$pdo || !$userId) return false;
    
    try {
        $stmt = $pdo->prepare("
            SELECT connection_status, last_activity 
            FROM users 
            WHERE id = ? AND connection_status = 'online'
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user || $user['connection_status'] !== 'online') {
            return false;
        }
        
        // Check if last activity was within timeout period
        if ($user['last_activity']) {
            $lastActivity = new DateTime($user['last_activity']);
            $now = new DateTime();
            $diff = $now->diff($lastActivity);
            $minutesDiff = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;
            
            if ($minutesDiff > $timeoutMinutes) {
                // Auto-logout user due to inactivity
                setUserOnlineStatus($userId, 'offline');
                return false;
            }
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Error checking user online status: " . $e->getMessage());
        return false;
    }
}

function getUserOnlineStatus($userData) {
    if (!$userData) return 'offline';
    
    // Check if user is marked as online
    if ($userData['connection_status'] === 'online') {
        // If no last_activity, assume just logged in (online)
        if (!$userData['last_activity']) {
            return 'online';
        }
        
        // Check if within activity timeout
        try {
            // Use server timezone for consistency
            $lastActivity = new DateTime($userData['last_activity']);
            $now = new DateTime();
            
            // Calculate time difference in seconds for more precision
            $timeDiffSeconds = $now->getTimestamp() - $lastActivity->getTimestamp();
            $minutesDiff = floor($timeDiffSeconds / 60);
            
            // Consider user offline if inactive for more than 5 minutes
            if ($minutesDiff <= 5) {
                return 'online';
            }
        } catch (Exception $e) {
            error_log("Error calculating time difference: " . $e->getMessage());
            // If there's an error with time calculation, fall back to connection_status
            return 'online';
        }
    }
    
    return 'offline';
}

// Authentication functions
function authenticateUser($username, $password) {
    global $pdo;

    if (!$pdo) return false;

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !$user['is_active'] || $user['account_status'] !== 'active') {
        return false;   // Treat as invalid if inactive or account_status is not active
    }

    if ($user) {
        // Handle both hashed and plain text passwords
        $isPasswordCorrect = false;
        if (strlen($user['password']) === 60 && substr($user['password'], 0, 4) === '$2y$') {
            // Password is hashed (bcrypt)
            $isPasswordCorrect = password_verify($password, $user['password']);
        } else {
            // Password is plain text
            $isPasswordCorrect = ($password === $user['password']);
        }
        
        if ($isPasswordCorrect) {
            // Set user as online when successfully authenticated
            setUserOnlineStatus($user['id'], 'online');
            return $user;
        }
    }

    return false;
}

function isAuthenticated() {
    return isset($_SESSION['user_id']);
}

function getCurrentUser() {
    global $pdo;

    if (!isset($_SESSION['user_id']) || !$pdo) return null;

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function requireAuth() {
    if (!isAuthenticated()) {
        redirectToLogin();
    }
    
    // Update user activity if authenticated
    if (isset($_SESSION['user_id'])) {
        updateUserActivity($_SESSION['user_id']);
    }
}

function redirectToLogin() {
    header('Location: login.php');
    exit;
}

function redirectToDashboard() {
    header('Location: dashboard.php');
    exit;
}


// Check if created_by column exists in patients table
function checkCreatedByColumnExists() {
    global $pdo;
    
    if (!$pdo) return false;
    
    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) as count 
             FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = DATABASE() 
             AND TABLE_NAME = 'patients' 
             AND COLUMN_NAME = 'created_by'"
        );
        $stmt->execute();
        $result = $stmt->fetch();
        return ($result['count'] > 0);
    } catch (PDOException $e) {
        error_log("Error checking created_by column: " . $e->getMessage());
        return false;
    }
}

// Reusable data functions (using PDO)
function getPatients($search = '', $statusFilter = 'all', $dateFrom = '', $dateTo = '', $userRole = '', $userId = null) {
    global $pdo;

    if (!$pdo) return [];

    // Check if created_by column exists
    $hasCreatedByColumn = checkCreatedByColumnExists();
    
    // Role-based patient access control
    if ($userRole === 'Clinical Instructor') {
        // Clinical Instructors only see patients assigned to them (accepted or completed)
        if (!$hasCreatedByColumn) {
            error_log("Warning: created_by column missing. Clinical Instructor access restricted. Please run migration.");
            return []; // Return empty if no assignment system exists
        }
        
        // Updated query to ensure patients appear after CI accepts the assignment
        $sql = "SELECT p.id, p.first_name, p.middle_initial, p.nickname, p.gender, p.last_name, p.birth_date, p.age, p.phone, p.email, p.status, p.treatment_hint, p.created_by, p.created_at, p.updated_at, u.full_name as created_by_name, pa.assignment_status, pa.assigned_at, pa.id as assignment_id 
                FROM patients p 
                LEFT JOIN users u ON p.created_by = u.id 
                INNER JOIN patient_assignments pa ON p.id = pa.patient_id 
                WHERE pa.clinical_instructor_id = ? 
                  AND pa.assignment_status IN ('accepted', 'completed')";
        $params = [$userId];
        
    } elseif ($hasCreatedByColumn) {
        // Use the new query with created_by filtering for other roles
        $sql = "SELECT p.id, p.first_name, p.middle_initial, p.nickname, p.gender, p.last_name, p.birth_date, p.age, p.phone, p.email, p.status, p.treatment_hint, p.created_by, p.created_at, p.updated_at, u.full_name as created_by_name FROM patients p LEFT JOIN users u ON p.created_by = u.id WHERE 1=1";
        $params = [];
        
        // Filter for Clinicians: only show patients they created
        if ($userRole === 'Clinician' && $userId) {
            $sql .= " AND p.created_by = ?";
            $params[] = $userId;
        }
        // COD and Admin see all patients created by Clinicians (no additional filtering needed)
        
    } else {
        // Fallback to old query without created_by for non-Clinical Instructor roles
        $sql = "SELECT p.id, p.first_name, p.middle_initial, p.nickname, p.gender, p.last_name, p.birth_date, p.age, p.phone, p.email, p.status, p.treatment_hint, p.created_by, p.created_at, p.updated_at, NULL as created_by_name FROM patients p WHERE 1=1";
        $params = [];
        
        // If column doesn't exist, show warning for roles that need it
        if (in_array($userRole, ['Clinician', 'COD'])) {
            error_log("Warning: created_by column missing. {$userRole} seeing all patients. Please run migration.");
        }
    }

    if (!empty($search)) {
        $sql .= " AND (
            CONCAT(p.first_name, ' ', p.last_name) LIKE ? OR 
            p.email LIKE ? OR 
            p.phone LIKE ?
        )";
        $searchTerm = "%$search%";
        array_push($params, $searchTerm, $searchTerm, $searchTerm);
    }

    if ($statusFilter !== 'all') {
        $sql .= " AND p.status = ?";
        $params[] = $statusFilter;
    }

    if (!empty($dateFrom)) {
        $sql .= " AND DATE(p.created_at) >= ?";
        $params[] = $dateFrom;
    }

    if (!empty($dateTo)) {
        $sql .= " AND DATE(p.created_at) <= ?";
        $params[] = $dateTo;
    }

    $sql .= " ORDER BY p.created_at DESC";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error in getPatients: " . $e->getMessage());
        // Fallback to basic query without filtering
        try {
            $basicSql = "SELECT id, first_name, middle_initial, nickname, gender, last_name, birth_date, age, phone, email, status, treatment_hint, created_by, created_at, updated_at, NULL as created_by_name FROM patients ORDER BY created_at DESC";
            $stmt = $pdo->prepare($basicSql);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e2) {
            error_log("Error in getPatients fallback: " . $e2->getMessage());
            return [];
        }
    }
}


function addPatient($firstName, $lastName, $age, $phone, $email, $status = 'Pending', $createdBy = null) {
    global $pdo;

    if (!$pdo) return false;

    try {
        // Check if created_by column exists
        $hasCreatedByColumn = checkCreatedByColumnExists();
        
        if ($hasCreatedByColumn) {
            // Use the new query with created_by
            $stmt = $pdo->prepare("INSERT INTO patients (first_name, last_name, age, phone, email, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            return $stmt->execute([$firstName, $lastName, $age, $phone, $email, $status, $createdBy]);
        } else {
            // Fallback to old query without created_by
            $stmt = $pdo->prepare("INSERT INTO patients (first_name, last_name, age, phone, email, status) VALUES (?, ?, ?, ?, ?, ?)");
            return $stmt->execute([$firstName, $lastName, $age, $phone, $email, $status]);
        }
    } catch (PDOException $e) {
        error_log("Error adding patient: " . $e->getMessage());
        return false;
    }
}

function getUsers($roleFilter = 'all', $search = '', $statusFilter = 'all') {
    global $pdo;

    if (!$pdo) return [];

    $sql = "SELECT * FROM users WHERE 1=1";
    $params = [];

    if ($roleFilter !== 'all') {
        $sql .= " AND role = ?";
        $params[] = $roleFilter;
    }

    if ($statusFilter !== 'all') {
        $sql .= " AND account_status = ?";
        $params[] = $statusFilter;
    }

    if ($search) {
        $sql .= " AND (username LIKE ? OR full_name LIKE ? OR email LIKE ? OR role LIKE ?)";
        $searchTerm = "%$search%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }

    $sql .= " ORDER BY created_at DESC";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll();
        
        // Add online status to each user
        foreach ($users as &$user) {
            $user['online_status'] = getUserOnlineStatus($user);
        }
        
        return $users;
    } catch (PDOException $e) {
        error_log("Error in getUsers: " . $e->getMessage());
        return [];
    }
}

function addUser($username, $fullName, $email, $password, $role, $accountStatus = 'active') {
    global $pdo;

    if (!$pdo) return false;

    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, full_name, email, password, role, account_status) VALUES (?, ?, ?, ?, ?, ?)");
        return $stmt->execute([$username, $fullName, $email, $password, $role, $accountStatus]);
    } catch (PDOException $e) {
        error_log("Error adding user: " . $e->getMessage());
        return false;
    }
}

function getLastUpdateTime($table) {
    global $pdo;

    if (!$pdo) return null;

    $stmt = $pdo->prepare("SELECT MAX(updated_at) as last_update FROM $table");
    $stmt->execute();
    $result = $stmt->fetch();

    return $result['last_update'] ?? null;
}

// COD-specific functions for patient assignment workflow

// Get patients for COD - show patients created by Clinicians that need assignment
function getCODPatients($search = '', $statusFilter = 'all', $dateFrom = '', $dateTo = '', $assignmentStatus = 'all', $page = 1, $itemsPerPage = 0) {
    global $pdo;

    if (!$pdo) return ['patients' => [], 'total' => 0];

    $sql = "SELECT 
                p.id,
                p.first_name,
                p.last_name,
                p.email,
                p.phone,
                p.age,
                p.status as patient_status,
                p.treatment_hint,
                p.created_at,
                u_clinician.full_name as created_by_clinician,
                u_clinician.id as created_by_id,
                pa.id as assignment_id,
                pa.assignment_status,
                pa.assigned_at,
                pa.notes as assignment_notes,
                u_ci.full_name as assigned_clinical_instructor,
                u_ci.id as clinical_instructor_id
            FROM patients p
            JOIN users u_clinician ON p.created_by = u_clinician.id AND u_clinician.role = 'Clinician'
            LEFT JOIN patient_assignments pa ON p.id = pa.patient_id
            LEFT JOIN users u_ci ON pa.clinical_instructor_id = u_ci.id
            WHERE 1=1";
    
    // Count query for total records
    $countSql = "SELECT COUNT(DISTINCT p.id) as total
            FROM patients p
            JOIN users u_clinician ON p.created_by = u_clinician.id AND u_clinician.role = 'Clinician'
            LEFT JOIN patient_assignments pa ON p.id = pa.patient_id
            LEFT JOIN users u_ci ON pa.clinical_instructor_id = u_ci.id
            WHERE 1=1";
    
    $params = [];

    if (!empty($search)) {
        $searchCondition = " AND (
            CONCAT(p.first_name, ' ', p.last_name) LIKE ? OR 
            p.email LIKE ? OR 
            p.phone LIKE ? OR
            u_clinician.full_name LIKE ?
        )";
        $sql .= $searchCondition;
        $countSql .= $searchCondition;
        $searchTerm = "%$search%";
        array_push($params, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
    }

    if ($statusFilter !== 'all') {
        $statusCondition = " AND p.status = ?";
        $sql .= $statusCondition;
        $countSql .= $statusCondition;
        $params[] = $statusFilter;
    }

    if ($assignmentStatus !== 'all') {
        if ($assignmentStatus === 'unassigned') {
            $assignmentCondition = " AND (pa.assignment_status IS NULL OR pa.assignment_status = 'pending')";
            $sql .= $assignmentCondition;
            $countSql .= $assignmentCondition;
        } else {
            $assignmentCondition = " AND pa.assignment_status = ?";
            $sql .= $assignmentCondition;
            $countSql .= $assignmentCondition;
            $params[] = $assignmentStatus;
        }
    }

    if (!empty($dateFrom)) {
        $dateFromCondition = " AND DATE(p.created_at) >= ?";
        $sql .= $dateFromCondition;
        $countSql .= $dateFromCondition;
        $params[] = $dateFrom;
    }

    if (!empty($dateTo)) {
        $dateToCondition = " AND DATE(p.created_at) <= ?";
        $sql .= $dateToCondition;
        $countSql .= $dateToCondition;
        $params[] = $dateTo;
    }

    $sql .= " ORDER BY p.created_at DESC";
    
    // Add pagination if itemsPerPage > 0
    if ($itemsPerPage > 0) {
        $offset = ($page - 1) * $itemsPerPage;
        $sql .= " LIMIT ? OFFSET ?";
    }

    try {
        // Get total count
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $totalRecords = $countStmt->fetch()['total'];
        
        // Get paginated results
        $stmt = $pdo->prepare($sql);
        if ($itemsPerPage > 0) {
            $offset = ($page - 1) * $itemsPerPage;
            $stmt->execute(array_merge($params, [$itemsPerPage, $offset]));
        } else {
            $stmt->execute($params);
        }
        
        $patients = $stmt->fetchAll();
        
        return [
            'patients' => $patients,
            'total' => $totalRecords
        ];
    } catch (PDOException $e) {
        error_log("Error in getCODPatients: " . $e->getMessage());
        return ['patients' => [], 'total' => 0];
    }
}

// Get Clinical Instructors for assignment dropdown
function getClinicalInstructors() {
    global $pdo;

    if (!$pdo) return [];

    try {
        $stmt = $pdo->prepare("SELECT id, full_name, email, specialty_hint FROM users WHERE role = 'Clinical Instructor' AND account_status = 'active' ORDER BY full_name ASC");
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error getting Clinical Instructors: " . $e->getMessage());
        return [];
    }
}

// Assign patient to Clinical Instructor (COD function)
function assignPatientToClinicalInstructor($patientId, $codUserId, $clinicalInstructorId, $notes = '') {
    global $pdo;

    if (!$pdo) return false;

    try {
        $pdo->beginTransaction();
        
        // Check if assignment already exists
        $checkStmt = $pdo->prepare("SELECT id FROM patient_assignments WHERE patient_id = ?");
        $checkStmt->execute([$patientId]);
        $existingAssignment = $checkStmt->fetch();

        if ($existingAssignment) {
            // Update existing assignment
            $stmt = $pdo->prepare("
                UPDATE patient_assignments 
                SET clinical_instructor_id = ?, 
                    assignment_status = 'pending', 
                    notes = ?, 
                    assigned_at = NOW(),
                    updated_at = NOW()
                WHERE patient_id = ?
            ");
            $result = $stmt->execute([$clinicalInstructorId, $notes, $patientId]);
            $assignmentId = $existingAssignment['id'];
        } else {
            // Create new assignment
            $stmt = $pdo->prepare("
                INSERT INTO patient_assignments 
                (patient_id, cod_user_id, clinical_instructor_id, assignment_status, notes) 
                VALUES (?, ?, ?, 'pending', ?)
            ");
            $result = $stmt->execute([$patientId, $codUserId, $clinicalInstructorId, $notes]);
            $assignmentId = $pdo->lastInsertId();
        }

        if ($result) {
            // Create or update approval record
            $approvalStmt = $pdo->prepare("
                INSERT INTO patient_approvals 
                (patient_id, assignment_id, clinical_instructor_id, approval_status) 
                VALUES (?, ?, ?, 'pending')
                ON DUPLICATE KEY UPDATE
                clinical_instructor_id = VALUES(clinical_instructor_id),
                approval_status = 'pending',
                updated_at = NOW()
            ");
            $approvalStmt->execute([$patientId, $assignmentId, $clinicalInstructorId]);
        }

        $pdo->commit();
        return $result;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error assigning patient: " . $e->getMessage());
        return false;
    }
}

// Get patients assigned to a Clinical Instructor
function getClinicalInstructorPatients($clinicalInstructorId, $search = '', $statusFilter = 'all') {
    global $pdo;

    if (!$pdo) return [];

    $sql = "SELECT 
                p.id,
                p.first_name,
                p.last_name,
                p.email,
                p.phone,
                p.age,
                p.status as patient_status,
                p.created_at,
                u_clinician.full_name as created_by_clinician,
                pa.id as assignment_id,
                pa.assignment_status,
                pa.assigned_at,
                pa.notes as assignment_notes,
                papp.approval_status,
                papp.approval_notes,
                papp.approved_at
            FROM patients p
            JOIN users u_clinician ON p.created_by = u_clinician.id
            JOIN patient_assignments pa ON p.id = pa.patient_id
            LEFT JOIN patient_approvals papp ON pa.id = papp.assignment_id
            WHERE pa.clinical_instructor_id = ?";
    
    $params = [$clinicalInstructorId];

    if (!empty($search)) {
        $sql .= " AND (
            CONCAT(p.first_name, ' ', p.last_name) LIKE ? OR 
            p.email LIKE ? OR 
            p.phone LIKE ?
        )";
        $searchTerm = "%$search%";
        array_push($params, $searchTerm, $searchTerm, $searchTerm);
    }

    if ($statusFilter !== 'all') {
        if ($statusFilter === 'approval_pending') {
            $sql .= " AND (papp.approval_status IS NULL OR papp.approval_status = 'pending')";
        } else {
            $sql .= " AND papp.approval_status = ?";
            $params[] = $statusFilter;
        }
    }

    $sql .= " ORDER BY pa.assigned_at DESC";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error in getClinicalInstructorPatients: " . $e->getMessage());
        return [];
    }
}

// Clinical Instructor approval function
function updatePatientApproval($assignmentId, $clinicalInstructorId, $approvalStatus, $approvalNotes = '') {
    global $pdo;

    if (!$pdo) return false;

    try {
        $pdo->beginTransaction();
        
        // Check if approval record exists
        $checkStmt = $pdo->prepare("
            SELECT id FROM patient_approvals 
            WHERE assignment_id = ? AND clinical_instructor_id = ?
        ");
        $checkStmt->execute([$assignmentId, $clinicalInstructorId]);
        $existingApproval = $checkStmt->fetch();
        
        if ($existingApproval) {
            // Update existing approval record
            $stmt = $pdo->prepare("
                UPDATE patient_approvals 
                SET approval_status = ?, 
                    approval_notes = ?, 
                    approved_at = NOW(),
                    updated_at = NOW()
                WHERE assignment_id = ? AND clinical_instructor_id = ?
            ");
            $result = $stmt->execute([$approvalStatus, $approvalNotes, $assignmentId, $clinicalInstructorId]);
        } else {
            // Insert new approval record
            $stmt = $pdo->prepare("
                INSERT INTO patient_approvals 
                (assignment_id, clinical_instructor_id, approval_status, approval_notes, approved_at, created_at, updated_at)
                VALUES (?, ?, ?, ?, NOW(), NOW(), NOW())
            ");
            $result = $stmt->execute([$assignmentId, $clinicalInstructorId, $approvalStatus, $approvalNotes]);
        }

        if ($result && $approvalStatus === 'approved') {
            // Update patient status to Approved when Clinical Instructor approves
            $patientStmt = $pdo->prepare("
                UPDATE patients p
                JOIN patient_assignments pa ON p.id = pa.patient_id
                SET p.status = 'Approved'
                WHERE pa.id = ?
            ");
            $patientStmt->execute([$assignmentId]);
            
            // Update assignment status
            $assignmentStmt = $pdo->prepare("
                UPDATE patient_assignments 
                SET assignment_status = 'completed'
                WHERE id = ?
            ");
            $assignmentStmt->execute([$assignmentId]);
        } elseif ($result && $approvalStatus === 'rejected') {
            // Update patient status to Disapproved when Clinical Instructor rejects
            $patientStmt = $pdo->prepare("
                UPDATE patients p
                JOIN patient_assignments pa ON p.id = pa.patient_id
                SET p.status = 'Disapproved'
                WHERE pa.id = ?
            ");
            $patientStmt->execute([$assignmentId]);
        }

        $pdo->commit();
        return $result;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error updating patient approval: " . $e->getMessage());
        return false;
    }
}

// Update patient status by Clinical Instructor (only for assigned patients)
function updatePatientStatusByClinicalInstructor($patientId, $clinicalInstructorId, $newStatus) {
    global $pdo;

    if (!$pdo) return false;

    // Validate status values
    $validStatuses = ['Pending', 'Approved', 'Disapproved'];
    if (!in_array($newStatus, $validStatuses)) {
        error_log("Invalid status: $newStatus");
        return false;
    }

    try {
        $pdo->beginTransaction();
        
        // First, verify that this patient is assigned to this Clinical Instructor (accepted OR completed)
        $checkStmt = $pdo->prepare("
            SELECT pa.id as assignment_id, p.status as current_status, pa.assignment_status
            FROM patients p
            INNER JOIN patient_assignments pa ON p.id = pa.patient_id
            WHERE p.id = ? 
            AND pa.clinical_instructor_id = ? 
            AND pa.assignment_status IN ('accepted', 'completed')
        ");
        $checkStmt->execute([$patientId, $clinicalInstructorId]);
        $assignment = $checkStmt->fetch();
        
        if (!$assignment) {
            $pdo->rollBack();
            error_log("Clinical Instructor $clinicalInstructorId not authorized to update patient $patientId");
            return false;
        }
        
        // Update the patient status
        $updateStmt = $pdo->prepare("
            UPDATE patients 
            SET status = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $result = $updateStmt->execute([$newStatus, $patientId]);
        
        if ($result) {
            // Update or create approval record based on status
            $approvalStatus = '';
            $approvalNotes = "Status updated directly to $newStatus by Clinical Instructor";
            
            if ($newStatus === 'Approved') {
                $approvalStatus = 'approved';
            } elseif ($newStatus === 'Disapproved') {
                $approvalStatus = 'rejected';
            } else {
                $approvalStatus = 'pending';
            }
            
            // Check if approval record exists
            $approvalCheckStmt = $pdo->prepare("
                SELECT id FROM patient_approvals 
                WHERE assignment_id = ? AND clinical_instructor_id = ?
            ");
            $approvalCheckStmt->execute([$assignment['assignment_id'], $clinicalInstructorId]);
            $existingApproval = $approvalCheckStmt->fetch();
            
            if ($existingApproval) {
                // Update existing approval
                $approvalUpdateStmt = $pdo->prepare("
                    UPDATE patient_approvals 
                    SET approval_status = ?, 
                        approval_notes = ?, 
                        approved_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $approvalUpdateStmt->execute([$approvalStatus, $approvalNotes, $existingApproval['id']]);
            } else {
                // Create new approval record
                $approvalInsertStmt = $pdo->prepare("
                    INSERT INTO patient_approvals 
                    (patient_id, assignment_id, clinical_instructor_id, approval_status, approval_notes, approved_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $approvalInsertStmt->execute([$patientId, $assignment['assignment_id'], $clinicalInstructorId, $approvalStatus, $approvalNotes]);
            }
            
            // Update assignment status based on new patient status
            if ($newStatus === 'Approved' || $newStatus === 'Disapproved') {
                // Mark as completed when final decision is made
                $assignmentUpdateStmt = $pdo->prepare("
                    UPDATE patient_assignments 
                    SET assignment_status = 'completed', updated_at = NOW()
                    WHERE id = ?
                ");
                $assignmentUpdateStmt->execute([$assignment['assignment_id']]);
            } elseif ($newStatus === 'Pending' && $assignment['assignment_status'] === 'completed') {
                // Revert back to accepted if changing from completed status back to pending
                $assignmentUpdateStmt = $pdo->prepare("
                    UPDATE patient_assignments 
                    SET assignment_status = 'accepted', updated_at = NOW()
                    WHERE id = ?
                ");
                $assignmentUpdateStmt->execute([$assignment['assignment_id']]);
            }
        }
        
        $pdo->commit();
        return $result;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error updating patient status by Clinical Instructor: " . $e->getMessage());
        return false;
    }
}

// Get COD dashboard statistics
function getCODDashboardStats($codUserId) {
    global $pdo;

    if (!$pdo) return [];

    try {
        $stats = [];

        // Total patients created by Clinicians (COD oversight)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM patients p
            JOIN users u ON p.created_by = u.id
            WHERE u.role = 'Clinician'
        ");
        $stmt->execute();
        $stats['total_clinician_patients'] = $stmt->fetch()['count'];

        // Pending assignments (unassigned patients)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM patients p
            JOIN users u ON p.created_by = u.id
            LEFT JOIN patient_assignments pa ON p.id = pa.patient_id
            WHERE u.role = 'Clinician' 
            AND (pa.assignment_status IS NULL OR pa.assignment_status = 'pending')
        ");
        $stmt->execute();
        $stats['pending_assignments'] = $stmt->fetch()['count'];

        // Assigned patients waiting for approval
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM patients p
            JOIN users u ON p.created_by = u.id
            JOIN patient_assignments pa ON p.id = pa.patient_id
            LEFT JOIN patient_approvals papp ON pa.id = papp.assignment_id
            WHERE u.role = 'Clinician' 
            AND pa.assignment_status = 'accepted'
            AND (papp.approval_status IS NULL OR papp.approval_status = 'pending')
        ");
        $stmt->execute();
        $stats['awaiting_approval'] = $stmt->fetch()['count'];

        // Approved patients
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM patients p
            JOIN users u ON p.created_by = u.id
            JOIN patient_assignments pa ON p.id = pa.patient_id
            JOIN patient_approvals papp ON pa.id = papp.assignment_id
            WHERE u.role = 'Clinician' 
            AND papp.approval_status = 'approved'
        ");
        $stmt->execute();
        $stats['approved_patients'] = $stmt->fetch()['count'];

        return $stats;
    } catch (PDOException $e) {
        error_log("Error getting COD dashboard stats: " . $e->getMessage());
        return [];
    }
}

// Get assigned Clinical Instructor for a patient (based on COD assignment)
function getAssignedClinicalInstructor($patientId) {
    global $pdo;
    
    if (!$pdo) return null;
    
    try {
        // Get the Clinical Instructor assigned to this patient by COD
        $stmt = $pdo->prepare("
            SELECT u.full_name
            FROM patient_assignments pa
            INNER JOIN users u ON pa.clinical_instructor_id = u.id
            WHERE pa.patient_id = ? AND pa.assignment_status IN ('accepted', 'completed')
            ORDER BY pa.assigned_at DESC
            LIMIT 1
        ");
        $stmt->execute([$patientId]);
        $result = $stmt->fetch();
        
        return $result ? $result['full_name'] : null;
    } catch (PDOException $e) {
        error_log("Error getting assigned Clinical Instructor: " . $e->getMessage());
        return null;
    }
}

// Get pending patient assignments for Clinical Instructor
function getCIPendingAssignments($clinicalInstructorId, $search = '') {
    global $pdo;

    if (!$pdo) return [];

    $sql = "SELECT 
                p.id,
                p.first_name,
                p.last_name,
                p.email,
                p.phone,
                p.age,
                p.status as patient_status,
                p.treatment_hint,
                p.created_at,
                u_clinician.full_name as created_by_clinician,
                pa.id as assignment_id,
                pa.assignment_status,
                pa.assigned_at,
                pa.notes as assignment_notes,
                u_cod.full_name as assigned_by_cod
            FROM patients p
            JOIN users u_clinician ON p.created_by = u_clinician.id
            JOIN patient_assignments pa ON p.id = pa.patient_id
            LEFT JOIN users u_cod ON pa.cod_user_id = u_cod.id
            WHERE pa.clinical_instructor_id = ?
            AND pa.assignment_status = 'pending'";
    
    $params = [$clinicalInstructorId];

    if (!empty($search)) {
        $sql .= " AND (
            CONCAT(p.first_name, ' ', p.last_name) LIKE ? OR 
            p.email LIKE ? OR 
            p.phone LIKE ?
        )";
        $searchTerm = "%$search%";
        array_push($params, $searchTerm, $searchTerm, $searchTerm);
    }

    $sql .= " ORDER BY pa.assigned_at DESC";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error in getCIPendingAssignments: " . $e->getMessage());
        return [];
    }
}

// Update patient assignment status (accept/deny by Clinical Instructor)
function updateAssignmentStatus($assignmentId, $clinicalInstructorId, $status, $notes = '') {
    global $pdo;

    if (!$pdo) return false;

    // Validate status
    $validStatuses = ['accepted', 'rejected'];
    if (!in_array($status, $validStatuses)) {
        error_log("Invalid assignment status: $status");
        return false;
    }

    try {
        $pdo->beginTransaction();
        
        // Verify assignment belongs to this CI
        $checkStmt = $pdo->prepare("
            SELECT pa.id, pa.patient_id, pa.assignment_status 
            FROM patient_assignments pa
            WHERE pa.id = ? AND pa.clinical_instructor_id = ?
        ");
        $checkStmt->execute([$assignmentId, $clinicalInstructorId]);
        $assignment = $checkStmt->fetch();
        
        if (!$assignment) {
            $pdo->rollBack();
            error_log("Assignment $assignmentId not found or not assigned to CI $clinicalInstructorId");
            return false;
        }

        // Update assignment status
        $updateStmt = $pdo->prepare("
            UPDATE patient_assignments 
            SET assignment_status = ?, 
                notes = CONCAT(IFNULL(notes, ''), '\n\nCI Response: ', ?),
                updated_at = NOW()
            WHERE id = ?
        ");
        $result = $updateStmt->execute([$status, $notes, $assignmentId]);
        
        if ($result && $status === 'accepted') {
            // Create or update approval record when accepted
            $approvalStmt = $pdo->prepare("
                INSERT INTO patient_approvals 
                (patient_id, assignment_id, clinical_instructor_id, approval_status) 
                VALUES (?, ?, ?, 'pending')
                ON DUPLICATE KEY UPDATE
                clinical_instructor_id = VALUES(clinical_instructor_id),
                approval_status = 'pending',
                updated_at = NOW()
            ");
            $approvalStmt->execute([$assignment['patient_id'], $assignmentId, $clinicalInstructorId]);
        } elseif ($result && $status === 'rejected') {
            // Mark assignment as rejected - COD will need to reassign
            // Patient status remains as is
        }

        $pdo->commit();
        return $result;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error updating assignment status: " . $e->getMessage());
        return false;
    }
}

// Profile management functions
function updateProfilePicture($userId, $profilePicturePath) {
    global $pdo;
    
    if (!$pdo) return false;
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
        return $stmt->execute([$profilePicturePath, $userId]);
    } catch (PDOException $e) {
        error_log("Error updating profile picture: " . $e->getMessage());
        return false;
    }
}

function getProfilePicture($userId) {
    global $pdo;
    
    if (!$pdo) return null;
    
    try {
        $stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        return $result ? $result['profile_picture'] : null;
    } catch (PDOException $e) {
        error_log("Error getting profile picture: " . $e->getMessage());
        return null;
    }
}

function uploadProfilePicture($file, $userId) {
    // Validate file
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'message' => 'No file uploaded or invalid file.'];
    }
    
    // Check file size (max 5MB)
    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'File size too large. Maximum 5MB allowed.'];
    }
    
    // Check file type
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $fileType = mime_content_type($file['tmp_name']);
    if (!in_array($fileType, $allowedTypes)) {
        return ['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, GIF, and WebP images are allowed.'];
    }
    
    // Create unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'profile_' . $userId . '_' . time() . '.' . $extension;
    $uploadDir = __DIR__ . '/uploads/profile_photos/';
    $uploadPath = $uploadDir . $filename;
    
    // Create directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        // Remove old profile picture if exists
        $oldPicture = getProfilePicture($userId);
        if ($oldPicture && file_exists(__DIR__ . '/' . $oldPicture)) {
            unlink(__DIR__ . '/' . $oldPicture);
        }
        
        // Update database
        $relativePath = 'uploads/profile_photos/' . $filename;
        if (updateProfilePicture($userId, $relativePath)) {
            return ['success' => true, 'message' => 'Profile picture updated successfully.', 'path' => $relativePath];
        } else {
            // Remove uploaded file if database update failed
            unlink($uploadPath);
            return ['success' => false, 'message' => 'Failed to update database.'];
        }
    } else {
        return ['success' => false, 'message' => 'Failed to upload file.'];
    }
}

function deleteProfilePicture($userId) {
    global $pdo;
    
    if (!$pdo) return false;
    
    try {
        // Get current profile picture path
        $currentPicture = getProfilePicture($userId);
        
        // Remove from database
        $stmt = $pdo->prepare("UPDATE users SET profile_picture = NULL WHERE id = ?");
        $success = $stmt->execute([$userId]);
        
        // Remove file if database update was successful
        if ($success && $currentPicture && file_exists(__DIR__ . '/' . $currentPicture)) {
            unlink(__DIR__ . '/' . $currentPicture);
        }
        
        return $success;
    } catch (PDOException $e) {
        error_log("Error deleting profile picture: " . $e->getMessage());
        return false;
    }
}

function getUserRoleActions($role) {
    $actions = [
        'Admin' => [
            'Manage all users in the system',
            'Create, edit, and delete user accounts',
            'View all patient records',
            'Manage system settings and permissions',
            'Generate comprehensive reports',
            'Monitor system activity and logs'
        ],
        'Clinician' => [
            'Create new patient records',
            'Edit patient information and medical history',
            'Perform dental examinations',
            'Fill out patient health questionnaires',
            'Complete informed consent forms',
            'Add progress notes and treatment records'
        ],
        'Clinical Instructor' => [
            'Review patient records assigned by COD',
            'Approve or disapprove patient treatments',
            'Provide feedback on clinical work',
            'Monitor student/clinician progress',
            'Generate patient reports',
            'View assigned patient details'
        ],
        'COD' => [
            'Oversee all patient assignments',
            'Assign patients to Clinical Instructors',
            'Monitor assignment statuses',
            'View all patients created by Clinicians',
            'Generate assignment reports',
            'Manage Clinical Instructor workloads'
        ]
    ];
    
    return $actions[$role] ?? [];
}

// User profile management functions
function updateUserFullName($userId, $newFullName) {
    global $pdo;
    
    if (!$pdo) return false;
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET full_name = ? WHERE id = ?");
        return $stmt->execute([$newFullName, $userId]);
    } catch (PDOException $e) {
        error_log("Error updating user full name: " . $e->getMessage());
        return false;
    }
}

function validateFullName($fullName) {
    $errors = [];
    
    if (empty(trim($fullName))) {
        $errors[] = 'Full name cannot be empty.';
    } elseif (strlen(trim($fullName)) < 2) {
        $errors[] = 'Full name must be at least 2 characters long.';
    } elseif (strlen(trim($fullName)) > 100) {
        $errors[] = 'Full name cannot exceed 100 characters.';
    } elseif (!preg_match('/^[a-zA-Z\s.-]+$/', trim($fullName))) {
        $errors[] = 'Full name can only contain letters, spaces, dots, and hyphens.';
    }
    
    return $errors;
}

function validatePassword($currentPassword, $newPassword, $confirmPassword, $userHashedPassword) {
    $errors = [];
    
    if (!password_verify($currentPassword, $userHashedPassword)) {
        $errors[] = 'Current password is incorrect.';
    } 
    
    if ($newPassword !== $confirmPassword) {
        $errors[] = 'New passwords do not match.';
    }
    
    if (strlen($newPassword) < 6) {
        $errors[] = 'New password must be at least 6 characters long.';
    }
    
    if (strlen($newPassword) > 255) {
        $errors[] = 'New password is too long.';
    }
    
    return $errors;
}

// ========================================================
// Online CI Management and Automatic Assignment Functions
// ========================================================

// Get online Clinical Instructors with their current patient counts
function getOnlineClinicalInstructors() {
    global $pdo;
    
    if (!$pdo) return [];
    
    try {
        // Query to get users marked as online in the database
        // We trust the connection_status field as the source of truth
        $sql = "SELECT 
                    u.id,
                    u.full_name,
                    u.email,
                    u.specialty_hint,
                    u.connection_status,
                    u.last_activity,
                    COALESCE(pa_counts.current_patient_count, 0) as current_patient_count,
                    TIMESTAMPDIFF(MINUTE, u.last_activity, NOW()) as minutes_since_activity
                FROM users u
                LEFT JOIN (
                    SELECT 
                        clinical_instructor_id,
                        COUNT(*) as current_patient_count
                    FROM patient_assignments 
                    WHERE assignment_status IN ('accepted', 'pending')
                    GROUP BY clinical_instructor_id
                ) pa_counts ON u.id = pa_counts.clinical_instructor_id
                WHERE u.role = 'Clinical Instructor' 
                    AND u.account_status = 'active'
                    AND u.connection_status = 'online'
                ORDER BY current_patient_count ASC, u.full_name ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $instructors = $stmt->fetchAll();
        
        // Process instructors and handle stale connections
        $filteredInstructors = [];
        foreach ($instructors as $instructor) {
            // Check if user has been inactive for too long (more than 10 minutes)
            // Only auto-logout users who are truly inactive
            if ($instructor['last_activity'] && $instructor['minutes_since_activity'] > 10) {
                // User has been inactive for over 10 minutes, mark as offline
                setUserOnlineStatus($instructor['id'], 'offline');
                error_log("Auto-logged out CI ID {$instructor['id']} ({$instructor['full_name']}) due to inactivity: {$instructor['minutes_since_activity']} minutes");
                continue; // Skip this user
            }
            
            // User is online and recently active (or just logged in), include them
            $instructor['online_status'] = 'online';
            $filteredInstructors[] = $instructor;
        }
        
        return $filteredInstructors;
    } catch (PDOException $e) {
        error_log("Error getting online Clinical Instructors: " . $e->getMessage());
        return getOnlineClinicalInstructorsBasic();
    }
}

// Function to check if user is actually online based on activity timeout
// This is a simplified version that trusts the connection_status field
function isUserActuallyOnline($userData, $timeoutMinutes = 10) {
    if (!$userData) {
        return false;
    }
    
    // If connection_status is offline, user is offline
    if ($userData['connection_status'] === 'offline') {
        return false;
    }
    
    // If connection_status is online, check last activity
    if ($userData['connection_status'] === 'online') {
        // If no last activity, assume they just came online
        if (!$userData['last_activity']) {
            return true;
        }
        
        try {
            // Use database time for consistency
            global $pdo;
            $stmt = $pdo->prepare("SELECT TIMESTAMPDIFF(MINUTE, ?, NOW()) as minutes_diff");
            $stmt->execute([$userData['last_activity']]);
            $result = $stmt->fetch();
            
            $minutesDiff = $result['minutes_diff'] ?? 0;
            
            // Consider online if activity within timeout period
            return $minutesDiff <= $timeoutMinutes;
        } catch (Exception $e) {
            // Fallback to PHP calculation if database query fails
            error_log("Error in timezone calculation: " . $e->getMessage());
            return true; // Assume online on error to avoid hiding active users
        }
    }
    
    return false;
}

// Fallback function for basic CI retrieval
function getOnlineClinicalInstructorsBasic() {
    global $pdo;
    
    if (!$pdo) return [];
    
    try {
        $sql = "SELECT 
                    u.id,
                    u.full_name,
                    u.email,
                    u.specialty_hint,
                    u.connection_status,
                    u.last_activity,
                    0 as current_patient_count
                FROM users u
                WHERE u.role = 'Clinical Instructor' 
                    AND u.account_status = 'active' 
                    AND u.connection_status = 'online'
                ORDER BY u.full_name ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $instructors = $stmt->fetchAll();
        
        $filteredInstructors = [];
        foreach ($instructors as $instructor) {
            $onlineStatus = getUserOnlineStatus($instructor);
            if ($onlineStatus === 'online') {
                $instructor['online_status'] = 'online';
                $filteredInstructors[] = $instructor;
            }
        }
        
        return $filteredInstructors;
    } catch (PDOException $e) {
        error_log("Error in basic CI retrieval: " . $e->getMessage());
        return [];
    }
}

// Function to clean up offline users
function cleanupOfflineUsers() {
    global $pdo;
    
    if (!$pdo) return false;
    
    try {
        // Call the stored procedure if it exists
        $stmt = $pdo->prepare("CALL CleanupOfflineUsers()");
        if ($stmt->execute()) {
            return true;
        }
    } catch (PDOException $e) {
        // If stored procedure doesn't exist, use manual cleanup
        try {
            $stmt = $pdo->prepare(
                "UPDATE users 
                 SET connection_status = 'offline' 
                 WHERE connection_status = 'online' 
                   AND role = 'Clinical Instructor'
                   AND account_status = 'active'
                   AND last_activity IS NOT NULL 
                   AND last_activity < DATE_SUB(NOW(), INTERVAL 5 MINUTE)"
            );
            return $stmt->execute();
        } catch (PDOException $e2) {
            error_log("Error in manual cleanup: " . $e2->getMessage());
            return false;
        }
    }
    
    return false;
}

// Get all Clinical Instructors (for dropdown) with current patient counts
function getAllClinicalInstructorsWithCounts() {
    global $pdo;
    
    if (!$pdo) return [];
    
    try {
        $sql = "SELECT 
                    u.id,
                    u.full_name,
                    u.email,
                    u.specialty_hint,
                    u.connection_status,
                    u.last_activity,
                    COUNT(CASE WHEN pa.assignment_status IN ('accepted', 'pending') THEN 1 END) as current_patient_count
                FROM users u
                LEFT JOIN patient_assignments pa ON u.id = pa.clinical_instructor_id 
                WHERE u.role = 'Clinical Instructor' 
                    AND u.account_status = 'active'
                GROUP BY u.id, u.full_name, u.email, u.specialty_hint, u.connection_status, u.last_activity
                ORDER BY u.full_name ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $instructors = $stmt->fetchAll();
        
        // Add online status for each instructor
        foreach ($instructors as &$instructor) {
            $instructor['online_status'] = getUserOnlineStatus($instructor);
        }
        
        return $instructors;
    } catch (PDOException $e) {
        error_log("Error getting all Clinical Instructors with counts: " . $e->getMessage());
        return [];
    }
}

// Automatically assign patient to the CI with least patients (load balancing)
function autoAssignPatientToBestClinicalInstructor($patientId, $codUserId, $notes = '', $treatmentHint = '') {
    global $pdo;
    
    if (!$pdo) return false;
    
    try {
        $pdo->beginTransaction();
        
        // First try to find online CIs with matching procedure details
        $bestCI = null;
        if (!empty($treatmentHint)) {
            $sql = "SELECT 
                        u.id,
                        u.full_name,
                        u.specialty_hint,
                        COUNT(CASE WHEN pa.assignment_status IN ('accepted', 'pending') THEN 1 END) as current_patient_count
                    FROM users u
                    LEFT JOIN patient_assignments pa ON u.id = pa.clinical_instructor_id 
                    WHERE u.role = 'Clinical Instructor' 
                        AND u.account_status = 'active'
                        AND u.connection_status = 'online'
                        AND (
                            u.last_activity IS NULL OR 
                            u.last_activity >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                        )
                        AND u.specialty_hint IS NOT NULL
                        AND (
                            LOWER(u.specialty_hint) LIKE LOWER(?) OR
                            LOWER(?) LIKE LOWER(u.specialty_hint)
                        )
                    GROUP BY u.id, u.full_name, u.specialty_hint
                    ORDER BY current_patient_count ASC, RAND()
                    LIMIT 1";
            
            $stmt = $pdo->prepare($sql);
            $treatmentPattern = "%$treatmentHint%";
            $stmt->execute([$treatmentPattern, $treatmentPattern]);
            $bestCI = $stmt->fetch();
        }
        
        // If no matching procedure details CI found, get any online CI with least patients
        if (!$bestCI) {
            $sql = "SELECT 
                        u.id,
                        u.full_name,
                        u.specialty_hint,
                        COUNT(CASE WHEN pa.assignment_status IN ('accepted', 'pending') THEN 1 END) as current_patient_count
                    FROM users u
                    LEFT JOIN patient_assignments pa ON u.id = pa.clinical_instructor_id 
                    WHERE u.role = 'Clinical Instructor' 
                        AND u.account_status = 'active'
                        AND u.connection_status = 'online'
                        AND (
                            u.last_activity IS NULL OR 
                            u.last_activity >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                        )
                    GROUP BY u.id, u.full_name, u.specialty_hint
                    ORDER BY current_patient_count ASC, RAND()
                    LIMIT 1";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $bestCI = $stmt->fetch();
        }
        
        if (!$bestCI) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'No online Clinical Instructors available for assignment.'];
        }
        
        // Check if assignment already exists
        $checkStmt = $pdo->prepare("SELECT id FROM patient_assignments WHERE patient_id = ?");
        $checkStmt->execute([$patientId]);
        $existingAssignment = $checkStmt->fetch();
        
        $autoNotes = "Auto-assigned to CI with lowest patient count" . (!empty($notes) ? ". " . $notes : "");
        if (!empty($bestCI['specialty_hint']) && !empty($treatmentHint)) {
            $autoNotes .= " (Procedure details match: {$bestCI['specialty_hint']} for {$treatmentHint})";
        }
        
        if ($existingAssignment) {
            // Update existing assignment
            $stmt = $pdo->prepare("
                UPDATE patient_assignments 
                SET clinical_instructor_id = ?, 
                    assignment_status = 'accepted', 
                    notes = ?, 
                    assigned_at = NOW(),
                    updated_at = NOW()
                WHERE patient_id = ?
            ");
            $result = $stmt->execute([$bestCI['id'], $autoNotes, $patientId]);
            $assignmentId = $existingAssignment['id'];
        } else {
            // Create new assignment
            $stmt = $pdo->prepare("
                INSERT INTO patient_assignments 
                (patient_id, cod_user_id, clinical_instructor_id, assignment_status, notes) 
                VALUES (?, ?, ?, 'accepted', ?)
            ");
            $result = $stmt->execute([$patientId, $codUserId, $bestCI['id'], $autoNotes]);
            $assignmentId = $pdo->lastInsertId();
        }
        
        if ($result) {
            // Create or update approval record
            $approvalStmt = $pdo->prepare("
                INSERT INTO patient_approvals 
                (patient_id, assignment_id, clinical_instructor_id, approval_status) 
                VALUES (?, ?, ?, 'pending')
                ON DUPLICATE KEY UPDATE
                clinical_instructor_id = VALUES(clinical_instructor_id),
                approval_status = 'pending',
                updated_at = NOW()
            ");
            $approvalStmt->execute([$patientId, $assignmentId, $bestCI['id']]);
        }
        
        $pdo->commit();
        return [
            'success' => true, 
            'message' => 'Patient automatically assigned successfully.', 
            'assigned_to' => $bestCI['full_name'],
            'ci_id' => $bestCI['id'],
            'patient_count' => $bestCI['current_patient_count'] + 1
        ];
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error in auto assignment: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to automatically assign patient.'];
    }
}

// Add CI to available pool (manual addition by COD)
function addCIToAssignmentPool($ciId) {
    global $pdo;
    
    if (!$pdo) return false;
    
    try {
        // Verify CI exists and is active
        $stmt = $pdo->prepare("
            SELECT id, full_name FROM users 
            WHERE id = ? AND role = 'Clinical Instructor' AND account_status = 'active'
        ");
        $stmt->execute([$ciId]);
        $ci = $stmt->fetch();
        
        if ($ci) {
            // Set CI as online (optional - COD can manually add them to pool)
            setUserOnlineStatus($ciId, 'online');
            return ['success' => true, 'message' => "CI {$ci['full_name']} added to assignment pool."];
        } else {
            return ['success' => false, 'message' => 'Clinical Instructor not found or not active.'];
        }
    } catch (PDOException $e) {
        error_log("Error adding CI to pool: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to add CI to assignment pool.'];
    }
}

// =========================================================
// Patient Transfer Functions for Clinical Instructors
// =========================================================

// Create a patient transfer request from one CI to another
function createPatientTransferRequest($patientId, $assignmentId, $fromCIId, $toCIId, $transferReason = '') {
    global $pdo;
    
    if (!$pdo) return false;
    
    try {
        $pdo->beginTransaction();
        
        // Verify the requesting CI owns this assignment
        $checkStmt = $pdo->prepare("
            SELECT id, clinical_instructor_id, assignment_status
            FROM patient_assignments
            WHERE id = ? AND clinical_instructor_id = ?
        ");
        $checkStmt->execute([$assignmentId, $fromCIId]);
        $assignment = $checkStmt->fetch();
        
        if (!$assignment) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Assignment not found or not assigned to you.'];
        }
        
        // Check if there's already a pending transfer for this patient
        $pendingCheckStmt = $pdo->prepare("
            SELECT id FROM patient_transfers
            WHERE patient_id = ? AND transfer_status = 'pending'
        ");
        $pendingCheckStmt->execute([$patientId]);
        if ($pendingCheckStmt->fetch()) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'There is already a pending transfer request for this patient.'];
        }
        
        // Verify target CI exists and is active
        $ciCheckStmt = $pdo->prepare("
            SELECT id, full_name FROM users
            WHERE id = ? AND role = 'Clinical Instructor' AND account_status = 'active'
        ");
        $ciCheckStmt->execute([$toCIId]);
        $targetCI = $ciCheckStmt->fetch();
        
        if (!$targetCI) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Target Clinical Instructor not found or not active.'];
        }
        
        // Create transfer request
        $insertStmt = $pdo->prepare("
            INSERT INTO patient_transfers 
            (patient_id, assignment_id, from_clinical_instructor_id, to_clinical_instructor_id, transfer_status, transfer_reason)
            VALUES (?, ?, ?, ?, 'pending', ?)
        ");
        $result = $insertStmt->execute([$patientId, $assignmentId, $fromCIId, $toCIId, $transferReason]);
        
        $pdo->commit();
        
        if ($result) {
            return [
                'success' => true, 
                'message' => "Transfer request sent to {$targetCI['full_name']} successfully!",
                'transfer_id' => $pdo->lastInsertId()
            ];
        } else {
            return ['success' => false, 'message' => 'Failed to create transfer request.'];
        }
    } catch (PDOException $e) {
        // Rollback only if transaction is still active
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error creating transfer request: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error occurred while creating transfer request.'];
    }
}

// Respond to a transfer request (accept or reject)
function respondToTransferRequest($transferId, $toCIId, $action, $responseNotes = '') {
    global $pdo;
    
    if (!$pdo) return false;
    
    // Validate action
    if (!in_array($action, ['accept', 'reject'])) {
        return ['success' => false, 'message' => 'Invalid action. Must be accept or reject.'];
    }
    
    try {
        $pdo->beginTransaction();
        
        // Verify transfer exists and is pending
        $checkStmt = $pdo->prepare("
            SELECT 
                pt.id, pt.patient_id, pt.assignment_id, 
                pt.from_clinical_instructor_id, pt.to_clinical_instructor_id,
                pt.transfer_status, pt.transfer_reason,
                p.first_name, p.last_name,
                u_from.full_name as from_ci_name
            FROM patient_transfers pt
            JOIN patients p ON pt.patient_id = p.id
            JOIN users u_from ON pt.from_clinical_instructor_id = u_from.id
            WHERE pt.id = ? AND pt.to_clinical_instructor_id = ?
        ");
        $checkStmt->execute([$transferId, $toCIId]);
        $transfer = $checkStmt->fetch();
        
        if (!$transfer) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Transfer request not found or not assigned to you.'];
        }
        
        if ($transfer['transfer_status'] !== 'pending') {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'This transfer request is no longer pending.'];
        }
        
        if ($action === 'accept') {
            // Use stored procedure to accept transfer
            $stmt = $pdo->prepare("CALL AcceptPatientTransfer(?, ?)");
            $result = $stmt->execute([$transferId, $responseNotes]);
            
            // Commit if transaction is still active
            if ($pdo->inTransaction()) {
                $pdo->commit();
            }
            
            return [
                'success' => true,
                'message' => "Patient {$transfer['first_name']} {$transfer['last_name']} has been successfully transferred to you!",
                'patient_name' => "{$transfer['first_name']} {$transfer['last_name']}"
            ];
        } else {
            // Use stored procedure to reject transfer
            $stmt = $pdo->prepare("CALL RejectPatientTransfer(?, ?)");
            $result = $stmt->execute([$transferId, $responseNotes]);
            
            // Commit if transaction is still active
            if ($pdo->inTransaction()) {
                $pdo->commit();
            }
            
            return [
                'success' => true,
                'message' => "Transfer request from {$transfer['from_ci_name']} has been rejected.",
                'from_ci' => $transfer['from_ci_name']
            ];
        }
    } catch (PDOException $e) {
        // Rollback only if transaction is still active
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error responding to transfer request: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error occurred while processing transfer response.'];
    }
}

// Cancel a transfer request (by the sender)
function cancelTransferRequest($transferId, $fromCIId) {
    global $pdo;
    
    if (!$pdo) return false;
    
    try {
        // Use stored procedure to cancel transfer
        $stmt = $pdo->prepare("CALL CancelPatientTransfer(?, ?)");
        $result = $stmt->execute([$transferId, $fromCIId]);
        
        if ($result) {
            return ['success' => true, 'message' => 'Transfer request has been cancelled successfully.'];
        } else {
            return ['success' => false, 'message' => 'Failed to cancel transfer request.'];
        }
    } catch (PDOException $e) {
        error_log("Error cancelling transfer request: " . $e->getMessage());
        
        // Check if error is from stored procedure validation
        if (strpos($e->getMessage(), 'Only the requesting CI') !== false) {
            return ['success' => false, 'message' => 'You can only cancel your own transfer requests.'];
        } elseif (strpos($e->getMessage(), 'Only pending transfers') !== false) {
            return ['success' => false, 'message' => 'Only pending transfers can be cancelled.'];
        }
        
        return ['success' => false, 'message' => 'Database error occurred while cancelling transfer.'];
    }
}

// Get incoming transfer requests for a CI (transfers TO this CI)
function getIncomingTransferRequests($toCIId, $statusFilter = 'pending') {
    global $pdo;
    
    if (!$pdo) return [];
    
    $sql = "SELECT 
                pt.id as transfer_id,
                pt.patient_id,
                p.first_name,
                p.last_name,
                p.email,
                p.phone,
                p.age,
                p.treatment_hint,
                pt.from_clinical_instructor_id,
                u_from.full_name as from_ci_name,
                u_from.email as from_ci_email,
                pt.transfer_status,
                pt.transfer_reason,
                pt.response_notes,
                pt.requested_at,
                pt.responded_at,
                pa.assignment_status
            FROM patient_transfers pt
            JOIN patients p ON pt.patient_id = p.id
            JOIN users u_from ON pt.from_clinical_instructor_id = u_from.id
            JOIN patient_assignments pa ON pt.assignment_id = pa.id
            WHERE pt.to_clinical_instructor_id = ?";
    
    $params = [$toCIId];
    
    if ($statusFilter !== 'all') {
        $sql .= " AND pt.transfer_status = ?";
        $params[] = $statusFilter;
    }
    
    $sql .= " ORDER BY pt.requested_at DESC";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error getting incoming transfer requests: " . $e->getMessage());
        return [];
    }
}

// Get outgoing transfer requests from a CI (transfers FROM this CI)
function getOutgoingTransferRequests($fromCIId, $statusFilter = 'pending') {
    global $pdo;
    
    if (!$pdo) return [];
    
    $sql = "SELECT 
                pt.id as transfer_id,
                pt.patient_id,
                p.first_name,
                p.last_name,
                p.email,
                p.phone,
                p.age,
                p.treatment_hint,
                pt.to_clinical_instructor_id,
                u_to.full_name as to_ci_name,
                u_to.email as to_ci_email,
                pt.transfer_status,
                pt.transfer_reason,
                pt.response_notes,
                pt.requested_at,
                pt.responded_at,
                pa.assignment_status
            FROM patient_transfers pt
            JOIN patients p ON pt.patient_id = p.id
            JOIN users u_to ON pt.to_clinical_instructor_id = u_to.id
            JOIN patient_assignments pa ON pt.assignment_id = pa.id
            WHERE pt.from_clinical_instructor_id = ?";
    
    $params = [$fromCIId];
    
    if ($statusFilter !== 'all') {
        $sql .= " AND pt.transfer_status = ?";
        $params[] = $statusFilter;
    }
    
    $sql .= " ORDER BY pt.requested_at DESC";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error getting outgoing transfer requests: " . $e->getMessage());
        return [];
    }
}

// Get all available CIs for transfer (excluding self)
function getAvailableCIsForTransfer($currentCIId) {
    global $pdo;
    
    if (!$pdo) return [];
    
    $sql = "SELECT 
                u.id,
                u.full_name,
                u.email,
                u.specialty_hint,
                u.connection_status,
                u.last_activity,
                COUNT(CASE WHEN pa.assignment_status IN ('accepted', 'pending') THEN 1 END) as current_patient_count
            FROM users u
            LEFT JOIN patient_assignments pa ON u.id = pa.clinical_instructor_id
            WHERE u.role = 'Clinical Instructor'
                AND u.account_status = 'active'
                AND u.id != ?
            GROUP BY u.id, u.full_name, u.email, u.specialty_hint, u.connection_status, u.last_activity
            ORDER BY current_patient_count ASC, u.full_name ASC";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$currentCIId]);
        $instructors = $stmt->fetchAll();
        
        // Add online status for each instructor
        foreach ($instructors as &$instructor) {
            $instructor['online_status'] = getUserOnlineStatus($instructor);
        }
        
        return $instructors;
    } catch (PDOException $e) {
        error_log("Error getting available CIs for transfer: " . $e->getMessage());
        return [];
    }
}

// Get transfer request counts for a CI
function getTransferRequestCounts($ciId) {
    global $pdo;
    
    if (!$pdo) return ['incoming_pending' => 0, 'outgoing_pending' => 0];
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(CASE WHEN to_clinical_instructor_id = ? AND transfer_status = 'pending' THEN 1 END) as incoming_pending,
                COUNT(CASE WHEN from_clinical_instructor_id = ? AND transfer_status = 'pending' THEN 1 END) as outgoing_pending
            FROM patient_transfers
        ");
        $stmt->execute([$ciId, $ciId]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error getting transfer request counts: " . $e->getMessage());
        return ['incoming_pending' => 0, 'outgoing_pending' => 0];
    }
}

// ========================================================
// Procedure Assignment Functions for COD and CI
// ========================================================

// Get procedure logs for COD with assignment details
function getCODProcedureAssignments($search = '', $dateFrom = '', $dateTo = '', $assignmentStatus = 'all', $page = 1, $itemsPerPage = 0) {
    global $pdo;

    if (!$pdo) return ['procedures' => [], 'total' => 0];

    $sql = "SELECT 
                pl.id as procedure_log_id,
                pl.patient_id,
                pl.patient_name,
                pl.age,
                pl.sex,
                pl.procedure_selected,
                pl.procedure_details,
                pl.chair_number,
                pl.status,
                pl.remarks,
                pl.clinician_name,
                pl.clinician_id,
                pl.logged_at,
                u_clinician.full_name as clinician_full_name,
                pra.id as assignment_id,
                pra.assignment_status,
                pra.assigned_at,
                pra.notes as assignment_notes,
                u_ci.full_name as assigned_clinical_instructor,
                u_ci.id as clinical_instructor_id
            FROM procedure_logs pl
            LEFT JOIN users u_clinician ON pl.clinician_id = u_clinician.id
            LEFT JOIN procedure_assignments pra ON pl.id = pra.procedure_log_id
            LEFT JOIN users u_ci ON pra.clinical_instructor_id = u_ci.id
            WHERE 1=1";
    
    // Count query for total records
    $countSql = "SELECT COUNT(DISTINCT pl.id) as total
            FROM procedure_logs pl
            LEFT JOIN users u_clinician ON pl.clinician_id = u_clinician.id
            LEFT JOIN procedure_assignments pra ON pl.id = pra.procedure_log_id
            LEFT JOIN users u_ci ON pra.clinical_instructor_id = u_ci.id
            WHERE 1=1";
    
    $params = [];

    if (!empty($search)) {
        $searchCondition = " AND (
            pl.patient_name LIKE ? OR 
            pl.clinician_name LIKE ? OR
            pl.procedure_selected LIKE ?
        )";
        $sql .= $searchCondition;
        $countSql .= $searchCondition;
        $searchTerm = "%$search%";
        array_push($params, $searchTerm, $searchTerm, $searchTerm);
    }

    if ($assignmentStatus !== 'all') {
        if ($assignmentStatus === 'unassigned') {
            $assignmentCondition = " AND (pra.assignment_status IS NULL OR pra.assignment_status = 'pending')";
            $sql .= $assignmentCondition;
            $countSql .= $assignmentCondition;
        } else {
            $assignmentCondition = " AND pra.assignment_status = ?";
            $sql .= $assignmentCondition;
            $countSql .= $assignmentCondition;
            $params[] = $assignmentStatus;
        }
    }

    if (!empty($dateFrom)) {
        $dateFromCondition = " AND DATE(pl.logged_at) >= ?";
        $sql .= $dateFromCondition;
        $countSql .= $dateFromCondition;
        $params[] = $dateFrom;
    }

    if (!empty($dateTo)) {
        $dateToCondition = " AND DATE(pl.logged_at) <= ?";
        $sql .= $dateToCondition;
        $countSql .= $dateToCondition;
        $params[] = $dateTo;
    }

    $sql .= " ORDER BY pl.logged_at DESC";
    
    // Add pagination if itemsPerPage > 0
    if ($itemsPerPage > 0) {
        $offset = ($page - 1) * $itemsPerPage;
        $sql .= " LIMIT ? OFFSET ?";
    }

    try {
        // Get total count
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $totalRecords = $countStmt->fetch()['total'];
        
        // Get paginated results
        $stmt = $pdo->prepare($sql);
        if ($itemsPerPage > 0) {
            $offset = ($page - 1) * $itemsPerPage;
            $stmt->execute(array_merge($params, [$itemsPerPage, $offset]));
        } else {
            $stmt->execute($params);
        }
        
        $procedures = $stmt->fetchAll();
        
        return [
            'procedures' => $procedures,
            'total' => $totalRecords
        ];
    } catch (PDOException $e) {
        error_log("Error in getCODProcedureAssignments: " . $e->getMessage());
        return ['procedures' => [], 'total' => 0];
    }
}

// Auto-assign procedure to best available Clinical Instructor
function autoAssignProcedureToBestCI($procedureLogId, $codUserId, $procedureDetails = '', $notes = '') {
    global $pdo;
    
    if (!$pdo) return ['success' => false, 'message' => 'Database connection failed'];
    
    try {
        // Get online Clinical Instructors with their current workload
        $onlineCIs = getOnlineClinicalInstructors();
        
        if (empty($onlineCIs)) {
            return ['success' => false, 'message' => 'No Clinical Instructors are currently online'];
        }
        
        // Find best match based on specialty and workload
        $bestCI = null;
        $bestScore = -1;
        
        foreach ($onlineCIs as $ci) {
            $score = 0;
            
            // Lower patient count = higher score
            $score += (10 - min($ci['current_patient_count'], 10)) * 10;
            
            // Specialty match bonus
            if (!empty($procedureDetails) && !empty($ci['specialty_hint'])) {
                $procedureDetailsLower = strtolower($procedureDetails);
                $specialtyLower = strtolower($ci['specialty_hint']);
                
                // Check for keyword matches
                if (strpos($procedureDetailsLower, $specialtyLower) !== false || 
                    strpos($specialtyLower, $procedureDetailsLower) !== false) {
                    $score += 50; // Bonus for specialty match
                }
            }
            
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestCI = $ci;
            }
        }
        
        if (!$bestCI) {
            return ['success' => false, 'message' => 'Could not find suitable Clinical Instructor'];
        }
        
        // Assign procedure to the best CI
        $assignmentNotes = "Auto-assigned based on specialty match and workload. " . ($notes ?: '');
        $result = assignProcedureToClinicalInstructor($procedureLogId, $codUserId, $bestCI['id'], $assignmentNotes);
        
        if ($result) {
            return [
                'success' => true,
                'message' => 'Procedure automatically assigned successfully',
                'assigned_to' => $bestCI['full_name'],
                'ci_id' => $bestCI['id']
            ];
        } else {
            return ['success' => false, 'message' => 'Failed to assign procedure'];
        }
    } catch (Exception $e) {
        error_log("Error in autoAssignProcedureToBestCI: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error occurred during auto-assignment'];
    }
}

// Assign procedure to Clinical Instructor (COD function)
function assignProcedureToClinicalInstructor($procedureLogId, $codUserId, $clinicalInstructorId, $notes = '') {
    global $pdo;

    if (!$pdo) return false;

    try {
        $pdo->beginTransaction();
        
        // Check if assignment already exists
        $checkStmt = $pdo->prepare("SELECT id FROM procedure_assignments WHERE procedure_log_id = ?");
        $checkStmt->execute([$procedureLogId]);
        $existingAssignment = $checkStmt->fetch();

        if ($existingAssignment) {
            // Update existing assignment
            $stmt = $pdo->prepare("
                UPDATE procedure_assignments 
                SET clinical_instructor_id = ?, 
                    assignment_status = 'pending', 
                    notes = ?, 
                    assigned_at = NOW(),
                    updated_at = NOW()
                WHERE procedure_log_id = ?
            ");
            $result = $stmt->execute([$clinicalInstructorId, $notes, $procedureLogId]);
        } else {
            // Create new assignment
            $stmt = $pdo->prepare("
                INSERT INTO procedure_assignments 
                (procedure_log_id, cod_user_id, clinical_instructor_id, assignment_status, notes) 
                VALUES (?, ?, ?, 'pending', ?)
            ");
            $result = $stmt->execute([$procedureLogId, $codUserId, $clinicalInstructorId, $notes]);
        }

        $pdo->commit();
        return $result;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error assigning procedure: " . $e->getMessage());
        return false;
    }
}

// Get pending procedure assignments for Clinical Instructor
function getCIPendingProcedureAssignments($clinicalInstructorId, $search = '') {
    global $pdo;

    if (!$pdo) return [];

    $sql = "SELECT 
                pl.id as procedure_log_id,
                pl.patient_id,
                pl.patient_name,
                pl.age,
                pl.sex,
                pl.procedure_selected,
                pl.procedure_details,
                pl.chair_number,
                pl.status,
                pl.remarks,
                pl.clinician_name,
                pl.clinician_id,
                pl.logged_at,
                u_clinician.full_name as clinician_full_name,
                pra.id as assignment_id,
                pra.assignment_status,
                pra.assigned_at,
                pra.notes as assignment_notes,
                u_cod.full_name as assigned_by_cod
            FROM procedure_logs pl
            LEFT JOIN users u_clinician ON pl.clinician_id = u_clinician.id
            JOIN procedure_assignments pra ON pl.id = pra.procedure_log_id
            LEFT JOIN users u_cod ON pra.cod_user_id = u_cod.id
            WHERE pra.clinical_instructor_id = ?
            AND pra.assignment_status = 'pending'";
    
    $params = [$clinicalInstructorId];

    if (!empty($search)) {
        $sql .= " AND (
            pl.patient_name LIKE ? OR 
            pl.clinician_name LIKE ? OR
            pl.procedure_selected LIKE ?
        )";
        $searchTerm = "%$search%";
        array_push($params, $searchTerm, $searchTerm, $searchTerm);
    }

    $sql .= " ORDER BY pra.assigned_at DESC";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error in getCIPendingProcedureAssignments: " . $e->getMessage());
        return [];
    }
}

// Update procedure assignment status (accept/deny by Clinical Instructor)
function updateProcedureAssignmentStatus($assignmentId, $clinicalInstructorId, $status, $notes = '') {
    global $pdo;

    if (!$pdo) return false;

    // Validate status
    $validStatuses = ['accepted', 'rejected', 'completed'];
    if (!in_array($status, $validStatuses)) {
        error_log("Invalid procedure assignment status: $status");
        return false;
    }

    try {
        $pdo->beginTransaction();
        
        // Verify assignment belongs to this CI
        $checkStmt = $pdo->prepare("
            SELECT pra.id, pra.procedure_log_id, pra.assignment_status 
            FROM procedure_assignments pra
            WHERE pra.id = ? AND pra.clinical_instructor_id = ?
        ");
        $checkStmt->execute([$assignmentId, $clinicalInstructorId]);
        $assignment = $checkStmt->fetch();
        
        if (!$assignment) {
            $pdo->rollBack();
            error_log("Procedure assignment $assignmentId not found or not assigned to CI $clinicalInstructorId");
            return false;
        }

        // Update assignment status
        $updateStmt = $pdo->prepare("
            UPDATE procedure_assignments 
            SET assignment_status = ?, 
                notes = CONCAT(IFNULL(notes, ''), '\n\nCI Response: ', ?),
                updated_at = NOW()
            WHERE id = ?
        ");
        $result = $updateStmt->execute([$status, $notes, $assignmentId]);
        
        $pdo->commit();
        return $result;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error updating procedure assignment status: " . $e->getMessage());
        return false;
    }
}

?>
