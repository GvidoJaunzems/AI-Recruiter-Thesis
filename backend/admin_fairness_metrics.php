<?php
// backend/admin_fairness_metrics.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: GET, OPTIONS'); 
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Use absolute paths for require_once for clarity
require_once __DIR__ . '/config/database.php';
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
        error_log("[Admin Fairness API] " . $message);
    }
}
// --------------------

// --- Main Request Handling ---
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
     http_response_code(405);
     echo json_encode(['error' => 'Method not allowed. Only GET is supported.']);
     exit;
}

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

log_admin_event("Admin user {$userId} requesting fairness metrics.");

// --- Database Interaction ---
$conn = get_db_connection();
if (!$conn) {
    http_response_code(500);
    log_admin_event("Database connection failed.");
    echo json_encode(['error' => 'Database connection error']);
    exit;
}

try {
    // Fetch the most recent calculation for each metric/stratum
    // This query gets the latest timestamp for each unique metric group
    // then joins back to get the actual data for that latest timestamp.
    $sql = "
        SELECT 
            fmr.metric_name,
            fmr.job_id,
            fmr.stratum,
            fmr.value,
            fmr.calculation_timestamp,
            j.title AS job_title -- Include job title if job_id is present
        FROM 
            fairness_monitoring_results fmr
        INNER JOIN (
            SELECT 
                metric_name, 
                IFNULL(job_id, -1) as job_id_key, -- Handle NULL job_id for grouping
                IFNULL(stratum, '') as stratum_key, -- Handle NULL stratum for grouping
                MAX(calculation_timestamp) as max_ts
            FROM 
                fairness_monitoring_results
            GROUP BY 
                metric_name, job_id_key, stratum_key
        ) latest ON fmr.metric_name = latest.metric_name 
                 AND IFNULL(fmr.job_id, -1) = latest.job_id_key
                 AND IFNULL(fmr.stratum, '') = latest.stratum_key
                 AND fmr.calculation_timestamp = latest.max_ts
        LEFT JOIN jobs j ON fmr.job_id = j.id -- Join to get job title
        ORDER BY 
            fmr.metric_name, fmr.job_id, fmr.stratum, fmr.calculation_timestamp DESC
    ";

    $stmt = $conn->query($sql);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    log_admin_event("Fetched " . count($results) . " fairness metric results.");

    // Structure the results for easier frontend consumption (e.g., group by metric name)
    $structuredResults = [];
    foreach ($results as $row) {
        $metric = $row['metric_name'];
        if (!isset($structuredResults[$metric])) {
            $structuredResults[$metric] = [
                'metric_name' => $metric,
                'last_calculated' => $row['calculation_timestamp'], // Assume latest timestamp applies to the metric group
                'data' => []
            ];
        }
        // Keep track of the latest timestamp for the group
        if ($row['calculation_timestamp'] > $structuredResults[$metric]['last_calculated']) {
             $structuredResults[$metric]['last_calculated'] = $row['calculation_timestamp'];
        }
        $structuredResults[$metric]['data'][] = [
            'job_id' => $row['job_id'],
            'job_title' => $row['job_title'], // Include job title
            'stratum' => $row['stratum'],
            'value' => $row['value']
        ];
    }

    echo json_encode(array_values($structuredResults)); // Return as an array of metric groups

} catch (PDOException $e) {
    http_response_code(500);
    log_admin_event("Database error fetching metrics: " . $e->getMessage());
    echo json_encode(['error' => 'An internal database error occurred while fetching metrics.']);
} catch (Exception $e) {
    http_response_code(500);
    log_admin_event("General error fetching metrics: " . $e->getMessage());
    echo json_encode(['error' => 'An unexpected error occurred while fetching metrics.']);
} finally {
    $conn = null;
}

?> 