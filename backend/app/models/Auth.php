<?php
/**
 * Auth Model
 */

require_once __DIR__ . '/../../config/database.php';

/**
 * Register a new user
 * 
 * @param array $userData User data
 * @return bool|string True on success, error message on failure
 */
function register_user($userData) {
    $db = get_db_connection();
    if (!$db) {
        return "Database connection failed";
    }
    
    // Validate required fields
    $required_fields = ['username', 'password', 'email', 'first_name', 'last_name'];
    foreach ($required_fields as $field) {
        if (empty($userData[$field])) {
            return "Field '$field' is required";
        }
    }
    
    // Validate email format
    if (!filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
        return "Invalid email format";
    }
    
    try {
        // Check if username or email already exists
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$userData['username'], $userData['email']]);
        if ($stmt->fetch()) {
            return "Username or email already exists";
        }
        
        // Hash password
        $userData['password'] = password_hash($userData['password'], PASSWORD_DEFAULT);
        
        // Insert user
        $stmt = $db->prepare("
            INSERT INTO users (
                username, password, email, first_name, last_name, 
                role, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $stmt->execute([
            $userData['username'],
            $userData['password'],
            $userData['email'],
            $userData['first_name'],
            $userData['last_name'],
            $userData['role'] ?? 'user'
        ]);
        
        return true;
    } catch (PDOException $e) {
        return "Failed to register user: " . $e->getMessage();
    }
}

/**
 * Login user
 * 
 * @param string $username Username
 * @param string $password Password
 * @return array|false User data or false on failure
 */
function login_user($username, $password) {
    $db = get_db_connection();
    if (!$db) {
        return false;
    }
    
    try {
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            unset($user['password']); // Remove password from returned data
            return $user;
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("Login failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Get current user
 * 
 * @return array|false User data or false if not logged in
 */
function get_logged_in_user() {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    $db = get_db_connection();
    if (!$db) {
        return false;
    }
    
    try {
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user) {
            unset($user['password']); // Remove password from returned data
            return $user;
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("Failed to get current user: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if user is admin
 * 
 * @return bool True if user is admin
 */
function is_admin() {
    $user = get_logged_in_user();
    return $user && $user['role'] === 'admin';
}

/**
 * Logout user
 * 
 * @return bool True on success
 */
function logout_user() {
    session_destroy();
    return true;
} 