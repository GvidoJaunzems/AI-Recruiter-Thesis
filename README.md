# AI-Enhanced Ethical Hiring Platform (Bachelor Thesis Project)

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

Welcome to the repository for the AI-Enhanced Ethical Hiring Platform, developed as part of a Bachelor Thesis. This project explores the practical implementation of ethical AI principles – specifically fairness, transparency, and explainability (XAI) – within a functional AI-driven recruitment system.

**Thesis Title:** Enhancing Fairness and Transparency in AI-Driven Hiring: Developing a Toolkit for Implementing and Auditing Fairness-aware Algorithms with Explainable AI

**Author:** Gvido Jaunzems
**Supervisor:** Ojārs Krūmiņš, PhD.cand.

## Problem & Motivation

AI offers efficiency in hiring but poses significant ethical risks like algorithmic bias and lack of transparency. While ethical guidelines exist, there's a gap in practical guidance for implementing these principles (Hunkenschroer & Luetge, 2022). This project aimed to bridge this gap by designing, building, and evaluating a toolkit and platform that operationalizes ethical AI concepts in a real-world hiring context.

## Key Features & Concepts Implemented

This full-stack application simulates a job recruitment platform with advanced AI capabilities focused on ethical considerations:

**1. Core Recruitment Functionality:**
    *   User Roles: Separate interfaces and permissions for Job Applicants and Administrators (Recruiters).
    *   Job Listings: Browse, search, and view job details.
    *   Application Submission: Candidates can apply for jobs, upload resumes (PDF), and provide relevant information.
    *   Admin Dashboard: Central hub for recruiters to manage jobs and review applications.

**2. AI-Powered Candidate Analysis (`RecruiterLib` Backend Toolkit):**
    *   **Integration:** Uses Google Gemini API for analysis.
    *   **Resume Parsing:** Extracts text content from uploaded PDF resumes.
    *   **AI Scoring:** Generates a 0-100 relevance score for applicants based on job description and candidate data.
    *   **Explainable AI (XAI):**
        *   Generates brief justifications for the AI score.
        *   Generates detailed explanations of the reasoning behind the score.
        *   Identifies key positive (+) and negative (-) factors influencing the score.
    *   **Structured Data Extraction:** Extracts key information (skills, experience years, education level) from application materials.
    *   **Candidate Feedback Generation:** Creates empathetic explanations and actionable counterfactual suggestions for rejected candidates.

**3. Transparency & Explainability in UI:**
    *   **Clear Labeling:** All AI-generated scores, explanations, and metrics are explicitly marked.
    *   **Recruiter Interface:** Displays AI score, justification (tooltip), detailed explanation, and key factors directly in the application review screen.
    *   **Transparency Info Page:** Static page explaining AI usage, model details, limitations, and links to fairness monitoring.
    *   **Candidate Feedback:** Rejected candidates can view AI-generated explanations and improvement suggestions.

**4. Fairness Monitoring:**
    *   **Audit Logging:** Automatically logs key data (decision, score, extracted info including inferred demographics) when applications are accepted/rejected into `ai_audit_log`.
    *   **Metric Calculation:** A PHP script (`calculate_fairness_metrics.php`) processes the audit log to compute metrics:
        *   Selection Rates (by score band, experience, education, inferred gender, inferred ethnicity context).
        *   Adverse Impact Ratio (AIR) for inferred gender/ethnicity.
    *   **Fairness Dashboard:** An admin-only interface displaying calculated fairness metrics with clear warnings about metrics based on inferred data.

## System Architecture Overview

*   **Frontend:** React Single Page Application (SPA) using Material-UI for components.
*   **Backend:** PHP RESTful API handling requests, business logic, and database interaction. Includes the custom `RecruiterLib` library for AI processing.
*   **Database:** MySQL stores user data, jobs, applications, AI results, audit logs, and fairness metrics.
*   **AI Model:** Leverages the Google Gemini API for analysis and generation tasks.
*   **Deployment:** Configured for deployment via GitHub Actions to Azure Web App (see `.github/workflows/deploy.yml`).

## Technology Stack

*   **Frontend:** React, JavaScript, Material-UI, Axios (for API calls)
*   **Backend:** PHP (>= 8.0 recommended), Composer
*   **Database:** MySQL
*   **AI:** Google Gemini API
*   **Deployment:** Azure Web Apps, GitHub Actions
*   **Web Server (Typical for PHP):** Apache or Nginx (when deployed or run locally via appropriate server setup)

## Getting Started

Follow these instructions to set up and run the project locally for development or testing.

### Prerequisites

*   Git
*   PHP (>= 8.0 recommended) with relevant extensions (e.g., pdo_mysql, mbstring, json)
*   Composer (PHP package manager)
*   Node.js (e.g., LTS version) and npm (or yarn)
*   MySQL Server (or compatible equivalent like MariaDB)
*   Access to Google Gemini API and an **API Key**

### Installation

1.  **Clone the repository:**
    ```bash
    git clone <repository-url>
    cd <repository-folder>
    ```
2.  **Install Backend Dependencies:**
    ```bash
    cd backend
    composer install
    cd ..
    ```
3.  **Install Frontend Dependencies:**
    ```bash
    cd frontend
    npm install
    # or: yarn install
    cd ..
    ```

### Configuration

1.  **Database Setup:**
    *   Ensure your MySQL server is running.
    *   Create a new database (e.g., `recruiter_app`).
    *   Import the database schema: Execute the SQL commands in `backend/database/schema.sql` using a MySQL client or tool like phpMyAdmin. This creates the necessary tables.
    *   (Optional but Recommended for Testing) Populate with initial data: Run the seeding script `php backend/database/seed.php`. This adds default admin users and sample jobs. Note that this script may clear existing data in certain tables first.

2.  **Backend Environment Variables:**
    *   Navigate to the `backend` directory.
    *   Create a `.env` file. You can copy the structure from `.env.example` if one is provided, or create it manually.
        *Example `.env` content:*
        ```dotenv
        # Database Credentials
        DB_HOST=127.0.0.1
        DB_PORT=3306
        DB_DATABASE=recruiter_app
        DB_USERNAME=your_db_user
        DB_PASSWORD=your_db_password

        # Google Gemini API Key
        GOOGLE_AI_API_KEY=YOUR_GEMINI_API_KEY

        # JWT Secret Key (generate a strong, random string for security)
        JWT_SECRET_KEY=YOUR_STRONG_RANDOM_SECRET
        ```
    *   Replace placeholder values with your actual database credentials, Gemini API key, and a secure JWT secret.
    *   **IMPORTANT:** Add `.env` to your root `.gitignore` file to prevent accidentally committing secrets!

3.  **Frontend API URL:**
    *   In `frontend/src/config.js` (or potentially via a frontend `.env` file if using `create-react-app` conventions), ensure `REACT_APP_API_URL` points to the correct URL where your backend PHP API will be served (e.g., `http://localhost:8000`).

4.  **Web Server Configuration (If not using PHP built-in server):**
    *   If using Apache or Nginx, configure a virtual host pointing to the `backend/` directory.
    *   Ensure `mod_rewrite` (Apache) or equivalent URL rewriting rules are enabled to handle the API routing correctly (allowing requests like `/api/jobs` instead of needing `/api/jobs.php`). Consult server documentation for specifics.
    *   Make sure the `backend/uploads/` directory is writable by the web server process for resume uploads.

### Running the Application

1.  **Start the Backend Server:**
    *   **Option A: PHP Built-in Server (for simple development):**
        *   From the *root* project directory:
            ```bash
            php -S localhost:8000 -t backend/
            ```
        *   *Note:* This server is single-threaded and not recommended for production. URL rewriting might not work as expected without additional configuration.
    *   **Option B: Dedicated Web Server (Apache/Nginx - Recommended):**
        *   Ensure your configured web server (Apache, Nginx) is running and serving the `backend/` directory based on your virtual host setup.

2.  **Start the Frontend Development Server:**
    *   Navigate to the `frontend` directory:
        ```bash
        cd frontend
        npm start
        # or: yarn start
        ```
    *   This will usually open the application automatically in your default web browser (likely at `http://localhost:3000`).

You should now be able to access the frontend application in your browser and it should communicate with the running backend API.

## Project Structure Overview

This section provides an overview of the main directories and their purpose within the project:

*   **`.github/`**: Contains GitHub Actions workflow files, primarily for automated deployment (`deploy.yml`).
*   **`backend/`**: Holds all the PHP backend code, acting as the API server root.
    *   `backend/config/`: Configuration files for database connections, JWT secrets, etc.
    *   `backend/database/`: Contains the SQL schema definition (`schema.sql`), database initialization script (`init_db.php`), and data seeding script (`seed.php`).
    *   `backend/lib/`: The core `RecruiterLib` toolkit, organized into subdirectories:
        *   `lib/AI/`: Classes for interacting with the Gemini API (`GeminiClient`), generating prompts (`PromptFactory`), and parsing responses (`ResponseParser`).
        *   `lib/Recruitment/`: Contains the main orchestrator for AI analysis (`ApplicationAnalyzer`).
        *   `lib/Util/`: Utility classes for tasks like PDF parsing (`PdfParser`) and data combination (`DataCombiner`).
    *   `backend/scripts/`: Standalone PHP scripts, notably `calculate_fairness_metrics.php`.
    *   `backend/uploads/`: Designated directory for storing uploaded resume PDFs (requires appropriate web server write permissions).
    *   `backend/*.php`: Root PHP files acting as API endpoints (e.g., `applications.php`, `jobs.php`, `auth_login.php`).
    *   `backend/composer.json` & `backend/composer.lock`: Define and lock backend PHP dependencies managed by Composer.
    *   `backend/vendor/`: Directory where Composer installs dependencies (usually excluded from Git).
*   **`frontend/`**: Contains the entire React frontend application.
    *   `frontend/public/`: Static assets and the main `index.html` file.
    *   `frontend/src/`: The core React application source code.
        *   `src/components/`: Reusable UI components.
        *   `src/contexts/`: React context providers (e.g., `AuthContext`).
        *   `src/pages/`: Components representing distinct application pages/views.
        *   `src/App.js`: Main application component, handling routing.
        *   `src/index.js`: Application entry point.
        *   `src/config.js`: Frontend configuration like the API URL.
    *   `frontend/package.json` & `frontend/package-lock.json` (or `yarn.lock`): Define and lock frontend Node.js dependencies.
    *   `frontend/node_modules/`: Directory where npm/yarn installs dependencies (usually excluded from Git).
*   **`knowledge_base/`**: Contains all project documentation, research materials, and thesis drafts (`project_overview.md`, `Thesis.md`, etc.).
*   **`.gitignore`**: Specifies files and directories intentionally excluded from Git version control (e.g., `.env` files, `node_modules`, `vendor`, potentially `uploads`).
*   **`LICENSE`**: Contains the open-source license text (e.g., MIT).
*   **`README.md`**: This file, providing an overview and setup instructions for the project.

*(Refer to `knowledge_base/project_overview.md` for a more exhaustive file breakdown and description).*

## Evaluation & Key Results

This platform was empirically evaluated through a mixed-methods comparative study (N=115 users) comparing the full implementation (Blue System) against a baseline version without the enhanced ethical features (Red System). The study demonstrated the effectiveness of the implemented toolkit:

*   **Perceived Fairness:** +40% improvement
*   **Perceived Transparency:** +50% improvement
*   **Overall User Satisfaction:** +34% improvement
*   **Recruiter Trust/Reliability:** Significantly increased (d=3.65)
*   **Recruiter Efficiency:** 28% faster time-to-decision observed

## License

This project is licensed under the MIT License.

## Acknowledgements

*   Ojārs Krūmiņš, PhD.cand. - Thesis Supervisor
