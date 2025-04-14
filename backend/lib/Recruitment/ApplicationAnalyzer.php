<?php

namespace RecruiterLib\Recruitment;

use RecruiterLib\AI\GeminiClient;
use RecruiterLib\AI\PromptFactory;
use RecruiterLib\AI\ResponseParser;
use RecruiterLib\Util\PdfParser;
use RecruiterLib\Util\DataCombiner;
use RecruiterLib\AI\AICommsException;
use RecruiterLib\AI\AIResponseException;
use RecruiterLib\Util\ParsingException;
use PDO;
use PDOException;

/**
 * Orchestrates the AI analysis of job applications.
 */
class ApplicationAnalyzer {
    private PDO $db;
    private GeminiClient $aiClient;
    private PromptFactory $promptFactory;
    private ResponseParser $responseParser;
    private PdfParser $pdfParser;
    private DataCombiner $dataCombiner;

    public function __construct(
        PDO $db,
        ?GeminiClient $aiClient = null,
        ?PromptFactory $promptFactory = null,
        ?ResponseParser $responseParser = null,
        ?PdfParser $pdfParser = null,
        ?DataCombiner $dataCombiner = null
    ) {
        $this->db = $db;
        
        // Allow dependency injection for testing, otherwise create defaults
        try {
            $this->aiClient = $aiClient ?: new GeminiClient(); 
            $this->promptFactory = $promptFactory ?: new PromptFactory();
            $this->responseParser = $responseParser ?: new ResponseParser();
            $this->pdfParser = $pdfParser ?: new PdfParser();
            $this->dataCombiner = $dataCombiner ?: new DataCombiner();
            error_log("[Analyzer Constructor] Dependencies instantiated successfully."); 
        } catch (\Throwable $t) {
             error_log("[Analyzer Constructor] FATAL: Error instantiating dependencies: " . $t->getMessage());
             // Re-throw to let the calling script handle it
             throw new \Exception("Failed to instantiate dependencies for ApplicationAnalyzer: " . $t->getMessage(), 0, $t);
        }
    }

    /**
     * Performs the full AI analysis (scoring, justification, explanation, key factors, extraction) 
     * for a given application ID.
     * Fetches required data, interacts with AI, and updates the database.
     *
     * @param int $applicationId The ID of the application to analyze.
     * @return array An array containing the results including score, analysis, explanation, key_factors, extracted_data, and error.
     */
    public function analyzeApplication(int $applicationId): array {
        $results = [
            PromptFactory::KEY_SCORE => null,
            PromptFactory::KEY_JUSTIFICATION => null,
            PromptFactory::KEY_EXPLANATION => null,
            PromptFactory::KEY_FACTORS => null,
            'extracted_data' => null,
            'error' => null
        ];

        try {
            log_app_event($applicationId, "[Analyzer] Starting analysis...");

            // 1. Fetch Application and Job Data
            $appData = $this->fetchApplicationAndJobData($applicationId);
            if (!$appData) {
                $results['error'] = "Application or job data not found.";
                log_app_event($applicationId, "[Analyzer] Error: Application or job data not found.");
                return $results;
            }
            $jobDescription = $appData['job_description'] ?? '';

            // 2. Parse Resume PDF
            $resumeText = $this->getResumeText($applicationId, $appData['resume_url']);
            if ($resumeText === false) { // Error during parsing or file not found
                 $errorMsg = "AI analysis cannot be performed: Error processing resume file."; 
                 $results[PromptFactory::KEY_JUSTIFICATION] = $errorMsg; // Use justification field for primary error
                 $this->updateApplicationAIFields($applicationId, null, $errorMsg, null, null, null); // Update DB with error
                 $results['error'] = $errorMsg;
                 return $results;
            }

            // 3. Combine Applicant Data
            $combinedApplicantData = $this->dataCombiner->combineApplicantData($resumeText, $appData);
            if (empty(trim($combinedApplicantData))) {
                 $results['error'] = "Failed to combine applicant data or data is empty.";
                 log_app_event($applicationId, "[Analyzer] Error: Combined applicant data is empty.");
                 return $results;
            }

            // 4. Perform AI Scoring, Analysis, Explanation, Key Factors
             if (!empty($jobDescription)) {
                try {
                    log_app_event($applicationId, "[Analyzer] Requesting AI analysis (score, justification, explanation, factors)...");
                    $analysisPrompt = $this->promptFactory->createAnalysisPrompt($jobDescription, $combinedApplicantData);
                    $analysisApiResponse = $this->aiClient->generateContent($analysisPrompt); 
                    $analysisParsed = $this->responseParser->parseAnalysisResponse($analysisApiResponse);
                    
                    // Assign all parsed fields to results
                    $results[PromptFactory::KEY_SCORE] = $analysisParsed[PromptFactory::KEY_SCORE];
                    $results[PromptFactory::KEY_JUSTIFICATION] = $analysisParsed[PromptFactory::KEY_JUSTIFICATION];
                    $results[PromptFactory::KEY_EXPLANATION] = $analysisParsed[PromptFactory::KEY_EXPLANATION];
                    $results[PromptFactory::KEY_FACTORS] = $analysisParsed[PromptFactory::KEY_FACTORS];
                    
                    log_app_event($applicationId, "[Analyzer] AI analysis successful. Score: {$results[PromptFactory::KEY_SCORE]}");

                } catch (AICommsException | AIResponseException $e) {
                    log_app_event($applicationId, "[Analyzer] Error during AI analysis call: " . $e->getMessage());
                    $errorMsg = "AI analysis failed: " . $e->getMessage();
                    $results[PromptFactory::KEY_JUSTIFICATION] = $errorMsg; // Use justification for main error message
                    $results['error'] = $errorMsg;
                } catch (\Exception $e) { // Catch unexpected errors
                    log_app_event($applicationId, "[Analyzer] Unexpected error during AI analysis: " . $e->getMessage());
                    $errorMsg = "AI analysis failed due to an unexpected error.";
                    $results[PromptFactory::KEY_JUSTIFICATION] = $errorMsg;
                    $results['error'] = $errorMsg;
                }
            } else {
                 log_app_event($applicationId, "[Analyzer] Skipping AI analysis: Job description is empty.");
                 $results[PromptFactory::KEY_JUSTIFICATION] = "AI analysis skipped: Job description missing.";
            }

            // 5. Perform AI Data Extraction
            if (!empty($jobDescription)) {
                try {
                    log_app_event($applicationId, "[Analyzer] Requesting AI data extraction...");
                    $extractionPrompt = $this->promptFactory->createExtractionPrompt($jobDescription, $combinedApplicantData);
                    $extractionApiResponse = $this->aiClient->generateContent($extractionPrompt, 'application/json', 0.1);
                    $extractionParsed = $this->responseParser->parseExtractionResponse($extractionApiResponse);
                    $results['extracted_data'] = $extractionParsed;
                    log_app_event($applicationId, "[Analyzer] AI data extraction successful.");
                } catch (AICommsException | AIResponseException $e) {
                    log_app_event($applicationId, "[Analyzer] Error during AI extraction call: " . $e->getMessage());
                    if ($results['error'] === null) { 
                       $results['error'] = "AI data extraction failed: " . $e->getMessage();
                    }
                } catch (\Exception $e) { 
                    log_app_event($applicationId, "[Analyzer] Unexpected error during AI extraction: " . $e->getMessage());
                     if ($results['error'] === null) {
                        $results['error'] = "AI data extraction failed due to an unexpected error.";
                    }
                }
            } else {
                log_app_event($applicationId, "[Analyzer] Skipping AI extraction: Job description is empty.");
            }

            // 6. Update Database with all results
            $this->updateApplicationAIFields(
                $applicationId,
                $results[PromptFactory::KEY_SCORE],
                $results[PromptFactory::KEY_JUSTIFICATION],
                $results[PromptFactory::KEY_EXPLANATION],
                $results[PromptFactory::KEY_FACTORS],
                $results['extracted_data']
            );
            
            log_app_event($applicationId, "[Analyzer] Analysis process completed.");

        } catch (PDOException $e) {
            log_app_event($applicationId, "[Analyzer] Database error during analysis process: " . $e->getMessage());
            $results['error'] = "Database error during analysis.";
        } catch (\Exception $e) {
            log_app_event($applicationId, "[Analyzer] General error during analysis process: " . $e->getMessage());
             $results['error'] = "An unexpected error occurred during analysis.";
        }

        return $results;
    }

    /**
     * Fetches the application data and associated job description.
     */
    private function fetchApplicationAndJobData(int $applicationId): ?array {
        try {
             // Fetch all needed fields from application and job
            $stmt = $this->db->prepare(
               "SELECT a.*, j.description as job_description, j.title as job_title 
                FROM applications a 
                JOIN jobs j ON a.job_id = j.id 
                WHERE a.id = :id LIMIT 1"
            );
            $stmt->bindParam(':id', $applicationId, PDO::PARAM_INT);
            $stmt->execute();
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            return $data ?: null;
        } catch (PDOException $e) {
             log_app_event($applicationId, "[Analyzer] DB Error fetching app/job data: " . $e->getMessage());
             return null;
        }
    }

    /**
     * Gets the resume text, handling path construction and parsing.
     * Returns false on error, null if no URL, string on success.
     */
    private function getResumeText(int $applicationId, ?string $resumeUrl): string|null|false {
        if (empty($resumeUrl)) {
            log_app_event($applicationId, "[Analyzer] Resume URL is missing. Cannot parse PDF.");
            return null; // No URL, not an error per se
        }

        $pdfPath = null;
        if (str_starts_with($resumeUrl, '/backend/uploads/')) { 
            // Go up THREE levels from backend/lib/Recruitment to get project root (/home/site/wwwroot)
            $baseDir = dirname(__DIR__, 3); 
            $relativePath = substr($resumeUrl, 1); // Remove leading /
            $pdfPath = $baseDir . '/' . $relativePath; 
            // Normalize path separators 
            $pdfPath = str_replace('/', DIRECTORY_SEPARATOR, $pdfPath);
             log_app_event($applicationId, "[Analyzer] Calculated PDF path: {$pdfPath}");
        } else {
            log_app_event($applicationId, "[Analyzer] Error: Unexpected resume_url format: {$resumeUrl}. Cannot determine file path.");
            return false; // Error state
        }

        if (!file_exists($pdfPath)) {
             log_app_event($applicationId, "[Analyzer] Error: PDF file not found at calculated path: {$pdfPath} (derived from URL: {$resumeUrl})");
             return false; // Error state
        }

        try {
            log_app_event($applicationId, "[Analyzer] Attempting to parse PDF at: {$pdfPath}");
            $resumeText = $this->pdfParser->parsePdf($pdfPath);
            $resumeTextLength = strlen($resumeText);
            log_app_event($applicationId, "[Analyzer] Successfully parsed PDF. Text length: {$resumeTextLength}");
            return $resumeText;
        } catch (ParsingException $e) {
            log_app_event($applicationId, "[Analyzer] Error parsing resume PDF: " . $e->getMessage());
            return false; // Error state
        }
    }

    /**
     * Updates the AI-related fields in the applications table.
     */
    private function updateApplicationAIFields(
        int $applicationId, 
        ?float $score, 
        ?string $justification,
        ?string $explanation,
        ?array $keyFactors,
        ?array $extractedData
    ): bool {
        $extractedDataJson = null;
        if ($extractedData !== null) {
            $extractedDataJson = json_encode($extractedData);
            if (json_last_error() !== JSON_ERROR_NONE) {
                 log_app_event($applicationId, "[Analyzer] DB Update Error: Failed to encode extracted_data to JSON: " . json_last_error_msg());
                 $extractedDataJson = null; 
            }
        }

        $keyFactorsJson = null;
        if ($keyFactors !== null) {
            $keyFactorsJson = json_encode($keyFactors);
            if (json_last_error() !== JSON_ERROR_NONE) {
                 log_app_event($applicationId, "[Analyzer] DB Update Error: Failed to encode key_factors to JSON: " . json_last_error_msg());
                 $keyFactorsJson = null; 
            }
        }

        try {
            $stmt = $this->db->prepare(
                "UPDATE applications SET 
                    ai_score = :score, 
                    ai_analysis = :justification,
                    ai_score_explanation = :explanation,
                    ai_key_factors = :key_factors,
                    ai_extracted_data = :extracted_data 
                 WHERE id = :id"
            );
            
            $stmt->bindValue(':score', $score, $score === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindParam(':justification', $justification, $justification === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindParam(':explanation', $explanation, $explanation === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindParam(':key_factors', $keyFactorsJson, $keyFactorsJson === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindParam(':extracted_data', $extractedDataJson, $extractedDataJson === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindParam(':id', $applicationId, PDO::PARAM_INT);
            
            $success = $stmt->execute();
            if ($success) {
                log_app_event($applicationId, "[Analyzer] DB updated successfully with AI results (including explanation/factors).");
            } else {
                log_app_event($applicationId, "[Analyzer] DB Update Error: execute() returned false.");
            }
            return $success;
        } catch (PDOException $e) {
             log_app_event($applicationId, "[Analyzer] DB Update Error: " . $e->getMessage());
             return false;
        }
    }

    /**
     * Generates and saves candidate feedback for a specific application.
     *
     * @param int $applicationId The ID of the application.
     * @param string|null $rejectionReason Optional reason from recruiter.
     * @return bool True on success, false on failure.
     */
    public function generateAndSaveCandidateFeedback(int $applicationId, ?string $rejectionReason = null): bool {
        log_app_event($applicationId, "[Analyzer] Starting candidate feedback generation...");
        $explanation = null;
        $counterfactuals = null;
        $success = false;

        try {
            // 1. Fetch Application and Job Data (needed for prompt)
            $appData = $this->fetchApplicationAndJobData($applicationId);
            if (!$appData) {
                log_app_event($applicationId, "[Analyzer Feedback] Error: Application or job data not found.");
                return false;
            }
            $jobDescription = $appData['job_description'] ?? '';

            // 2. Parse Resume PDF (needed for prompt)
            $resumeText = $this->getResumeText($applicationId, $appData['resume_url']);
            if ($resumeText === false) {
                 log_app_event($applicationId, "[Analyzer Feedback] Error processing resume file. Cannot generate feedback.");
                 return false;
            }

            // 3. Combine Applicant Data (needed for prompt)
            $combinedApplicantData = $this->dataCombiner->combineApplicantData($resumeText, $appData);
            if (empty(trim($combinedApplicantData))) {
                 log_app_event($applicationId, "[Analyzer Feedback] Error: Combined applicant data is empty. Cannot generate feedback.");
                 return false;
            }

            // 4. Generate Feedback using AI
            if (!empty($jobDescription)) {
                log_app_event($applicationId, "[Analyzer Feedback] Requesting AI feedback...");
                $feedbackPrompt = $this->promptFactory->createCandidateFeedbackPrompt($jobDescription, $combinedApplicantData, $rejectionReason);
                $feedbackApiResponse = $this->aiClient->generateContent($feedbackPrompt);
                $feedbackParsed = $this->responseParser->parseCandidateFeedbackResponse($feedbackApiResponse);

                $explanation = $feedbackParsed['candidate_explanation'];
                $counterfactuals = $feedbackParsed['counterfactuals'];
                log_app_event($applicationId, "[Analyzer Feedback] AI feedback generation successful.");
            } else {
                 log_app_event($applicationId, "[Analyzer Feedback] Skipping AI feedback generation: Job description missing.");
                 // Set default message or leave null?
                 $explanation = "Feedback could not be generated as job details were unavailable.";
                 $counterfactuals = [];
            }

            // 5. Update Database
            $counterfactualsJson = json_encode($counterfactuals);
            if (json_last_error() !== JSON_ERROR_NONE) {
                log_app_event($applicationId, "[Analyzer Feedback] Failed to encode counterfactuals to JSON: " . json_last_error_msg());
                $counterfactualsJson = '[]'; // Store empty array on encoding error
            }

            $stmt = $this->db->prepare(
                "UPDATE applications SET 
                    ai_candidate_explanation = :explanation, 
                    ai_counterfactuals = :counterfactuals 
                 WHERE id = :id"
            );
            $stmt->bindParam(':explanation', $explanation, $explanation === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindParam(':counterfactuals', $counterfactualsJson, PDO::PARAM_STR);
            $stmt->bindParam(':id', $applicationId, PDO::PARAM_INT);
            
            $success = $stmt->execute();
            if ($success) {
                log_app_event($applicationId, "[Analyzer Feedback] DB updated successfully with candidate feedback.");
            } else {
                 log_app_event($applicationId, "[Analyzer Feedback] DB Update Error: execute() returned false.");
            }

        } catch (AICommsException | AIResponseException | ParsingException $e) {
            log_app_event($applicationId, "[Analyzer Feedback] Error during AI feedback generation/parsing: " . $e->getMessage());
            // Optionally update DB with an error message
             $this->updateCandidateFeedbackError($applicationId, "Error generating feedback: " . $e->getMessage());
            $success = false;
        } catch (PDOException $e) {
            log_app_event($applicationId, "[Analyzer Feedback] Database error during feedback process: " . $e->getMessage());
            $success = false;
        } catch (\Exception $e) {
            log_app_event($applicationId, "[Analyzer Feedback] General error during feedback process: " . $e->getMessage());
             $this->updateCandidateFeedbackError($applicationId, "Unexpected error generating feedback.");
            $success = false;
        }

        return $success;
    }

    /** Helper to update feedback fields with an error message */
    private function updateCandidateFeedbackError(int $applicationId, string $errorMessage): void {
         try {
             $stmt = $this->db->prepare(
                 "UPDATE applications SET 
                     ai_candidate_explanation = :explanation, 
                     ai_counterfactuals = :counterfactuals 
                  WHERE id = :id"
             );
             $errorExplanation = "An error occurred while generating feedback: " . $errorMessage;
             $emptyJson = '[]';
             $stmt->bindParam(':explanation', $errorExplanation, PDO::PARAM_STR);
             $stmt->bindParam(':counterfactuals', $emptyJson, PDO::PARAM_STR);
             $stmt->bindParam(':id', $applicationId, PDO::PARAM_INT);
             $stmt->execute();
         } catch (PDOException $e) {
             log_app_event($applicationId, "[Analyzer Feedback] Failed to update DB with feedback error: " . $e->getMessage());
         }
    }

    // --- Future Methods (for Phases 1 & 2) ---
    // public function enhanceAnalysisWithExplanation(int $applicationId): array { ... }
    // public function generateCandidateFeedback(int $applicationId): array { ... }

}

// Helper function for logging (consider moving to a dedicated logging utility)
// This assumes the function exists globally as it was in applications.php
if (!function_exists('log_app_event')) {
    function log_app_event($appId, $message) {
        $safeAppId = is_numeric($appId) ? (int)$appId : 'general';
        error_log("[App ID {$safeAppId}] " . $message);
    }
} 