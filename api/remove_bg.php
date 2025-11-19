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

// Remove.bg API configuration
$API_KEY = 'mZXrcNG2qNdcLd4ECz75kjg7';
$API_URL = 'https://api.remove.bg/v1.0/removebg';

try {
    // Check if image file is provided
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No valid image file provided');
    }

    $imageFile = $_FILES['image'];
    
    // Validate image type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $imageFile['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        throw new Exception('Invalid image format. Please use JPG or PNG.');
    }
    
    // Validate image size (max 12MB as per remove.bg limit)
    if ($imageFile['size'] > 12 * 1024 * 1024) {
        throw new Exception('Image too large. Maximum size is 12MB.');
    }
    
    // Prepare the request to remove.bg API
    $postFields = [
        'image_file' => new CURLFile($imageFile['tmp_name'], $mimeType, 'image'),
        'size' => 'auto',
        'bg_color' => 'ffffff' // White background
    ];
    
    // Initialize cURL
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $API_URL,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postFields,
        CURLOPT_HTTPHEADER => [
            'X-Api-Key: ' . $API_KEY,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 60, // 60 seconds timeout
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    
    // Execute the request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    // Check for cURL errors
    if ($error) {
        throw new Exception('Network error: ' . $error);
    }
    
    // Check API response
    if ($httpCode !== 200) {
        $errorData = json_decode($response, true);
        $errorMessage = isset($errorData['errors'][0]['title']) 
            ? $errorData['errors'][0]['title'] 
            : 'API request failed with code ' . $httpCode;
        throw new Exception($errorMessage);
    }
    
    // Save processed image to uploads directory
    $uploadDir = '../uploads/photos/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $filename = 'removebg_' . uniqid() . '_' . time() . '.png';
    $filepath = $uploadDir . $filename;
    
    if (file_put_contents($filepath, $response) === false) {
        throw new Exception('Failed to save processed image');
    }
    
    // Log successful processing
    error_log("Remove.bg API: Successfully processed image for user " . getCurrentUser()['id']);
    
    // Return success response
    echo json_encode([
        'success' => true,
        'image_path' => 'uploads/photos/' . $filename,
        'message' => 'Background removed successfully',
        'api_calls_remaining' => null // Remove.bg doesn't always return this info
    ]);
    
} catch (Exception $e) {
    // Log error
    error_log("Remove.bg API Error: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>