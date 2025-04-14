<?php
require_once 'config/database.php';
require_once 'app/models/Auth.php';

// Test database connection
$db = get_db_connection();
if (!$db) {
    die("Database connection failed");
}

// Create admin user
$adminData = [
    'username' => 'admin',
    'password' => 'admin123',
    'email' => 'admin@example.com',
    'first_name' => 'Admin',
    'last_name' => 'User',
    'role' => 'admin'
];

$result = register_user($adminData);
if ($result === true) {
    echo "Admin user created successfully!\n";
} else {
    echo "Failed to create admin user: " . $result . "\n";
}

// Test login
$user = login_user('admin', 'admin123');
if ($user) {
    echo "Admin login successful!\n";
    echo "User data: " . print_r($user, true) . "\n";
} else {
    echo "Admin login failed\n";
}

// Test admin check
if (is_admin()) {
    echo "Admin check passed!\n";
} else {
    echo "Admin check failed\n";
}

// Test database tables
try {
    // Check users table
    $stmt = $db->query("SELECT * FROM users");
    echo "\nUsers in database:\n";
    while ($row = $stmt->fetch()) {
        echo "ID: {$row['id']}, Username: {$row['username']}, Role: {$row['role']}\n";
    }

    // Check jobs table
    $stmt = $db->query("SELECT * FROM jobs");
    echo "\nJobs in database:\n";
    while ($row = $stmt->fetch()) {
        echo "ID: {$row['id']}, Title: {$row['title']}, Company: {$row['company']}\n";
    }

    // Check applications table
    $stmt = $db->query("SELECT * FROM applications");
    echo "\nApplications in database:\n";
    while ($row = $stmt->fetch()) {
        echo "ID: {$row['id']}, Job ID: {$row['job_id']}, User ID: {$row['user_id']}\n";
    }

} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
} 