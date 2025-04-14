<?php
// Simple API endpoint for getting current user
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/models/Auth.php';

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
    // Decode the token
    $payload = json_decode(base64_decode($token), true);
    
    // Check if token is expired
    if (!$payload || !isset($payload['exp']) || $payload['exp'] < time()) {
        http_response_code(401); // Unauthorized
        echo json_encode(['error' => 'Token expired or invalid']);
        exit;
    }
    
    // Get user from database using the ID from the token
    $db = get_db_connection();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$payload['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        http_response_code(404); // Not Found
        echo json_encode(['error' => 'User not found']);
        exit;
    }
    
    // Remove password from response
    unset($user['password']);
    
    echo json_encode(['user' => $user]);
    
} catch (Exception $e) {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'Invalid token']);
    exit;
} 