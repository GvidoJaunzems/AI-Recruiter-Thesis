<?php
/**
 * Database Configuration
 */

// MySQL Database connection settings
$db_config = [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'username' => getenv('DB_USERNAME') ?: 'root',
    'password' => getenv('DB_PASSWORD') ?: '',
    'database' => getenv('DB_DATABASE') ?: 'recruiter_db',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
];

/**
 * Function to get a database connection
 * 
 * @return PDO|null Database connection or null on failure
 */
function get_db_connection() {
    global $db_config;
    
    try {
        $dsn = "mysql:host={$db_config['host']};dbname={$db_config['database']};charset={$db_config['charset']}";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        return new PDO($dsn, $db_config['username'], $db_config['password'], $options);
    } catch (PDOException $e) {
        // Log error but don't expose database credentials
        error_log("Database connection failed: " . $e->getMessage());
        return null;
    }
} 