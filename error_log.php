<?php
// error_log.php - Place this in your project root folder
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP Error Log Checker</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h2 {
            color: #333;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
        .success {
            color: #27ae60;
            background: #d4edda;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .error {
            color: #721c24;
            background: #f8d7da;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
        pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #3498db;
            overflow-x: auto;
            font-size: 12px;
        }
        .section {
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>PHP Error Log Checker</h2>
        
        <?php
        // Display PHP configuration
        echo "<div class='section'>";
        echo "<h3>PHP Configuration</h3>";
        echo "<strong>PHP Version:</strong> " . phpversion() . "<br>";
        echo "<strong>Error Reporting:</strong> " . ini_get('error_reporting') . "<br>";
        echo "<strong>Display Errors:</strong> " . ini_get('display_errors') . "<br>";
        echo "<strong>Log Errors:</strong> " . ini_get('log_errors') . "<br>";
        echo "<strong>Error Log File:</strong> " . ini_get('error_log') . "<br>";
        echo "</div>";
        
        // Check if error log file exists
        $error_log_path = ini_get('error_log');
        
        if ($error_log_path && file_exists($error_log_path)) {
            echo "<div class='success'>✅ Found error log file: $error_log_path</div>";
            
            // Get file size
            $file_size = filesize($error_log_path);
            echo "<div><strong>File Size:</strong> " . number_format($file_size) . " bytes</div>";
            
            // Read last 50 lines
            $lines = file($error_log_path);
            $last_lines = array_slice($lines, -50);
            
            echo "<div class='section'>";
            echo "<h3>Last 50 Lines from Error Log</h3>";
            echo "<pre>" . htmlspecialchars(implode("", $last_lines)) . "</pre>";
            echo "</div>";
        } else {
            echo "<div class='error'>❌ Error log file not found or not set in php.ini</div>";
            
            // Check common locations
            echo "<div class='section'>";
            echo "<h3>Checking Common Error Log Locations:</h3>";
            
            $common_locations = [
                'C:\xampp\php\logs\php_error_log',
                'C:\wamp64\logs\php_error.log',
                'C:\wamp\logs\php_error.log',
                '/var/log/apache2/error.log',
                '/var/log/nginx/error.log',
                '/tmp/php_errors.log',
                getcwd() . '/php_errors.log'
            ];
            
            foreach ($common_locations as $location) {
                if (file_exists($location)) {
                    echo "<div class='success'>✅ Found: $location</div>";
                    $lines = file($location);
                    $last_lines = array_slice($lines, -20);
                    echo "<pre>Last 20 lines:\n" . htmlspecialchars(implode("", $last_lines)) . "</pre>";
                    break;
                } else {
                    echo "<div style='color: #666; margin: 5px 0;'>❌ Not found: $location</div>";
                }
            }
            echo "</div>";
        }
        
        // Create a test error
        error_log("=== TEST ERROR LOG MESSAGE FROM error_log.php ===");
        echo "<div class='section'>";
        echo "<h3>Test Error Logged</h3>";
        echo "A test error message has been logged at: " . date('Y-m-d H:i:s');
        echo "<br>Refresh this page to see if it appears in the logs.";
        echo "</div>";
        
        // Check project structure
        echo "<div class='section'>";
        echo "<h3>Project Directory Structure</h3>";
        echo "<pre>";
        function listDir($dir, $depth = 0) {
            $items = scandir($dir);
            foreach ($items as $item) {
                if ($item == '.' || $item == '..') continue;
                $path = $dir . '/' . $item;
                echo str_repeat('  ', $depth) . "├── " . $item . "\n";
                if (is_dir($path) && $depth < 2) {
                    listDir($path, $depth + 1);
                }
            }
        }
        listDir(getcwd());
        echo "</pre>";
        echo "</div>";
        ?>
    </div>
</body>
</html>