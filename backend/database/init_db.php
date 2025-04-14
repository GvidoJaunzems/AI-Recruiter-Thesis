<?php
/**
 * Database Initialization Script
 */

// Load database configuration
require_once __DIR__ . '/../config/database.php';

try {
    // Get database connection
    $db = get_db_connection();
    if (!$db) {
        die("Failed to connect to database");
    }

    // Temporarily disable foreign key checks for smoother table drops/creates
    $db->exec('SET FOREIGN_KEY_CHECKS=0;');
    echo "Disabled foreign key checks.\n";
    
    // *** TEMPORARY: Explicitly truncate new tables before CREATE attempt ***
    try {
        echo "Attempting to TRUNCATE ai_audit_log (if exists)...\n";
        $db->exec("TRUNCATE TABLE ai_audit_log");
        echo "TRUNCATE ai_audit_log succeeded (or table didn't exist).\n";
    } catch (PDOException $e) {
        echo "Warning: Failed to TRUNCATE ai_audit_log (may not exist yet): " . $e->getMessage() . "\n";
        // Continue even if truncate fails (table might not exist)
    }
    try {
        echo "Attempting to TRUNCATE fairness_monitoring_results (if exists)...\n";
        $db->exec("TRUNCATE TABLE fairness_monitoring_results");
        echo "TRUNCATE fairness_monitoring_results succeeded (or table didn't exist).\n";
    } catch (PDOException $e) {
        echo "Warning: Failed to TRUNCATE fairness_monitoring_results (may not exist yet): " . $e->getMessage() . "\n";
        // Continue even if truncate fails (table might not exist)
    }
    // *** END TEMPORARY TRUNCATE ***

    // Read and execute schema.sql
    $schemaFile = __DIR__ . '/schema.sql';
    if (!file_exists($schemaFile)) {
        die("Schema file not found: $schemaFile");
    }

    $schema = file_get_contents($schemaFile);
    if ($schema === false) {
        die("Failed to read schema file");
    }

    // Split schema into individual statements
    $statements = array_filter(
        array_map('trim', 
            explode(';', $schema)
        )
    );

    // Execute each statement
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            try {
                $db->exec($statement);
            } catch (PDOException $e) {
                echo "Warning: Failed to execute statement: " . $e->getMessage() . "\n";
                // Continue with next statement
            }
        }
    }

    echo "Database initialized successfully!\n";

} catch (Exception $e) {
    die("Database initialization failed: " . $e->getMessage());
} 