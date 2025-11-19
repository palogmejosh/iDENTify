<?php
require_once 'config.php';
requireAuth();

// Test script to validate progress notes synchronization between 
// patients.php CI modal and edit_patient_step5.php

$user = getCurrentUser();
$role = $user['role'] ?? '';

// Only Clinical Instructors can use this test
if ($role !== 'Clinical Instructor') {
    exit('This test is only for Clinical Instructors');
}

$patientId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$patientId) {
    exit('Please provide a valid patient ID: ?id=123');
}

echo "<h2>Progress Notes Synchronization Test</h2>";
echo "<p>Patient ID: {$patientId}</p>";
echo "<p>Testing data consistency between CI modal and edit_patient_step5.php</p>";

// Test 1: Load data using ci_edit_progress_notes.php endpoint
echo "<h3>Test 1: Data from ci_edit_progress_notes.php</h3>";
$url = "http://localhost/identify/ci_edit_progress_notes.php?id={$patientId}";
$context = stream_context_create([
    'http' => [
        'header' => [
            'Cookie: ' . $_SERVER['HTTP_COOKIE'] ?? '',
        ]
    ]
]);

$apiData = file_get_contents($url, false, $context);
$ciData = json_decode($apiData, true);

if ($ciData && $ciData['success']) {
    echo "<pre>";
    echo "Number of progress notes: " . count($ciData['progressNotes']) . "\n";
    foreach ($ciData['progressNotes'] as $i => $note) {
        echo "Row " . ($i + 1) . ": ID={$note['id']}, Date={$note['date']}, Progress=" . substr($note['progress'], 0, 50) . "...\n";
    }
    echo "</pre>";
} else {
    echo "<p>Error loading data from CI endpoint</p>";
}

// Test 2: Load data directly from database (same as edit_patient_step5.php)
echo "<h3>Test 2: Direct database query (edit_patient_step5.php method)</h3>";
$stmt = $pdo->prepare("SELECT * FROM progress_notes WHERE patient_id = ? ORDER BY id ASC");
$stmt->execute([$patientId]);
$directData = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<pre>";
echo "Number of progress notes: " . count($directData) . "\n";
foreach ($directData as $i => $note) {
    echo "Row " . ($i + 1) . ": ID={$note['id']}, Date={$note['date']}, Progress=" . substr($note['progress'], 0, 50) . "...\n";
}
echo "</pre>";

// Test 3: Compare data consistency
echo "<h3>Test 3: Data Consistency Check</h3>";
if ($ciData && $ciData['success'] && count($ciData['progressNotes']) === count($directData)) {
    $isConsistent = true;
    foreach ($ciData['progressNotes'] as $i => $ciNote) {
        $directNote = $directData[$i];
        if ($ciNote['id'] != $directNote['id'] || 
            $ciNote['date'] != $directNote['date'] ||
            $ciNote['tooth'] != $directNote['tooth']) {
            $isConsistent = false;
            echo "<p style='color: red;'>Inconsistency found in row " . ($i + 1) . "</p>";
            break;
        }
    }
    
    if ($isConsistent) {
        echo "<p style='color: green;'><strong>✓ Data is consistent between both interfaces!</strong></p>";
    }
} else {
    echo "<p style='color: red;'>Row count mismatch or API error</p>";
}

echo "<hr>";
echo "<p><a href='patients.php'>← Back to Patients</a> | <a href='edit_patient_step5.php?id={$patientId}'>Open Step 5 Editor</a></p>";
?>