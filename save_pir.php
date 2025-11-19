<?php
require_once 'config.php';
requireAuth();

$user = getCurrentUser();
$role = $user['role'] ?? '';
if (!in_array($role, ['Admin', 'Clinician'])) {
    header('Location: patients.php');
    exit;
}

$patientId = $_POST['patient_id'] ?? null;
if (!$patientId || !is_numeric($patientId)) {
    // Redirect or show error if patient ID is invalid
    header('Location: patients.php?error=invalid_id');
    exit;
}
$patientId = (int)$patientId;

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ---------- 1.  Helper to save Base64 Signature ---------- */
// This function saves a base64 encoded image to a file and returns the path.
function saveBase64(string $base64Key, int $patientId): string
{
    if (empty($_POST[$base64Key])) {
        return '';
    }
    
    $raw = $_POST[$base64Key];
    if (!str_starts_with($raw, 'data:image/png;base64,')) {
        return '';
    }

    $folder = __DIR__ . '/uploads/signature';
    if (!is_dir($folder)) {
        if (!mkdir($folder, 0755, true)) {
            // In a real app, handle this error more gracefully
            die('Failed to create signature directory.');
        }
    }

    $bin = base64_decode(substr($raw, 22), true);
    if ($bin === false) {
        return '';
    }

    // Use a consistent name for the shared signature file
    $filename = $patientId . '_data_privacy_signature_path.png';
    $filepath = $folder . '/' . $filename;
    file_put_contents($filepath, $bin);

    return 'uploads/signature/' . $filename;
}

/* ---------- 2. Handle Shared Signature ---------- */
// Save the new signature if one was drawn.
$newSignaturePath = saveBase64('data_privacy_signature_path_base64', $patientId);
$finalSignaturePath = $newSignaturePath ?: ($_POST['old_data_privacy_signature_path'] ?? '');

// If there's a signature path, update the informed_consent table.
if (!empty($finalSignaturePath)) {
    $consentSql = "INSERT INTO informed_consent (patient_id, data_privacy_signature_path) VALUES (?, ?)
                   ON DUPLICATE KEY UPDATE data_privacy_signature_path = VALUES(data_privacy_signature_path)";
    $consentStmt = $pdo->prepare($consentSql);
    $consentStmt->execute([$patientId, $finalSignaturePath]);
}


/* ---------- 3.  Collect all text fields for patient_pir table ---------- */
$fields = [
    'last_name', 'first_name', 'mi', 'nickname', 'age', 'gender', 'date_of_birth', 
    'civil_status', 'home_address', 'home_phone', 'mobile_no', 'email', 'occupation', 
    'work_address', 'work_phone', 'ethnicity', 'guardian_name', 'guardian_contact', 
    'emergency_contact_name', 'emergency_contact_number', 'date_today', 'clinician', 
    'clinic', 'chief_complaint', 'present_illness', 'medical_history', 'dental_history', 
    'family_history', 'personal_history', 'skin', 'extremities', 'eyes', 'ent', 
    'respiratory', 'cardiovascular', 'gastrointestinal', 'genitourinary', 'endocrine', 
    'hematopoietic', 'neurological', 'psychiatric', 'growth_or_tumor', 'summary', 
    'asa', 'asa_notes', 'patient_signature' // This now holds the printed name
];

$data = [];
foreach ($fields as $field) {
    // Handle empty dates as NULL
    if (str_ends_with($field, '_date') && empty($_POST[$field])) {
        $data[$field] = null;
    } else {
        $data[$field] = $_POST[$field] ?? null;
    }
}

/* ---------- 4.  Helper to upload Photo/Thumbmark files ---------- */
function uploadFile($key, $prefix, &$targetPath)
{
    if (empty($_FILES[$key]['name'])) return false;

    $uploadDir = 'uploads/' . $prefix . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $filename   = basename($_FILES[$key]['name']);
    $targetPath = $uploadDir . uniqid($prefix . '_') . '_' . $filename;

    return move_uploaded_file($_FILES[$key]['tmp_name'], $targetPath);
}

/* ---------- 4b. Helper to save camera captured image ---------- */
function saveCameraImage($base64Data, $prefix, &$targetPath)
{
    if (empty($base64Data)) return false;
    
    // Check if it's a valid base64 image
    if (!preg_match('/^data:image\/([a-zA-Z]*);base64,/', $base64Data, $matches)) {
        return false;
    }
    
    $imageType = $matches[1]; // jpeg, png, etc.
    $base64Image = preg_replace('/^data:image\/[a-zA-Z]*;base64,/', '', $base64Data);
    $imageData = base64_decode($base64Image);
    
    if ($imageData === false) {
        return false;
    }
    
    $uploadDir = 'uploads/' . $prefix . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    // Generate unique filename
    $filename = uniqid($prefix . '_camera_') . '.' . $imageType;
    $targetPath = $uploadDir . $filename;
    
    return file_put_contents($targetPath, $imageData) !== false;
}

/* ---------- 5.  Handle 1×1 picture and thumbmark ---------- */
$photoPath = null;

// Priority: Camera data first, then file upload
if (!empty($_POST['photo_camera_data'])) {
    // Handle camera captured image
    if (saveCameraImage($_POST['photo_camera_data'], 'photos', $photoPath)) {
        $data['photo'] = $photoPath;
    }
} elseif (uploadFile('photo', 'photos', $photoPath)) {
    // Handle regular file upload
    $data['photo'] = $photoPath;
}

$thumbPath = null;
// Priority: Camera data first (from fingerprint scanner), then file upload
if (!empty($_POST['thumbmark_camera_data'])) {
    // Handle fingerprint scanner captured image
    if (saveCameraImage($_POST['thumbmark_camera_data'], 'thumbs', $thumbPath)) {
        $data['thumbmark'] = $thumbPath;
    }
} elseif (uploadFile('thumbmark', 'thumbs', $thumbPath)) {
    // Handle regular file upload
    $data['thumbmark'] = $thumbPath;
}

/* ---------- 6.  Save data to patient_pir table ---------- */
$stmt = $pdo->prepare("SELECT patient_id FROM patient_pir WHERE patient_id = ?");
$stmt->execute([$patientId]);
$exists = $stmt->fetch();

$data['patient_id'] = $patientId;

if ($exists) {
    // UPDATE
    $setParts = [];
    foreach ($data as $key => $value) {
        if ($key !== 'patient_id') $setParts[] = "$key = :$key";
    }
    $sql = "UPDATE patient_pir SET " . implode(', ', $setParts) . " WHERE patient_id = :patient_id";
} else {
    // INSERT
    $columns = implode(', ', array_keys($data));
    $placeholders = ':' . implode(', :', array_keys($data));
    $sql = "INSERT INTO patient_pir ($columns) VALUES ($placeholders)";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($data);

/* ---------- 7. Clean up unused temporary photos ---------- */
if (isset($photoPath) && $photoPath) {
    cleanupTemporaryPhotos($patientId, $photoPath);
}

// Redirect to the next step
header("Location: edit_patient_step2.php?id=$patientId");
exit;

/* ---------- Helper function to clean up temporary photos ---------- */
function cleanupTemporaryPhotos($patientId, $finalPhotoPath) {
    $photoDir = 'uploads/photos/';
    
    if (!is_dir($photoDir)) {
        return;
    }
    
    // Get all photo files that might belong to this patient session
    $files = glob($photoDir . '*');
    $finalPhotoName = basename($finalPhotoPath);
    $cleanupPatterns = [
        'photos_camera_',     // Camera captures
        'removebg_',         // Remove.bg processed images
        'temp_capture_'      // Temporary captures
    ];
    
    $deletedCount = 0;
    $totalSize = 0;
    
    foreach ($files as $file) {
        if (!is_file($file)) continue;
        
        $fileName = basename($file);
        
        // Skip the final photo that we're keeping
        if ($fileName === $finalPhotoName) {
            continue;
        }
        
        // Check if this file matches temporary photo patterns and is old enough
        $shouldDelete = false;
        $fileTime = filemtime($file);
        $currentTime = time();
        
        // Delete files older than 1 hour from any of the cleanup patterns
        foreach ($cleanupPatterns as $pattern) {
            if (strpos($fileName, $pattern) === 0 && ($currentTime - $fileTime) > 3600) {
                $shouldDelete = true;
                break;
            }
        }
        
        // Also delete very recent temp files (within last 5 minutes) that match session patterns
        // This catches files from the current session that weren't used
        if (!$shouldDelete && ($currentTime - $fileTime) < 300) {
            foreach ($cleanupPatterns as $pattern) {
                if (strpos($fileName, $pattern) === 0 && $fileName !== $finalPhotoName) {
                    // Additional check: if this is a very recent file and we have a final photo,
                    // it's likely an unused retake
                    $shouldDelete = true;
                    break;
                }
            }
        }
        
        if ($shouldDelete) {
            $fileSize = filesize($file);
            if (unlink($file)) {
                $deletedCount++;
                $totalSize += $fileSize;
                error_log("Cleaned up temporary photo: $fileName (" . formatBytes($fileSize) . ")");
            }
        }
    }
    
    if ($deletedCount > 0) {
        error_log("Photo cleanup: Deleted $deletedCount temporary files, freed " . formatBytes($totalSize) . " of space");
    }
}

function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}
