# Project Overview: AI-Enhanced Recruiter App

## 1. Project Goal

This application serves as a modern job recruitment platform designed with ethical AI principles in mind, directly inspired by research provided in this knowledge base. It allows candidates to browse job listings, apply for jobs, and manage their profiles. For administrators (recruiters), it provides tools to post jobs, review applications with AI-driven assistance, monitor fairness metrics, and manage the platform. A key focus was integrating Explainable AI (XAI) and transparency features to promote fairness and build user trust, bridging the gap between ethical guidelines and practical implementation.

## 2. Core Application Features

*   **User Authentication & Roles:** Secure registration and login (`/backend/auth_register.php`, `/backend/auth_login.php`). Role-based access (candidate vs. admin) enforced via `ProtectedRoute` (frontend) and backend checks. User data managed in the `users` table (`backend/database/schema.sql`).
*   **Job Discovery:** Public homepage (`Home.js`), filterable job listings (`Jobs.js`), detailed job view (`JobDetails.js`). Job data managed via `/backend/jobs.php` and the `jobs` table.
*   **Candidate Application Workflow:** Application submission via `/apply/:jobId` route (`Apply.js`, `ApplicationForm.js`), resume upload (handled by `/backend/applications.php`), viewing submitted application status (`Applications.js` via `/my-applications` route).
*   **Candidate Profile Management:** Viewing/updating profile information (`Profile.js` via `/backend/user_profile.php`). *(Note: Profile update functionality might need further implementation)*.
*   **Administrator Features:**
    *   **Admin Dashboard (`AdminDashboard.js`):** Central hub for admin actions. Includes job management and application review sections. Links to AI Transparency and Fairness Metrics dashboards.
    *   **Job Posting Management:** Create (`POST /backend/jobs.php`) and delete (`DELETE /backend/job_delete.php`) job listings. View all jobs.
    *   **Application Review (`AdminApplications.js`, `ApplicationReview.js`):** List all submitted applications (`GET /backend/applications.php`) with advanced filtering (including AI score and extracted data). Review individual application details (`GET /backend/applications.php?id=...`), update status (`PUT /backend/applications.php?id=...`). This section integrates enhanced AI insights.
    *   **Fairness Monitoring (`AdminFairnessDashboard.js`):** Displays calculated fairness metrics (see AI Features below). Includes a button to trigger metric calculation (`POST /backend/admin_trigger_metric_calculation.php`). Data fetched via `GET /backend/admin_fairness_metrics.php`.
    *   **AI Transparency Information (`TransparencyInfo.js`):** Static page explaining AI usage, model, purpose, and limitations.

## 3. AI-Powered & Ethical Features (`RecruiterLib`)

The platform leverages AI (Google Gemini) via a modular backend PHP library (`backend/lib/RecruiterLib`) to enhance the recruitment process while prioritizing ethics and transparency. *(Note: Early documentation mentioned a Blue/Red version split; this concept was superseded by implementing all ethical AI enhancements directly into the main application path described here).*

**A. Core AI Analysis (Handled by `RecruiterLib/Recruitment/ApplicationAnalyzer.php`)**

*   **Trigger:** Runs automatically upon new application submission (`POST /backend/applications.php`) or manually via admin request (`GET /backend/applications.php?id=...&recalculate=true`).
*   **Process:**
    1.  Fetches application data and job description from the database.
    2.  Parses the candidate's resume PDF (`RecruiterLib/Util/PdfParser.php`).
    3.  Combines resume text, cover letter, and form data (`RecruiterLib/Util/DataCombiner.php`).
    4.  Generates prompts using `RecruiterLib/AI/PromptFactory.php`.
    5.  Sends requests to Google Gemini API via `RecruiterLib/AI/GeminiClient.php`.
    6.  Parses and validates JSON responses using `RecruiterLib/AI/ResponseParser.php`.
    7.  Updates the `applications` table in the database.
*   **Outputs Stored in `applications` Table:**
    *   `ai_score` (DECIMAL): 0-100 relevance score.
    *   `ai_analysis` (TEXT): Brief justification for the score.
    *   `ai_score_explanation` (TEXT): More detailed explanation of the score reasoning for recruiters.
    *   `ai_key_factors` (JSON): Array of key positive/negative factors influencing the score.
    *   `ai_extracted_data` (JSON): Structured data extracted from the resume/application (skills, experience, education, *AI-inferred demographics* etc.).
    *   `ai_candidate_explanation` (TEXT): Empathetic feedback generated for rejected candidates.
    *   `ai_counterfactuals` (JSON): Actionable suggestions generated for rejected candidates.

**B. Enhanced Recruiter Experience (XAI & Transparency)**

*   **Application Review UI (`ApplicationReview.js`):** Displays the `ai_score`, `ai_analysis` (tooltip), `ai_score_explanation` (dedicated section), and `ai_key_factors` (list with +/- indicators) to provide deeper insight into the AI's assessment.
*   **Advanced Filtering (`AdminApplications.js`):** Allows admins to filter the application list not only by standard fields but also by `ai_score` range and specific fields within the `ai_extracted_data` JSON (e.g., skill, location, experience years, inferred education). *(Note: Extracted data filtering currently happens in PHP after fetching all results, which could be optimized)*.
*   **Clear UI Labeling:** AI-generated fields (scores, explanations, inferred metrics on dashboard) are explicitly labeled as such to ensure recruiters understand the source of the information.
*   **Transparency Page (`TransparencyInfo.js`):** Provides admins with documentation on AI usage, model details, limitations, and links to the fairness dashboard.

**C. Enhanced Candidate Experience (XAI & Transparency)**

*   **Candidate Feedback (`Applications.js` & `CandidateFeedbackModal.js`):** When an application status is updated to 'rejected' (via `PUT /backend/applications.php`), the backend triggers `ApplicationAnalyzer::generateAndSaveCandidateFeedback`. Rejected candidates can then click a "View Feedback" button on their `/my-applications` page. This fetches data from a secure endpoint (`GET /backend/application_feedback.php`) and displays the `ai_candidate_explanation` and `ai_counterfactuals` in a modal, offering constructive insights.

**D. Fairness Monitoring**

*   **Audit Logging:** When an application is marked 'accepted' or 'rejected' (`PUT /backend/applications.php`), key details (App ID, Job ID, Score, Decision, Extracted Data including Inferred Demographics) are logged to the `ai_audit_log` table.
*   **Metric Calculation:** A script (`backend/scripts/calculate_fairness_metrics.php`) processes the `ai_audit_log` to calculate metrics like:
    *   Average Score per Job
    *   Selection Rate per Score Band
    *   Selection Rate per Experience Band (AI-Inferred)
    *   Selection Rate per Education Level (AI-Inferred)
    *   Selection Rate per Gender (AI-Inferred)
    *   Adverse Impact Ratio (AIR) for Gender (AI-Inferred)
    *   Selection Rate per Ethnicity Context (AI-Inferred)
    *   Results are stored in `fairness_monitoring_results`.
*   **Triggering Calculation:** Admins can trigger the calculation script via a button on the Fairness Dashboard, which calls `POST /backend/admin_trigger_metric_calculation.php`. This script executes the calculation script (synchronously in the current debug state).
*   **Dashboard (`AdminFairnessDashboard.js`):** Fetches the latest results from `GET /backend/admin_fairness_metrics.php` and displays the calculated metrics. Crucially, metrics based on inferred demographics are clearly labeled with warnings about potential inaccuracies.

## 6. Deployment (`.github/workflows/deploy.yml`)

*   **Process:** Deployed via GitHub Actions on pushes to `main`.
*   **Steps:** Checks out code, sets up PHP/Node, installs backend (Composer) & frontend (npm) dependencies, builds frontend (injecting `REACT_APP_API_URL` and `PUBLIC_URL`), copies necessary backend directories (`vendor`, `config`, `database`, `lib`, `scripts`, `*.php`) and frontend build (`frontend/build/*`) into a `deploy` directory, creates `web.config` files (root and frontend for SPA routing), zips the `deploy` directory, deploys to Azure Web App.
*   **Database Initialization:** The workflow executes `php database/init_db.php` within the `deploy/backend` directory after deployment. This script reads `backend/database/schema.sql` and executes it (currently using a statement-by-statement approach with error checking) to drop and recreate tables.
*   **Seeding:** The workflow then runs `php database/seed.php` to clear tables (including audit/monitoring tables) and insert default admin/test users and sample jobs. **Note:** This means application data is cleared on each deployment in the current setup.

## 7. Key Files & Structure Overview

*   **`frontend/src/`:** React components and pages.
    *   `pages/`: Top-level page components (`Home.js`, `Jobs.js`, `AdminDashboard.js`, `AdminFairnessDashboard.js`, `Applications.js`, `ApplicationReview.js`, `TransparencyInfo.js`, etc.)
    *   `components/`: Reusable components (`Navbar.js`, `ProtectedRoute.js`, `CandidateFeedbackModal.js`).
    *   `contexts/AuthContext.js`: Manages authentication state.
    *   `config.js`: Defines `API_URL`.
    *   `App.js`: Main application component, handles routing and themes.
*   **`backend/`:** PHP backend logic.
    *   `*.php`: API endpoint handlers (`applications.php`, `jobs.php`, `auth_login.php`, `admin_fairness_metrics.php`, etc.).
    *   `config/`: `database.php` (DB connection), `jwt_helper.php`.
    *   `database/`: `schema.sql` (defines DB structure), `init_db.php` (applies schema), `seed.php` (populates initial data).
    *   `lib/`: Contains the `RecruiterLib` library (see Section 5).
    *   `scripts/`: Contains standalone scripts (`calculate_fairness_metrics.php`).
    *   `vendor/`: Composer dependencies.
    *   `uploads/`: Directory for storing uploaded resumes.
*   **`knowledge_base/`:** Documentation.
    *   `project_overview.md` (This file).
    *   Research documents (Markdown/Original).
*   **`.github/workflows/deploy.yml`:** GitHub Actions deployment script.
*   **`GUIDELINES_ETHICAL_AI_HIRING.md`:** Practical implementation guide document.

## 8. Considerations & Future Work

*   **Seeding Strategy:** The current deployment clears all application and user data (except default admin/test). For persistent environments, the seeding script or `init_db.php` would need modification, and database migrations would be essential.
*   **Error Handling:** Frontend could provide more user-friendly error messages for API failures. Backend logging could be further enhanced.
*   **Job Editing:** Currently not implemented in the admin dashboard.
*   **Scalability:** AI analysis and metric calculation could become slow with many applications. Implementing background queues (Redis, etc.) would be necessary for production scaling. The current `exec` trigger is basic. *(Note: Trigger script is currently running synchronously for debugging; should be reverted to background execution for production).*
*   **Demographic Data:** Using AI-inferred demographics is a placeholder. For robust fairness analysis, integrating self-reported demographic data (collected ethically and with consent) would be required.
*   **Advanced Metrics:** Implement more sophisticated fairness metrics (individual fairness, intersectionality) if needed.
*   **Security:** Review authentication, authorization, input validation, and file upload security thoroughly for production. Centralize helper functions (auth, logging).

This overview provides a comprehensive snapshot of the project's current state, incorporating the ethical AI enhancements and refactoring efforts.