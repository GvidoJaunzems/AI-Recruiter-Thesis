<?php
/**
 * Startup Script
 * This script runs when the application starts and handles database initialization
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';

// Only run in production environment
if (getenv('APP_ENV') !== 'production') {
    exit(0);
}

// Function to initialize database
function initialize_database() {
    global $db_config;
    
    try {
        // Connect without selecting a database
        $dsn = "mysql:host={$db_config['host']};charset={$db_config['charset']}";
        $pdo = new PDO($dsn, $db_config['username'], $db_config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        // Check if database exists
        $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '{$db_config['database']}'");
        if ($stmt->rowCount() === 0) {
            // Create new database
            $sql = "CREATE DATABASE {$db_config['database']} 
                    CHARACTER SET {$db_config['charset']} 
                    COLLATE {$db_config['collation']}";
            $pdo->exec($sql);
            error_log("Created new database '{$db_config['database']}'");
        }
        
        // Select the database
        $pdo->exec("USE {$db_config['database']}");
        
        // Check if tables exist
        $stmt = $pdo->query("SHOW TABLES");
        if ($stmt->rowCount() === 0) {
            // Read and execute schema
            $schema = file_get_contents(__DIR__ . '/database/schema.sql');
            $pdo->exec($schema);
            error_log("Created database tables");
        }
        
    } catch (PDOException $e) {
        error_log("Database Error: " . $e->getMessage());
    }
}

// Function to clean up old files
function cleanup_old_files() {
    $upload_dir = __DIR__ . '/public/uploads/';
    if (is_dir($upload_dir)) {
        $files = glob($upload_dir . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}

// Run initialization
cleanup_old_files();
initialize_database(); 