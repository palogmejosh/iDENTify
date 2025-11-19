<?php
require_once 'config.php';
requireAuth();

// Get all patients for testing
$stmt = $pdo->query("SELECT id, first_name, last_name FROM patients ORDER BY last_name, first_name LIMIT 5");
$patients = $stmt->fetchAll();

header('Content-Type: text/html; charset=utf-8');
echo '<h2>Debug: CI Progress Notes Loading</h2>';

foreach ($patients as $patient) {
    $patientId = $patient['id'];
    $patientName = $patient['first_name'] . ' ' . $patient['last_name'];
    
    echo "<h3>Patient: {$patientName} (ID: {$patientId})</h3>";
    
    // Test progress notes loading
    $url = "http://localhost/iDENTify/ci_edit_progress_notes.php?id={$patientId}";
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "Content-Type: application/json\r\n"
        ]
    ]);
    
    $response = file_get_contents($url, false, $context);
    
    if ($response === false) {
        echo '<p style="color: red;">Error: Failed to get response</p>';
        
        // Check for PHP errors
        $error = error_get_last();
        if ($error) {
            echo '<p style="color: red;">PHP Error: ' . htmlspecialchars($error['message']) . '</p>';
        }
    } else {
        echo '<p>Response:</p>';
        echo '<pre>' . htmlspecialchars($response) . '</pre>';
    }
    
    echo '<hr>';
}

// Check if user is Clinical Instructor
$user = getCurrentUser();
$role = $user['role'] ?? '';
echo "<p>Current user role: " . htmlspecialchars($role) . "</p>";

// If not CI, show test with simulated CI data
if ($role !== 'Clinical Instructor') {
    echo '<p>Note: You are not logged in as Clinical Instructor. This test is showing real responses.</p>';
    
    // Simulate what a successful response should look like
    $testPatient = $patients[0] ?? ['id' => 1];
    echo '<h3>Expected successful response format:</h3>';
    $expected = [
        'success' => true,
        'patient' => [
            'age' => 25,
            'gender' => 'Male'
        ],
        'patientFullName' => $testPatient['first_name'] . ' ' . $testPatient['last_name'],
        'progressNotes' => [
            [
                'id' => 1,
                'date' => '2023-01-01',
                'tooth' => '1',
                'progress' => '[AUTO] Procedure Logged: Test procedure',
                'clinician' => 'Test Clinician',
                'ci' => 'Test CI',
                'remarks' => '',
                'is_auto_generated' => true,
                'treatment_plan' => 'Test procedure'
            ]
        ],
        'sharedSignaturePath' => null,
        'currentUserName' => 'Test CI Username'
    ];
    echo '<pre>' . htmlspecialchars(json_encode($expected, JSON_PRETTY_PRINT)) . '</pre>';
}
?>