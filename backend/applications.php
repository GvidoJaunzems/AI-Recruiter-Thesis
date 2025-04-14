<?php
// Attempt to reset OpCache explicitly
if (function_exists('opcache_reset')) {
    error_log("[OpCache] Attempting opcache_reset()...");
    opcache_reset();
    error_log("[OpCache] opcache_reset() executed.");
} else {
    error_log("[OpCache] opcache_reset() function does not exist.");
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
// Allow GET, POST, PUT, OPTIONS
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS'); 
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Include Composer autoloader
$autoloaderPath = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloaderPath)) {
    error_log("[Autoload Check] Autoloader file found at: " . $autoloaderPath);
    require_once $autoloaderPath;
    error_log("[Autoload Check] Autoloader included.");
} else {
    error_log("[Autoload Check] FATAL: Autoloader file NOT FOUND at: " . $autoloaderPath);
    http_response_code(500);
    echo json_encode(['error' => 'Server configuration error: Autoloader missing.']);
    exit;
}

// *** Log Composer's Base Directory ***
$psr4Path = __DIR__ . '/vendor/composer/autoload_psr4.php';
if (file_exists($psr4Path)) {
    $psr4Content = file_get_contents($psr4Path);
    // Basic parsing to find the $baseDir assignment
    if (preg_match('/\$baseDir\s*=\s*dirname\(\$vendorDir\);/', $psr4Content, $matches)) {
        // This is tricky as we can't easily *execute* this line to get the value
        // Let's just log the structure confirmation
         error_log("[Autoload Check] Found standard \$baseDir calculation in autoload_psr4.php.");
         // Log __DIR__ from this context to compare
         error_log("[Autoload Check] Current script __DIR__ is: " . __DIR__); 
    } else {
         error_log("[Autoload Check] Could not find standard \$baseDir calculation in autoload_psr4.php snippet: " . substr($psr4Content, 0, 300));
    }
} else {
     error_log("[Autoload Check] autoload_psr4.php not found at: " . $psr4Path);
}
// *** End Log Base Directory ***

// Include DB config and JWT helper
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/jwt_helper.php'; 
// REMOVED: require_once __DIR__ . '/config/ai_helper.php';

// --- REMOVED Autoloader Debugging ---

// REMOVED: use Smalot\PdfParser\Parser; 

// Use statements for the new library
use RecruiterLib\Recruitment\ApplicationAnalyzer;
use RecruiterLib\AI\AICommsException;
use RecruiterLib\AI\AIResponseException;
use RecruiterLib\Util\ParsingException;

// --- Logging Helper ---
// Keep this or move to a dedicated utility included via autoloader later
if (!function_exists('log_app_event')) {
    function log_app_event($appId, $message) {
        $safeAppId = is_numeric($appId) ? (int)$appId : 'general';
        error_log("[App ID {$safeAppId}] " . $message);
    }
}
// --------------------


// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// --- Authentication Helper --- 
// Keep this or refactor into an Auth class later
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
    // Return the whole payload which should include user_id and role
    return $decodedPayload; 
}
// ---------------------------

// --- Main Request Handling ---

$conn = get_db_connection();
if (!$conn) {
    http_response_code(500);
    error_log("Database connection failed in applications.php");
    echo json_encode(['error' => 'Database connection error']);
    exit;
}

// *** Runtime Schema Verification ***
try {
    $stmt = $conn->query('DESCRIBE applications;');
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    error_log("[Runtime Schema Check] 'applications' columns found: " . implode(", ", $columns));
    if (!in_array('ai_score_explanation', $columns)) {
        error_log("[Runtime Schema Check] FATAL: Column 'ai_score_explanation' is MISSING!");
        // Optionally die here if this is unexpected and should halt execution
        // die("Server Error: Database schema mismatch."); 
    } else {
        error_log("[Runtime Schema Check] Column 'ai_score_explanation' exists.");
    }
} catch (PDOException $e) {
    error_log("[Runtime Schema Check] ERROR: Could not DESCRIBE 'applications' table: " . $e->getMessage());
    // Decide if this should halt execution
    // die("Server Error: Cannot verify database schema.");
}
// *** End Runtime Schema Verification ***


// Instantiate the ApplicationAnalyzer
$analyzer = null;
try {
    // *** REMOVED diagnostic file_exists, is_readable, require_once checks ***
    $className = 'RecruiterLib\Recruitment\ApplicationAnalyzer';
    $classExists = class_exists($className); // Rely on autoloader now
    error_log("[Autoload Check] Does class '{$className}' exist via class_exists()? " . ($classExists ? 'Yes' : 'No'));

    if (!$classExists) {
         // Log details if class still not found (shouldn't happen if autoloader works)
         $psr4Path = __DIR__ . '/vendor/composer/autoload_psr4.php';
         $staticPath = __DIR__ . '/vendor/composer/autoload_static.php';
         error_log("[Autoload Check] Class not found! PSR-4 snippet: " . (file_exists($psr4Path) ? substr(file_get_contents($psr4Path), 0, 300) : 'Not found'));
         error_log("[Autoload Check] Class not found! Static snippet: " . (file_exists($staticPath) ? substr(file_get_contents($staticPath), 0, 300) : 'Not found'));
         throw new \Exception("Class {$className} not found by autoloader.");
    }

    $analyzer = new ApplicationAnalyzer($conn);
    error_log("[Autoload Check] ApplicationAnalyzer instantiated successfully.");

} catch (InvalidArgumentException $e) {
    // Catch API key issues specifically during instantiation
    http_response_code(500);
    error_log("Failed to instantiate ApplicationAnalyzer: " . $e->getMessage());
    echo json_encode(['error' => 'Server configuration error related to AI setup.']);
    exit;
} catch (\Exception $e) {
     // Catch any other potential instantiation errors
    http_response_code(500);
    error_log("Failed to instantiate ApplicationAnalyzer: " . $e->getMessage());
    echo json_encode(['error' => 'Server initialization error.']);
    exit;
}


// Handle POST request for creating a new application
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- Authentication --- 
    $tokenPayload = authenticate_and_get_user();
    $userId = $tokenPayload->data->userId ?? null; 
    $userRole = $tokenPayload->data->role ?? 'user'; 

    if (!$userId) {
        http_response_code(401);
        error_log("[Apply POST] User ID not found in valid token payload.");
        echo json_encode(['error' => 'Invalid token data - user ID missing']);
        exit;
    }
    
    log_app_event($userId, "[Apply POST] User ID: $userId (Role: $userRole) attempting application.");
    // Logging POST/FILES data can be helpful for debugging form issues
    // error_log("[Apply POST] Received POST data: " . print_r($_POST, true));
    // error_log("[Apply POST] Received FILES data: " . print_r($_FILES, true));
    
    // --- Input Data & File Upload Handling --- 
    if (!isset($_POST['job_id']) || !is_numeric($_POST['job_id'])) {
        http_response_code(400); 
        echo json_encode(['error' => 'Missing or invalid required field: job_id']);
        exit;
    }
    $jobId = (int)$_POST['job_id'];

    // --- Resume File Upload Logic (Remains mostly the same) ---
    $resumeUrl = null; // Initialize
    $uploadedPdfPath = null; // Store the actual file path for potential deletion on error
    if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/uploads/'; 
        if (!is_dir($uploadDir)) {
            // Attempt to create directory
            if (!mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) { 
                 http_response_code(500);
                 error_log("[Apply POST] Failed to create upload directory: $uploadDir");
                 echo json_encode(['error' => 'Server error: Cannot save uploaded file.']);
                 exit;
            }
        }
        // Ensure unique filename and prevent directory traversal
        $baseName = preg_replace("/[^a-zA-Z0-9.\-_]/", "", basename($_FILES['resume']['name']));
        $fileName = uniqid('resume_', true) . '_' . $baseName;
        $targetPath = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['resume']['tmp_name'], $targetPath)) {
            // Use a relative path from the web root perspective for DB storage
            $resumeUrl = '/backend/uploads/' . $fileName; 
            $uploadedPdfPath = $targetPath; // Keep track of the actual path
            log_app_event($userId, "[Apply POST] Resume uploaded successfully: " . $resumeUrl);
        } else {
            http_response_code(500);
            error_log("[Apply POST] Failed to move uploaded resume file for user $userId.");
            echo json_encode(['error' => 'Failed to process resume upload. Check server permissions.']);
            exit;
        }
    } else {
        http_response_code(400);
        $uploadError = isset($_FILES['resume']['error']) ? strval($_FILES['resume']['error']) : 'No file provided';
        error_log("[Apply POST] Resume file missing or upload error for user $userId: " . $uploadError);
        echo json_encode(['error' => 'Resume file is required or failed to upload. Error code: ' . $uploadError]);
        exit;
    }
    // --------------------------------------

    // Extract other fields (Remains the same)
    $coverLetter = $_POST['cover_letter'] ?? null;
    $currentEmployer = $_POST['current_employer'] ?? null;
    $currentJobTitle = $_POST['current_job_title'] ?? null;
    $yearsOfExperience = isset($_POST['years_of_experience']) && is_numeric($_POST['years_of_experience']) ? (int)$_POST['years_of_experience'] : null;
    
    // Handle JSON Array Fields (Remains the same)
    $workExperienceJson = null;
    if (isset($_POST['work_experience'])) {
        $decodedWorkExp = json_decode($_POST['work_experience'], true);
        if (is_array($decodedWorkExp)) {
            $workExperienceJson = json_encode($decodedWorkExp); 
        } else {
             error_log("[Apply POST] Received INVALID work_experience JSON.");
             $workExperienceJson = '[]'; 
        }
    }
    $educationDetailsJson = null;
    if (isset($_POST['education_details'])) {
        $decodedEduDetails = json_decode($_POST['education_details'], true);
        if (is_array($decodedEduDetails)) {
            $educationDetailsJson = json_encode($decodedEduDetails);
        } else {
             error_log("[Apply POST] Received INVALID education_details JSON.");
             $educationDetailsJson = '[]';
        }
    }
    
    $highestEducation = $_POST['highest_education'] ?? null;
    $skills = $_POST['skills'] ?? null;
    $certifications = $_POST['certifications'] ?? null;
    $languages = $_POST['languages'] ?? null;
    $referralSource = $_POST['referral_source'] ?? null;
    $willingToRelocate = filter_var($_POST['willing_to_relocate'] ?? '0', FILTER_VALIDATE_BOOLEAN);
    $availableStartDate = $_POST['available_start_date'] ?? null;
    $salaryExpectations = $_POST['salary_expectations'] ?? null;
    $legallyAuthorized = filter_var($_POST['legally_authorized_to_work'] ?? '0', FILTER_VALIDATE_BOOLEAN);
    $requireSponsorship = filter_var($_POST['require_sponsorship'] ?? '0', FILTER_VALIDATE_BOOLEAN);
    $gender = $_POST['gender'] ?? null;
    $ethnicity = $_POST['ethnicity'] ?? null;
    $veteranStatus = $_POST['veteran_status'] ?? null;
    $disabilityStatus = $_POST['disability_status'] ?? null;

    // --- Database Interaction --- 
    $newApplicationId = null; // Initialize
    try {
        $conn->beginTransaction(); // Start transaction

        // Check for duplicate application (Remains the same)
        $stmtCheck = $conn->prepare("SELECT id FROM applications WHERE user_id = :user_id AND job_id = :job_id LIMIT 1");
        $stmtCheck->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmtCheck->bindParam(':job_id', $jobId, PDO::PARAM_INT);
        $stmtCheck->execute();
        if ($stmtCheck->fetch()) {
            http_response_code(409); // Conflict
            echo json_encode(['error' => 'You have already applied for this job.']);
            $conn->rollBack(); // Rollback before exiting
            // Clean up uploaded file if duplicate detected
            if ($uploadedPdfPath && file_exists($uploadedPdfPath)) {
                unlink($uploadedPdfPath);
                 error_log("[Apply POST] Deleted uploaded resume due to duplicate application: " . $uploadedPdfPath);
            }
            exit;
        }

        // Prepare INSERT statement (Initialize AI fields as NULL)
        $sql = "INSERT INTO applications (
                    user_id, job_id, resume_url, cover_letter, status, 
                    current_employer, current_job_title, years_of_experience, work_experience, 
                    highest_education, education_details, 
                    skills, certifications, languages, 
                    referral_source, willing_to_relocate, available_start_date, salary_expectations, 
                    legally_authorized_to_work, require_sponsorship, 
                    gender, ethnicity, veteran_status, disability_status,
                    ai_score, ai_analysis, ai_extracted_data /* Initialize AI fields as NULL */
                ) VALUES (
                    :user_id, :job_id, :resume_url, :cover_letter, :status, 
                    :current_employer, :current_job_title, :years_of_experience, :work_experience, 
                    :highest_education, :education_details, 
                    :skills, :certifications, :languages, 
                    :referral_source, :willing_to_relocate, :available_start_date, :salary_expectations, 
                    :legally_authorized_to_work, :require_sponsorship, 
                    :gender, :ethnicity, :veteran_status, :disability_status,
                    NULL, NULL, NULL 
                )";
        $stmt = $conn->prepare($sql);

        // Bind parameters (Remains the same)
        $status = 'pending'; // Initial status
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':job_id', $jobId, PDO::PARAM_INT);
        $stmt->bindParam(':resume_url', $resumeUrl, $resumeUrl === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(':cover_letter', $coverLetter, $coverLetter === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':current_employer', $currentEmployer, $currentEmployer === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(':current_job_title', $currentJobTitle, $currentJobTitle === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(':years_of_experience', $yearsOfExperience, $yearsOfExperience === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindParam(':work_experience', $workExperienceJson, $workExperienceJson === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(':highest_education', $highestEducation, $highestEducation === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(':education_details', $educationDetailsJson, $educationDetailsJson === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(':skills', $skills, $skills === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(':certifications', $certifications, $certifications === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(':languages', $languages, $languages === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(':referral_source', $referralSource, $referralSource === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(':willing_to_relocate', $willingToRelocate, PDO::PARAM_BOOL);
        $stmt->bindParam(':available_start_date', $availableStartDate, $availableStartDate === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(':salary_expectations', $salaryExpectations, $salaryExpectations === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(':legally_authorized_to_work', $legallyAuthorized, PDO::PARAM_BOOL);
        $stmt->bindParam(':require_sponsorship', $requireSponsorship, PDO::PARAM_BOOL);
        $stmt->bindParam(':gender', $gender, $gender === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(':ethnicity', $ethnicity, $ethnicity === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(':veteran_status', $veteranStatus, $veteranStatus === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(':disability_status', $disabilityStatus, $disabilityStatus === null ? PDO::PARAM_NULL : PDO::PARAM_STR);

        // Execute statement
        if ($stmt->execute()) {
            $newApplicationId = $conn->lastInsertId();
            log_app_event($newApplicationId, "Application record created successfully by user $userId.");
            
            // --- Trigger Immediate AI Analysis using the new Analyzer ---
            if ($analyzer) { // Check if analyzer was instantiated correctly
                 log_app_event($newApplicationId, "Attempting immediate AI analysis via ApplicationAnalyzer...");
                try {
                    $analysisResults = $analyzer->analyzeApplication($newApplicationId); 
                    // Log success or failure based on results
                    if (isset($analysisResults['error'])) {
                         log_app_event($newApplicationId, "Immediate AI analysis completed with error: " . $analysisResults['error']);
                         // Decide if this error should prevent commit - maybe not if app record is already saved
                    } else {
                         log_app_event($newApplicationId, "Immediate AI analysis completed successfully.");
                    }
                } catch (\Exception $e) {
                    // Catch any unexpected exceptions during analysis
                     log_app_event($newApplicationId, "Immediate AI Error (Outer Catch): Unexpected error during analyzer->analyzeApplication: " . $e->getMessage());
                     // Do not re-throw, allow the original success response to be sent, but maybe log status?
                }
            } else {
                 log_app_event($newApplicationId, "Error: ApplicationAnalyzer was not available for immediate analysis.");
                 // This indicates an instantiation error caught earlier
            }
            // --- End Immediate AI Analysis ---

            // Commit transaction AFTER successful insert and AI attempt (even if AI had errors)
            $conn->commit(); 
            
            // Send success response
            http_response_code(201); // Created
            echo json_encode([
                'message' => 'Application submitted successfully',
                'applicationId' => $newApplicationId
            ]);

        } else { // Failed to insert application record
            $conn->rollBack(); // Rollback on failure
             // Clean up uploaded file if DB insert fails
            if ($uploadedPdfPath && file_exists($uploadedPdfPath)) {
                unlink($uploadedPdfPath);
                 error_log("[Apply POST] Deleted uploaded resume due to DB insert failure: " . $uploadedPdfPath);
            }
            $errorInfo = $stmt->errorInfo();
            http_response_code(500);
            error_log("[Apply POST] Failed to insert application for user $userId: " . implode(", ", $errorInfo));
            echo json_encode(['error' => 'Failed to submit application', 'details' => $errorInfo[2]]);
        }

    } catch (PDOException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack(); // Rollback on PDO exception
        }
         // Clean up uploaded file on general DB error
        if ($uploadedPdfPath && file_exists($uploadedPdfPath)) {
            unlink($uploadedPdfPath);
            error_log("[Apply POST] Deleted uploaded resume due to PDOException: " . $uploadedPdfPath);
        }
        http_response_code(500);
        error_log("Database error during application submission for user $userId: " . $e->getMessage());
        echo json_encode(['error' => 'An internal error occurred during application submission']);
    } catch (Exception $e) { // Catch other exceptions
        if ($conn->inTransaction()) {
            $conn->rollBack(); // Rollback on general exception
        }
         // Clean up uploaded file on general error
        if ($uploadedPdfPath && file_exists($uploadedPdfPath)) {
            unlink($uploadedPdfPath);
            error_log("[Apply POST] Deleted uploaded resume due to general Exception: " . $uploadedPdfPath);
        }
        http_response_code(500);
        error_log("General error during application submission for user $userId: " . $e->getMessage());
        echo json_encode(['error' => 'An unexpected error occurred during application submission']);
    } finally {
        // No need to set $conn = null here as it's outside the loop/request handling
    }

// Handle GET request for fetching applications
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $tokenPayload = authenticate_and_get_user();
    $userId = $tokenPayload->data->userId ?? null;
    $userRole = $tokenPayload->data->role ?? 'user'; 

    if (!$userId) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token data - user ID missing']);
        exit;
    }
    
    try {
        $applicationId = isset($_GET['id']) ? (int)$_GET['id'] : null;
        $shouldRecalculate = isset($_GET['recalculate']) && $_GET['recalculate'] === 'true' && $userRole === 'admin';

        if ($applicationId) {
             // --- Handle Recalculation Request ---
            if ($shouldRecalculate) {
                log_app_event($applicationId, "[Recalculate] AI recalculation requested by admin.");
                 if ($analyzer) {
                     try {
                        $analysisResults = $analyzer->analyzeApplication($applicationId); 
                        if (isset($analysisResults['error'])) {
                            log_app_event($applicationId, "[Recalculate] AI recalculation completed with error: " . $analysisResults['error']);
                        } else {
                            log_app_event($applicationId, "[Recalculate] AI recalculation completed successfully.");
                        }
                     } catch (\Exception $e) {
                          log_app_event($applicationId, "[Recalculate] Unexpected error during analyzer->analyzeApplication: " . $e->getMessage());
                     }
                 } else {
                     log_app_event($applicationId, "[Recalculate] Error: ApplicationAnalyzer was not available.");
                 }
                 // Recalculation attempt finished, proceed to fetch the latest data
                 log_app_event($applicationId, "[Recalculate] Recalculation process completed, fetching final data.");
            } // --- End Recalculation Request ---

            // --- Fetch Specific Application (always fetch after potential recalc) --- 
            $sql = "SELECT a.*, 
                           CONCAT(u.first_name, ' ', u.last_name) as applicant_name, 
                           u.email as applicant_email, 
                           j.title as job_title 
                    FROM applications a 
                    JOIN users u ON a.user_id = u.id 
                    JOIN jobs j ON a.job_id = j.id 
                    WHERE a.id = :application_id";
            
            if ($userRole !== 'admin') {
                $sql .= " AND a.user_id = :user_id";
            }
            
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':application_id', $applicationId, PDO::PARAM_INT);
            if ($userRole !== 'admin') {
                $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            }
            $stmt->execute();
            $application = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($application) {
                log_app_event($applicationId, "Successfully fetched details for User ID $userId.");
                // Decode JSON fields 
                $application['work_experience'] = json_decode($application['work_experience'] ?? '[]', true) ?: [];
                $application['education_details'] = json_decode($application['education_details'] ?? '[]', true) ?: [];
                 // Decode extracted data 
                 $application['ai_extracted_data'] = json_decode($application['ai_extracted_data'] ?? 'null', true);
                 // Decode key factors (NEW)
                 $application['ai_key_factors'] = json_decode($application['ai_key_factors'] ?? '[]', true) ?: [];

                echo json_encode($application);
                exit;
            } else {
                http_response_code(404); // Not Found or Not Authorized
                log_app_event($applicationId, "Application not found or user $userId not authorized.");
                echo json_encode(['error' => 'Application not found or access denied']);
                exit; 
            }

        } else { // Fetching the list view
            log_app_event('list_view', "Fetching application list. User ID: $userId, Role: $userRole");

            // --- Get applications list (filtered for user or all for admin) --- 
            if ($userRole === 'admin') {
                // --- Admin List View Logic (Filters, Sorting) ---
                // This part remains largely the same, as it queries based on existing DB fields
                // except for ai_extracted_data filtering which needs adjustment.
                
                 $sqlBase = "SELECT a.id, a.user_id, a.job_id, a.status, a.created_at, 
                               a.ai_score, a.ai_extracted_data, /* Keep ai_extracted_data for PHP filter */
                               CONCAT(u.first_name, ' ', u.last_name) as applicant_name, 
                               j.title as job_title, 
                               a.years_of_experience, a.highest_education, 
                               a.willing_to_relocate, a.require_sponsorship 
                        FROM applications a 
                        JOIN users u ON a.user_id = u.id 
                        JOIN jobs j ON a.job_id = j.id";
                
                $whereClauses = [];
                $params = [];

                // --- Standard Filters (Name, Job Title, Status, Date, Experience, etc.) ---
                // ... (Keep existing filter logic here - it operates on standard columns) ...
                 if (isset($_GET['name']) && !empty(trim($_GET['name']))) {
                    $whereClauses[] = "CONCAT(u.first_name, ' ', u.last_name) LIKE :name";
                    $params[':name'] = '%' . trim($_GET['name']) . '%';
                }
                if (isset($_GET['jobTitle']) && !empty(trim($_GET['jobTitle']))) {
                    $whereClauses[] = "j.title LIKE :jobTitle";
                    $params[':jobTitle'] = '%' . trim($_GET['jobTitle']) . '%';
                }
                 if (isset($_GET['status']) && !empty($_GET['status'])) {
                    $allowedStatuses = ['pending', 'reviewed', 'accepted', 'rejected']; 
                    if (in_array($_GET['status'], $allowedStatuses)) {
                        $whereClauses[] = "a.status = :status";
                        $params[':status'] = $_GET['status'];
                    }
                }
                 if (isset($_GET['applyDateStart']) && !empty($_GET['applyDateStart'])) {
                    $dateStart = date('Y-m-d', strtotime($_GET['applyDateStart']));
                    if ($dateStart) {
                        $whereClauses[] = "DATE(a.created_at) >= :applyDateStart";
                        $params[':applyDateStart'] = $dateStart;
                    }
                }
                 if (isset($_GET['applyDateEnd']) && !empty($_GET['applyDateEnd'])) {
                    $dateEnd = date('Y-m-d', strtotime($_GET['applyDateEnd']));
                    if ($dateEnd) {
                        $whereClauses[] = "DATE(a.created_at) <= :applyDateEnd";
                        $params[':applyDateEnd'] = $dateEnd;
                    }
                }
                if (isset($_GET['minExp']) && is_numeric($_GET['minExp'])) {
                    $whereClauses[] = "a.years_of_experience >= :minExp";
                    $params[':minExp'] = (int)$_GET['minExp'];
                }
                if (isset($_GET['maxExp']) && is_numeric($_GET['maxExp'])) {
                    $whereClauses[] = "a.years_of_experience <= :maxExp";
                    $params[':maxExp'] = (int)$_GET['maxExp'];
                }
                 if (isset($_GET['education']) && !empty($_GET['education'])) {
                    $educationLevel = trim($_GET['education']); 
                    if (!empty($educationLevel)) {
                        $whereClauses[] = "a.highest_education = :education";
                        $params[':education'] = $educationLevel;
                    }
                }
                 if (isset($_GET['relocate']) && filter_var($_GET['relocate'], FILTER_VALIDATE_BOOLEAN)) {
                   $whereClauses[] = "a.willing_to_relocate = 1"; 
                }
                 if (isset($_GET['sponsorship']) && filter_var($_GET['sponsorship'], FILTER_VALIDATE_BOOLEAN)) {
                   $whereClauses[] = "a.require_sponsorship = 1"; 
                 }
                 if (isset($_GET['minScore']) && is_numeric($_GET['minScore'])) {
                    $minScoreValue = (int)$_GET['minScore'];
                    if ($minScoreValue >= 0) {
                        $whereClauses[] = "a.ai_score >= :minScore";
                        $params[':minScore'] = $minScoreValue;
                        $whereClauses[] = "a.ai_score IS NOT NULL"; // Ensure scored apps only
                    }
                }
                 // --- End Standard Filters ---

                // Construct the final SQL query
                $sql = $sqlBase;
                if (!empty($whereClauses)) {
                    $sql .= " WHERE " . implode(" AND ", $whereClauses);
                }
               
                // --- Sorting (Keep existing logic) ---
                $allowedSortFields = [
                    'created_at' => 'a.created_at',
                    'ai_score' => 'a.ai_score',
                    'applicant_name' => 'applicant_name', 
                    'job_title' => 'job_title',
                    'status' => 'a.status'
                ];
                $sortFieldInput = $_GET['sortField'] ?? 'created_at';
                $sortDirectionInput = $_GET['sortDirection'] ?? 'DESC';
                $sortColumn = $allowedSortFields[$sortFieldInput] ?? $allowedSortFields['created_at']; 
                $sortDirection = strtoupper($sortDirectionInput) === 'ASC' ? 'ASC' : 'DESC'; 
                $sql .= " ORDER BY $sortColumn $sortDirection";
                if ($sortFieldInput !== 'created_at') { 
                    $sql .= ", a.created_at DESC"; // Secondary sort
                }
                 // --- End Sorting --- 

                log_app_event('list_view', "Admin Query (Pre-PHP Filter): $sql");
                // log_app_event('list_view', "Admin Params: " . json_encode($params));

                $stmt = $conn->prepare($sql);
                foreach ($params as $key => $value) {
                    $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
                    $stmt->bindValue($key, $value, $type);
                }
                $stmt->execute();
                $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

                 // --- PHP-Based Filtering on ai_extracted_data ---
                 // This section remains necessary until DB-level JSON filtering is implemented
                 $skillFilter = isset($_GET['skill']) ? trim(strtolower($_GET['skill'])) : null;
                 $locationFilter = isset($_GET['locationKeyword']) ? trim(strtolower($_GET['locationKeyword'])) : null;
                 $minTotalYearsFilter = isset($_GET['minTotalYears']) && is_numeric($_GET['minTotalYears']) ? (int)$_GET['minTotalYears'] : null;
                 $highestDegreeFilter = isset($_GET['highestDegree']) ? trim(strtolower($_GET['highestDegree'])) : null;

                 if ($skillFilter || $locationFilter || $minTotalYearsFilter !== null || $highestDegreeFilter) {
                     log_app_event('list_view', "Applying PHP filters on AI extracted data...");
                     $filteredApplications = [];
                     foreach ($applications as $app) {
                        if (empty($app['ai_extracted_data'])) { // Check if column exists and is not empty/null
                            continue; 
                        }
                        
                        // Decode JSON here
                        $extractedData = json_decode($app['ai_extracted_data'], true);
                        if (!$extractedData || json_last_error() !== JSON_ERROR_NONE) {
                            log_app_event($app['id'] ?? 'unknown', "PHP Filter Warning: Could not decode ai_extracted_data JSON for app.");
                            continue; 
                        }

                        $match = true; // Assume match initially

                        // Apply Skill Filter
                        if ($match && $skillFilter) {
                            $skillFound = false;
                            $skillCategories = ['programming', 'software', 'technical', 'soft_skills', 'languages', 'other'];
                             // Check if skills key and categories exist
                             if (isset($extractedData['skills']) && is_array($extractedData['skills'])) {
                                foreach ($skillCategories as $category) {
                                    if (isset($extractedData['skills'][$category]) && is_array($extractedData['skills'][$category])) {
                                        foreach ($extractedData['skills'][$category] as $skill) {
                                            if (is_string($skill) && stripos($skill, $skillFilter) !== false) {
                                                $skillFound = true;
                                                break 2; 
                                            }
                                        }
                                    }
                                }
                             }
                            if (!$skillFound) $match = false;
                        }

                        // Apply Location Keyword Filter
                        if ($match && $locationFilter) {
                            $locationFound = false;
                            // Check structure exists
                            if (isset($extractedData['contact']['location_keywords']) && is_array($extractedData['contact']['location_keywords'])) {
                                foreach ($extractedData['contact']['location_keywords'] as $loc) {
                                    if (is_string($loc) && stripos($loc, $locationFilter) !== false) { 
                                        $locationFound = true;
                                        break;
                                    }
                                }
                            }
                             if (!$locationFound) $match = false;
                        }
                        
                        // Apply Min Total Years Filter
                        if ($match && $minTotalYearsFilter !== null) {
                             // Check structure exists and is numeric
                            $totalYears = $extractedData['experience']['total_years_approx'] ?? null;
                            if ($totalYears === null || !is_numeric($totalYears) || (int)$totalYears < $minTotalYearsFilter) {
                                $match = false;
                            }
                        }
                        
                        // Apply Highest Degree Filter
                        if ($match && $highestDegreeFilter) {
                             // Check structure exists and is string
                            $degreeGuess = isset($extractedData['education']['highest_level_guess']) && is_string($extractedData['education']['highest_level_guess']) 
                                           ? strtolower($extractedData['education']['highest_level_guess']) 
                                           : null;
                            if ($degreeGuess !== $highestDegreeFilter) {
                                 $match = false;
                            }
                        }

                        // If all filters passed, add to result
                        if ($match) {
                            $filteredApplications[] = $app;
                        }
                     }
                     $applications = $filteredApplications; // Replace original array
                     log_app_event('list_view', "PHP filtering completed. Result count: " . count($applications));
                 }
                 // --- END PHP-Based Filtering --- 

                log_app_event('list_view', "Final applications result count: " . count($applications));
                echo json_encode(['applications' => $applications]);
                exit;

            } else { // Non-admin user list view (Simpler query)
                 $sql = "SELECT a.id, a.job_id, a.status, a.created_at, a.updated_at, /* Add updated_at for candidate */
                                j.title as job_title, j.company as company_name, j.location /* Add company/location */
                        FROM applications a
                        JOIN jobs j ON a.job_id = j.id
                        WHERE a.user_id = :user_id
                        ORDER BY a.created_at DESC";
                 $stmt = $conn->prepare($sql);
                 $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
                 $stmt->execute();
                 $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
                  // No need to decode JSON here as it's not selected
                 echo json_encode(['applications' => $applications]); // Return directly
                 exit;
            }
        } // End list view block

    } catch (PDOException $e) {
        http_response_code(500);
        error_log("Database error during GET applications: " . $e->getMessage());
        echo json_encode(['error' => 'An internal database error occurred']);
    }
    catch (Exception $e) {
        http_response_code(500);
        error_log("General error during GET applications: " . $e->getMessage());
        echo json_encode(['error' => 'An unexpected error occurred']);
    }

// Handle PUT request for updating application status (Admin only)
} elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    // This logic remains the same as it doesn't involve AI directly
    $tokenPayload = authenticate_and_get_user();
    $userId = $tokenPayload->data->userId ?? null;
    $userRole = $tokenPayload->data->role ?? 'user'; 

    if ($userRole !== 'admin') {
        http_response_code(403);
         echo json_encode(['error' => 'Permission denied. Admin access required.']);
        exit;
    }
    
    $applicationId = isset($_GET['id']) ? (int)$_GET['id'] : null;
    if (!$applicationId) {
        http_response_code(400);
        echo json_encode(['error' => 'Application ID is required in the URL query string (e.g., ?id=123)']);
        exit;
    }

    $requestBody = file_get_contents('php://input');
    $data = json_decode($requestBody, true);

    if (!$data || !isset($data['status'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid request body. Expecting JSON with a "status" field.']);
        exit;
    }
    $newStatus = $data['status'];
    
    $allowedStatuses = ['pending', 'reviewed', 'accepted', 'rejected'];
    if (!in_array($newStatus, $allowedStatuses)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid status value. Allowed values: ' . implode(', ', $allowedStatuses)]);
        exit;
    }
    
    try {
        // Add updated_at timestamp
        $sql = "UPDATE applications SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id"; 
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':status', $newStatus);
        $stmt->bindParam(':id', $applicationId, PDO::PARAM_INT);

        if ($stmt->execute()) {
            if ($stmt->rowCount() > 0) {
                http_response_code(200); // OK
                log_app_event($applicationId, "Status updated to '{$newStatus}' by admin {$userId}.");
                echo json_encode(['message' => 'Application status updated successfully']);
                
                // --- Log to Audit Table on Final Decision ---
                if ($newStatus === 'accepted' || $newStatus === 'rejected') {
                    try {
                        // Fetch score, job_id, and extracted data for logging
                        $stmtFetch = $conn->prepare("SELECT job_id, ai_score, ai_extracted_data FROM applications WHERE id = :id");
                        $stmtFetch->bindParam(':id', $applicationId, PDO::PARAM_INT);
                        $stmtFetch->execute();
                        $appInfo = $stmtFetch->fetch(PDO::FETCH_ASSOC);
                        
                        if ($appInfo) {
                            // Extract inferred demographics (handle potential null/decode errors)
                            $extractedData = json_decode($appInfo['ai_extracted_data'] ?? 'null', true);
                            $inferredGender = $extractedData['inferred_demographics']['gender'] ?? null; 
                            $inferredEthnicity = $extractedData['inferred_demographics']['ethnicity_context'] ?? null;
                            
                            // Ensure they are within expected values or NULL 
                            $allowedGenders = ["male", "female", "unknown"];
                            // Remove 'unclear' from allowed ethnicities for logging consistency
                            $allowedEthnicities = ["likely_european", "likely_east_asian", "likely_south_asian", "likely_african", "likely_hispanic"];
                            
                            if ($inferredGender !== null && !in_array($inferredGender, $allowedGenders, true)) {
                                error_log("[Audit Log] Unexpected inferred gender value found: '{$inferredGender}'. Setting to NULL.");
                                $inferredGender = null; 
                            }
                             if ($inferredEthnicity !== null && !in_array($inferredEthnicity, $allowedEthnicities, true)) {
                                error_log("[Audit Log] Unexpected inferred ethnicity value found: '{$inferredEthnicity}'. Setting to NULL.");
                                // Since we forced a choice, invalid value likely means AI failed, log as NULL.
                                $inferredEthnicity = null; 
                            }

                            $stmtLog = $conn->prepare(
                                "INSERT INTO ai_audit_log (application_id, job_id, ai_score, decision, ai_extracted_data, ai_inferred_gender, ai_inferred_ethnicity_context) VALUES (:app_id, :job_id, :score, :decision, :extracted_data, :gender, :ethnicity)"
                            );
                            $stmtLog->bindParam(':app_id', $applicationId, PDO::PARAM_INT);
                            $stmtLog->bindParam(':job_id', $appInfo['job_id'], PDO::PARAM_INT);
                            $stmtLog->bindValue(':score', $appInfo['ai_score'], $appInfo['ai_score'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR); 
                            $stmtLog->bindParam(':decision', $newStatus, PDO::PARAM_STR);
                            // Bind extracted data (as JSON string or NULL)
                            $stmtLog->bindParam(':extracted_data', $appInfo['ai_extracted_data'], $appInfo['ai_extracted_data'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR); 
                            // Bind inferred demographics (allow NULL)
                            $stmtLog->bindParam(':gender', $inferredGender, $inferredGender === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                            $stmtLog->bindParam(':ethnicity', $inferredEthnicity, $inferredEthnicity === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                            
                            if ($stmtLog->execute()) {
                                log_app_event($applicationId, "Logged final decision '{$newStatus}' to ai_audit_log (with inferred demographics).");
                            } else {
                                 log_app_event($applicationId, "Failed to log final decision to ai_audit_log.");
                                 error_log("Audit Log Error Info: " . implode(", ", $stmtLog->errorInfo()));
                            }
                        } else {
                             log_app_event($applicationId, "Could not fetch app info for audit logging.");
                        }
                    } catch (PDOException $e) {
                        log_app_event($applicationId, "Database error during audit logging: " . $e->getMessage());
                        // Don't fail the main response for logging errors
                    }
                }
                // --- End Audit Logging ---
                
                 // --- Trigger Candidate Feedback Generation on Rejection --- 
                 if ($newStatus === 'rejected' && $analyzer) {
                     log_app_event($applicationId, "Triggering candidate feedback generation...");
                     try {
                        // Run feedback generation (can run in background if slow, but sync for now)
                        $feedbackSuccess = $analyzer->generateAndSaveCandidateFeedback($applicationId);
                        if ($feedbackSuccess) {
                             log_app_event($applicationId, "Candidate feedback generation completed successfully.");
                        } else {
                             log_app_event($applicationId, "Candidate feedback generation failed (see previous logs).");
                        }
                     } catch (\Exception $e) { // Catch errors from the feedback method itself
                        log_app_event($applicationId, "Error generating candidate feedback: " . $e->getMessage());
                        // Don't fail the main status update response due to feedback error
                     }
                 }
                 // -----------------------------------------------------------------------
                
            } else {
                // Check if application exists
                $stmtCheck = $conn->prepare("SELECT id FROM applications WHERE id = :id LIMIT 1");
                $stmtCheck->bindParam(':id', $applicationId, PDO::PARAM_INT);
                $stmtCheck->execute();
                if ($stmtCheck->fetch()) {
                     http_response_code(200); // OK, but no change
                     echo json_encode(['message' => 'Application found, but status was not changed (possibly already set).']);
                } else {
                    http_response_code(404); // Not Found
                    echo json_encode(['error' => 'Application not found']);
                }
            }
        } else {
            $errorInfo = $stmt->errorInfo();
            http_response_code(500);
            error_log("[Apply PUT] Failed to update application status for ID $applicationId by admin $userId: " . implode(", ", $errorInfo));
            echo json_encode(['error' => 'Failed to update application status', 'details' => $errorInfo[2]]);
        }

    } catch (PDOException $e) {
        http_response_code(500);
        error_log("Database error during PUT application status update: " . $e->getMessage());
        echo json_encode(['error' => 'An internal database error occurred']);
    }
    catch (Exception $e) {
        http_response_code(500);
        error_log("General error during PUT application status update: " . $e->getMessage());
        echo json_encode(['error' => 'An unexpected error occurred']);
    }

// Handle unsupported methods
} else {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Method not allowed']);
}

// Close connection (optional, PHP usually handles this)
// $conn = null; 

?>