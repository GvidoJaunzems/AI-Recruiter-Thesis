<?php
/**
 * Clear cache utility for Azure App Service
 * This file can be accessed to clear application cache and display current server state
 */

// Set no cache headers
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Clear opcache if available
if (function_exists('opcache_reset')) {
    opcache_reset();
    $opcache_cleared = true;
} else {
    $opcache_cleared = false;
}

// Get server information
$server_info = [
    'PHP Version' => phpversion(),
    'Server Software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'Server Name' => $_SERVER['SERVER_NAME'] ?? 'Unknown',
    'Document Root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
    'Web Root Files' => scandir($_SERVER['DOCUMENT_ROOT']),
    'Current Time' => date('Y-m-d H:i:s T'),
    'OPCache Cleared' => $opcache_cleared ? 'Yes' : 'Not available',
];

// Get deployment information
$deployment_file = $_SERVER['DOCUMENT_ROOT'] . '/.deployment';
$deployment_config = file_exists($deployment_file) ? file_get_contents($deployment_file) : 'Not found';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Azure App Service Cache Clear</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        h1 {
            color: #0078D4;
            border-bottom: 2px solid #0078D4;
            padding-bottom: 10px;
        }
        h2 {
            color: #0078D4;
            margin-top: 30px;
        }
        pre {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .file-list {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
            max-height: 300px;
            overflow-y: auto;
        }
        .file-item {
            padding: 3px 0;
            border-bottom: 1px solid #eee;
        }
    </style>
</head>
<body>
    <h1>Azure App Service Diagnostics</h1>
    
    <div class="success">
        <?php if ($opcache_cleared): ?>
            ✅ OPCache successfully cleared!
        <?php else: ?>
            ⚠️ OPCache not available on this server.
        <?php endif; ?>
    </div>
    
    <h2>Server Information</h2>
    <pre><?php 
    foreach ($server_info as $key => $value) {
        if ($key !== 'Web Root Files') {
            echo htmlspecialchars("$key: $value") . "\n";
        }
    }
    ?></pre>
    
    <h2>Web Root Files</h2>
    <div class="file-list">
        <?php foreach ($server_info['Web Root Files'] as $file): ?>
            <div class="file-item"><?= htmlspecialchars($file) ?></div>
        <?php endforeach; ?>
    </div>
    
    <h2>Deployment Configuration</h2>
    <pre><?= htmlspecialchars($deployment_config) ?></pre>
    
    <h2>Next Steps</h2>
    <ol>
        <li>Return to <a href="/">index.php</a> to verify your application is working</li>
        <li>If the application is still not working correctly, try a manual reset using the Azure Portal</li>
        <li>Check GitHub Actions for deployment status</li>
    </ol>
    
    <footer>
        <p>Generated at: <?= date('Y-m-d H:i:s T') ?></p>
    </footer>
</body>
</html> 