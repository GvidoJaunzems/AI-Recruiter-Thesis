 <?php
/**
 * Minimal PHP application for Azure App Service
 * This file replaces the entire Laravel application to verify basic deployment.
 */

// Basic information about the environment
$serverInfo = [
    'PHP Version' => phpversion(),
    'Server Software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'Server Name' => $_SERVER['SERVER_NAME'] ?? 'Unknown',
    'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'Unknown',
    'DOCUMENT_ROOT' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
    'Current Time' => date('Y-m-d H:i:s T'),
    'Environment Variables' => getenv(),
];

// Current working directory
$currentDirectory = getcwd();

// Available PHP extensions
$extensions = get_loaded_extensions();
sort($extensions);

// Create a simple HTML output
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Azure PHP Test</title>
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
            color: #0078D4; /* Azure blue */
            border-bottom: 2px solid #0078D4;
            padding-bottom: 10px;
        }
        h2 {
            color: #0078D4;
            margin-top: 30px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 12px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .extension-list {
            display: flex;
            flex-wrap: wrap;
        }
        .extension {
            background: #f7f7f7;
            margin: 5px;
            padding: 8px 12px;
            border-radius: 4px;
        }
        .status {
            padding: 15px;
            margin: 20px 0;
            background-color: #d4edda;
            border-radius: 4px;
            color: #155724;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <h1>Azure PHP Test Page</h1>
    
    <div class="status">
        ✅ Deployment Successful! This minimal PHP application is running on Azure App Service.
    </div>
    
    <h2>Server Information</h2>
    <table>
        <?php foreach ($serverInfo as $key => $value): ?>
            <?php if (!is_array($value)): ?>
                <tr>
                    <th><?= htmlspecialchars($key) ?></th>
                    <td><?= htmlspecialchars($value) ?></td>
                </tr>
            <?php endif; ?>
        <?php endforeach; ?>
    </table>
    
    <h2>Current Working Directory</h2>
    <p><?= htmlspecialchars($currentDirectory) ?></p>
    
    <h2>PHP Extensions (<?= count($extensions) ?>)</h2>
    <div class="extension-list">
        <?php foreach ($extensions as $extension): ?>
            <div class="extension"><?= htmlspecialchars($extension) ?></div>
        <?php endforeach; ?>
    </div>
    
    <h2>Next Steps</h2>
    <p>Now that you've confirmed basic PHP functionality on Azure, you can:</p>
    <ul>
        <li>Start building your application incrementally</li>
        <li>Add composer.json to install dependencies</li>
        <li>Create a simple framework structure</li>
        <li>Test database connections</li>
    </ul>
    
    <footer>
        <p>Generated at: <?= date('Y-m-d H:i:s T') ?></p>
    </footer>
</body>
</html> 