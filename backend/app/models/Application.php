<?php
/**
 * Application Model
 */

/**
 * Get all applications
 * 
 * @return array Array of applications
 */
function get_all_applications() {
    $db = get_db_connection();
    if (!$db) {
        return [];
    }
    
    try {
        $stmt = $db->query("
            SELECT a.*, j.title as job_title, u.username as applicant_name 
            FROM applications a 
            JOIN jobs j ON a.job_id = j.id 
            JOIN users u ON a.user_id = u.id 
            ORDER BY a.created_at DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching applications: " . $e->getMessage());
        return [];
    }
}

/**
 * Get application by ID
 * 
 * @param int $id Application ID
 * @return array|false Application data or false if not found
 */
function get_application_by_id($id) {
    $db = get_db_connection();
    if (!$db) {
        return false;
    }
    
    try {
        $stmt = $db->prepare("
            SELECT a.*, j.title as job_title, u.username as applicant_name 
            FROM applications a 
            JOIN jobs j ON a.job_id = j.id 
            JOIN users u ON a.user_id = u.id 
            WHERE a.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching application: " . $e->getMessage());
        return false;
    }
}

/**
 * Create a new application
 * 
 * @param array $applicationData Application data
 * @return bool|string True on success, error message on failure
 */
function create_application($applicationData) {
    $db = get_db_connection();
    if (!$db) {
        return "Database connection failed";
    }
    
    // Validate required fields
    $required_fields = ['job_id', 'user_id', 'resume_url', 'cover_letter'];
    foreach ($required_fields as $field) {
        if (empty($applicationData[$field])) {
            return "Field '$field' is required";
        }
    }
    
    try {
        // Check if user has already applied
        $stmt = $db->prepare("SELECT id FROM applications WHERE job_id = ? AND user_id = ?");
        $stmt->execute([$applicationData['job_id'], $applicationData['user_id']]);
        
        if ($stmt->rowCount() > 0) {
            return "You have already applied for this job";
        }
        
        // Create application
        $stmt = $db->prepare("
            INSERT INTO applications (job_id, user_id, resume_url, cover_letter, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, 'pending', NOW(), NOW())
        ");
        
        $stmt->execute([
            $applicationData['job_id'],
            $applicationData['user_id'],
            $applicationData['resume_url'],
            $applicationData['cover_letter']
        ]);
        
        return true;
    } catch (PDOException $e) {
        return "Failed to create application: " . $e->getMessage();
    }
}

/**
 * Update application status
 * 
 * @param int $id Application ID
 * @param string $status New status
 * @return bool|string True on success, error message on failure
 */
function update_application_status($id, $status) {
    $db = get_db_connection();
    if (!$db) {
        return "Database connection failed";
    }
    
    // Validate status
    $valid_statuses = ['pending', 'reviewed', 'accepted', 'rejected'];
    if (!in_array($status, $valid_statuses)) {
        return "Invalid status";
    }
    
    try {
        $stmt = $db->prepare("
            UPDATE applications 
            SET status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([$status, $id]);
        return true;
    } catch (PDOException $e) {
        return "Failed to update application status: " . $e->getMessage();
    }
}

/**
 * Delete an application
 * 
 * @param int $id Application ID
 * @return bool|string True on success, error message on failure
 */
function delete_application($id) {
    $db = get_db_connection();
    if (!$db) {
        return "Database connection failed";
    }
    
    try {
        $stmt = $db->prepare("DELETE FROM applications WHERE id = ?");
        $stmt->execute([$id]);
        return true;
    } catch (PDOException $e) {
        return "Failed to delete application: " . $e->getMessage();
    }
} 