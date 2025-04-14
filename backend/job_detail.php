<?php
// Simple API endpoint for getting job details
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/models/Job.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle CORS preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get job ID from query string
$job_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($job_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid job ID']);
    exit;
}

try {
    // Get database connection
    $db = get_db_connection();
    if (!$db) {
        throw new Exception("Database connection failed");
    }
    
    // Get job details
    $stmt = $db->prepare("
        SELECT j.*, u.username as posted_by_name 
        FROM jobs j 
        JOIN users u ON j.posted_by = u.id 
        WHERE j.id = ?
    ");
    
    $stmt->execute([$job_id]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$job) {
        http_response_code(404);
        echo json_encode(['error' => 'Job not found']);
        exit;
    }
    
    echo json_encode($job);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
} 