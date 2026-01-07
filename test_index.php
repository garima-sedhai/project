<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing project index...<br>";

// Test if config file exists
$config_path = __DIR__ . '/includes/config.php';
echo "Config path: $config_path<br>";

if (file_exists($config_path)) {
    echo "Config file exists!<br>";
    
    // Test including it
    try {
        require_once $config_path;
        echo "Config included successfully!<br>";
    } catch (Exception $e) {
        echo "Error including config: " . $e->getMessage() . "<br>";
    }
} else {
    echo "Config file NOT found!<br>";
}

// List files in includes directory
echo "<br>Files in includes directory:<br>";
$files = scandir(__DIR__ . '/includes/');
foreach ($files as $file) {
    if ($file !== '.' && $file !== '..') {
        echo "- $file<br>";
    }
}
?>