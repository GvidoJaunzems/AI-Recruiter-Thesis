<?php
/**
 * Application Controller
 */

require_once __DIR__ . '/../models/Application.php';
require_once __DIR__ . '/../models/Auth.php';

// Set content type to JSON
header('Content-Type: application/json');

// Handle CORS preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    exit(0);
}

// Get the request path
$request_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path_parts = explode('/', trim($request_path, '/'));
$endpoint = $path_parts[1] ?? ''; // 'api/applications' -> 'applications'
$id = $path_parts[2] ?? null;

// Handle different endpoints
switch ($endpoint) {
    case 'applications':
        switch ($_SERVER['REQUEST_METHOD']) {
            case 'GET':
                // Check if user is logged in
                if (!is_logged_in()) {
                    http_response_code(401);
                    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                    break;
                }
                
                if ($id) {
                    // Get single application
                    $application = get_application_by_id($id);
                    
                    // Check if user has permission to view this application
                    $currentUser = get_current_user();
                    if (!$application || 
                        ($currentUser['role'] !== 'admin' && 
                         $application['user_id'] !== $currentUser['id'])) {
                        http_response_code(403);
                        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                        break;
                    }
                    
                    echo json_encode(['success' => true, 'data' => $application]);
                } else {
                    // Get all applications (admin only) or user's applications
                    if (is_admin()) {
                        $applications = get_all_applications();
                    } else {
                        $userId = get_current_user()['id'];
                        $applications = get_applications_by_user($userId);
                    }
                    
                    echo json_encode(['success' => true, 'data' => $applications]);
                }
                break;
                
            case 'POST':
                // Check if user is logged in
                if (!is_logged_in()) {
                    http_response_code(401);
                    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                    break;
                }
                
                // Create new application
                $data = json_decode(file_get_contents('php://input'), true);
                
                // Add user_id from current user
                $data['user_id'] = get_current_user()['id'];
                
                $result = create_application($data);
                if ($result === true) {
                    http_response_code(201);
                    echo json_encode(['success' => true, 'message' => 'Application submitted successfully']);
                } else {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => $result]);
                }
                break;
                
            case 'PUT':
                if (!$id) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Application ID is required']);
                    break;
                }
                
                // Check if user is logged in and is admin
                if (!is_logged_in() || !is_admin()) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                    break;
                }
                
                // Update application status
                $data = json_decode(file_get_contents('php://input'), true);
                if (empty($data['status'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Status is required']);
                    break;
                }
                
                $result = update_application_status($id, $data['status']);
                if ($result === true) {
                    echo json_encode(['success' => true, 'message' => 'Application status updated successfully']);
                } else {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => $result]);
                }
                break;
                
            case 'DELETE':
                if (!$id) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Application ID is required']);
                    break;
                }
                
                // Check if user is logged in and is admin
                if (!is_logged_in() || !is_admin()) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                    break;
                }
                
                // Delete application
                $result = delete_application($id);
                if ($result === true) {
                    echo json_encode(['success' => true, 'message' => 'Application deleted successfully']);
                } else {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => $result]);
                }
                break;
                
            default:
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
                break;
        }
        break;
        
    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Endpoint not found']);
        break;
} 