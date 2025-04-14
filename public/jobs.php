<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/models/Auth.php';

// Get database connection
$db = get_db_connection();
if (!$db) {
    die("Database connection failed");
}

// Get all active job listings
try {
    $stmt = $db->query("
        SELECT j.*, u.username as created_by_username 
        FROM job_listings j 
        LEFT JOIN users u ON j.created_by = u.id 
        WHERE j.status = 'open' 
        ORDER BY j.created_at DESC
    ");
    $jobs = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching jobs: " . $e->getMessage());
    $jobs = [];
}

// Start output buffering
ob_start();
?>

<div class="row mb-4">
    <div class="col">
        <h1>Open Positions</h1>
        <p class="text-muted">Browse our current job openings and apply today.</p>
    </div>
</div>

<div class="row">
    <?php if (empty($jobs)): ?>
        <div class="col">
            <div class="alert alert-info">
                No open positions at the moment. Please check back later.
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($jobs as $job): ?>
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($job['title']); ?></h5>
                        <h6 class="card-subtitle mb-2 text-muted">
                            <?php echo htmlspecialchars($job['department']); ?> • 
                            <?php echo htmlspecialchars($job['employment_type']); ?>
                        </h6>
                        <p class="card-text">
                            <?php echo nl2br(htmlspecialchars(substr($job['description'], 0, 200))); ?>...
                        </p>
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-muted">
                                    Location: <?php echo htmlspecialchars($job['location']); ?>
                                </small>
                                <?php if ($job['salary_range']): ?>
                                    <br>
                                    <small class="text-muted">
                                        Salary: <?php echo htmlspecialchars($job['salary_range']); ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                            <a href="<?php echo base_url('apply.php?job_id=' . $job['id']); ?>" 
                               class="btn btn-primary">
                                Apply Now
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
$page_title = 'Job Listings';

// Include the main layout
require_once __DIR__ . '/../app/views/layouts/main.php';
?> 