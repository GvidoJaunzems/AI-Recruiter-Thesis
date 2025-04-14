<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../app/models/Auth.php';

// Check if user is logged in and is admin
if (!is_admin()) {
    $_SESSION['flash_message'] = "You must be logged in as an admin to access this page.";
    $_SESSION['flash_type'] = "danger";
    redirect(base_url('login.php'));
}

// Get database connection
$db = get_db_connection();
if (!$db) {
    die("Database connection failed");
}

// Handle form submission for creating new job
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    
    // Validate required fields
    $required_fields = ['title', 'description', 'department', 'employment_type', 'location'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . " is required";
        }
    }
    
    // If no errors, create job listing
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("
                INSERT INTO job_listings (
                    title, description, requirements, department, 
                    employment_type, location, salary_range, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $_POST['title'],
                $_POST['description'],
                $_POST['requirements'] ?? null,
                $_POST['department'],
                $_POST['employment_type'],
                $_POST['location'],
                $_POST['salary_range'] ?? null,
                $_SESSION['user']['id']
            ]);
            
            $_SESSION['flash_message'] = "Job listing created successfully!";
            $_SESSION['flash_type'] = "success";
        } catch (PDOException $e) {
            error_log("Error creating job listing: " . $e->getMessage());
            $errors[] = "Failed to create job listing. Please try again.";
        }
    }
}

// Get all job listings
try {
    $stmt = $db->query("
        SELECT j.*, u.username as created_by_username,
               (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id) as application_count
        FROM job_listings j 
        LEFT JOIN users u ON j.created_by = u.id 
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
        <h1>Admin Dashboard</h1>
        <p class="text-muted">Manage job listings and view applications.</p>
    </div>
    <div class="col-auto">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createJobModal">
            Create New Job
        </button>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Department</th>
                        <th>Location</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Applications</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($jobs as $job): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($job['title']); ?></td>
                            <td><?php echo htmlspecialchars($job['department']); ?></td>
                            <td><?php echo htmlspecialchars($job['location']); ?></td>
                            <td><?php echo htmlspecialchars($job['employment_type']); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $job['status'] === 'open' ? 'success' : 'danger'; ?>">
                                    <?php echo ucfirst($job['status']); ?>
                                </span>
                            </td>
                            <td><?php echo $job['application_count']; ?></td>
                            <td><?php echo date('M j, Y', strtotime($job['created_at'])); ?></td>
                            <td>
                                <a href="<?php echo base_url('admin/applications.php?job_id=' . $job['id']); ?>" 
                                   class="btn btn-sm btn-info">
                                    View Applications
                                </a>
                                <button type="button" class="btn btn-sm btn-warning" 
                                        onclick="editJob(<?php echo htmlspecialchars(json_encode($job)); ?>)">
                                    Edit
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create Job Modal -->
<div class="modal fade" id="createJobModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Job Listing</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="title" class="form-label">Job Title</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>

                    <div class="mb-3">
                        <label for="department" class="form-label">Department</label>
                        <input type="text" class="form-control" id="department" name="department" required>
                    </div>

                    <div class="mb-3">
                        <label for="employment_type" class="form-label">Employment Type</label>
                        <select class="form-select" id="employment_type" name="employment_type" required>
                            <option value="full-time">Full Time</option>
                            <option value="part-time">Part Time</option>
                            <option value="contract">Contract</option>
                            <option value="temporary">Temporary</option>
                            <option value="internship">Internship</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="location" class="form-label">Location</label>
                        <input type="text" class="form-control" id="location" name="location" required>
                    </div>

                    <div class="mb-3">
                        <label for="salary_range" class="form-label">Salary Range</label>
                        <input type="text" class="form-control" id="salary_range" name="salary_range" 
                               placeholder="e.g., $50,000 - $70,000">
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Job Description</label>
                        <textarea class="form-control" id="description" name="description" rows="5" required></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="requirements" class="form-label">Requirements</label>
                        <textarea class="form-control" id="requirements" name="requirements" rows="5"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Job</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editJob(job) {
    // TODO: Implement job editing functionality
    alert('Job editing will be implemented in the next phase');
}
</script>

<?php
$content = ob_get_clean();
$page_title = 'Admin Dashboard';

// Include the main layout
require_once __DIR__ . '/../../app/views/layouts/main.php';
?> 