<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/models/Auth.php';

// If user is already logged in, redirect to appropriate page
if (is_logged_in()) {
    redirect(is_admin() ? base_url('admin/dashboard.php') : base_url('jobs.php'));
}

$errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $errors[] = "Both username and password are required";
    } else {
        $user = login_user($username, $password);
        if ($user) {
            $_SESSION['flash_message'] = "Welcome back, " . htmlspecialchars($user['first_name']) . "!";
            $_SESSION['flash_type'] = "success";
            redirect(is_admin() ? base_url('admin/dashboard.php') : base_url('jobs.php'));
        } else {
            $errors[] = "Invalid username or password";
        }
    }
}

// Start output buffering
ob_start();
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-4">
        <div class="card">
            <div class="card-body">
                <h2 class="card-title text-center mb-4">Login</h2>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" 
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Login</button>
                    </div>
                </form>

                <div class="text-center mt-3">
                    <p class="mb-0">Don't have an account? <a href="<?php echo base_url('register.php'); ?>">Register</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$page_title = 'Login';

// Include the main layout
require_once __DIR__ . '/../app/views/layouts/main.php';
?> 