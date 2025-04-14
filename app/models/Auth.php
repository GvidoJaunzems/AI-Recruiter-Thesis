<?php
/**
 * Authentication Model
 */

/**
 * Register a new user
 * 
 * @param array $userData User data (username, password, email, first_name, last_name)
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
    
    // Validate email
    if (!filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
        return "Invalid email format";
    }
    
    try {
        // Check if username or email already exists
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$userData['username'], $userData['email']]);
        
        if ($stmt->rowCount() > 0) {
            return "Username or email already exists";
        }
        
        // Hash password
        $hashed_password = password_hash($userData['password'], PASSWORD_DEFAULT);
        
        // Insert new user
        $stmt = $db->prepare("
            INSERT INTO users (username, password, email, first_name, last_name, role) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $role = isset($userData['role']) ? $userData['role'] : 'user';
        $stmt->execute([
            $userData['username'],
            $hashed_password,
            $userData['email'],
            $userData['first_name'],
            $userData['last_name'],
            $role
        ]);
        
        return true;
    } catch (PDOException $e) {
        return "Registration failed: " . $e->getMessage();
    }
}

/**
 * Authenticate a user
 * 
 * @param string $username Username
 * @param string $password Password
 * @return array|bool User data on success, false on failure
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
            // Don't store password in session
            unset($user['password']);
            
            // Set user session
            $_SESSION['user'] = $user;
            return $user;
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("Login failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Log out the current user
 * 
 * @return void
 */
function logout_user() {
    // Unset user session
    unset($_SESSION['user']);
    
    // Regenerate session ID for security
    session_regenerate_id(true);
}

/**
 * Check if a user is logged in
 * 
 * @return bool
 */
function is_logged_in() {
    return isset($_SESSION['user']);
}

/**
 * Check if logged in user is admin
 * 
 * @return bool
 */
function is_admin() {
    return isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin';
}

/**
 * Get the current logged in user
 * 
 * @return array|null User data or null if not logged in
 */
function get_logged_in_user() {
    if (isset($_SESSION['user_id'])) {
        $db = get_db_connection();
        if (!$db) {
            return false;
        }
        
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    return false;
} 