<?php

// --- Original Code Restored ---

require_once __DIR__ . '/config/database.php'; // Include database connection helper
require_once __DIR__ . '/config/jwt_helper.php'; // Include JWT helper

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

// Basic validation
if (!isset($data['username']) || !isset($data['email']) || !isset($data['password']) || !isset($data['first_name']) || !isset($data['last_name'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Missing required fields: username, email, password, first_name, last_name']);
    exit;
}
if (empty(trim($data['username'])) || empty(trim($data['email'])) || empty(trim($data['password'])) || empty(trim($data['first_name'])) || empty(trim($data['last_name']))) {
     http_response_code(400); // Bad Request
     echo json_encode(['error' => 'Username, email, password, first_name, and last_name cannot be empty']);
     exit;
}
if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Invalid email format']);
    exit;
}


$username = trim($data['username']);
$email = trim($data['email']);
$password = $data['password']; // Keep original for hashing
$firstName = trim($data['first_name']);
$lastName = trim($data['last_name']);
$defaultRole = 'user'; // Explicitly set default role for new users

// Get database connection
$conn = get_db_connection();

if (!$conn) {
    http_response_code(500);
    error_log("Database connection failed in auth_register.php");
    echo json_encode(['error' => 'Database connection error']);
    exit;
}

try {
    // Check if username or email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1");
    $stmt->bindParam(':username', $username);
    $stmt->bindParam(':email', $email);
    $stmt->execute();

    if ($stmt->fetch()) {
        http_response_code(409); // Conflict
        echo json_encode(['error' => 'Username or email already exists']);
        exit;
    }

    // Hash the password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    if ($hashedPassword === false) {
        // Handle password hashing failure
        http_response_code(500);
        error_log("Password hashing failed for user: " . $username);
        echo json_encode(['error' => 'Failed to process registration data']);
        exit;
    }


    // Insert new user
    $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, role) VALUES (:username, :email, :password_hash, :first_name, :last_name, :role)");
    $stmt->bindParam(':username', $username);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':password_hash', $hashedPassword);
    $stmt->bindParam(':first_name', $firstName);
    $stmt->bindParam(':last_name', $lastName);
    $stmt->bindParam(':role', $defaultRole);

    if ($stmt->execute()) {
        // Registration successful
        $newUserId = $conn->lastInsertId();
        error_log("[AuthRegister] User registered successfully. ID: $newUserId, Username: $username");
        
        // Generate JWT token
        $token = null;
        try {
            $token = generate_jwt_token($newUserId, $username, $defaultRole); 
            error_log("[AuthRegister] JWT generated for new user ID: $newUserId");
        } catch (Exception $jwtError) {
            // Log error but proceed without token if generation fails
            error_log("[AuthRegister] CRITICAL ERROR during JWT generation for new user ID $newUserId: " . $jwtError->getMessage());
        }

        // Prepare response payload
        $responsePayload = [
            'message' => 'User registered successfully',
            'user' => [
                'id' => (int)$newUserId, // Cast ID to int
                'username' => $username,
                'email' => $email,
                'role' => $defaultRole
            ]
        ];
        // Include token if generated successfully
        if ($token) {
            $responsePayload['token'] = $token;
        }

        http_response_code(201); // Created
        echo json_encode($responsePayload);
    } else {
        // Handle potential insert failure
        http_response_code(500);
        error_log("Failed to insert user: " . implode(", ", $stmt->errorInfo()));
        echo json_encode(['error' => 'Registration failed']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Database error during registration: " . $e->getMessage());
    echo json_encode(['error' => 'An internal error occurred']);
} catch (Exception $e) {
    http_response_code(500);
    error_log("General error during registration: " . $e->getMessage());
    echo json_encode(['error' => 'An unexpected error occurred']);
} finally {
    // Close connection explicitly if needed, though PDO usually handles this
    $conn = null;
}

?>