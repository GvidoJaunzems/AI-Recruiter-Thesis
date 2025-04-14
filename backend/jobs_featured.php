<?php
// Simple API endpoint for getting featured jobs
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

try {
    // Get database connection
    $db = get_db_connection();
    if (!$db) {
        throw new Exception("Database connection failed");
    }
    
    // Get a limit of 3 jobs ordered by most recent
    $limit = 3;
    
    $stmt = $db->prepare("
        SELECT j.*, u.username as posted_by_name 
        FROM jobs j 
        JOIN users u ON j.posted_by = u.id 
        ORDER BY j.created_at DESC
        LIMIT ?
    ");
    
    $stmt->bindParam(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['jobs' => $jobs]);
    
} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => $e->getMessage()]);
    exit;
} 