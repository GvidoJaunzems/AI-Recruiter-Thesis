<?php
/**
 * Job Controller
 */

require_once __DIR__ . '/../models/Job.php';
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
$endpoint = $path_parts[1] ?? ''; // 'api/jobs' -> 'jobs'
$id = $path_parts[2] ?? null;

// Handle different endpoints
switch ($endpoint) {
    case 'jobs':
        switch ($_SERVER['REQUEST_METHOD']) {
            case 'GET':
                if ($id) {
                    // Get single job
                    $job = get_job_by_id($id);
                    if ($job) {
                        echo json_encode(['success' => true, 'data' => $job]);
                    } else {
                        http_response_code(404);
                        echo json_encode(['success' => false, 'message' => 'Job not found']);
                    }
                } else {
                    // Get all jobs
                    $jobs = get_all_jobs();
                    echo json_encode(['success' => true, 'data' => $jobs]);
                }
                break;
                
            case 'POST':
                // Create new job
                $data = json_decode(file_get_contents('php://input'), true);
                
                // Check if user is logged in and is admin
                if (!is_logged_in() || !is_admin()) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                    break;
                }
                
                // Add posted_by from current user
                $data['posted_by'] = get_current_user()['id'];
                
                $result = create_job($data);
                if ($result === true) {
                    http_response_code(201);
                    echo json_encode(['success' => true, 'message' => 'Job created successfully']);
                } else {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => $result]);
                }
                break;
                
            case 'PUT':
                if (!$id) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Job ID is required']);
                    break;
                }
                
                // Check if user is logged in and is admin
                if (!is_logged_in() || !is_admin()) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                    break;
                }
                
                // Update job
                $data = json_decode(file_get_contents('php://input'), true);
                $result = update_job($id, $data);
                
                if ($result === true) {
                    echo json_encode(['success' => true, 'message' => 'Job updated successfully']);
                } else {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => $result]);
                }
                break;
                
            case 'DELETE':
                if (!$id) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Job ID is required']);
                    break;
                }
                
                // Check if user is logged in and is admin
                if (!is_logged_in() || !is_admin()) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                    break;
                }
                
                // Delete job
                $result = delete_job($id);
                
                if ($result === true) {
                    echo json_encode(['success' => true, 'message' => 'Job deleted successfully']);
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
        
    case 'my-jobs':
        // Get jobs posted by current user
        if (!is_logged_in()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            break;
        }
        
        $userId = get_current_user()['id'];
        $jobs = get_jobs_by_user($userId);
        echo json_encode(['success' => true, 'data' => $jobs]);
        break;
        
    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Endpoint not found']);
        break;
} 