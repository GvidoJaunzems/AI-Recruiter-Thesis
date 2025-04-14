<?php
/**
 * Application Startup
 */

// Load deployment configuration
require_once __DIR__ . '/deploy.php';

// Load database configuration
require_once __DIR__ . '/config/database.php';

// Load models
require_once __DIR__ . '/app/models/Auth.php';
require_once __DIR__ . '/app/models/Job.php';
require_once __DIR__ . '/app/models/Application.php';

// Set up error handling
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    
    $error = [
        'type' => $errno,
        'message' => $errstr,
        'file' => $errfile,
        'line' => $errline
    ];
    
    error_log(json_encode($error));
    
    if ($_ENV['APP_DEBUG'] === 'true') {
        echo json_encode(['error' => $error]);
    } else {
        echo json_encode(['error' => 'An error occurred']);
    }
    
    exit(1);
});

// Set up exception handling
set_exception_handler(function($exception) {
    $error = [
        'type' => get_class($exception),
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine()
    ];
    
    error_log(json_encode($error));
    
    if ($_ENV['APP_DEBUG'] === 'true') {
        echo json_encode(['error' => $error]);
    } else {
        echo json_encode(['error' => 'An error occurred']);
    }
    
    exit(1);
});

// Set up session handling
session_start();

// Set up CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

echo "Application startup completed successfully!\n"; 