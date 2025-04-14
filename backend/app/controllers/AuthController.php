<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../models/Auth.php';

header('Content-Type: application/json');

// Handle CORS preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    exit(0);
}

// Get the request path
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);

// Clean up the path (remove /api/auth from the beginning and normalize slashes)
if (strpos($path, '/api/auth') === 0) {
    $path = substr($path, strlen('/api/auth'));
} else if (strpos($path, 'api/auth') === 0) {
    $path = substr($path, strlen('api/auth'));
}

// Ensure path starts with a slash
if (empty($path) || $path[0] !== '/') {
    $path = '/' . $path;
}

// Log the path for debugging
error_log("AuthController path: " . $path);

// Handle different endpoints
switch ($path) {
    case '/register':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        
        // Get POST data
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate required fields
        $required_fields = ['username', 'password', 'email', 'first_name', 'last_name'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                http_response_code(400);
                echo json_encode(['error' => "Field '$field' is required"]);
                exit;
            }
        }
        
        // Register user
        $result = register_user($data);
        
        if ($result === true) {
            // Get user data without password
            $user = login_user($data['username'], $data['password']);
            
            if (!$user) {
                http_response_code(500);
                echo json_encode(['error' => 'User created but login failed']);
                exit;
            }
            
            unset($user['password']);
            
            echo json_encode([
                'message' => 'Registration successful',
                'user' => $user
            ]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => $result]);
        }
        break;
        
    case '/login':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        
        // Get POST data
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate required fields
        if (empty($data['username']) || empty($data['password'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Username and password are required']);
            exit;
        }
        
        // Login user
        $user = login_user($data['username'], $data['password']);
        
        if ($user) {
            unset($user['password']);
            echo json_encode([
                'message' => 'Login successful',
                'user' => $user
            ]);
        } else {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid credentials']);
        }
        break;
        
    case '/me':
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        
        // Get current user
        $user = get_logged_in_user();
        
        if ($user) {
            unset($user['password']);
            echo json_encode(['user' => $user]);
        } else {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated']);
        }
        break;
        
    case '/logout':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        
        // Logout user
        logout_user();
        echo json_encode(['message' => 'Logged out successfully']);
        break;
        
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
        break;
} 