<?php
// Simple test API endpoint for file uploads without authentication
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Debug logging
function debugLog($message, $data = null) {
    $logFile = __DIR__ . '/test_upload_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message";
    
    if ($data !== null) {
        $logMessage .= " - " . json_encode($data);
    }
    
    file_put_contents($logFile, $logMessage . PHP_EOL, FILE_APPEND);
}

// Log request information
debugLog("Received test upload request", [
    'method' => $_SERVER['REQUEST_METHOD'],
    'post_vars' => count($_POST),
    'files' => isset($_FILES) ? count($_FILES) : 0
]);

// Handle CORS preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Debug POST data
    debugLog("POST data", $_POST);
    debugLog("FILES data", isset($_FILES) ? $_FILES : 'No files');
    
    // Get test data
    $test_name = isset($_POST['test_name']) ? $_POST['test_name'] : 'Unknown';
    debugLog("Test data", ['name' => $test_name]);
    
    // Handle file upload
    $file_url = '';
    if (isset($_FILES['test_file']) && $_FILES['test_file']['error'] === UPLOAD_ERR_OK) {
        $tmp_name = $_FILES['test_file']['tmp_name'];
        $name = basename($_FILES['test_file']['name']);
        $upload_dir = __DIR__ . '/uploads/';
        
        debugLog("Processing test file upload", ['name' => $name, 'tmp_name' => $tmp_name]);
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
            debugLog("Created upload directory", ['path' => $upload_dir]);
        }
        
        // Generate unique filename
        $filename = 'test_' . uniqid() . '_' . $name;
        $upload_path = $upload_dir . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($tmp_name, $upload_path)) {
            $file_url = '/backend/uploads/' . $filename;
            debugLog("Test file uploaded successfully", ['path' => $file_url]);
        } else {
            $upload_error = error_get_last();
            debugLog("Failed to upload test file", $upload_error);
            throw new Exception('Failed to upload test file: ' . ($upload_error ? $upload_error['message'] : 'Unknown error'));
        }
    } else {
        if (isset($_FILES['test_file'])) {
            $upload_error = $_FILES['test_file']['error'];
            debugLog("Test file upload error", ['error_code' => $upload_error]);
        } else {
            debugLog("No test file provided");
        }
    }
    
    // Success response
    http_response_code(200);
    echo json_encode([
        'message' => 'Test upload successful',
        'test_name' => $test_name,
        'file_url' => $file_url
    ]);
    
    debugLog("Test upload completed successfully");
    
} catch (Exception $e) {
    debugLog("Exception in test upload", ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
} 