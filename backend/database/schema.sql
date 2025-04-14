-- Drop tables if they exist to avoid conflicts
DROP TABLE IF EXISTS applications;
DROP TABLE IF EXISTS jobs;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS ai_audit_log;
DROP TABLE IF EXISTS fairness_monitoring_results;

-- Create users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    first_name VARCHAR(255) NOT NULL,
    last_name VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') DEFAULT 'user',
    phone VARCHAR(20) DEFAULT NULL,
    bio TEXT DEFAULT NULL,
    skills TEXT DEFAULT NULL,
    experience TEXT DEFAULT NULL,
    education TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create jobs table
CREATE TABLE jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    company VARCHAR(255) NOT NULL,
    location VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    requirements TEXT NOT NULL,
    salary_range VARCHAR(255) NOT NULL,
    posted_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (posted_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Create applications table
CREATE TABLE applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    user_id INT NOT NULL,
    
    -- Basic Application Info
    resume_url TEXT NOT NULL,
    cover_letter TEXT,
    status ENUM('pending', 'reviewed', 'accepted', 'rejected') DEFAULT 'pending',
    
    -- AI Scoring Info
    ai_score DECIMAL(5, 2) NULL DEFAULT NULL, -- Score from 0.00 to 100.00
    ai_analysis TEXT NULL DEFAULT NULL,     -- Text justification from AI
    ai_score_explanation TEXT NULL DEFAULT NULL,
    ai_key_factors JSON NULL DEFAULT NULL,
    ai_extracted_data JSON NULL DEFAULT NULL,
    -- Candidate Feedback Fields (NEW)
    ai_candidate_explanation TEXT NULL DEFAULT NULL,
    ai_counterfactuals JSON NULL DEFAULT NULL,

    -- Work Experience
    current_employer VARCHAR(255),
    current_job_title VARCHAR(255),
    years_of_experience INT,
    work_experience TEXT,
    
    -- Education
    highest_education ENUM('high_school', 'associates', 'bachelors', 'masters', 'phd', 'other'),
    education_details TEXT,
    
    -- Skills & Qualifications
    skills TEXT,
    certifications TEXT,
    languages TEXT,
    
    -- Additional Questions
    referral_source VARCHAR(255),
    willing_to_relocate BOOLEAN,
    available_start_date DATE,
    salary_expectations VARCHAR(255),
    
    -- Legal Information
    legally_authorized_to_work BOOLEAN,
    require_sponsorship BOOLEAN,
    
    -- Diversity Information (Optional)
    gender VARCHAR(100),
    ethnicity VARCHAR(100),
    veteran_status VARCHAR(100),
    disability_status VARCHAR(100),
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- NEW: Create AI Audit Log Table
CREATE TABLE ai_audit_log (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    job_id INT NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ai_score DECIMAL(5, 2) NULL, -- Log the score at the time of decision
    decision ENUM('accepted', 'rejected') NOT NULL,
    ai_extracted_data JSON NULL DEFAULT NULL, -- Add extracted data at time of decision
    -- NEW: Add inferred demographic columns
    ai_inferred_gender VARCHAR(50) NULL DEFAULT NULL,
    ai_inferred_ethnicity_context VARCHAR(100) NULL DEFAULT NULL,
    -- Add other relevant non-sensitive fields if needed, e.g.:
    -- user_role_at_decision VARCHAR(50), -- Role of person making decision (if trackable)
    
    INDEX idx_audit_app_id (application_id), -- Index for potential lookups
    INDEX idx_audit_job_id (job_id),     -- Index for job-based aggregation
    INDEX idx_audit_decision (decision),
    INDEX idx_audit_inferred_gender (ai_inferred_gender), -- Index for grouping
    INDEX idx_audit_inferred_ethnicity (ai_inferred_ethnicity_context) -- Index for grouping
    -- FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE SET NULL, -- Optional FK
    -- FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE SET NULL              -- Optional FK
);

-- NEW: Create Fairness Monitoring Results Table
CREATE TABLE fairness_monitoring_results (
    result_id INT AUTO_INCREMENT PRIMARY KEY,
    metric_name VARCHAR(100) NOT NULL, -- e.g., 'selection_rate_by_score_band', 'avg_score_per_job'
    job_id INT NULL,                   -- Link to job if metric is job-specific (NULL for overall)
    stratum VARCHAR(255) NULL,         -- e.g., Score band '80-90', Job Title, etc. (Used for grouping)
    value DECIMAL(10, 4) NULL,         -- The calculated metric value
    calculation_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_metric (metric_name, job_id, stratum, calculation_timestamp) -- Prevent duplicate results for same run
);

-- Insert a default admin user ...
// ... rest of schema ... 