<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS'); // Only allow POST and OPTIONS
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/config/database.php'; // Include database connection helper
require_once __DIR__ . '/config/jwt_helper.php'; // Include JWT helper
// require_once __DIR__ . '/vendor/autoload.php'; // Example if using Composer for JWT
// use Firebase\JWT\JWT;

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Ensure it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Only POST method is allowed']);
    exit;
}

// Get input data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Basic validation (allow login via username or email)
if (!isset($data['loginIdentifier']) || !isset($data['password'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Missing required fields: loginIdentifier (username or email) and password']);
    exit;
}

$loginIdentifier = trim($data['loginIdentifier']);
$password = $data['password'];

if (empty($loginIdentifier) || empty($password)) {
    http_response_code(400);
    echo json_encode(['error' => 'Login identifier and password cannot be empty']);
    exit;
}

// Get database connection
$conn = get_db_connection();

if (!$conn) {
    http_response_code(500);
    error_log("Database connection failed in auth_login.php");
    echo json_encode(['error' => 'Database connection error']);
    exit;
}

try {
    // Find user by username or email
    error_log("[AuthLogin] Attempting to find user: " . $loginIdentifier);
    $stmt = $conn->prepare("SELECT id, username, email, password_hash, role FROM users WHERE username = :username OR email = :email LIMIT 1");
    $stmt->bindParam(':username', $loginIdentifier);
    $stmt->bindParam(':email', $loginIdentifier);
    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        error_log("[AuthLogin] User not found: " . $loginIdentifier);
        http_response_code(404); // Not Found (or 401 Unauthorized for security)
        echo json_encode(['error' => 'Invalid credentials']); // Keep error message generic
        exit;
    }
    
    error_log("[AuthLogin] User found: ID = " . $user['id'] . ", Username = " . $user['username']);
    // Be cautious logging hashes, but useful for debugging format/presence
    error_log("[AuthLogin] Stored password hash: " . ($user['password_hash'] ? 'Present (length: ' . strlen($user['password_hash']) . ')' : 'MISSING or NULL'));

    // Verify password
    $isPasswordCorrect = password_verify($password, $user['password_hash']);
    error_log("[AuthLogin] password_verify result: " . ($isPasswordCorrect ? 'true' : 'false'));

    if ($isPasswordCorrect) {
        // Password is correct
        error_log("[AuthLogin] Password verified successfully for user ID: " . $user['id']);
        
        // Fetch role (ensure it exists, default to 'user' if null/missing)
        $role = $user['role'] ?? 'user'; 
        
        error_log("[AuthLogin] Attempting JWT generation with role: " . $role);
        $token = null; 
        try {
            // Pass the role to generate_jwt_token
            $token = generate_jwt_token($user['id'], $user['username'], $role);
            $tokenType = gettype($token);
            $tokenLooksValid = (is_string($token) && substr_count($token, '.') === 2);
            error_log("[AuthLogin] generate_jwt_token completed. Type: " . $tokenType . ", Looks Valid: " . ($tokenLooksValid ? 'Yes' : 'No'));
        } catch (Exception $jwtError) {
            error_log("[AuthLogin] CRITICAL ERROR during generate_jwt_token: " . $jwtError->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error during authentication token generation.']);
            exit;
        }

        if (!is_string($token) || !$tokenLooksValid) {
             error_log("[AuthLogin] CRITICAL: Token generation did not produce a valid string token.");
             http_response_code(500);
             echo json_encode(['error' => 'Internal error generating session.']);
             exit;
        }
        
        http_response_code(200);
        $responsePayload = [
            'message' => 'Login successful',
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $role // Include the fetched role here
            ]
        ];
        error_log("[AuthLogin] Sending successful response payload with token and role: " . $role);
        echo json_encode($responsePayload);

    } else {
        // Invalid password
        error_log("[AuthLogin] Invalid password for user ID: " . $user['id']);
        http_response_code(401); 
        echo json_encode(['error' => 'Invalid credentials']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Database error during login: " . $e->getMessage());
    echo json_encode(['error' => 'An internal error occurred during login']);
} catch (Exception $e) {
    http_response_code(500);
    error_log("General error during login: " . $e->getMessage());
    echo json_encode(['error' => 'An unexpected error occurred during login']);
} finally {
    $conn = null;
}

?>