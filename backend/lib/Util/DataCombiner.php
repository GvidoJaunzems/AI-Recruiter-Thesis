<?php

namespace RecruiterLib\Util;

/**
 * Combines various application data sources into a single string for AI processing.
 */
class DataCombiner {

    /**
     * Combines application details into a single string suitable for AI analysis.
     *
     * @param string|null $resumeText Extracted text from the resume PDF.
     * @param array $applicationData Associative array of application data fetched from the DB 
     *                              (should include fields like cover_letter, skills, work_experience, etc.).
     * @return string The combined text.
     */
    public function combineApplicantData(?string $resumeText, array $applicationData): string {
        $combined = "--- Extracted Resume Text ---\n";
        $combined .= ($resumeText !== null && $resumeText !== '') ? $resumeText : '[Resume text not available or could not be parsed]';
        $combined .= "\n\n";

        $combined .= "--- Submitted Application Form Data ---\n";
        $combined .= "Cover Letter: " . ($applicationData['cover_letter'] ?? 'N/A') . "\n";
        $combined .= "Skills: " . ($applicationData['skills'] ?? 'N/A') . "\n";
        $combined .= "Current Employer: " . ($applicationData['current_employer'] ?? 'N/A') . "\n";
        $combined .= "Current Job Title: " . ($applicationData['current_job_title'] ?? 'N/A') . "\n";
        $combined .= "Years of Experience: " . ($applicationData['years_of_experience'] ?? 'N/A') . "\n";
        $combined .= "Highest Education: " . ($applicationData['highest_education'] ?? 'N/A') . "\n"; // Consider mapping code to label
        $combined .= "Certifications: " . ($applicationData['certifications'] ?? 'N/A') . "\n";
        $combined .= "Languages: " . ($applicationData['languages'] ?? 'N/A') . "\n";
        $combined .= "Available Start Date: " . ($applicationData['available_start_date'] ?? 'N/A') . "\n";
        $combined .= "Salary Expectations: " . ($applicationData['salary_expectations'] ?? 'N/A') . "\n";
        $combined .= "Willing to Relocate: " . (isset($applicationData['willing_to_relocate']) ? ($applicationData['willing_to_relocate'] ? 'Yes' : 'No') : 'N/A') . "\n";
        $combined .= "Legally Authorized to Work: " . (isset($applicationData['legally_authorized_to_work']) ? ($applicationData['legally_authorized_to_work'] ? 'Yes' : 'No') : 'N/A') . "\n";
        $combined .= "Require Sponsorship: " . (isset($applicationData['require_sponsorship']) ? ($applicationData['require_sponsorship'] ? 'Yes' : 'No') : 'N/A') . "\n";

        // Append Work Experience JSON (decode and format)
        $workExperienceText = $this->formatJsonHistory($applicationData['work_experience'] ?? null, [
            'jobTitle' => 'Title',
            'company' => 'Company',
            'location' => 'Location',
            'startDate' => 'Start Date',
            'endDate' => 'End Date',
            'responsibilities' => 'Responsibilities'
        ]);
        $combined .= "\nWork Experience History:\n" . ($workExperienceText ?: 'N/A');

        // Append Education Details JSON (decode and format)
        $educationDetailsText = $this->formatJsonHistory($applicationData['education_details'] ?? null, [
            'institution' => 'Institution',
            'degree' => 'Degree',
            'fieldOfStudy' => 'Field of Study',
            'startDate' => 'Start Date',
            'endDate' => 'End Date',
            'notes' => 'Notes'
        ]);
        $combined .= "\n\nEducation History Details:\n" . ($educationDetailsText ?: 'N/A');

        // TODO: Consider adding other relevant fields if they exist

        $combinedDataLength = strlen($combined);
        error_log("[DataCombiner] Combined applicant data created. Total length: {$combinedDataLength} chars.");
        // Optional: Log a snippet for verification (careful with PII)
        // error_log("[DataCombiner] Combined Data Snippet: " . substr($combined, 0, 200));

        return trim($combined);
    }

    /**
     * Helper function to format JSON array history (like work experience or education) into a readable string.
     *
     * @param string|null $jsonString The JSON string from the database.
     * @param array $fieldMap Associative array mapping JSON keys to display labels.
     * @return string|null Formatted string or null if input is invalid.
     */
    private function formatJsonHistory(?string $jsonString, array $fieldMap): ?string {
        if (empty($jsonString)) {
            return null;
        }

        $historyArray = json_decode($jsonString, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($historyArray)) {
            error_log("[DataCombiner] Could not decode history JSON: " . json_last_error_msg());
            return '[Could not decode history data]';
        }

        if (empty($historyArray)) {
             return 'None provided.';
        }

        $formattedText = "";
        foreach ($historyArray as $index => $item) {
            if (!is_array($item)) continue; // Skip invalid entries
            $formattedText .= "  Entry " . ($index + 1) . ":\n";
            foreach ($fieldMap as $key => $label) {
                $value = $item[$key] ?? 'N/A';
                 // Handle potential boolean values if needed, e.g. for 'currentJob'
                 if (is_bool($value)) {
                     $value = $value ? 'Yes' : 'No';
                 }
                $formattedText .= "    {$label}: {$value}\n";
            }
            $formattedText .= "\n"; // Add space between entries
        }

        return trim($formattedText);
    }
} 