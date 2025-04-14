<?php
// API endpoint for submitting job applications - Version 2 (Enhanced)
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/models/Auth.php';
require_once __DIR__ . '/app/models/Application.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

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

// Get the token from the Authorization header
$headers = getallheaders();
$auth_header = isset($headers['Authorization']) ? $headers['Authorization'] : '';

if (!$auth_header || !preg_match('/^Bearer\s+(.*)$/', $auth_header, $matches)) {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'No valid token provided']);
    exit;
}

$token = $matches[1];

try {
    // Decode token and verify
    $payload = json_decode(base64_decode($token), true);
    
    // Check token validity
    if (!$payload || !isset($payload['user_id']) || !isset($payload['exp']) || $payload['exp'] < time()) {
        http_response_code(401); // Unauthorized
        echo json_encode(['error' => 'Token expired or invalid']);
        exit;
    }
    
    // Get job ID from query string
    $job_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($job_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid job ID']);
        exit;
    }
    
    // Get user ID from token
    $user_id = $payload['user_id'];
    
    // Get database connection
    $db = get_db_connection();
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Check if job exists
    $stmt = $db->prepare("SELECT id FROM jobs WHERE id = ?");
    $stmt->execute([$job_id]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Job not found']);
        exit;
    }
    
    // Check if user has already applied
    $stmt = $db->prepare("SELECT id FROM applications WHERE job_id = ? AND user_id = ?");
    $stmt->execute([$job_id, $user_id]);
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['error' => 'You have already applied for this job']);
        exit;
    }
    
    // Get all application data from the request
    $applicationData = json_decode(file_get_contents('php://input'), true);
    
    // Handle resume upload if files are sent
    $resume_url = '';
    if (isset($_FILES['resume'])) {
        $handle_file_upload = function($file) {
            // Upload file if provided
            if ($file['error'] === UPLOAD_ERR_OK) {
                $tmp_name = $file['tmp_name'];
                $name = basename($file['name']);
                $upload_dir = __DIR__ . '/uploads/';
                
                // Create directory if it doesn't exist
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Generate unique filename
                $filename = uniqid() . '_' . $name;
                $upload_path = $upload_dir . $filename;
                
                // Move uploaded file
                if (move_uploaded_file($tmp_name, $upload_path)) {
                    return '/backend/uploads/' . $filename;
                } else {
                    throw new Exception('Failed to upload file');
                }
            }
            return null;
        };
        
        $resume_url = $handle_file_upload($_FILES['resume']);
        
        if (empty($resume_url)) {
            http_response_code(400);
            echo json_encode(['error' => 'Resume upload failed']);
            exit;
        }
    } else if (isset($applicationData['resume_url'])) {
        // If the resume is provided as a URL (from a previous upload or external source)
        $resume_url = $applicationData['resume_url'];
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Resume is required']);
        exit;
    }
    
    // Prepare fields for database insertion
    $fields = [
        'job_id' => $job_id,
        'user_id' => $user_id,
        'resume_url' => $resume_url
    ];
    
    // Map form fields to database fields
    $fieldMappings = [
        'cover_letter' => 'cover_letter',
        'current_employer' => 'current_employer',
        'current_job_title' => 'current_job_title',
        'years_of_experience' => 'years_of_experience',
        'work_experience' => 'work_experience',
        'highest_education' => 'highest_education',
        'education_details' => 'education_details',
        'skills' => 'skills',
        'certifications' => 'certifications',
        'languages' => 'languages',
        'referral_source' => 'referral_source',
        'willing_to_relocate' => 'willing_to_relocate',
        'available_start_date' => 'available_start_date',
        'salary_expectations' => 'salary_expectations',
        'legally_authorized_to_work' => 'legally_authorized_to_work',
        'require_sponsorship' => 'require_sponsorship',
        'gender' => 'gender',
        'ethnicity' => 'ethnicity',
        'veteran_status' => 'veteran_status',
        'disability_status' => 'disability_status'
    ];
    
    // Add all available fields from the application data
    foreach ($fieldMappings as $formField => $dbField) {
        if (isset($applicationData[$formField])) {
            $fields[$dbField] = $applicationData[$formField];
        }
    }
    
    // Build SQL query dynamically based on available fields
    $columns = implode(', ', array_keys($fields));
    $placeholders = implode(', ', array_fill(0, count($fields), '?'));
    
    $sql = "INSERT INTO applications ($columns, status, created_at, updated_at) 
            VALUES ($placeholders, 'pending', NOW(), NOW())";
    
    $stmt = $db->prepare($sql);
    $result = $stmt->execute(array_values($fields));
    
    if ($result) {
        $application_id = $db->lastInsertId();
        
        http_response_code(201); // Created
        echo json_encode([
            'message' => 'Application submitted successfully',
            'application_id' => $application_id
        ]);
    } else {
        throw new Exception('Failed to submit application');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
} 