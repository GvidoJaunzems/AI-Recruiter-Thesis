<?php
// API endpoint for handling job applications - SIMPLIFIED WITH NO AUTH VALIDATION
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Debug logging
function debugLog($message, $data = null) {
    $logFile = __DIR__ . '/apply_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message";
    
    if ($data !== null) {
        $logMessage .= " - " . json_encode($data);
    }
    
    file_put_contents($logFile, $logMessage . PHP_EOL, FILE_APPEND);
}

// Log request information
debugLog("Received request", [
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
    // SIMPLIFIED: Use a fixed test user ID and skip token validation entirely
    $user_id = 1; // Default test user
    debugLog("Using test user ID: 1");
    
    // Debug POST data
    debugLog("POST data", $_POST);
    debugLog("FILES data", isset($_FILES) ? $_FILES : 'No files');
    
    // Get job ID from form data
    $job_id = isset($_POST['job_id']) ? $_POST['job_id'] : null;
    $job_title = isset($_POST['job_title']) ? $_POST['job_title'] : 'Unknown';
    $company = isset($_POST['company']) ? $_POST['company'] : 'Unknown';
    
    debugLog("Job data", ['id' => $job_id, 'title' => $job_title, 'company' => $company]);
    
    // Validate job ID - must be non-empty
    if (empty($job_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid job ID']);
        debugLog("Error: Invalid job ID", ['job_id' => $job_id]);
        exit;
    }
    
    // Format job_id consistently
    $job_id = trim((string)$job_id);
    
    // Get cover letter from form data
    $cover_letter = isset($_POST['cover_letter']) ? $_POST['cover_letter'] : '';
    
    // Handle resume upload
    $resume_url = '';
    if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
        $tmp_name = $_FILES['resume']['tmp_name'];
        $name = basename($_FILES['resume']['name']);
        $upload_dir = __DIR__ . '/uploads/';
        
        debugLog("Processing file upload", ['name' => $name, 'tmp_name' => $tmp_name]);
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
            debugLog("Created upload directory", ['path' => $upload_dir]);
        }
        
        // Generate unique filename
        $filename = uniqid() . '_' . $name;
        $upload_path = $upload_dir . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($tmp_name, $upload_path)) {
            $resume_url = '/backend/uploads/' . $filename;
            debugLog("File uploaded successfully", ['path' => $resume_url]);
        } else {
            $upload_error = error_get_last();
            debugLog("Failed to upload file", $upload_error);
            throw new Exception('Failed to upload resume: ' . ($upload_error ? $upload_error['message'] : 'Unknown error'));
        }
    } else {
        $upload_error = isset($_FILES['resume']) ? $_FILES['resume']['error'] : 'No resume file';
        debugLog("Resume upload error", ['error_code' => $upload_error]);
    }
    
    // Mock the application submission (since we don't have a real database)
    $application_id = rand(1000, 9999);
    $application = [
        'id' => $application_id,
        'job_id' => $job_id,
        'user_id' => $user_id,
        'job_title' => $job_title,
        'company' => $company,
        'resume_url' => $resume_url,
        'cover_letter' => $cover_letter,
        'status' => 'pending',
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    debugLog("Created application", $application);
    
    // In a real application, we would save to database
    // For our mock application, let's update the applications.json file
    $applications_file = __DIR__ . '/applications.json';
    $applications = [];
    
    if (file_exists($applications_file)) {
        $applications_data = file_get_contents($applications_file);
        $applications = json_decode($applications_data, true);
        
        if (!isset($applications['applications'])) {
            $applications = ['applications' => []];
        }
    } else {
        $applications = ['applications' => []];
    }
    
    // Add new application
    $applications['applications'][] = $application;
    
    // Save back to file
    file_put_contents($applications_file, json_encode($applications, JSON_PRETTY_PRINT));
    debugLog("Updated applications.json");
    
    http_response_code(201); // Created
    echo json_encode([
        'message' => 'Application submitted successfully',
        'application_id' => $application_id
    ]);
    
    debugLog("Application submitted successfully", ['id' => $application_id]);
    
} catch (Exception $e) {
    debugLog("Exception", ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
} 