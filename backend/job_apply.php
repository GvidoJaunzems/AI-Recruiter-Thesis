<?php
// Simple API endpoint for submitting job applications
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
    
    // Get form data
    $cover_letter = isset($_POST['coverLetter']) ? $_POST['coverLetter'] : '';
    
    if (empty($cover_letter)) {
        http_response_code(400);
        echo json_encode(['error' => 'Cover letter is required']);
        exit;
    }
    
    // Handle resume upload
    $resume_url = '';
    
    if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
        $tmp_name = $_FILES['resume']['tmp_name'];
        $name = basename($_FILES['resume']['name']);
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
            $resume_url = '/backend/uploads/' . $filename;
        } else {
            throw new Exception('Failed to upload resume');
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Resume is required']);
        exit;
    }
    
    // Insert application
    $stmt = $db->prepare("
        INSERT INTO applications (job_id, user_id, resume_url, cover_letter, status, created_at, updated_at)
        VALUES (?, ?, ?, ?, 'pending', NOW(), NOW())
    ");
    
    $stmt->execute([
        $job_id,
        $user_id,
        $resume_url,
        $cover_letter
    ]);
    
    http_response_code(201); // Created
    echo json_encode([
        'message' => 'Application submitted successfully',
        'application_id' => $db->lastInsertId()
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
} 