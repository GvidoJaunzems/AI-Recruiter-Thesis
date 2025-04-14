<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS'); // Only allow GET and OPTIONS
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/config/database.php'; // Include database connection helper
require_once __DIR__ . '/config/jwt_helper.php'; // Include JWT helper

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Ensure it's a GET request
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Only GET method is allowed']);
    exit;
}

// Get the token from the Authorization header
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? null;

if (!$authHeader) {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'Authorization header missing']);
    exit;
}

// Validate the token
$decodedPayload = validate_jwt_token($authHeader);

if (!$decodedPayload) {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'Invalid or expired token']);
    exit;
}

// Get user ID from token
$userId = get_user_id_from_token($decodedPayload);

if (!$userId) {
    http_response_code(401); // Unauthorized
    error_log("User ID not found in valid token payload.");
    echo json_encode(['error' => 'Invalid token data']);
    exit;
}

// Get database connection
$conn = get_db_connection();

if (!$conn) {
    http_response_code(500);
    error_log("Database connection failed in user_profile.php");
    echo json_encode(['error' => 'Database connection error']);
    exit;
}

try {
    // Fetch user profile data (excluding password hash)
    $sql = "SELECT u.id, u.username, u.email, u.role, up.first_name, up.last_name, up.phone, up.address 
            FROM red_users u 
            LEFT JOIN red_user_profiles up ON u.id = up.user_id 
            WHERE u.id = :user_id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        http_response_code(200);
        echo json_encode($user); // Return user profile data
    } else {
        // This case should be rare if the token was valid and user ID exists
        http_response_code(404); // Not Found
        error_log("User with ID {$userId} found in token but not in database.");
        echo json_encode(['error' => 'User not found']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Database error fetching profile for user ID {$userId}: " . $e->getMessage());
    echo json_encode(['error' => 'An internal error occurred while fetching profile']);
} catch (Exception $e) {
    http_response_code(500);
    error_log("General error fetching profile for user ID {$userId}: " . $e->getMessage());
    echo json_encode(['error' => 'An unexpected error occurred while fetching profile']);
} finally {
    $conn = null;
}

?>