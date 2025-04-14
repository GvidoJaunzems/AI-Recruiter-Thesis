<?php
/**
 * Job Model
 */

/**
 * Get all jobs
 * 
 * @return array Array of jobs
 */
function get_all_jobs() {
    $db = get_db_connection();
    if (!$db) {
        return [];
    }
    
    try {
        $stmt = $db->query("
            SELECT j.*, u.username as posted_by_name 
            FROM jobs j 
            JOIN users u ON j.posted_by = u.id 
            ORDER BY j.created_at DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching jobs: " . $e->getMessage());
        return [];
    }
}

/**
 * Get job by ID
 * 
 * @param int $id Job ID
 * @return array|false Job data or false if not found
 */
function get_job_by_id($id) {
    $db = get_db_connection();
    if (!$db) {
        return false;
    }
    
    try {
        $stmt = $db->prepare("
            SELECT j.*, u.username as posted_by_name 
            FROM jobs j 
            JOIN users u ON j.posted_by = u.id 
            WHERE j.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching job: " . $e->getMessage());
        return false;
    }
}

/**
 * Create a new job posting
 * 
 * @param array $jobData Job data
 * @return bool|string True on success, error message on failure
 */
function create_job($jobData) {
    $db = get_db_connection();
    if (!$db) {
        return "Database connection failed";
    }
    
    // Validate required fields
    $required_fields = ['title', 'company', 'location', 'description', 'requirements', 'salary_range', 'posted_by'];
    foreach ($required_fields as $field) {
        if (empty($jobData[$field])) {
            return "Field '$field' is required";
        }
    }
    
    try {
        $stmt = $db->prepare("
            INSERT INTO jobs (
                title, company, location, description, requirements, 
                salary_range, posted_by, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $stmt->execute([
            $jobData['title'],
            $jobData['company'],
            $jobData['location'],
            $jobData['description'],
            $jobData['requirements'],
            $jobData['salary_range'],
            $jobData['posted_by']
        ]);
        
        return true;
    } catch (PDOException $e) {
        return "Failed to create job: " . $e->getMessage();
    }
}

/**
 * Update a job posting
 * 
 * @param int $id Job ID
 * @param array $jobData Updated job data
 * @return bool|string True on success, error message on failure
 */
function update_job($id, $jobData) {
    $db = get_db_connection();
    if (!$db) {
        return "Database connection failed";
    }
    
    // Validate required fields
    $required_fields = ['title', 'company', 'location', 'description', 'requirements', 'salary_range'];
    foreach ($required_fields as $field) {
        if (empty($jobData[$field])) {
            return "Field '$field' is required";
        }
    }
    
    try {
        $stmt = $db->prepare("
            UPDATE jobs 
            SET title = ?, company = ?, location = ?, description = ?, 
                requirements = ?, salary_range = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $jobData['title'],
            $jobData['company'],
            $jobData['location'],
            $jobData['description'],
            $jobData['requirements'],
            $jobData['salary_range'],
            $id
        ]);
        
        return true;
    } catch (PDOException $e) {
        return "Failed to update job: " . $e->getMessage();
    }
}

/**
 * Delete a job posting
 * 
 * @param int $id Job ID
 * @return bool|string True on success, error message on failure
 */
function delete_job($id) {
    $db = get_db_connection();
    if (!$db) {
        return "Database connection failed";
    }
    
    try {
        // First delete related applications
        $stmt = $db->prepare("DELETE FROM applications WHERE job_id = ?");
        $stmt->execute([$id]);
        
        // Then delete the job
        $stmt = $db->prepare("DELETE FROM jobs WHERE id = ?");
        $stmt->execute([$id]);
        
        return true;
    } catch (PDOException $e) {
        return "Failed to delete job: " . $e->getMessage();
    }
}

/**
 * Get jobs posted by a specific user
 * 
 * @param int $userId User ID
 * @return array Array of jobs
 */
function get_jobs_by_user($userId) {
    $db = get_db_connection();
    if (!$db) {
        return [];
    }
    
    try {
        $stmt = $db->prepare("
            SELECT j.*, u.username as posted_by_name 
            FROM jobs j 
            JOIN users u ON j.posted_by = u.id 
            WHERE j.posted_by = ?
            ORDER BY j.created_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching user's jobs: " . $e->getMessage());
        return [];
    }
} 