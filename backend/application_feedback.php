<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: GET, OPTIONS'); 
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/jwt_helper.php';

// --- Authentication Helper (Copied from applications.php - consider centralizing) ---
function authenticate_and_get_user() {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
    if (!$authHeader) {
        http_response_code(401);
        echo json_encode(['error' => 'Authorization header missing']);
        exit;
    }
    $decodedPayload = validate_jwt_token($authHeader);
    if (!$decodedPayload) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid or expired token']);
        exit;
    }
    // Return the whole payload which should include user_id and role
    return $decodedPayload; 
}
// ---------------------------

// --- Logging Helper (Copied from applications.php - consider centralizing) ---
if (!function_exists('log_app_event')) {
    function log_app_event($appId, $message) {
        $safeAppId = is_numeric($appId) ? (int)$appId : 'general';
        error_log("[App Feedback - AppID {$safeAppId}] " . $message);
    }
}
// --------------------

// --- Main Request Handling ---
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
     http_response_code(405); // Method Not Allowed
     echo json_encode(['error' => 'Method not allowed. Only GET is supported.']);
     exit;
}

// --- Authentication --- 
$tokenPayload = authenticate_and_get_user();
$userId = $tokenPayload->data->userId ?? null;

if (!$userId) {
    http_response_code(401);
    log_app_event('N/A', "User ID not found in valid token payload.");
    echo json_encode(['error' => 'Invalid token data - user ID missing']);
    exit;
}

// --- Get Application ID from Query String --- 
$applicationId = isset($_GET['id']) ? (int)$_GET['id'] : null;
if (!$applicationId) {
    http_response_code(400);
    log_app_event('N/A', "Application ID missing from query string.");
    echo json_encode(['error' => 'Application ID is required.']);
    exit;
}

log_app_event($applicationId, "User {$userId} requesting feedback.");

// --- Database Interaction --- 
$conn = get_db_connection();
if (!$conn) {
    http_response_code(500);
    log_app_event($applicationId, "Database connection failed.");
    echo json_encode(['error' => 'Database connection error']);
    exit;
}

try {
    // Fetch feedback only if status is rejected and user owns the application
    $sql = "SELECT 
                a.ai_candidate_explanation, 
                a.ai_counterfactuals
            FROM applications a
            WHERE a.id = :application_id 
              AND a.user_id = :user_id
              AND a.status = 'rejected' -- Only allow feedback for rejected applications
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':application_id', $applicationId, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();

    $feedback = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($feedback) {
        log_app_event($applicationId, "Feedback found and user authorized.");
        // Decode counterfactuals JSON
        $feedback['ai_counterfactuals'] = json_decode($feedback['ai_counterfactuals'] ?? '[]', true) ?: [];
        
        // Ensure explanation is not null (provide default if needed)
        if ($feedback['ai_candidate_explanation'] === null) {
             $feedback['ai_candidate_explanation'] = "Feedback is not yet available for this application.";
        }

        echo json_encode($feedback);

    } else {
        // Check if the application exists but status is not rejected or doesn't belong to user
        $stmtCheck = $conn->prepare("SELECT id, status, user_id FROM applications WHERE id = :id LIMIT 1");
        $stmtCheck->bindParam(':id', $applicationId, PDO::PARAM_INT);
        $stmtCheck->execute();
        $appExists = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($appExists) {
            if ($appExists['user_id'] != $userId) {
                http_response_code(403); // Forbidden
                log_app_event($applicationId, "Access denied: User {$userId} does not own application.");
                echo json_encode(['error' => 'Access Denied: You do not own this application.']);
            } elseif ($appExists['status'] !== 'rejected') {
                http_response_code(404); // Not Found (or Forbidden depending on policy)
                log_app_event($applicationId, "Feedback not available: Application status is '{$appExists['status']}'.");
                echo json_encode(['error' => 'Feedback is only available for applications that were not selected.']);
            } else {
                // Should not happen if main query failed but check found it
                 http_response_code(404); 
                 log_app_event($applicationId, "Feedback not found, although application exists and belongs to user with rejected status.");
                 echo json_encode(['error' => 'Feedback not found for this application.']);
            }
        } else {
            http_response_code(404); // Not Found
            log_app_event($applicationId, "Application not found.");
            echo json_encode(['error' => 'Application not found.']);
        }
    }

} catch (PDOException $e) {
    http_response_code(500);
    log_app_event($applicationId, "Database error: " . $e->getMessage());
    echo json_encode(['error' => 'An internal database error occurred']);
} catch (Exception $e) {
    http_response_code(500);
    log_app_event($applicationId, "General error: " . $e->getMessage());
    echo json_encode(['error' => 'An unexpected error occurred']);
} finally {
    $conn = null;
}

?> 