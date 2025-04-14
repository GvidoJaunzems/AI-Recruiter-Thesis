<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/models/Auth.php';

// Get database connection
$db = get_db_connection();
if (!$db) {
    die("Database connection failed");
}

// Get job details
$job_id = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;
$job = null;

if ($job_id) {
    try {
        $stmt = $db->prepare("SELECT * FROM job_listings WHERE id = ? AND status = 'open'");
        $stmt->execute([$job_id]);
        $job = $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error fetching job: " . $e->getMessage());
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $job) {
    $errors = [];
    
    // Validate required fields
    $required_fields = ['full_name', 'email', 'phone', 'cover_letter'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . " is required";
        }
    }
    
    // Validate email
    if (!empty($_POST['email']) && !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    // Handle file upload
    $resume_path = null;
    if (!empty($_FILES['resume']['name'])) {
        $file = $_FILES['resume'];
        $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        
        if (!in_array($file['type'], $allowed_types)) {
            $errors[] = "Invalid file type. Only PDF and Word documents are allowed.";
        } elseif ($file['size'] > $app_config['max_file_size']) {
            $errors[] = "File is too large. Maximum size is 5MB.";
        } else {
            $upload_dir = $app_config['upload_dir'];
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $file_name = uniqid() . '.' . $file_extension;
            $resume_path = $upload_dir . $file_name;
            
            if (!move_uploaded_file($file['tmp_name'], $resume_path)) {
                $errors[] = "Failed to upload file";
            }
        }
    }
    
    // If no errors, save application
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("
                INSERT INTO applications (job_id, full_name, email, phone, resume_path, cover_letter)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $job_id,
                $_POST['full_name'],
                $_POST['email'],
                $_POST['phone'],
                $resume_path,
                $_POST['cover_letter']
            ]);
            
            $_SESSION['flash_message'] = "Your application has been submitted successfully!";
            $_SESSION['flash_type'] = "success";
            redirect(base_url('jobs.php'));
        } catch (PDOException $e) {
            error_log("Error saving application: " . $e->getMessage());
            $errors[] = "Failed to submit application. Please try again.";
        }
    }
}

// Start output buffering
ob_start();
?>

<?php if (!$job): ?>
    <div class="alert alert-danger">
        Invalid job listing or job is no longer open.
    </div>
<?php else: ?>
    <div class="row mb-4">
        <div class="col">
            <h1>Apply for <?php echo htmlspecialchars($job['title']); ?></h1>
            <p class="text-muted">
                <?php echo htmlspecialchars($job['department']); ?> • 
                <?php echo htmlspecialchars($job['employment_type']); ?> • 
                <?php echo htmlspecialchars($job['location']); ?>
            </p>
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

    <div class="row">
        <div class="col-md-8">
            <form method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="full_name" class="form-label">Full Name</label>
                    <input type="text" class="form-control" id="full_name" name="full_name" 
                           value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                </div>

                <div class="mb-3">
                    <label for="phone" class="form-label">Phone</label>
                    <input type="tel" class="form-control" id="phone" name="phone" 
                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required>
                </div>

                <div class="mb-3">
                    <label for="resume" class="form-label">Resume (PDF or Word)</label>
                    <input type="file" class="form-control" id="resume" name="resume" 
                           accept=".pdf,.doc,.docx">
                    <div class="form-text">Maximum file size: 5MB</div>
                </div>

                <div class="mb-3">
                    <label for="cover_letter" class="form-label">Cover Letter</label>
                    <textarea class="form-control" id="cover_letter" name="cover_letter" 
                              rows="5" required><?php echo htmlspecialchars($_POST['cover_letter'] ?? ''); ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary">Submit Application</button>
            </form>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Job Details</h5>
                    <p class="card-text">
                        <?php echo nl2br(htmlspecialchars($job['description'])); ?>
                    </p>
                    <?php if ($job['requirements']): ?>
                        <h6 class="card-subtitle mb-2">Requirements</h6>
                        <p class="card-text">
                            <?php echo nl2br(htmlspecialchars($job['requirements'])); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
$content = ob_get_clean();
$page_title = 'Apply for Job';

// Include the main layout
require_once __DIR__ . '/../app/views/layouts/main.php';
?> 