<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../app/models/Auth.php';

// Log out the user
logout_user();

// Redirect to login page with a message
$_SESSION['flash_message'] = "You have been logged out successfully.";
$_SESSION['flash_type'] = "success";
redirect(base_url('login.php')); 