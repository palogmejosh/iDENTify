<?php
/**
 * Scheduled cleanup script for old temporary photos
 * 
 * This script should be run daily via cron job to clean up:
 * - Old temporary photos that weren't cleaned up properly
 * - Remove.bg processed images older than 24 hours
 * - Camera captures older than 24 hours that aren't linked to patients
 * 
 * Usage: php cleanup_old_photos.php
 * Cron: 0 2 * * * /usr/bin/php /path/to/cleanup_old_photos.php
 */

require_once '../config.php';

// Configuration
$CLEANUP_OLDER_THAN_HOURS = 24; // Delete temp files older than 24 hours
$EMERGENCY_CLEANUP_OLDER_THAN_HOURS = 72; // Delete ALL temp files older than 72 hours (emergency cleanup)
$DRY_RUN = false; // Set to true to see what would be deleted without actually deleting

// Directories to clean
$photoDir = '../uploads/photos/';
$signatureDir = '../uploads/signature/';

// Temporary file patterns to look for
$tempPatterns = [
    'photos_camera_',     // Camera captures
    'removebg_',         // Remove.bg processed images
    'temp_capture_',     // Other temporary captures
];

// Statistics tracking
$stats = [
    'total_scanned' => 0,
    'deleted_count' => 0,
    'freed_bytes' => 0,
    'errors' => [],
    'kept_files' => 0,
    'directories' => []
];

function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

function cleanupDirectory($dir, $patterns, $olderThanHours, $emergencyOlderThanHours, $dryRun = false) {
    global $stats, $pdo;
    
    if (!is_dir($dir)) {
        echo "Directory $dir does not exist.\n";
        return;
    }
    
    $stats['directories'][] = $dir;
    echo "Cleaning directory: $dir\n";
    
    $files = glob($dir . '*');
    $currentTime = time();
    
    foreach ($files as $file) {
        if (!is_file($file)) continue;
        
        $stats['total_scanned']++;
        $fileName = basename($file);
        $fileTime = filemtime($file);
        $fileAge = ($currentTime - $fileTime) / 3600; // Age in hours
        $fileSize = filesize($file);
        
        // Check if file matches any temporary pattern
        $isTemporary = false;
        foreach ($patterns as $pattern) {
            if (strpos($fileName, $pattern) === 0) {
                $isTemporary = true;
                break;
            }
        }
        
        if (!$isTemporary) {
            continue;
        }
        
        // Determine if we should delete this file
        $shouldDelete = false;
        $reason = '';
        
        if ($fileAge > $emergencyOlderThanHours) {
            $shouldDelete = true;
            $reason = "Emergency cleanup (older than {$emergencyOlderThanHours}h)";
        } elseif ($fileAge > $olderThanHours) {
            // Check if this file is referenced in the database
            $isReferenced = false;
            
            try {
                // Check if file is referenced in patient_pir table
                $relativePath = 'uploads/' . basename($dir) . '/' . $fileName;
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM patient_pir WHERE photo = ? OR thumbmark = ?");
                $stmt->execute([$relativePath, $relativePath]);
                $result = $stmt->fetch();
                
                if ($result && $result['count'] > 0) {
                    $isReferenced = true;
                }
            } catch (Exception $e) {
                $stats['errors'][] = "Database check failed for $fileName: " . $e->getMessage();
            }
            
            if (!$isReferenced) {
                $shouldDelete = true;
                $reason = "Not referenced in database (older than {$olderThanHours}h)";
            } else {
                $stats['kept_files']++;
                echo "  KEPT: $fileName (referenced in database)\n";
            }
        } else {
            // File is too recent to delete
            continue;
        }
        
        // Delete the file if we determined we should
        if ($shouldDelete) {
            if ($dryRun) {
                echo "  DRY RUN - Would delete: $fileName (" . formatBytes($fileSize) . ") - $reason\n";
                $stats['deleted_count']++;
                $stats['freed_bytes'] += $fileSize;
            } else {
                if (unlink($file)) {
                    echo "  DELETED: $fileName (" . formatBytes($fileSize) . ") - $reason\n";
                    $stats['deleted_count']++;
                    $stats['freed_bytes'] += $fileSize;
                } else {
                    $stats['errors'][] = "Failed to delete: $fileName";
                    echo "  ERROR: Failed to delete $fileName\n";
                }
            }
        }
    }
}

// Start cleanup process
echo "=== iDENTify Photo Cleanup Tool ===\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n";
echo "Mode: " . ($DRY_RUN ? "DRY RUN (no files will be deleted)" : "LIVE MODE") . "\n";
echo "Cleanup threshold: {$CLEANUP_OLDER_THAN_HOURS} hours\n";
echo "Emergency cleanup threshold: {$EMERGENCY_CLEANUP_OLDER_THAN_HOURS} hours\n\n";

// Clean photos directory
if (is_dir($photoDir)) {
    cleanupDirectory($photoDir, $tempPatterns, $CLEANUP_OLDER_THAN_HOURS, $EMERGENCY_CLEANUP_OLDER_THAN_HOURS, $DRY_RUN);
}

// Clean signature directory (if needed)
$signatureTempPatterns = ['temp_sig_'];
if (is_dir($signatureDir)) {
    cleanupDirectory($signatureDir, $signatureTempPatterns, $CLEANUP_OLDER_THAN_HOURS, $EMERGENCY_CLEANUP_OLDER_THAN_HOURS, $DRY_RUN);
}

// Display summary
echo "\n=== Cleanup Summary ===\n";
echo "Directories processed: " . count($stats['directories']) . "\n";
echo "Files scanned: {$stats['total_scanned']}\n";
echo "Files deleted: {$stats['deleted_count']}\n";
echo "Files kept: {$stats['kept_files']}\n";
echo "Space freed: " . formatBytes($stats['freed_bytes']) . "\n";
echo "Errors: " . count($stats['errors']) . "\n";

if (!empty($stats['errors'])) {
    echo "\nErrors encountered:\n";
    foreach ($stats['errors'] as $error) {
        echo "  - $error\n";
    }
}

// Log to system log
$logMessage = "iDENTify photo cleanup: " . 
              "Deleted {$stats['deleted_count']} files, " .
              "freed " . formatBytes($stats['freed_bytes']) . ", " .
              count($stats['errors']) . " errors";

error_log($logMessage);

echo "\nCompleted at: " . date('Y-m-d H:i:s') . "\n";

// Exit with appropriate code
exit(count($stats['errors']) > 0 ? 1 : 0);
?>