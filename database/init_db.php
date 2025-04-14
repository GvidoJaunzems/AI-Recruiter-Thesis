<?php
/**
 * Database Initialization Script
 * Run this script to create the database and tables
 */

require_once __DIR__ . '/../config/database.php';

// Try to connect without specifying a database first to create the database
try {
    $dsn = "mysql:host={$db_config['host']};charset={$db_config['charset']}";
    $pdo = new PDO($dsn, $db_config['username'], $db_config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    // Create database if it doesn't exist
    $sql = "CREATE DATABASE IF NOT EXISTS {$db_config['database']} 
            CHARACTER SET {$db_config['charset']} 
            COLLATE {$db_config['collation']}";
    
    $pdo->exec($sql);
    echo "Database '{$db_config['database']}' created or already exists.\n";
    
    // Select the database
    $pdo->exec("USE {$db_config['database']}");
    
    // Read schema file
    $schema = file_get_contents(__DIR__ . '/schema.sql');
    
    // Execute schema
    $pdo->exec($schema);
    echo "Database tables created successfully.\n";
    
    echo "Database initialization completed successfully!\n";
    
} catch (PDOException $e) {
    die("DB ERROR: " . $e->getMessage());
} 