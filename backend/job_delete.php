<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// This handles DELETE requests to remove a job
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Check for job ID
if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Job ID is required']);
    exit;
}

$jobId = $_GET['id'];
$jsonFile = __DIR__ . '/jobs.json';

if (!file_exists($jsonFile)) {
    http_response_code(404);
    echo json_encode(['error' => 'Jobs data not found']);
    exit;
}

try {
    // Read current jobs
    $jobsData = json_decode(file_get_contents($jsonFile), true);
    $jobs = $jobsData['jobs'];
    $jobFound = false;
    
    // Find and remove the job
    foreach ($jobs as $key => $job) {
        if ($job['id'] == $jobId) {
            unset($jobs[$key]);
            $jobFound = true;
            break;
        }
    }
    
    if (!$jobFound) {
        http_response_code(404);
        echo json_encode(['error' => 'Job not found']);
        exit;
    }
    
    // Reindex array and save
    $jobsData['jobs'] = array_values($jobs);
    $jobsData['message'] = 'Job deleted successfully';
    file_put_contents($jsonFile, json_encode($jobsData, JSON_PRETTY_PRINT));
    
    // Return success
    echo json_encode(['message' => 'Job deleted successfully']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to delete job: ' . $e->getMessage()]);
}
?>