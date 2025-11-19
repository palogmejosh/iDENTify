<?php
require_once '../config.php';
requireAuth();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['temp_files'])) {
        throw new Exception('Invalid input data');
    }
    
    $tempFiles = $input['temp_files'] ?? [];
    $keepFile = $input['keep_file'] ?? null;
    
    $deletedCount = 0;
    $totalSize = 0;
    $errors = [];
    
    // Clean up each temporary file
    foreach ($tempFiles as $tempPath) {
        // Ensure the file path is within our uploads directory (security check)
        $realPath = realpath($tempPath);
        $uploadsDir = realpath('../uploads/');
        
        if (!$realPath || !$uploadsDir || strpos($realPath, $uploadsDir) !== 0) {
            $errors[] = "Invalid file path: $tempPath";
            continue;
        }
        
        // Skip if this is the file we want to keep
        if ($keepFile && $realPath === realpath($keepFile)) {
            continue;
        }
        
        // Check if file exists and is a file (not directory)
        if (!is_file($realPath)) {
            continue;
        }
        
        // Get file info before deletion
        $fileName = basename($realPath);
        $fileSize = filesize($realPath);
        
        // Only delete files that match our temporary patterns
        $tempPatterns = [
            'photos_camera_',
            'removebg_',
            'temp_capture_'
        ];
        
        $isTemporary = false;
        foreach ($tempPatterns as $pattern) {
            if (strpos($fileName, $pattern) === 0) {
                $isTemporary = true;
                break;
            }
        }
        
        if (!$isTemporary) {
            $errors[] = "Skipped non-temporary file: $fileName";
            continue;
        }
        
        // Delete the file
        if (unlink($realPath)) {
            $deletedCount++;
            $totalSize += $fileSize;
            error_log("Temporary photo cleanup: Deleted $fileName (" . formatBytes($fileSize) . ")");
        } else {
            $errors[] = "Failed to delete: $fileName";
        }
    }
    
    $freedSpace = formatBytes($totalSize);
    
    // Log cleanup summary
    if ($deletedCount > 0) {
        $user = getCurrentUser();
        error_log("Photo cleanup completed by user {$user['id']}: Deleted $deletedCount files, freed $freedSpace");
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'deleted_count' => $deletedCount,
        'freed_space' => $freedSpace,
        'total_bytes_freed' => $totalSize,
        'errors' => $errors,
        'message' => $deletedCount > 0 ? 
            "Successfully cleaned up $deletedCount temporary photos and freed $freedSpace" : 
            'No temporary photos to clean up'
    ]);
    
} catch (Exception $e) {
    error_log("Temporary photo cleanup error: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}
?>