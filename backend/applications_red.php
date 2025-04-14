<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
// Allow GET, POST, PUT, OPTIONS
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS'); 
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/jwt_helper.php';
require_once __DIR__ . '/config/ai_helper.php'; // Include the AI helper

// --- Autoloader Debugging (Simplified) ---
$autoloaderPath = __DIR__ . '/vendor/autoload.php';
error_log("[Autoload Check Simplified] Attempting to load autoloader from: " . $autoloaderPath);
if (file_exists($autoloaderPath)) {
    error_log("[Autoload Check Simplified] Autoloader file exists. Including it.");
    require_once $autoloaderPath;
    error_log("[Autoload Check Simplified] Autoloader included.");
    // Removed class_exists checks for now to isolate the include issue
} else {
    error_log("[Autoload Check Simplified] FATAL: Autoloader file NOT FOUND at: " . $autoloaderPath);
    http_response_code(500);
    echo json_encode(['error' => 'Server configuration error: Autoloader missing.']);
    exit;
}
// --- End Autoloader Debugging ---


use Smalot\PdfParser\Parser; // PDF Parser

// // --- Autoloader Debugging 2 ---
// error_log("[Autoload Check] Passed 'use' statement. Checking class AFTER use statement...");
//  if (class_exists('Smalot\PdfParser\Parser', false)) { 
//      error_log("[Autoload Check] Class 'Smalot\PdfParser\Parser' known AFTER use. Should be OK.");
//  } else {
//      error_log("[Autoload Check] Class 'Smalot\PdfParser\Parser' NOT known AFTER use. Autoloading likely failed during the 'use' or instantiation.");
//  }
// // --- End Autoloader Debugging 2 ---

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// --- Authentication Helper ---
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

// --- Logging Helper ---
function log_app_event($appId, $message) {
    // Ensure appId is somewhat safe before logging if it comes directly from input
    $safeAppId = is_numeric($appId) ? (int)$appId : 'invalid_id'; 
    error_log("[App ID $safeAppId] " . $message);
}
// --------------------

// Handle POST request for creating a new application
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- Authentication --- 
    $tokenPayload = authenticate_and_get_user();
    // Correctly access nested data
    $userId = $tokenPayload->data->userId ?? null; 
    $userRole = $tokenPayload->data->role ?? 'user'; // Get role, default to user

    if (!$userId) {
        http_response_code(401);
        error_log("[Apply POST] User ID not found in valid token payload.");
        echo json_encode(['error' => 'Invalid token data - user ID missing']);
        exit;
    }
    
    error_log("[Apply POST] User ID: $userId (Role: $userRole) attempting application.");
    error_log("[Apply POST] Received POST data: " . print_r($_POST, true));
    error_log("[Apply POST] Received FILES data: " . print_r($_FILES, true));
    // --- Try reading raw input AFTER checking POST/FILES ---
    $raw_input = file_get_contents('php://input');
    error_log("[Apply] Raw input stream length: " . strlen($raw_input));
    // Log first few chars of raw input (be careful with large data)
    error_log("[Apply] Raw input stream start: " . substr($raw_input, 0, 200)); 
    // ----------------------------------------------------

    // --- Input Data & File Upload Handling --- 
    // Basic validation (job_id is required)
    if (!isset($_POST['job_id']) || !is_numeric($_POST['job_id'])) {
        http_response_code(400); 
        echo json_encode(['error' => 'Missing or invalid required field: job_id']);
        exit;
    }
    $jobId = (int)$_POST['job_id'];

    // --- Resume File Upload Logic ---
    $resumeUrl = null; // Initialize
    if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/uploads/'; 
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true); 
        }
        // Ensure unique filename and prevent directory traversal
        $baseName = preg_replace("/[^a-zA-Z0-9.\-_]/", "", basename($_FILES['resume']['name']));
        $fileName = uniqid('resume_', true) . '_' . $baseName;
        $targetPath = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['resume']['tmp_name'], $targetPath)) {
            // Use a relative path from the web root perspective
            $resumeUrl = '/backend/uploads/' . $fileName; 
            error_log("[Apply POST] Resume uploaded successfully for user $userId: " . $resumeUrl);
        } else {
            http_response_code(500);
            error_log("[Apply POST] Failed to move uploaded resume file for user $userId.");
            echo json_encode(['error' => 'Failed to process resume upload.']);
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

    // Extract other fields from _POST
    $coverLetter = $_POST['cover_letter'] ?? null;
    $currentEmployer = $_POST['current_employer'] ?? null;
    $currentJobTitle = $_POST['current_job_title'] ?? null;
    $yearsOfExperience = isset($_POST['years_of_experience']) && is_numeric($_POST['years_of_experience']) ? (int)$_POST['years_of_experience'] : null;
    
    // --- Handle JSON Array Fields --- 
    $workExperienceJson = null;
    if (isset($_POST['work_experience'])) {
        // Attempt to decode to validate, then re-encode for storage
        $decodedWorkExp = json_decode($_POST['work_experience'], true);
        if (is_array($decodedWorkExp)) {
            $workExperienceJson = json_encode($decodedWorkExp); // Store valid JSON
             error_log("[Apply POST] Received valid work_experience JSON.");
        } else {
             error_log("[Apply POST] Received INVALID work_experience JSON: " . $_POST['work_experience']);
             // Decide handling: store NULL, store original invalid string, or store empty array?
             $workExperienceJson = '[]'; // Store empty JSON array as a safe default
        }
    }
    
    $educationDetailsJson = null;
    if (isset($_POST['education_details'])) {
        $decodedEduDetails = json_decode($_POST['education_details'], true);
        if (is_array($decodedEduDetails)) {
            $educationDetailsJson = json_encode($decodedEduDetails); // Store valid JSON
             error_log("[Apply POST] Received valid education_details JSON.");
        } else {
             error_log("[Apply POST] Received INVALID education_details JSON: " . $_POST['education_details']);
             $educationDetailsJson = '[]'; // Store empty JSON array
        }
    }
    // -------------------------------
    
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
    $conn = get_db_connection();
    if (!$conn) {
        http_response_code(500);
        error_log("Database connection failed in applications.php (POST)");
        echo json_encode(['error' => 'Database connection error']);
        exit;
    }

    try {
        // Check for duplicate application
        $stmtCheck = $conn->prepare("SELECT id FROM red_applications WHERE user_id = :user_id AND job_id = :job_id LIMIT 1");
        $stmtCheck->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmtCheck->bindParam(':job_id', $jobId, PDO::PARAM_INT);
        $stmtCheck->execute();

        if ($stmtCheck->fetch()) {
            http_response_code(409); // Conflict
            echo json_encode(['error' => 'You have already applied for this job.']);
            exit;
        }

        // Prepare INSERT statement
        $sql = "INSERT INTO red_applications (
                    user_id, job_id, resume_url, cover_letter, status, 
                    current_employer, current_job_title, years_of_experience, work_experience, 
                    highest_education, education_details, 
                    skills, certifications, languages, 
                    referral_source, willing_to_relocate, available_start_date, salary_expectations, 
                    legally_authorized_to_work, require_sponsorship, 
                    gender, ethnicity, veteran_status, disability_status 
                ) VALUES (
                    :user_id, :job_id, :resume_url, :cover_letter, :status, 
                    :current_employer, :current_job_title, :years_of_experience, :work_experience, 
                    :highest_education, :education_details, 
                    :skills, :certifications, :languages, 
                    :referral_source, :willing_to_relocate, :available_start_date, :salary_expectations, 
                    :legally_authorized_to_work, :require_sponsorship, 
                    :gender, :ethnicity, :veteran_status, :disability_status
                )";
        
        $stmt = $conn->prepare($sql);

        // Bind parameters
        $status = 'pending';
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

            // --- Trigger Immediate AI Analysis --- 
            try {
                log_app_event($newApplicationId, "Attempting immediate AI analysis...");
                // 1. Fetch needed data (job description, resume url, cover letter etc.)
                $stmtFetch = $conn->prepare(
                   "SELECT a.resume_url, a.cover_letter, j.description as job_description 
                    FROM red_applications a 
                    JOIN red_jobs j ON a.job_id = j.id 
                    WHERE a.id = :id LIMIT 1"
                );
                $stmtFetch->bindParam(':id', $newApplicationId, PDO::PARAM_INT);
                $stmtFetch->execute();
                $appDataForAI = $stmtFetch->fetch(PDO::FETCH_ASSOC);

                if (!$appDataForAI) {
                    log_app_event($newApplicationId, "Immediate AI Error: Failed to re-fetch app/job data.");
                } else {
                    $jobDescription = $appDataForAI['job_description'];
                    $resumeUrlForAI = $appDataForAI['resume_url'];
                    $coverLetterForAI = $appDataForAI['cover_letter']; // Get cover letter if available
                    $resumeText = null;
                    $pdfPath = null;

                    // 2. Parse Resume PDF
                    if (empty($resumeUrlForAI)) {
                        log_app_event($newApplicationId, "Immediate AI Warning: Resume URL missing.");
                    } else {
                        $resumeBaseName = basename($resumeUrlForAI);
                        if (strpos($resumeUrlForAI, '/backend/uploads/') === 0) {
                            $pdfPath = __DIR__ . '/uploads/' . $resumeBaseName;
                        } else {
                            log_app_event($newApplicationId, "Immediate AI Error: Unexpected resume_url format: $resumeUrlForAI");
                        }

                        if ($pdfPath && file_exists($pdfPath)) {
                            try {
                                $parser = new Parser();
                                $pdf = $parser->parseFile($pdfPath);
                                $resumeText = $pdf->getText();
                                log_app_event($newApplicationId, "Immediate AI: PDF parsed successfully. Length: " . strlen($resumeText));
                            } catch (\Exception $e) {
                                log_app_event($newApplicationId, "Immediate AI Error: Failed to parse PDF '$pdfPath': " . $e->getMessage());
                                $resumeText = null; // Ensure it's null on error
                            }
                        } elseif ($pdfPath) {
                            log_app_event($newApplicationId, "Immediate AI Error: PDF file not found at: $pdfPath");
                        }
                    }

                    // 3. Combine Data (Resume + Cover Letter + Potentially other fields if available)
                    $combinedApplicantData = "--- Extracted Resume Text ---\n" . ($resumeText ?? '[Resume text not available or parsing failed]') . "\n\n";
                    $combinedApplicantData .= "--- Submitted Cover Letter ---\n" . ($coverLetterForAI ?? '[Not Provided]');
                    // TODO: Consider adding other submitted fields (skills, experience etc. if available)
                    // This part might be simpler for Quick Apply vs Detailed Apply

                    // 4. Call AI Helper
                    if (!empty($jobDescription) && !empty($combinedApplicantData)) {
                        log_app_event($newApplicationId, "Immediate AI: Calling AI helper...");
                        $aiResult = null;
                        $aiScore = null;
                        $aiAnalysis = null;
                        try {
                            $aiResult = get_ai_application_analysis($jobDescription, $combinedApplicantData);
                            if ($aiResult !== null && isset($aiResult['score']) && isset($aiResult['justification'])) {
                                $aiScore = (int)$aiResult['score'];
                                $aiAnalysis = $aiResult['justification'];
                                log_app_event($newApplicationId, "Immediate AI: Analysis successful. Score: $aiScore");
                            } else {
                                log_app_event($newApplicationId, "Immediate AI Warning: AI helper returned null or invalid format.");
                                $aiAnalysis = "AI analysis did not return valid results."; // Store indication
                            }
                        } catch (\Exception $e) {
                            log_app_event($newApplicationId, "Immediate AI Error: Exception during AI call: " . $e->getMessage());
                            $aiAnalysis = "AI analysis failed due to an error during API call."; // Store indication
                        }

                        // 5. Update Application Record
                        if ($aiScore !== null || $aiAnalysis !== null) {
                             log_app_event($newApplicationId, "Immediate AI: Updating application record.");
                             try {
                                $stmtUpdateAI = $conn->prepare("UPDATE red_applications SET ai_score = :score, ai_analysis = :analysis WHERE id = :id");
                                $stmtUpdateAI->bindValue(':score', $aiScore, $aiScore === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                                $stmtUpdateAI->bindParam(':analysis', $aiAnalysis, PDO::PARAM_STR);
                                $stmtUpdateAI->bindParam(':id', $newApplicationId, PDO::PARAM_INT);
                                $updateSuccess = $stmtUpdateAI->execute();
                                if ($updateSuccess) {
                                    log_app_event($newApplicationId, "Immediate AI: DB updated successfully.");
                                } else {
                                     log_app_event($newApplicationId, "Immediate AI DB Error: Update execute() returned false.");
                                }
                             } catch (PDOException $e) {
                                log_app_event($newApplicationId, "Immediate AI DB Error: Failed to update DB with AI results: " . $e->getMessage());
                             }
                        }
                    } else {
                        log_app_event($newApplicationId, "Immediate AI Error: Missing Job Description or Combined Data for AI call.");
                    }
                }
            } catch (\Exception $e) {
                // Catch any unexpected errors during the immediate AI processing phase
                log_app_event($newApplicationId, "Immediate AI Error (Outer Catch): Unexpected error during AI processing: " . $e->getMessage());
                // Do not re-throw, allow the original success response to be sent
            }
            // --- End Immediate AI Analysis ---
            
            // --- ADD Immediate AI Data Extraction --- 
            try {
                 log_app_event($newApplicationId, "Attempting immediate AI data extraction...");
                 // We reuse $jobDescription and $combinedApplicantData from the previous block
                 // Ensure they are still available in this scope or refetch if necessary (assuming they are)
                 if (!empty($jobDescription) && !empty($combinedApplicantData)) {
                     $extractedData = get_ai_extracted_data($jobDescription, $combinedApplicantData);

                     if ($extractedData !== null && is_array($extractedData)) {
                        log_app_event($newApplicationId, "Immediate Extraction: Successfully received structured data.");
                        try {
                            // Encode the array as JSON for storage
                            $extractedDataJson = json_encode($extractedData);
                            if (json_last_error() !== JSON_ERROR_NONE) {
                                throw new Exception("Failed to encode extracted data to JSON: " . json_last_error_msg());
                            }

                            $stmtUpdateExtract = $conn->prepare("UPDATE red_applications SET ai_extracted_data = :data WHERE id = :id");
                            $stmtUpdateExtract->bindParam(':data', $extractedDataJson, PDO::PARAM_STR); // Store as JSON string
                            $stmtUpdateExtract->bindParam(':id', $newApplicationId, PDO::PARAM_INT);
                            $updateExtractSuccess = $stmtUpdateExtract->execute();

                            if ($updateExtractSuccess) {
                                log_app_event($newApplicationId, "Immediate Extraction: DB updated successfully with extracted data.");
                            } else {
                                log_app_event($newApplicationId, "Immediate Extraction DB Error: Update execute() returned false.");
                            }
                        } catch (\Exception $e) {
                            log_app_event($newApplicationId, "Immediate Extraction DB Error: Failed to update DB: " . $e->getMessage());
                        }
                     } else {
                         log_app_event($newApplicationId, "Immediate Extraction Warning: AI helper did not return valid structured data.");
                     }
                 } else {
                     log_app_event($newApplicationId, "Immediate Extraction Error: Missing Job Description or Combined Data for AI call.");
                 }
            } catch (\Exception $e) {
                log_app_event($newApplicationId, "Immediate Extraction Error (Outer Catch): Unexpected error during AI extraction: " . $e->getMessage());
            }
            // --- End Immediate AI Data Extraction ---

            // Send original success response regardless of AI outcome
            http_response_code(201); // Created
            echo json_encode([
                'message' => 'Application submitted successfully',
                'applicationId' => $newApplicationId
            ]);
        } else {
            $errorInfo = $stmt->errorInfo();
            http_response_code(500);
            // Log with admin user ID
            error_log("[Apply POST] Failed to insert application for user $userId: " . implode(", ", $errorInfo));
            echo json_encode(['error' => 'Failed to submit application', 'details' => $errorInfo[2]]);
        }

    } catch (PDOException $e) {
        http_response_code(500);
        error_log("Database error during application submission for user $userId: " . $e->getMessage());
        echo json_encode(['error' => 'An internal error occurred during application submission']);
    } catch (Exception $e) {
        http_response_code(500);
        error_log("General error during application submission for user $userId: " . $e->getMessage());
        echo json_encode(['error' => 'An unexpected error occurred during application submission']);
    } finally {
        $conn = null;
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
    
    $conn = get_db_connection();
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection error']);
        exit;
    }

    try {
        $applicationId = isset($_GET['id']) ? (int)$_GET['id'] : null;
        
        // Check for the recalculate flag
        $shouldRecalculate = isset($_GET['recalculate']) && $_GET['recalculate'] === 'true';

        if ($applicationId) {
            // --- Fetch a specific application --- 
            // Select all relevant fields including AI fields
            $sql = "SELECT a.*, 
                           CONCAT(u.first_name, ' ', u.last_name) as applicant_name, 
                           u.email as applicant_email, 
                           j.title as job_title, 
                           j.description as job_description, 
                           j.requirements as job_requirements
                    FROM red_applications a 
                    JOIN red_users u ON a.user_id = u.id 
                    JOIN red_jobs j ON a.job_id = j.id 
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
                error_log("[Application Get ID={$applicationId}] Found application.");
                // Decode JSON fields
                $application['work_experience'] = json_decode($application['work_experience'], true);
                if (json_last_error() !== 0) {
                    error_log("[Application Get ID={$applicationId}] Failed to decode work_experience JSON: " . json_last_error_msg());
                    $application['work_experience'] = []; // Default to empty array on error
                }
                $application['education_details'] = json_decode($application['education_details'], true);
                 if (json_last_error() !== 0) {
                    error_log("[Application Get ID={$applicationId}] Failed to decode education_details JSON: " . json_last_error_msg());
                    $application['education_details'] = []; // Default to empty array on error
                }

                // --- Check for AI Recalculation Request ---
                if (isset($_GET['recalculate']) && $_GET['recalculate'] === 'true' && $userRole === 'admin') {
                    error_log("[Application Get ID={$applicationId}] Recalculate AI score requested by admin.");

                    $resumeText = null; // Initialize resume text
                    $jobDescription = null;
                    $appData = null;

                    try {
                        // 1. Fetch current application data needed (resume_url, job_id, etc.)
                        $stmtFetchApp = $conn->prepare(
                            "SELECT a.*, j.title as job_title, j.description as job_description 
                             FROM red_applications a 
                             JOIN red_jobs j ON a.job_id = j.id 
                             WHERE a.id = :id"
                        );
                        $stmtFetchApp->bindParam(':id', $applicationId, PDO::PARAM_INT);
                        $stmtFetchApp->execute();
                        $appData = $stmtFetchApp->fetch(PDO::FETCH_ASSOC);

                        if (!$appData) {
                           log_app_event($applicationId, "Recalculate Error: Application data not found during recalculation attempt.");
                           // Skip further recalculation steps if core data is missing
                        } elseif (empty($appData['resume_url'])) {
                            log_app_event($applicationId, "Recalculate Warning: Resume URL is missing. Cannot parse PDF.");
                            // Update DB status/analysis to indicate PDF missing
                            $aiAnalysisUpdate = "AI analysis cannot be performed: Resume file URL missing from application record.";
                            $aiScoreUpdate = null;
                            try {
                                $stmtUpdate = $conn->prepare("UPDATE red_applications SET ai_score = :score, ai_analysis = :analysis WHERE id = :id");
                                $stmtUpdate->bindValue(':score', $aiScoreUpdate, PDO::PARAM_NULL);
                                $stmtUpdate->bindParam(':analysis', $aiAnalysisUpdate, PDO::PARAM_STR);
                                $stmtUpdate->bindParam(':id', $applicationId, PDO::PARAM_INT);
                                $stmtUpdate->execute();
                                log_app_event($applicationId, "DB updated: Marked as unable to analyze (missing resume URL).");
                            } catch (PDOException $e) {
                                log_app_event($applicationId, "Recalculate DB Error (Updating for Missing Resume URL): " . $e->getMessage());
                            }
                        } else {
                            // Have app data and resume URL, proceed with parsing
                            $jobDescription = $appData['job_description'] ?? 'Job description not found.'; // Store for later
                            
                            // 2. Construct PDF Path
                            $resumeUrl = $appData['resume_url']; // e.g., /backend/uploads/resume_...pdf
                            $resumeBaseName = basename($resumeUrl); 
                            // Check if path starts with expected prefix, adjust if needed
                            // ASSUMPTION: URL is like '/backend/uploads/filename.pdf'
                            if (strpos($resumeUrl, '/backend/uploads/') === 0) {
                                 // Path relative to *this* script's directory (__DIR__)
                                $pdfPath = __DIR__ . '/uploads/' . $resumeBaseName; 
                            } else {
                                // Handle potentially different URL structure or log an error
                                log_app_event($applicationId, "Recalculate Error: Unexpected resume_url format: $resumeUrl. Cannot determine file path.");
                                $pdfPath = null; // Prevent further processing
                            }


                            if ($pdfPath && !file_exists($pdfPath)) {
                                log_app_event($applicationId, "Recalculate Error: PDF file not found at expected path: $pdfPath (derived from URL: $resumeUrl)");
                                // Update DB status/analysis to indicate PDF missing on disk
                                $aiAnalysisUpdate = "AI analysis cannot be performed: Resume file not found on server.";
                                $aiScoreUpdate = null;
                                try {
                                    $stmtUpdate = $conn->prepare("UPDATE red_applications SET ai_score = :score, ai_analysis = :analysis WHERE id = :id");
                                    $stmtUpdate->bindValue(':score', $aiScoreUpdate, PDO::PARAM_NULL);
                                    $stmtUpdate->bindParam(':analysis', $aiAnalysisUpdate, PDO::PARAM_STR);
                                    $stmtUpdate->bindParam(':id', $applicationId, PDO::PARAM_INT);
                                    $stmtUpdate->execute();
                                    log_app_event($applicationId, "DB updated: Marked as unable to analyze (file not found).");
                                } catch (PDOException $db_e) {
                                    log_app_event($applicationId, "Recalculate DB Error (Updating for PDF Parse Error): " . $db_e->getMessage());
                                }
                            } elseif ($pdfPath) {
                                // File path seems valid and file exists, attempt parsing
                                log_app_event($applicationId, "Recalculate: Attempting to parse PDF at: $pdfPath");
                                // 3. Parse PDF
                                try {
                                    $parser = new Parser();
                                    $pdf = $parser->parseFile($pdfPath);
                                    $resumeText = $pdf->getText(); // Assign to the variable declared earlier
                                    $resumeTextLength = $resumeText ? strlen($resumeText) : 0;
                                    log_app_event($applicationId, "Recalculate: Successfully parsed PDF. Text length: $resumeTextLength");

                                    // --- PARSING SUCCESSFUL - AI CALL AND DB UPDATE WILL GO HERE LATER ---
                                    $aiScore = null; 
                                    $aiAnalysis = "AI Analysis Pending - Recalculation Triggered"; 

                                    // --- Combine Applicant Data (Resume + Form Fields) --- 
                                    log_app_event($applicationId, "Recalculate: Combining resume text with other application fields.");
                                    
                                    $combinedApplicantData = "--- Extracted Resume Text ---\n" . ($resumeText ?? '[Resume text not available]') . "\n\n";
                                    
                                    $combinedApplicantData .= "--- Submitted Application Form Data ---";
                                    $combinedApplicantData .= "\nCover Letter: " . ($appData['cover_letter'] ?? 'N/A');
                                    $combinedApplicantData .= "\nSkills: " . ($appData['skills'] ?? 'N/A');
                                    $combinedApplicantData .= "\nCurrent Employer: " . ($appData['current_employer'] ?? 'N/A');
                                    $combinedApplicantData .= "\nCurrent Job Title: " . ($appData['current_job_title'] ?? 'N/A');
                                    $combinedApplicantData .= "\nYears of Experience: " . ($appData['years_of_experience'] ?? 'N/A');
                                    $combinedApplicantData .= "\nHighest Education: " . ($appData['highest_education'] ?? 'N/A');
                                    $combinedApplicantData .= "\nCertifications: " . ($appData['certifications'] ?? 'N/A');
                                    $combinedApplicantData .= "\nLanguages: " . ($appData['languages'] ?? 'N/A');
                                    $combinedApplicantData .= "\nAvailable Start Date: " . ($appData['available_start_date'] ?? 'N/A');
                                    $combinedApplicantData .= "\nSalary Expectations: " . ($appData['salary_expectations'] ?? 'N/A');
                                    $combinedApplicantData .= "\nWilling to Relocate: " . (isset($appData['willing_to_relocate']) ? ($appData['willing_to_relocate'] ? 'Yes' : 'No') : 'N/A');
                                    $combinedApplicantData .= "\nLegally Authorized to Work: " . (isset($appData['legally_authorized_to_work']) ? ($appData['legally_authorized_to_work'] ? 'Yes' : 'No') : 'N/A');
                                    $combinedApplicantData .= "\nRequire Sponsorship: " . (isset($appData['require_sponsorship']) ? ($appData['require_sponsorship'] ? 'Yes' : 'No') : 'N/A');

                                    // Decode and append Work Experience JSON
                                    $workExperienceText = 'N/A';
                                    if (!empty($appData['work_experience'])) {
                                        $workExperienceArray = json_decode($appData['work_experience'], true);
                                        if (json_last_error() === 0 && is_array($workExperienceArray)) {
                                            $workExperienceText = "\n"; // Start on new line
                                            foreach ($workExperienceArray as $index => $job) {
                                                $workExperienceText .= "  Job " . ($index + 1) . ":\n";
                                                $workExperienceText .= "    Title: " . ($job['jobTitle'] ?? 'N/A') . "\n";
                                                $workExperienceText .= "    Company: " . ($job['company'] ?? 'N/A') . "\n";
                                                $workExperienceText .= "    Location: " . ($job['location'] ?? 'N/A') . "\n";
                                                $workExperienceText .= "    Start Date: " . ($job['startDate'] ?? 'N/A') . "\n";
                                                $workExperienceText .= "    End Date: " . ($job['endDate'] ?? 'N/A') . "\n";
                                                $workExperienceText .= "    Responsibilities: " . ($job['responsibilities'] ?? 'N/A') . "\n";
                                            }
                                        } else {
                                            $workExperienceText = '[Could not decode work experience data]';
                                        }
                                    }
                                    $combinedApplicantData .= "\n\nWork Experience History:" . $workExperienceText;

                                    // Decode and append Education Details JSON
                                    $educationDetailsText = 'N/A';
                                    if (!empty($appData['education_details'])) {
                                        $educationDetailsArray = json_decode($appData['education_details'], true);
                                        if (json_last_error() === 0 && is_array($educationDetailsArray)) {
                                             $educationDetailsText = "\n"; // Start on new line
                                            foreach ($educationDetailsArray as $index => $edu) {
                                                $educationDetailsText .= "  Entry " . ($index + 1) . ":\n";
                                                $educationDetailsText .= "    Institution: " . ($edu['institution'] ?? 'N/A') . "\n";
                                                $educationDetailsText .= "    Degree: " . ($edu['degree'] ?? 'N/A') . "\n";
                                                $educationDetailsText .= "    Field of Study: " . ($edu['fieldOfStudy'] ?? 'N/A') . "\n";
                                                $educationDetailsText .= "    Start Date: " . ($edu['startDate'] ?? 'N/A') . "\n";
                                                $educationDetailsText .= "    End Date: " . ($edu['endDate'] ?? 'N/A') . "\n";
                                                $educationDetailsText .= "    Notes: " . ($edu['notes'] ?? 'N/A') . "\n";
                                            }
                                        } else {
                                            $educationDetailsText = '[Could not decode education details data]';
                                        }
                                    }
                                    $combinedApplicantData .= "\n\nEducation History Details:" . $educationDetailsText;

                                    $combinedDataLength = strlen($combinedApplicantData);
                                    log_app_event($applicationId, "Recalculate: Combined applicant data created. Total length: $combinedDataLength chars.");
                                    // Optional: Log a snippet for verification (careful with PII)
                                    // log_app_event($applicationId, "Recalculate: Combined Data Snippet: " . substr($combinedApplicantData, 0, 200));

                                    // --- Call AI Helper --- 
                                    log_app_event($applicationId, "Recalculate: Calling get_ai_application_analysis.");
                                    
                                    // --- ADD LOGGING FOR AI INPUT ---
                                    log_app_event($applicationId, "Recalculate AI Input - Job Description: " . ($jobDescription ?? '[Not Available]'));
                                    // Log combined data - might be long!
                                    log_app_event($applicationId, "Recalculate AI Input - Combined Applicant Data (Includes Resume):\n" . ($combinedApplicantData ?? '[Not Available]')); 
                                    // --- END LOGGING FOR AI INPUT ---
                                    
                                    $aiResult = null; // Initialize
                                    try {
                                        // Ensure required inputs are not empty/null before calling
                                        if (!empty($jobDescription) && !empty($combinedApplicantData)) {
                                             $aiResult = get_ai_application_analysis($jobDescription, $combinedApplicantData); 
                                             if ($aiResult !== null && isset($aiResult['score']) && isset($aiResult['justification'])) {
                                                 log_app_event($applicationId, "Recalculate: AI analysis successful. Score received.");
                                                 // Temporarily assign results to placeholders for the next step
                                                 $aiScore = $aiResult['score'];
                                                 $aiAnalysis = $aiResult['justification'];
                                             } else {
                                                 // AI Helper returned null or unexpected format
                                                 log_app_event($applicationId, "Recalculate Warning: AI analysis function returned null or unexpected format.");
                                                 // Keep placeholders or set specific error message
                                                 $aiAnalysis = "AI analysis call completed but returned no valid results.";
                                                 $aiScore = null; 
                        }
                    } else {
                                             log_app_event($applicationId, "Recalculate Error: Missing Job Description or Combined Applicant Data for AI call.");
                                             $aiAnalysis = "AI analysis could not be performed due to missing input data (job description or applicant info).";
                                             $aiScore = null;
                                        }

                                    } catch (\Exception $e) {
                                        // Catch any unexpected exceptions from the AI helper call itself
                                        log_app_event($applicationId, "Recalculate Error: Exception during AI analysis call: " . $e->getMessage());
                                        $aiAnalysis = "AI analysis failed due to an unexpected error during the API call.";
                                        $aiScore = null;
                                    }
                                    
                                    // Values for $aiScore and $aiAnalysis are now set based on AI call outcome

                                    // --- Update Database with actual AI results --- 
                                    log_app_event($applicationId, "Recalculate: Preparing to update DB. Score: " . ($aiScore ?? 'NULL') . ", Analysis: " . substr($aiAnalysis, 0, 100) . "...");
                                    
                                    try {
                                        $stmtUpdate = $conn->prepare("UPDATE red_applications SET ai_score = :score, ai_analysis = :analysis WHERE id = :id");
                                        
                                        // Bind score (handle null correctly)
                                        if ($aiScore === null) {
                                            $stmtUpdate->bindValue(':score', null, PDO::PARAM_NULL);
                                        } else {
                                            // Ensure score is treated as an integer
                                            $stmtUpdate->bindValue(':score', (int)$aiScore, PDO::PARAM_INT);
                                        }
                                        
                                        // Bind analysis (should always be a string)
                                        $stmtUpdate->bindParam(':analysis', $aiAnalysis, PDO::PARAM_STR);
                                        
                                        // Bind application ID
                                        $stmtUpdate->bindParam(':id', $applicationId, PDO::PARAM_INT);
                                        
                                        $updateSuccess = $stmtUpdate->execute();
                                        
                        if ($updateSuccess) {
                                            log_app_event($applicationId, "Recalculate: DB updated successfully with AI results.");
                        } else {
                                            // This case might be rare with PDO exceptions enabled, but log just in case
                                            log_app_event($applicationId, "Recalculate DB Error: PDO execute() returned false but did not throw an exception.");
                                        }
                                        
                                    } catch (PDOException $e) {
                                        log_app_event($applicationId, "Recalculate DB Error (Updating with AI Results): " . $e->getMessage());
                                        // If DB update fails, we might want to reflect this in the response,
                                        // but for now, we just log it. The subsequent fetch will get the old data.
                                    }

                                    // --- ADD Recalculation for Extracted Data --- 
                                    try {
                                        log_app_event($applicationId, "Recalculate: Attempting AI data extraction...");
                                        // Reuse $jobDescription and $combinedApplicantData which should be available here
                                         if (!empty($jobDescription) && !empty($combinedApplicantData)) {
                                            $extractedData = get_ai_extracted_data($jobDescription, $combinedApplicantData);
                                            if ($extractedData !== null && is_array($extractedData)) {
                                                log_app_event($applicationId, "Recalculate Extraction: Successfully received structured data.");
                                                try {
                                                    $extractedDataJson = json_encode($extractedData);
                                                    if (json_last_error() !== JSON_ERROR_NONE) {
                                                        // Log the error but maybe don't throw an exception that stops everything?
                                                        // Or maybe clear the column if encoding fails? For now, just log.
                                                        log_app_event($applicationId, "Recalculate: Failed to encode extracted data to JSON: " . json_last_error_msg());
                                                        // Optionally set $extractedDataJson to null or an empty JSON object '{}'
                                                        // $extractedDataJson = null; 
                                                    }
                                                    
                                                    // Only attempt update if encoding was successful (or handle null case)
                                                    if ($extractedDataJson !== false) { // json_encode returns false on failure
                                                        $stmtUpdateExtract = $conn->prepare("UPDATE red_applications SET ai_extracted_data = :data WHERE id = :id");
                                                        $stmtUpdateExtract->bindParam(':data', $extractedDataJson, PDO::PARAM_STR); // Store as JSON string or potentially NULL if encoding failed and we set it to null
                                                        $stmtUpdateExtract->bindParam(':id', $applicationId, PDO::PARAM_INT);
                                                        $updateExtractSuccess = $stmtUpdateExtract->execute();

                                                        if ($updateExtractSuccess) {
                                                            log_app_event($applicationId, "Recalculate Extraction: DB updated successfully.");
                                                        } else {
                                                            log_app_event($applicationId, "Recalculate Extraction DB Error: Update execute() returned false.");
                                                        }
                                                    } else {
                                                         log_app_event($applicationId, "Recalculate Extraction DB Error: Skipping DB update due to JSON encoding failure.");
                                                    }
                                                } catch (PDOException $e) { // Catch DB errors specifically
                                                     log_app_event($applicationId, "Recalculate Extraction DB Error: " . $e->getMessage());
                                                } catch (\Exception $e) { // Catch other potential errors (like json encoding exception if we threw one)
                                                     log_app_event($applicationId, "Recalculate Extraction Error during DB update prep: " . $e->getMessage());
                                                }
                                            } else {
                                                log_app_event($applicationId, "Recalculate Extraction Warning: AI helper did not return valid data or returned null.");
                                                // Optionally: Clear the existing ai_extracted_data field if the AI fails?
                                                // $stmtClearExtract = $conn->prepare("UPDATE red_applications SET ai_extracted_data = NULL WHERE id = :id");
                                                // $stmtClearExtract->bindParam(':id', $applicationId, PDO::PARAM_INT);
                                                // $stmtClearExtract->execute();
                                            }
                                         } else {
                                             log_app_event($applicationId, "Recalculate Extraction Error: Missing Job Description or Combined Applicant Data for AI call.");
                                         }
                                    } catch (\Exception $e) {
                                        log_app_event($applicationId, "Recalculate Extraction Error (Outer Catch): Unexpected error during AI extraction: " . $e->getMessage());
                                        // Don't re-throw, allow process to continue
                                    }
                                    // --- END Recalculation for Extracted Data --- 

                                } catch (\Exception $e) { // Catch PDF parsing errors
                                    log_app_event($applicationId, "Recalculate Error: Failed to parse PDF file '$pdfPath': " . $e->getMessage());
                                    // Update DB status/analysis to indicate PDF parsing error
                                    $aiAnalysisUpdate = "AI analysis cannot be performed: Error parsing resume PDF.";
                                    $aiScoreUpdate = null;
                                    try {
                                        $stmtUpdate = $conn->prepare("UPDATE red_applications SET ai_score = :score, ai_analysis = :analysis WHERE id = :id");
                                        $stmtUpdate->bindValue(':score', $aiScoreUpdate, PDO::PARAM_NULL);
                                        $stmtUpdate->bindParam(':analysis', $aiAnalysisUpdate, PDO::PARAM_STR);
                                        $stmtUpdate->bindParam(':id', $applicationId, PDO::PARAM_INT);
                                        $stmtUpdate->execute();
                                        log_app_event($applicationId, "DB updated: Marked as unable to analyze (PDF parse error).");
                                    } catch (PDOException $db_e) {
                                        log_app_event($applicationId, "Recalculate DB Error (Updating for PDF Parse Error): " . $db_e->getMessage());
                                    }
                                }
                            } // End if ($pdfPath && file_exists)
                        }
                    } catch (PDOException $e) { // Catch errors fetching initial app data for recalculation
                         log_app_event($applicationId, "Recalculate DB Error (Initial Fetch for Recalc): " . $e->getMessage());
                         // Decide if we should bubble up an HTTP error or just log - logging for now
                    }
                    catch (Exception $e) { // Catch any other general errors during the recalculation process
                         log_app_event($applicationId, "Recalculate Error (General Exception): " . $e->getMessage());
                    }
                    // Recalculation attempt finished (successfully or with logged errors). 
                    // The script will now proceed to fetch and return the potentially updated application data.
                    log_app_event($applicationId, "Recalculation process completed.");
                }
                // End AI Recalculation Block

                // --- Fetch application data AGAIN (to get potentially updated ai_score/analysis) --- 
                try {
                    // Determine the correct query based on user role
                    if ($userRole === 'admin') {
                        // Admin can see any application
                        $sql = "SELECT a.*, u.username, u.email as user_email, j.title as job_title 
                                FROM red_applications a 
                                JOIN red_users u ON a.user_id = u.id 
                                JOIN red_jobs j ON a.job_id = j.id 
                                WHERE a.id = :id";
                        $stmt = $conn->prepare($sql);
                        $stmt->bindParam(':id', $applicationId, PDO::PARAM_INT);
                    } else {
                        // Regular user can only see their own application
                        $sql = "SELECT a.*, j.title as job_title 
                                FROM red_applications a 
                                JOIN red_jobs j ON a.job_id = j.id 
                                WHERE a.id = :id AND a.user_id = :user_id";
                        $stmt = $conn->prepare($sql);
                        $stmt->bindParam(':id', $applicationId, PDO::PARAM_INT);
                        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
                    }

                    $stmt->execute();
                    $application = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($application) {
                         // Decode JSON fields before sending
                         if (!empty($application['work_experience'])) {
                            $decodedWorkExp = json_decode($application['work_experience'], true);
                            // Check if decoding was successful, otherwise keep original or set to empty array
                            $application['work_experience'] = (json_last_error() === 0) ? $decodedWorkExp : []; // Use 0 instead of JSON_NO_ERROR
                         } else {
                            $application['work_experience'] = []; // Ensure it's an array if null/empty
                         }
                        if (!empty($application['education_details'])) {
                            $decodedEduDetails = json_decode($application['education_details'], true);
                            $application['education_details'] = (json_last_error() === 0) ? $decodedEduDetails : []; // Use 0 instead of JSON_NO_ERROR
                         } else {
                            $application['education_details'] = []; // Ensure it's an array if null/empty
                         }
                        
                        log_app_event($applicationId, "Successfully fetched details for User ID $userId after potential recalculation.");
                        echo json_encode($application);
                        exit;
                    } else {
                        // If application wasn't found *after* potential recalculation, it's still 404
                        http_response_code(404); // Not Found or Not Authorized
                        log_app_event($applicationId, "Application not found or user $userId not authorized (post-recalc check).");
                        echo json_encode(['error' => 'Application not found or access denied']);
                        exit; // Exit here if the specific ID was not found
                    }
                } catch (PDOException $e) {
                    http_response_code(500);
                    log_app_event($applicationId, "Database error fetching final application details: " . $e->getMessage());
                    echo json_encode(['error' => 'Database error retrieving application details', 'details' => $e->getMessage()]);
                    exit;
                }
            } else {
                // Application with the specified ID was not found or not authorized
                http_response_code(404); // Not Found or Not Authorized
                log_app_event($applicationId, "Application not found or user $userId not authorized (inside if(\$application) else block).");
                echo json_encode(['error' => 'Application not found or access denied']);
                exit; // Exit here if the specific ID was not found
            }

        } else { // <<<<<<< This is the correct block for the LIST VIEW (no $applicationId)
            // Log access to the list view
            log_app_event('list_view', "Fetching application list. User ID: $userId, Role: $userRole");

            // --- Get applications (filtered for user or all for admin) --- 
            if ($userRole === 'admin') {
                // Base query for admin list view
                $sqlBase = "SELECT a.id, a.user_id, a.job_id, a.status, a.created_at, 
                               a.ai_score, a.ai_extracted_data,
                               CONCAT(u.first_name, ' ', u.last_name) as applicant_name, 
                               j.title as job_title, 
                               a.years_of_experience, a.highest_education, 
                               a.willing_to_relocate, a.require_sponsorship 
                        FROM red_applications a 
                        JOIN red_users u ON a.user_id = u.id 
                        JOIN red_jobs j ON a.job_id = j.id";
                
                $whereClauses = [];
                $params = [];

                // --- NEW FILTERS --- 
                // Filter: Applicant Name (Text Search)
                if (isset($_GET['name']) && !empty(trim($_GET['name']))) {
                    $whereClauses[] = "CONCAT(u.first_name, ' ', u.last_name) LIKE :name";
                    $params[':name'] = '%' . trim($_GET['name']) . '%';
                }

                // Filter: Job Title (Text Search)
                if (isset($_GET['jobTitle']) && !empty(trim($_GET['jobTitle']))) {
                    $whereClauses[] = "j.title LIKE :jobTitle";
                    $params[':jobTitle'] = '%' . trim($_GET['jobTitle']) . '%';
                }

                // Filter: Status (Exact Match)
                if (isset($_GET['status']) && !empty($_GET['status'])) {
                    $allowedStatuses = ['pending', 'reviewed', 'accepted', 'rejected']; // Reuse validation if needed
                    if (in_array($_GET['status'], $allowedStatuses)) {
                        $whereClauses[] = "a.status = :status";
                        $params[':status'] = $_GET['status'];
                    }
                }

                // Filter: Apply Date Range
                if (isset($_GET['applyDateStart']) && !empty($_GET['applyDateStart'])) {
                    // Basic validation - ideally use proper date validation
                    $dateStart = date('Y-m-d', strtotime($_GET['applyDateStart']));
                    if ($dateStart) {
                        $whereClauses[] = "DATE(a.created_at) >= :applyDateStart";
                        $params[':applyDateStart'] = $dateStart;
                    }
                }
                 if (isset($_GET['applyDateEnd']) && !empty($_GET['applyDateEnd'])) {
                    // Basic validation - ideally use proper date validation
                    $dateEnd = date('Y-m-d', strtotime($_GET['applyDateEnd']));
                    if ($dateEnd) {
                        $whereClauses[] = "DATE(a.created_at) <= :applyDateEnd";
                        $params[':applyDateEnd'] = $dateEnd;
                    }
                }
                // --- END NEW FILTERS --- 

                // Filter: Years of Experience (Range)
                if (isset($_GET['minExp']) && is_numeric($_GET['minExp'])) {
                    $whereClauses[] = "a.years_of_experience >= :minExp";
                    $params[':minExp'] = (int)$_GET['minExp'];
                }
                if (isset($_GET['maxExp']) && is_numeric($_GET['maxExp'])) {
                    $whereClauses[] = "a.years_of_experience <= :maxExp";
                    $params[':maxExp'] = (int)$_GET['maxExp'];
                }

                // Filter: Education Level (Exact Match)
                if (isset($_GET['education']) && !empty($_GET['education'])) {
                    // Use the raw value from GET, assuming it matches DB values ('bachelors', 'high-school', etc.)
                    $educationLevel = trim($_GET['education']); 
                    if (!empty($educationLevel)) {
                        $whereClauses[] = "a.highest_education = :education";
                        $params[':education'] = $educationLevel;
                    }
                }
                
                // Filter: Willing to Relocate (Boolean)
                if (isset($_GET['relocate']) && filter_var($_GET['relocate'], FILTER_VALIDATE_BOOLEAN)) {
                   $whereClauses[] = "a.willing_to_relocate = 1"; 
                }
                
                 // Filter: Requires Sponsorship (Boolean)
                 if (isset($_GET['sponsorship']) && filter_var($_GET['sponsorship'], FILTER_VALIDATE_BOOLEAN)) {
                   $whereClauses[] = "a.require_sponsorship = 1"; 
                 }

                // Filter: Minimum AI Score
                if (isset($_GET['minScore']) && is_numeric($_GET['minScore'])) {
                    $minScoreValue = (int)$_GET['minScore'];
                    // Only add filter if score is 0 or positive
                    if ($minScoreValue >= 0) {
                        $whereClauses[] = "a.ai_score >= :minScore";
                        $params[':minScore'] = $minScoreValue;
                        // Also ensure we don't include unscored applications 
                        $whereClauses[] = "a.ai_score IS NOT NULL";
                    }
                }
                
                // Construct the final SQL query
                $sql = $sqlBase;
                if (!empty($whereClauses)) {
                    $sql .= " WHERE " . implode(" AND ", $whereClauses);
                }
                // $sql .= " ORDER BY a.created_at DESC"; // Remove fixed sort

                // --- ADD SORTING --- 
                $allowedSortFields = [
                    'created_at' => 'a.created_at',
                    'ai_score' => 'a.ai_score',
                    'applicant_name' => 'applicant_name', // Use alias
                    'job_title' => 'job_title',
                    'status' => 'a.status'
                    // Add other sortable columns here (map public name to DB column/alias)
                ];
                $sortFieldInput = $_GET['sortField'] ?? 'created_at';
                $sortDirectionInput = $_GET['sortDirection'] ?? 'DESC';
                
                // Validate sort field
                $sortColumn = $allowedSortFields[$sortFieldInput] ?? $allowedSortFields['created_at']; // Default to created_at
                
                // Validate sort direction
                $sortDirection = strtoupper($sortDirectionInput) === 'ASC' ? 'ASC' : 'DESC'; // Default to DESC
                
                $sql .= " ORDER BY $sortColumn $sortDirection";
                // Add secondary sort if needed, e.g., ORDER BY primary DESC, secondary ASC
                if ($sortFieldInput !== 'created_at') { // Add creation date as secondary sort for consistency
                    $sql .= ", a.created_at DESC";
                }
                // --- END SORTING --- 

                log_app_event('list_view', "Admin Query: $sql");
                log_app_event('list_view', "Admin Params: " . json_encode($params));

                $stmt = $conn->prepare($sql);
                // Bind parameters
                foreach ($params as $key => $value) {
                    $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
                    $stmt->bindValue($key, $value, $type);
                }

            } else { // Non-admin user list view (no filters applied here for now)
                 $sql = "SELECT a.id, a.job_id, a.status, a.created_at, j.title as job_title
                        FROM red_applications a
                        JOIN red_jobs j ON a.job_id = j.id
                        WHERE a.user_id = :user_id
                        ORDER BY a.created_at DESC";
                 $stmt = $conn->prepare($sql);
                 $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            }

            $stmt->execute();
            $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // --- ADD PHP-Based Filtering on ai_extracted_data --- 
            $skillFilter = isset($_GET['skill']) ? trim(strtolower($_GET['skill'])) : null;
            $locationFilter = isset($_GET['locationKeyword']) ? trim(strtolower($_GET['locationKeyword'])) : null;
            $minTotalYearsFilter = isset($_GET['minTotalYears']) && is_numeric($_GET['minTotalYears']) ? (int)$_GET['minTotalYears'] : null;
            $highestDegreeFilter = isset($_GET['highestDegree']) ? trim(strtolower($_GET['highestDegree'])) : null;

            if ($skillFilter || $locationFilter || $minTotalYearsFilter !== null || $highestDegreeFilter) {
                 log_app_event('list_view', "Applying PHP filters on AI extracted data...");
                 $filteredApplications = [];
                 foreach ($applications as $app) {
                    if (empty($app['ai_extracted_data'])) {
                        continue; // Skip if no extracted data
                    }
                    
                    $extractedData = json_decode($app['ai_extracted_data'], true);
                    if (!$extractedData || json_last_error() !== JSON_ERROR_NONE) {
                        log_app_event($app['id'], "PHP Filter Warning: Could not decode ai_extracted_data JSON.");
                        continue; // Skip if JSON is invalid
                    }

                    $match = true; // Assume match initially

                    // Apply Skill Filter
                    if ($skillFilter) {
                        $skillFound = false;
                        $skillCategories = ['programming', 'software', 'technical', 'soft_skills', 'languages', 'other'];
                        foreach ($skillCategories as $category) {
                            if (isset($extractedData['skills'][$category]) && is_array($extractedData['skills'][$category])) {
                                foreach ($extractedData['skills'][$category] as $skill) {
                                    if (stripos($skill, $skillFilter) !== false) { // Case-insensitive check
                                        $skillFound = true;
                                        break 2; // Found in this category, break outer loop
                                    }
                                }
                            }
                        }
                        if (!$skillFound) $match = false;
                    }

                    // Apply Location Keyword Filter
                    if ($match && $locationFilter) {
                        $locationFound = false;
                        if (isset($extractedData['contact']['location_keywords']) && is_array($extractedData['contact']['location_keywords'])) {
                            foreach ($extractedData['contact']['location_keywords'] as $loc) {
                                if (stripos($loc, $locationFilter) !== false) { // Case-insensitive check
                                    $locationFound = true;
                                    break;
                                }
                            }
                        }
                         if (!$locationFound) $match = false;
                    }
                    
                    // Apply Min Total Years Filter
                    if ($match && $minTotalYearsFilter !== null) {
                        $totalYears = $extractedData['experience']['total_years_approx'] ?? null;
                        if ($totalYears === null || (int)$totalYears < $minTotalYearsFilter) {
                            $match = false;
                        }
                    }
                    
                    // Apply Highest Degree Filter
                    if ($match && $highestDegreeFilter) {
                        $degreeGuess = isset($extractedData['education']['highest_level_guess']) ? strtolower($extractedData['education']['highest_level_guess']) : null;
                        if ($degreeGuess !== $highestDegreeFilter) {
                             $match = false;
                        }
                    }

                    // If all filters passed, add to result
                    if ($match) {
                        $filteredApplications[] = $app;
                    }
                 }
                 $applications = $filteredApplications; // Replace original array with filtered one
                 log_app_event('list_view', "PHP filtering completed. Result count: " . count($applications));
            }
            // --- END PHP-Based Filtering --- 

            // ---> ADD LOGGING HERE TO SEE THE RESULT <--- 
            log_app_event('list_view', "Final applications result count (after SQL + PHP filters): " . count($applications));
            log_app_event('list_view', "Filtered applications data: " . json_encode($applications)); // Log the actual data
            echo json_encode(['applications' => $applications]);
            exit; // Exit after handling list view
        }

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
    finally {
        $conn = null;
    }

// Handle PUT request for updating application status (Admin only)
} elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $tokenPayload = authenticate_and_get_user();
    // Correctly access nested data
    $userId = $tokenPayload->data->userId ?? null;
    $userRole = $tokenPayload->data->role ?? 'user'; 

    // Check access based on correctly retrieved role
    if ($userRole !== 'admin') {
        http_response_code(403);
         echo json_encode(['error' => 'Permission denied. Admin access required.']);
        exit;
    }
    
    // Log admin action
    error_log("[Apply PUT] Admin User ID: $userId attempting to update application status.");

    // Get application ID from query string
    $applicationId = isset($_GET['id']) ? (int)$_GET['id'] : null;
    if (!$applicationId) {
        http_response_code(400);
        echo json_encode(['error' => 'Application ID is required in the URL query string (e.g., ?id=123)']);
        exit;
    }

    // Get new status from request body
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE || !isset($data['status'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid input. ' . json_last_error_msg()]);
        exit;
    }

    $newStatus = $data['status'];
    $allowedStatuses = ['pending', 'reviewed', 'accepted', 'rejected'];
    if (!in_array($newStatus, $allowedStatuses)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid status value.']);
        exit;
    }

    $conn = get_db_connection();
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection error']);
        exit;
    }

    try {
        $sql = "UPDATE red_applications SET status = :status WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':status', $newStatus, PDO::PARAM_STR);
        $stmt->bindParam(':id', $applicationId, PDO::PARAM_INT);

        if ($stmt->execute()) {
            // Optionally fetch and return the updated application
            echo json_encode(['message' => 'Application status updated successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update application status']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        error_log("Database error updating application status: " . $e->getMessage());
        echo json_encode(['error' => 'Database error updating application status']);
    } finally {
        $conn = null;
    }

// Handle unsupported methods
} else {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Method not allowed']);
}

?>