
<?php
require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$action = $_GET['action'] ?? '';
$response = ['success' => false, 'data' => null, 'message' => ''];

switch ($action) {
    case 'get_patients':
        $search = $_GET['search'] ?? '';
        $lastUpdate = $_GET['last_update'] ?? '';
        
        $patients = getPatients($search);
        $currentUpdate = getLastUpdateTime('patients');
        
        $response = [
            'success' => true,
            'data' => $patients,
            'last_update' => $currentUpdate,
            'has_updates' => $lastUpdate !== $currentUpdate
        ];
        break;
        
    case 'get_users':
        $roleFilter = $_GET['role'] ?? 'all';
        $search = $_GET['search'] ?? '';
        $lastUpdate = $_GET['last_update'] ?? '';
        
        $users = getUsers($roleFilter, $search);
        $currentUpdate = getLastUpdateTime('users');
        
        $response = [
            'success' => true,
            'data' => $users,
            'last_update' => $currentUpdate,
            'has_updates' => $lastUpdate !== $currentUpdate
        ];
        break;
        
    case 'add_patient':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            
            // Get current user to track who created the patient
            $currentUser = getCurrentUser();
            $createdBy = $currentUser ? $currentUser['id'] : null;
            
            if (addPatient($input['firstName'], $input['lastName'], $input['age'], $input['phone'], $input['email'], $input['status'] ?? 'Pending', $createdBy)) {
                $response = [
                    'success' => true,
                    'message' => 'Patient added successfully',
                    'last_update' => getLastUpdateTime('patients')
                ];
            } else {
                $response['message'] = 'Failed to add patient';
            }
        }
        break;
        
    case 'add_user':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (addUser($input['username'], $input['fullName'], $input['email'], $input['password'], $input['role'])) {
                $response = [
                    'success' => true,
                    'message' => 'User added successfully',
                    'last_update' => getLastUpdateTime('users')
                ];
            } else {
                $response['message'] = 'Failed to add user';
            }
        }
        break;
        
    default:
        $response['message'] = 'Invalid action';
}

echo json_encode($response);
?>
