<?php
/**
 * Application Configuration
 */

// Base URL of the application
$base_url = getenv('APP_URL') ?: 'http://localhost';

// Application settings
$app_config = [
    'name' => 'Recruiter App',
    'debug' => getenv('APP_DEBUG') ?: true,
    'upload_dir' => __DIR__ . '/../public/uploads/',
    'max_file_size' => 5 * 1024 * 1024, // 5MB
    'allowed_file_types' => ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
];

// Session settings
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.use_strict_mode', 1);
if (isSecureConnection()) {
    ini_set('session.cookie_secure', 1);
}

/**
 * Check if the current connection is secure (HTTPS)
 * 
 * @return bool
 */
function isSecureConnection() {
    return (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
        (!empty($_SERVER['HTTP_FRONT_END_HTTPS']) && $_SERVER['HTTP_FRONT_END_HTTPS'] !== 'off') ||
        (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
    );
}

/**
 * Get base URL
 * 
 * @param string $path Path to append to base URL
 * @return string Full URL
 */
function base_url($path = '') {
    global $base_url;
    $path = ltrim($path, '/');
    return rtrim($base_url, '/') . '/' . $path;
}

/**
 * Redirect to a URL
 * 
 * @param string $url URL to redirect to
 * @return void
 */
function redirect($url) {
    header('Location: ' . $url);
    exit;
} 