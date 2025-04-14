<?php

namespace RecruiterLib\AI;

/**
 * Factory class for creating prompts for the Google AI API.
 */
class PromptFactory {

    // Constants for JSON keys might be useful if used elsewhere
    public const KEY_SCORE = 'score';
    public const KEY_JUSTIFICATION = 'justification';
    public const KEY_EXPLANATION = 'explanation';
    public const KEY_FACTORS = 'key_factors';

    /**
     * Creates the prompt for application analysis (scoring, justification, explanation, key factors).
     *
     * @param string $jobDetails Text containing job description and requirements.
     * @param string $applicantDetails Text containing applicant's combined details (resume, cover letter, etc.).
     * @return string The constructed prompt.
     */
    public function createAnalysisPrompt(string $jobDetails, string $applicantDetails): string {
        // Using constants for the keys in the prompt example
        $scoreKey = self::KEY_SCORE;
        $justificationKey = self::KEY_JUSTIFICATION;
        $explanationKey = self::KEY_EXPLANATION;
        $factorsKey = self::KEY_FACTORS;

        // Heredoc syntax for clarity
        $prompt = <<<PROMPT
Analyze the following applicant's details against the provided job description and requirements.

Provide the following analysis in a valid JSON object ONLY:
1.  A suitability score from 0 to 100 (key: "{$scoreKey}").
2.  A brief justification (1-2 sentences) for the score (key: "{$justificationKey}").
3.  A more detailed explanation (2-4 sentences) elaborating on the reasoning behind the score, mentioning specific matches or mismatches (key: "{$explanationKey}").
4.  A list of the top 3-5 key factors (strings) that most influenced the score, indicating positive (+) or negative (-) impact (e.g., "+ Strong experience in X", "- Lacks required certification Y") (key: "{$factorsKey}").

**Important:** Respond ONLY with the valid JSON object containing these four keys: "{$scoreKey}", "{$justificationKey}", "{$explanationKey}", "{$factorsKey}". Do not include any other text, preamble, or markdown formatting.

**Job Details:**
---
{$jobDetails}
---

**Applicant Details:**
---
{$applicantDetails}
---

**JSON Response:**
PROMPT;
        return $prompt;
    }

    /**
     * Creates the prompt for extracting structured data from application details.
     *
     * @param string $jobDetails Text containing job description and requirements (for context).
     * @param string $applicantDetails Text containing applicant's combined details.
     * @return string The constructed prompt.
     */
    public function createExtractionPrompt(string $jobDetails, string $applicantDetails): string {
        // Define the target JSON schema 
        $schemaDescription = <<<'SCHEMA'
{
  "contact": {
    "location_keywords": ["City", "State/Region"], 
    "linkedin_url": "url" 
  },
  "experience": {
    "total_years_approx": 0, 
    "management_years_approx": 0, 
    "job_titles": ["Title1", "Title2"], 
    "company_names": ["Company1", "Company2"] 
  },
  "education": {
    "highest_level_guess": "bachelors", // null, 'high-school', 'associates', 'bachelors', 'masters', 'phd'
    "majors": ["Major1", "Major2"], 
    "institutions": ["Institution1", "Institution2"] 
  },
  "skills": {
     "programming": ["Skill1", "Skill2"],
     "software": ["Software1", "Software2"],
     "technical": ["TechSkill1", "TechSkill2"],
     "soft_skills": ["SoftSkill1", "SoftSkill2"],
     "languages": ["Lang1", "Lang2"],
     "other": ["OtherSkill1", "OtherSkill2"]
  },
  "keywords": ["keyword1", "keyword2"], 
  "inferred_demographics": { 
    "gender": "unknown", // Infer based ONLY on pronouns/names if obvious, else "unknown". Allowed: "male", "female", "unknown"
    "ethnicity_context": "likely_european" // Based ONLY on name/language context, choose the SINGLE most likely option from the allowed list. Allowed: "likely_european", "likely_east_asian", "likely_south_asian", "likely_african", "likely_hispanic"
  }
}
SCHEMA;

        $prompt = <<<PROMPT
Analyze the following applicant's details (primarily resume and cover letter) against the provided job description.
Extract key information and structure it according to the following JSON schema. 
Populate fields based ONLY on the provided text. If information for a field is not found, use null for strings/numbers, or an empty array [] for arrays.
Do NOT invent information.

For "inferred_demographics.gender", ONLY use explicit cues like pronouns (he/she) or strong contextual name patterns. Prioritize "unknown" if unsure.
For "inferred_demographics.ethnicity_context", based ONLY on name and language context, you MUST choose the SINGLE most likely option from the allowed list below. Make the best possible guess even if ambiguous.
Allowed values for inferred_demographics.gender: "male", "female", "unknown".
Allowed values for inferred_demographics.ethnicity_context: "likely_european", "likely_east_asian", "likely_south_asian", "likely_african", "likely_hispanic".

Target JSON Schema:
```json
{$schemaDescription}
```

Respond ONLY with the valid JSON object containing the extracted data matching the schema. Do not include any other text, preamble, markdown formatting, or explanations.

**Job Details (for context):**
---
{$jobDetails}
---

**Applicant Details (Resume, Cover Letter, etc.):**
---
{$applicantDetails}
---

**JSON Response:**
PROMPT;
        return $prompt;
    }

    /**
     * Creates the prompt for generating candidate-facing feedback and counterfactuals.
     *
     * @param string $jobDetails Text containing job description and requirements.
     * @param string $applicantDetails Text containing applicant's combined details.
     * @param string|null $rejectionReason Optional brief reason provided by recruiter (if available).
     * @return string The constructed prompt.
     */
    public function createCandidateFeedbackPrompt(string $jobDetails, string $applicantDetails, ?string $rejectionReason = null): string {
        $explanationKey = 'candidate_explanation';
        $counterfactualsKey = 'counterfactuals';
        
        $reasonText = $rejectionReason ? "An internal note indicates the following reason: \"{$rejectionReason}\"\n" : "";

        $prompt = <<<PROMPT
Analyze the following applicant's details against the provided job description. This applicant was not selected for the role.
{$reasonText}
Generate feedback suitable for the candidate in a valid JSON object ONLY.

1.  Provide a helpful, empathetic, and brief explanation (2-3 sentences) for why the application might not have been the strongest match for *this specific role*, based on the provided details (key: "{$explanationKey}"). Focus on the alignment with the job description, not generic advice. Avoid making definitive statements about the candidate's overall qualifications.
2.  Provide a list of 2-4 concrete, actionable counterfactual suggestions (strings) on how the candidate could potentially strengthen their application *for similar roles in the future* (key: "{$counterfactualsKey}"). Examples: "Highlighting specific achievements using [Technology X]", "Quantifying results in the [Previous Role Y] description", "Expanding on experience related to [Job Requirement Z]".

**Important:** Respond ONLY with the valid JSON object containing these two keys: "{$explanationKey}" and "{$counterfactualsKey}". Do not include any other text, preamble, or markdown formatting.

**Job Details:**
---
{$jobDetails}
---

**Applicant Details:**
---
{$applicantDetails}
---

**JSON Response:**
PROMPT;
        return $prompt;
    }

    // Future methods can be added here for:
    // - Recruiter Explanation Prompt (Phase 1)
    // - Candidate Feedback/Counterfactual Prompt (Phase 2)
} 