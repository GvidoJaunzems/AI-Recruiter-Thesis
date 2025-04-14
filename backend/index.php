<?php
/**
 * API Entry Point
 */

// Load startup configuration
require_once __DIR__ . '/startup.php';

// Get the request path
$request_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Check if request is coming from the web.config rewrite rule
if (strpos($request_path, '/api') === false) {
    // Reconstruct the API path from the original URL if it's not present
    $request_path = '/api' . $request_path;
}

$path_parts = explode('/', trim($request_path, '/'));
$api = $path_parts[0] ?? '';
$endpoint = $path_parts[1] ?? '';

// Check if this is an API request
if ($api !== 'api') {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Not found']);
    exit;
}

// Route to appropriate controller
switch ($endpoint) {
    case 'auth':
        require_once __DIR__ . '/app/controllers/AuthController.php';
        break;
        
    case 'jobs':
        require_once __DIR__ . '/app/controllers/JobController.php';
        break;
        
    case 'applications':
        require_once __DIR__ . '/app/controllers/ApplicationController.php';
        break;
        
    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Endpoint not found']);
        break;
}

/**
 * Simple API Status Endpoint
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

echo json_encode([
    'status' => 'online',
    'message' => 'Recruiter API is running',
    'version' => '1.0.0',
    'documentation' => 'For API documentation, contact the administrator',
    'endpoints' => [
        'auth_register.php' => 'User registration',
        'auth_login.php' => 'User login',
        'auth_me.php' => 'Get current user info',
        // Add other endpoints as needed
    ]
]); 