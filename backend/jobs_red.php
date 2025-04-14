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

require_once __DIR__ . '/config/database.php'; // Include DB connection helper

// GET Request: Fetch jobs from database
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $conn = get_db_connection();
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection error']);
        exit;
    }

    try {
        // Check if a specific job ID is requested
        if (isset($_GET['id']) && is_numeric($_GET['id'])) {
            $jobId = (int)$_GET['id'];
            $sql = "SELECT * FROM red_jobs WHERE id = :id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $jobId, PDO::PARAM_INT);
            $stmt->execute();
            $job = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($job) {
                echo json_encode($job);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Job not found']);
            }
        } else {
            // Fetch all jobs
            $sql = "SELECT * FROM red_jobs ORDER BY created_at DESC";
            $stmt = $conn->query($sql);
            $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['jobs' => $jobs]); // Wrap in 'jobs' key for consistency with previous structure if needed
        }
    } catch (PDOException $e) {
        http_response_code(500);
        error_log("Database error fetching jobs: " . $e->getMessage());
        echo json_encode(['error' => 'Database error fetching jobs']);
    } finally {
        $conn = null;
    }

// POST Request: Add a new job to the database
} else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic check if content type is JSON - adjust if using form-data
    // if (strpos($_SERVER["CONTENT_TYPE"], "application/json") !== 0) {
    //     http_response_code(400);
    //     echo json_encode(['error' => 'Invalid Content-Type, expected application/json']);
    //     exit;
    // }
    
    // Read the JSON body
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON body: ' . json_last_error_msg()]);
        exit;
    }

    // Validate required fields (adjust based on actual requirements)
    if (empty($data['title']) || empty($data['company']) || empty($data['location']) || empty($data['description'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields: title, company, location, description']);
        exit;
    }

    $conn = get_db_connection();
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection error']);
        exit;
    }

    try {
        $sql = "INSERT INTO red_jobs (title, company, location, description, requirements, salary_range) 
                VALUES (:title, :company, :location, :description, :requirements, :salary_range)";
        $stmt = $conn->prepare($sql);

        // Bind parameters
        $stmt->bindParam(':title', $data['title']);
        $stmt->bindParam(':company', $data['company']);
        $stmt->bindParam(':location', $data['location']);
        $stmt->bindParam(':description', $data['description']);
        
        // Handle optional fields
        $requirements = $data['requirements'] ?? null;
        $salary_range = $data['salary_range'] ?? null;
        $stmt->bindParam(':requirements', $requirements, $requirements === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(':salary_range', $salary_range, $salary_range === null ? PDO::PARAM_NULL : PDO::PARAM_STR);

        if ($stmt->execute()) {
            $newJobId = $conn->lastInsertId();
            // Fetch the newly created job to return it
            $stmtFetch = $conn->prepare("SELECT * FROM red_jobs WHERE id = :id");
            $stmtFetch->bindParam(':id', $newJobId, PDO::PARAM_INT);
            $stmtFetch->execute();
            $newJob = $stmtFetch->fetch(PDO::FETCH_ASSOC);

            http_response_code(201); // Created
            echo json_encode(['message' => 'Job created successfully', 'job' => $newJob]);
        } else {
            $errorInfo = $stmt->errorInfo();
            http_response_code(500);
            error_log("Failed to insert job: " . implode(", ", $errorInfo));
            echo json_encode(['error' => 'Failed to create job in database', 'details' => $errorInfo[2]]);
        }

    } catch (PDOException $e) {
        http_response_code(500);
        error_log("Database error creating job: " . $e->getMessage());
        echo json_encode(['error' => 'Database error creating job']);
    } finally {
        $conn = null;
    }

} else {
    // Unsupported method
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

?>