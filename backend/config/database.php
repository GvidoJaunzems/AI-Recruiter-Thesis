<?php
/**
 * Database Configuration
 */

function get_db_connection() {
    static $db = null;
    
    if ($db === null) {
        $host = getenv('DB_HOST') ?: 'localhost';
        $database = getenv('DB_DATABASE') ?: 'recruit';
        $username = getenv('DB_USERNAME') ?: 'root';
        $password = getenv('DB_PASSWORD') ?: '';
        
        try {
            $dsn = "mysql:host=$host;dbname=$database;charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $db = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            error_log("Connection failed: " . $e->getMessage());
            return false;
        }
    }
    
    return $db;
} 