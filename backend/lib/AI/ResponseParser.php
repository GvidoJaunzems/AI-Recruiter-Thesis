<?php

namespace RecruiterLib\AI;

/**
 * Parses and validates responses received from the Google AI API.
 */
class ResponseParser {

    /**
     * Parses the response for application analysis (score, justification, explanation, key_factors).
     *
     * @param array $apiResponse The decoded JSON response array from GeminiClient.
     * @return array An array containing ['score' => float, 'justification' => string, 'explanation' => string, 'key_factors' => array].
     * @throws AIResponseException If the response format is invalid or missing required data.
     */
    public function parseAnalysisResponse(array $apiResponse): array {
        // Expected structure: response -> candidates -> [0] -> content -> parts -> [0] -> text (containing JSON)
        $generatedText = $apiResponse['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if ($generatedText === null || !is_string($generatedText)) {
            error_log("[ResponseParser] Failed to extract generated text from API analysis response. Structure invalid.");
            // Log the problematic response structure for debugging
            // error_log("[ResponseParser] Invalid Response Structure: " . json_encode($apiResponse));
            throw new AIResponseException('Invalid API response structure: Missing generated text.');
        }

        $jsonText = $this->cleanJsonString($generatedText);
        if (empty($jsonText)) {
            error_log("[ResponseParser] Cleaned JSON string is empty. Original text: " . $generatedText);
            throw new AIResponseException('AI response yielded empty content after cleaning.');
        }

        $analysisResult = json_decode($jsonText, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("[ResponseParser] Failed to decode JSON from AI analysis response. Error: " . json_last_error_msg() . ". Cleaned Text: " . $jsonText);
            throw new AIResponseException("Failed to decode JSON from AI analysis response: " . json_last_error_msg());
        }

        // Validate expected keys and types
        $scoreKey = PromptFactory::KEY_SCORE;
        $justificationKey = PromptFactory::KEY_JUSTIFICATION;
        $explanationKey = PromptFactory::KEY_EXPLANATION;
        $factorsKey = PromptFactory::KEY_FACTORS;

        // Check for all four required keys and their basic types
        if (!isset($analysisResult[$scoreKey]) || !is_numeric($analysisResult[$scoreKey]) ||
            !isset($analysisResult[$justificationKey]) || !is_string($analysisResult[$justificationKey]) ||
            !isset($analysisResult[$explanationKey]) || !is_string($analysisResult[$explanationKey]) || // Check explanation
            !isset($analysisResult[$factorsKey]) || !is_array($analysisResult[$factorsKey]) // Check key_factors is an array
           ) {
            error_log("[ResponseParser] Decoded JSON for analysis is missing required keys ('{$scoreKey}', '{$justificationKey}', '{$explanationKey}', '{$factorsKey}') or has incorrect types.");
             // Log the problematic JSON for debugging
             error_log("[ResponseParser] Invalid Analysis JSON: " . $jsonText);
            throw new AIResponseException("AI analysis response JSON missing required keys ('{$scoreKey}', '{$justificationKey}', '{$explanationKey}', '{$factorsKey}') or has incorrect types.");
        }

        // Normalize score
        $score = max(0.0, min(100.0, (float) $analysisResult[$scoreKey]));

        // Ensure key_factors contains only strings (optional stricter check)
        $keyFactors = array_filter($analysisResult[$factorsKey], 'is_string');
        if (count($keyFactors) !== count($analysisResult[$factorsKey])) {
             error_log("[ResponseParser] Warning: Some elements in key_factors were not strings and were filtered out.");
        }

        return [
            $scoreKey => $score,
            $justificationKey => trim($analysisResult[$justificationKey]),
            $explanationKey => trim($analysisResult[$explanationKey]),
            $factorsKey => $keyFactors // Return the filtered array
        ];
    }

    /**
     * Parses the response for structured data extraction.
     *
     * @param array $apiResponse The decoded JSON response array from GeminiClient.
     * @return array The extracted data array conforming to the expected schema.
     * @throws AIResponseException If the response format is invalid or extraction failed.
     */
    public function parseExtractionResponse(array $apiResponse): array {
        $generatedText = $apiResponse['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if ($generatedText === null || !is_string($generatedText)) {
             error_log("[ResponseParser] Failed to extract generated text from API extraction response. Structure invalid.");
            // error_log("[ResponseParser] Invalid Response Structure: " . json_encode($apiResponse));
            throw new AIResponseException('Invalid API response structure: Missing generated text for extraction.');
        }

        $jsonText = $this->cleanJsonString($generatedText);
        if (empty($jsonText)) {
            error_log("[ResponseParser] Cleaned JSON string for extraction is empty. Original text: " . $generatedText);
            throw new AIResponseException('AI extraction response yielded empty content after cleaning.');
        }

        error_log("[ResponseParser] Attempting to parse extraction JSON from AI: " . substr($jsonText, 0, 200) . "...");
        $extractedData = json_decode($jsonText, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("[ResponseParser] Failed to decode JSON from AI extraction response. Error: " . json_last_error_msg() . ". Cleaned Text Snippet: " . substr($jsonText, 0, 500));
            throw new AIResponseException("Failed to decode JSON from AI extraction response: " . json_last_error_msg());
        }

        // Basic validation: Check if it's an array (expected top-level JSON object)
        if (!is_array($extractedData)) {
             error_log("[ResponseParser] Decoded extraction JSON is not an array/object as expected.");
             throw new AIResponseException("Decoded extraction JSON is not an array/object as expected.");
        }

        // *** Validate inferred demographics ***
        $allowedGenders = ["male", "female", "unknown"];
        // Remove 'unclear' from allowed ethnicities
        $allowedEthnicities = ["likely_european", "likely_east_asian", "likely_south_asian", "likely_african", "likely_hispanic"];
        
        // Ensure the structure exists
        if (!isset($extractedData['inferred_demographics'])) {
            $extractedData['inferred_demographics'] = [];
             error_log("[ResponseParser] Warning: Missing 'inferred_demographics' key in extracted data. Defaulting gender/ethnicity.");
        }
        // Validate or default gender
        if (!isset($extractedData['inferred_demographics']['gender']) || !in_array($extractedData['inferred_demographics']['gender'], $allowedGenders, true)) {
             error_log("[ResponseParser] Warning: Invalid or missing inferred gender. Defaulting to 'unknown'. Value: " . ($extractedData['inferred_demographics']['gender'] ?? 'NULL'));
            $extractedData['inferred_demographics']['gender'] = 'unknown';
        }
         // Validate or default ethnicity context (now must be one of the allowed, or default to something like european if missing/invalid)
         if (!isset($extractedData['inferred_demographics']['ethnicity_context']) || !in_array($extractedData['inferred_demographics']['ethnicity_context'], $allowedEthnicities, true)) {
             error_log("[ResponseParser] Warning: Invalid or missing inferred ethnicity context. Defaulting to 'likely_european'. Value: " . ($extractedData['inferred_demographics']['ethnicity_context'] ?? 'NULL'));
            // Default to one of the allowed values if invalid or missing, as "unclear" is no longer valid.
            $extractedData['inferred_demographics']['ethnicity_context'] = 'likely_european'; 
        }
        // *** End Validation ***
        
        // Log warning if basic structure is missing
        if (!isset($extractedData['contact']) || !isset($extractedData['experience']) || !isset($extractedData['education']) || !isset($extractedData['skills'])) {
             error_log("[ResponseParser] Warning: Extracted data JSON is missing some expected top-level keys (contact, experience, education, skills). Proceeding cautiously.");
        }

        error_log("[ResponseParser] Successfully parsed extracted data JSON (including inferred demographics). G:" . ($extractedData['inferred_demographics']['gender'] ?? '?') . " E:" .($extractedData['inferred_demographics']['ethnicity_context'] ?? '?'));
        return $extractedData; // Return the entire structured data array
    }

    /**
     * Parses the response for candidate feedback (explanation and counterfactuals).
     *
     * @param array $apiResponse The decoded JSON response array from GeminiClient.
     * @return array An array containing ['candidate_explanation' => string, 'counterfactuals' => array].
     * @throws AIResponseException If the response format is invalid or missing required data.
     */
    public function parseCandidateFeedbackResponse(array $apiResponse): array {
        $generatedText = $apiResponse['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if ($generatedText === null || !is_string($generatedText)) {
            error_log("[ResponseParser] Failed to extract generated text from API feedback response.");
            throw new AIResponseException('Invalid API response structure: Missing generated text for feedback.');
        }

        $jsonText = $this->cleanJsonString($generatedText);
        if (empty($jsonText)) {
            error_log("[ResponseParser] Cleaned JSON string for feedback is empty.");
            throw new AIResponseException('AI feedback response yielded empty content after cleaning.');
        }

        $feedbackResult = json_decode($jsonText, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("[ResponseParser] Failed to decode JSON from AI feedback response. Error: " . json_last_error_msg() . ". Cleaned Text: " . $jsonText);
            throw new AIResponseException("Failed to decode JSON from AI feedback response: " . json_last_error_msg());
        }

        $explanationKey = 'candidate_explanation';
        $counterfactualsKey = 'counterfactuals';

        // Validate expected keys and types
        if (!isset($feedbackResult[$explanationKey]) || !is_string($feedbackResult[$explanationKey]) ||
            !isset($feedbackResult[$counterfactualsKey]) || !is_array($feedbackResult[$counterfactualsKey])) {
            error_log("[ResponseParser] Decoded JSON for feedback is missing required keys ('{$explanationKey}', '{$counterfactualsKey}') or has incorrect types.");
            error_log("[ResponseParser] Invalid Feedback JSON: " . $jsonText);
            throw new AIResponseException("AI feedback response JSON missing required keys ('{$explanationKey}', '{$counterfactualsKey}') or has incorrect types.");
        }

        // Ensure counterfactuals contains only strings
        $counterfactuals = array_filter($feedbackResult[$counterfactualsKey], 'is_string');
        if (count($counterfactuals) !== count($feedbackResult[$counterfactualsKey])) {
             error_log("[ResponseParser] Warning: Some elements in feedback counterfactuals were not strings and were filtered out.");
        }

        return [
            $explanationKey => trim($feedbackResult[$explanationKey]),
            $counterfactualsKey => $counterfactuals
        ];
    }

    /**
     * Cleans the JSON string received from the AI, removing potential markdown code blocks.
     *
     * @param string $rawJsonText The raw text potentially containing the JSON.
     * @return string The cleaned JSON string.
     */
    private function cleanJsonString(string $rawJsonText): string {
        $jsonText = trim($rawJsonText);

        // Remove potential markdown code block fences (` ```json` or ` ``` `)
        if (str_starts_with($jsonText, '```json')) {
            $jsonText = substr($jsonText, 7);
            if (str_ends_with($jsonText, '```')) {
                $jsonText = substr($jsonText, 0, -3);
            }
            $jsonText = trim($jsonText);
        } elseif (str_starts_with($jsonText, '```')) {
             $jsonText = substr($jsonText, 3);
             if (str_ends_with($jsonText, '```')) {
                 $jsonText = substr($jsonText, 0, -3);
             }
             $jsonText = trim($jsonText);
        }
        
        // Add any other cleaning steps if needed (e.g., removing leading/trailing non-JSON characters)

        return $jsonText;
    }
} 