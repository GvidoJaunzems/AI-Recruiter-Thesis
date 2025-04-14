-- Drop tables if they exist to avoid conflicts
DROP TABLE IF EXISTS applications;
DROP TABLE IF EXISTS job_listings;
DROP TABLE IF EXISTS users;

-- Create users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create job listings table
CREATE TABLE job_listings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    requirements TEXT,
    location VARCHAR(100),
    department VARCHAR(100),
    employment_type ENUM('full-time', 'part-time', 'contract', 'temporary', 'internship') NOT NULL,
    salary_range VARCHAR(50),
    status ENUM('open', 'closed') DEFAULT 'open',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Create applications table
CREATE TABLE applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    resume_path VARCHAR(255),
    cover_letter TEXT,
    status ENUM('pending', 'reviewed', 'interviewed', 'rejected', 'hired') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id) REFERENCES job_listings(id) ON DELETE CASCADE
);

-- Insert a default admin user (password: admin123)
INSERT INTO users (username, password, email, first_name, last_name, role) 
VALUES ('admin', '$2y$10$vgKJfLhY1QCoFQJyfiCeWePuJIjE8Zt5eLDs1Z5mX0rjTPCfL8XCe', 'admin@example.com', 'Admin', 'User', 'admin'); 