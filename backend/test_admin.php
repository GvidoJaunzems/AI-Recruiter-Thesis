<?php
require_once 'config/database.php';

// Test database connection
$db = get_db_connection();
if (!$db) {
    die("Database connection failed");
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