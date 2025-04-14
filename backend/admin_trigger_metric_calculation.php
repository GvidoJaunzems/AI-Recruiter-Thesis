<?php
// backend/admin_trigger_metric_calculation.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, OPTIONS'); // Allow POST
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST requests for triggering
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
     http_response_code(405);
     echo json_encode(['error' => 'Method not allowed. Only POST is supported.']);
     exit;
}

require_once __DIR__ . '/config/database.php'; // Need this for logging helper path potentially
require_once __DIR__ . '/config/jwt_helper.php'; 

// --- Authentication Helper (Copied - Centralize later) ---
function authenticate_and_get_user() {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
    if (!$authHeader) {
        http_response_code(401);
        echo json_encode(['error' => 'Authorization header missing']);
        exit;
    }
    $decodedPayload = validate_jwt_token($authHeader);
    if (!$decodedPayload) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid or expired token']);
        exit;
    }
    return $decodedPayload; 
}
// ---------------------------

// --- Logging Helper (Copied - Centralize later) ---
if (!function_exists('log_admin_event')) {
    function log_admin_event($message) {
        // Check if log_app_event exists, otherwise use basic error_log
        if (function_exists('log_app_event')) {
            log_app_event('AdminTrigger', "[Metric Trigger API] " . $message);
        } else {
            error_log("[AdminTrigger - Metric Trigger API] " . $message);
        }
    }
}
// --------------------

// --- Authentication & Authorization ---
$tokenPayload = authenticate_and_get_user();
$userId = $tokenPayload->data->userId ?? null;
$userRole = $tokenPayload->data->role ?? 'user';

if ($userRole !== 'admin') {
     http_response_code(403); // Forbidden
     log_admin_event("Access denied for user {$userId} (role: {$userRole}). Admin required.");
     echo json_encode(['error' => 'Access denied. Administrator privileges required.']);
     exit;
}

log_admin_event("Admin user {$userId} triggering metric calculation.");

// --- Execute the Calculation Script ---
try {
    // *** Check if exec is disabled ***
    if (!function_exists('exec')) {
        throw new Exception('exec() function is not available.');
    }
    $disabled_functions = ini_get('disable_functions');
    if (stripos($disabled_functions, 'exec') !== false) {
        throw new Exception('exec() function is disabled in php.ini.');
    }
    log_admin_event("exec() function is available and not disabled.");
    // *** End Check ***
    
    // Determine the absolute path to the script
    $scriptsDir = __DIR__ . '/scripts';
    $scriptName = 'calculate_fairness_metrics.php';
    $scriptPath = $scriptsDir . '/' . $scriptName; 
    
    // *** Add specific directory/file checks ***
    $dirExists = is_dir($scriptsDir);
    $dirReadable = $dirExists && is_readable($scriptsDir);
    $dirExecutable = $dirExists && is_executable($scriptsDir); // Check execute permission
    error_log("[Trigger Check] Scripts Directory: {$scriptsDir}. Exists? " . ($dirExists?'Y':'N') . " Readable? " . ($dirReadable?'Y':'N') . " Executable? " . ($dirExecutable?'Y':'N'));
    
    $fileExists = file_exists($scriptPath);
    $fileReadable = $fileExists && is_readable($scriptPath);
    $fileExecutable = $fileExists && is_executable($scriptPath); // Might not be needed if calling via php executable
    error_log("[Trigger Check] Script File: {$scriptPath}. Exists? " . ($fileExists?'Y':'N') . " Readable? " . ($fileReadable?'Y':'N') . " Executable? " . ($fileExecutable?'Y':'N'));
    // *** End specific checks ***

    $scriptPathResolved = realpath($scriptPath);
    log_admin_event("[Trigger Check] realpath resolved to: " . ($scriptPathResolved ?: 'false'));

    if (!$scriptPathResolved || !file_exists($scriptPathResolved)) {
        throw new Exception("Calculation script not found. Attempted path: {$scriptPath}, Resolved path: " . ($scriptPathResolved ?: 'false'));
    }

    $phpExecutable = 'php';

    // *** TEMPORARY: Run synchronously for debugging ***
    // $command = $phpExecutable . ' ' . escapeshellarg($scriptPathResolved) . ' > /dev/null 2>&1 &';
    $command = $phpExecutable . ' ' . escapeshellarg($scriptPathResolved);
    log_admin_event("(DEBUG) Executing command synchronously: {$command}");
    
    $output = [];
    $return_var = -1; // Initialize
    exec($command, $output, $return_var);
    
    log_admin_event("exec() completed. Return code: {$return_var}");
    log_admin_event("exec() output: " . implode("\n", $output));
    // *** End Temporary Debugging ***

    // Check return code - 0 usually means success
    if ($return_var !== 0) {
         throw new Exception("Metric calculation script execution failed with return code: {$return_var}. Check server logs for script errors.");
    }

    // If synchronous execution works, return success (200 OK for now)
    // Later, revert to background exec and 202 Accepted
    http_response_code(200); 
    echo json_encode(['message' => 'Metric calculation completed.', 'output' => $output]); // Include output for debug
    log_admin_event("Successfully executed metric calculation command synchronously.");

} catch (Exception $e) {
    http_response_code(500);
    log_admin_event("Error triggering metric calculation: " . $e->getMessage());
    echo json_encode(['error' => 'Failed to trigger metric calculation: ' . $e->getMessage()]);
} 

?> 