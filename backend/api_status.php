<?php
/**
 * Simple API Status Endpoint
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

echo json_encode([
    'status' => 'online',
    'message' => 'Recruiter API is running',
    'version' => '1.0.0',
    'documentation' => 'For API documentation, contact the administrator',
    'endpoints' => [
        'auth_register.php' => 'User registration',
        'auth_login.php' => 'User login',
        'auth_me.php' => 'Get current user info',
        // Add other endpoints as needed
    ]
]); 