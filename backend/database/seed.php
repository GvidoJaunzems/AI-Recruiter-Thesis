<?php
/**
 * Database Seeder
 */

require_once __DIR__ . '/../config/database.php';
// Removed model requires as they aren't strictly needed for basic seeding
// require_once __DIR__ . '/../app/models/Auth.php';
// require_once __DIR__ . '/../app/models/Job.php';

// Get database connection
$db = get_db_connection();
if (!$db) {
    die("Failed to connect to database");
}

try {
    // Check if tables exist before trying to delete data
    $tables = [
        'applications' => false,
        'jobs' => false, 
        'users' => false,
        // Add new tables to check/clear
        'ai_audit_log' => false,
        'fairness_monitoring_results' => false
    ];
    
    // Check which tables exist
    $stmt = $db->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        if (isset($tables[$row[0]])) {
            $tables[$row[0]] = true;
        }
    }
    
    // Clear existing data only from tables that exist
    if ($tables['fairness_monitoring_results']) {
        // No foreign keys, no need for checks
        $db->exec("TRUNCATE TABLE fairness_monitoring_results");
        echo "Cleared fairness_monitoring_results table.\n";
    }
    if ($tables['ai_audit_log']) {
        // No foreign keys, no need for checks
        $db->exec("TRUNCATE TABLE ai_audit_log");
        echo "Cleared ai_audit_log table.\n";
    }
    if ($tables['applications']) {
        $db->exec("SET FOREIGN_KEY_CHECKS=0");
        $db->exec("TRUNCATE TABLE applications");
        $db->exec("SET FOREIGN_KEY_CHECKS=1");
        echo "Cleared applications table.\n";
    }
    
    if ($tables['jobs']) {
        $db->exec("SET FOREIGN_KEY_CHECKS=0");
        $db->exec("TRUNCATE TABLE jobs");
        $db->exec("SET FOREIGN_KEY_CHECKS=1");
        echo "Cleared jobs table.\n";
    }
    
    if ($tables['users']) {
        $db->exec("SET FOREIGN_KEY_CHECKS=0");
        $db->exec("TRUNCATE TABLE users");
        $db->exec("SET FOREIGN_KEY_CHECKS=1");
        echo "Cleared users table.\n";
    }
    
    // Create admin user
    echo "Creating admin user...\n";
    $adminData = [
        'username' => 'admin',
        'password_hash' => password_hash('admin123', PASSWORD_DEFAULT),
        'email' => 'admin@example.com',
        'first_name' => 'Admin',
        'last_name' => 'User',
        'role' => 'admin'
    ];
    
    $stmt = $db->prepare("
        INSERT INTO users (username, password_hash, email, first_name, last_name, role, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    
    $stmt->execute([
        $adminData['username'],
        $adminData['password_hash'],
        $adminData['email'],
        $adminData['first_name'],
        $adminData['last_name'],
        $adminData['role']
    ]);
    
    $adminId = $db->lastInsertId();
    echo "Admin user created with ID: $adminId\n";
    
    // Also create a regular test user for convenience
    echo "Creating test user...\n";
    $testUserData = [
        'username' => 'testuser',
        'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
        'email' => 'test@example.com',
        'first_name' => 'Test',
        'last_name' => 'User',
        'role' => 'user'
    ];
    
    $stmt = $db->prepare("
        INSERT INTO users (username, password_hash, email, first_name, last_name, role, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    
    $stmt->execute([
        $testUserData['username'],
        $testUserData['password_hash'],
        $testUserData['email'],
        $testUserData['first_name'],
        $testUserData['last_name'],
        $testUserData['role']
    ]);
    
    $testUserId = $db->lastInsertId();
    echo "Test user created with ID: $testUserId\n";
    
    // Create sample jobs
    echo "Creating sample jobs...\n";
    $sampleJobs = [
        [
            'title' => 'Senior Software Engineer',
            'company' => 'Tech Corp',
            'location' => 'San Francisco, CA',
            'description' => 'We are looking for an experienced software engineer to join our team.',
            'requirements' => '5+ years of experience, strong problem-solving skills, team player',
            'salary_range' => '$120,000 - $180,000',
            'posted_by' => $adminId
        ],
        [
            'title' => 'Product Manager',
            'company' => 'Innovation Labs',
            'location' => 'New York, NY',
            'description' => 'Join our product team to drive innovation and user experience.',
            'requirements' => '3+ years of product management, strong communication skills',
            'salary_range' => '$100,000 - $150,000',
            'posted_by' => $adminId
        ],
        [
            'title' => 'UX Designer',
            'company' => 'Design Studio',
            'location' => 'Remote',
            'description' => 'Looking for a talented UX designer to create beautiful user experiences.',
            'requirements' => 'Portfolio required, experience with Figma, user research',
            'salary_range' => '$90,000 - $130,000',
            'posted_by' => $adminId
        ]
    ];
    
    $stmt = $db->prepare("
        INSERT INTO jobs (
            title, company, location, description, requirements, 
            salary_range, posted_by, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    
    foreach ($sampleJobs as $job) {
        $stmt->execute([
            $job['title'],
            $job['company'],
            $job['location'],
            $job['description'],
            $job['requirements'],
            $job['salary_range'],
            $job['posted_by']
        ]);
        echo "Created job: {$job['title']}\n";
    }
    
    echo "Database seeded successfully!\n";
    echo "Admin credentials: admin@example.com / admin123\n";
    echo "Test user credentials: test@example.com / password123\n";
    
} catch (PDOException $e) {
    die("Database seeding failed: " . $e->getMessage() . "\n");
} 