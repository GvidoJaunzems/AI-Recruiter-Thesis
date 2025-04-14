<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Run startup script
try {
    require_once __DIR__ . '/../startup.php';
} catch (Exception $e) {
    error_log("Startup Error: " . $e->getMessage());
    die("Application startup failed. Please check the logs.");
}

// Start session
session_start();

// Get the requested URI
$requestUri = $_SERVER['REQUEST_URI'];
$scriptName = $_SERVER['SCRIPT_NAME'];

// Remove query string from request URI
$requestUri = strtok($requestUri, '?');

// If the request is for the root, redirect to jobs.php
if ($requestUri === '/' || $requestUri === '') {
    header('Location: jobs.php');
    exit;
}

// Get the requested file path
$requestedFile = basename($requestUri);

// Check if the file exists in the public directory
if (file_exists(__DIR__ . '/' . $requestedFile)) {
    require __DIR__ . '/' . $requestedFile;
    exit;
}

// If file doesn't exist, show 404
header("HTTP/1.0 404 Not Found");
die("Page not found"); 