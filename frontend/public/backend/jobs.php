<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// This is a fallback PHP script that handles both GET and POST requests
$jsonFile = __DIR__ . '/jobs.json';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // For GET requests, return the jobs list or a specific job
    if (file_exists($jsonFile)) {
        $jobsData = json_decode(file_get_contents($jsonFile), true);
        
        // Check if a specific job ID is requested
        if (isset($_GET['id'])) {
            $jobId = $_GET['id'];
            $jobFound = false;
            
            // Find the requested job
            foreach ($jobsData['jobs'] as $job) {
                if ($job['id'] == $jobId) {
                    echo json_encode($job);
                    $jobFound = true;
                    break;
                }
            }
            
            // If job wasn't found, return a 404
            if (!$jobFound) {
                http_response_code(404);
                echo json_encode(['error' => 'Job not found']);
            }
        } else {
            // Return all jobs
            echo json_encode($jobsData);
        }
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Jobs data not found']);
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // For POST requests, add a new job
    try {
        // Read the JSON body
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        // Validate required fields
        if (!isset($data['title']) || !isset($data['company']) || !isset($data['location']) || !isset($data['description'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            exit;
        }
        
        // Read existing jobs
        if (file_exists($jsonFile)) {
            $jobsData = json_decode(file_get_contents($jsonFile), true);
        } else {
            $jobsData = ['jobs' => []];
        }
        
        // Generate a new ID
        $maxId = 0;
        foreach ($jobsData['jobs'] as $job) {
            if ($job['id'] > $maxId) {
                $maxId = $job['id'];
            }
        }
        $newId = $maxId + 1;
        
        // Create new job
        $newJob = [
            'id' => $newId,
            'title' => $data['title'],
            'company' => $data['company'],
            'location' => $data['location'],
            'description' => $data['description'],
            'requirements' => isset($data['requirements']) ? $data['requirements'] : '',
            'salary_range' => isset($data['salary_range']) ? $data['salary_range'] : '',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // Add to jobs array
        $jobsData['jobs'][] = $newJob;
        $jobsData['message'] = 'Job created successfully';
        
        // Save back to file
        file_put_contents($jsonFile, json_encode($jobsData, JSON_PRETTY_PRINT));
        
        // Return success
        http_response_code(201);
        echo json_encode(['message' => 'Job created successfully', 'job' => $newJob]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create job: ' . $e->getMessage()]);
    }
} else {
    // Unsupported method
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>