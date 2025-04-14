<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// This is a fallback PHP script that simply returns the JSON from auth_register.json
$jsonFile = __DIR__ . '/auth_register.json';

if (file_exists($jsonFile)) {
    echo file_get_contents($jsonFile);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'JSON file not found']);
}
?>