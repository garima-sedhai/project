<?php
// check_errors.php
echo "<h2>PHP Error Reporting Test</h2>";

// Test different ways to set error logging
ini_set('log_errors', 1);

// Try to set error log to a writable location
$possible_locations = [
    __DIR__ . '/php_errors.log',  // In your project folder
    'C:\xampp\htdocs\php_errors.log',  // XAMPP root
    sys_get_temp_dir() . '/php_errors.log',  // System temp
];

foreach ($possible_locations as $location) {
    ini_set('error_log', $location);
    
    // Create a test error
    error_log("=== TEST ERROR TO LOCATION: $location ===");
    
    if (file_exists($location)) {
        echo "<p style='color: green;'>✅ Error log created at: $location</p>";
        echo "<p>File size: " . filesize($location) . " bytes</p>";
        
        // Show last 10 lines
        $lines = file($location);
        $last_lines = array_slice($lines, -10);
        echo "<pre>Last 10 lines:\n" . htmlspecialchars(implode("", $last_lines)) . "</pre>";
        break;
    } else {
        echo "<p style='color: orange;'>⚠️ Could not create error log at: $location</p>";
    }
}

// Show current configuration
echo "<h3>Current PHP Configuration:</h3>";
echo "<pre>";
echo "error_reporting: " . ini_get('error_reporting') . "\n";
echo "display_errors: " . ini_get('display_errors') . "\n";
echo "log_errors: " . ini_get('log_errors') . "\n";
echo "error_log: " . ini_get('error_log') . "\n";
echo "</pre>";

// Test if we can write to logs folder
echo "<h3>Testing Write Permissions:</h3>";
$test_file = __DIR__ . '/logs/test_write.log';
if (file_put_contents($test_file, "Test write at " . date('Y-m-d H:i:s'))) {
    echo "<p style='color: green;'>✅ Can write to logs folder</p>";
    unlink($test_file);
} else {
    echo "<p style='color: red;'>❌ Cannot write to logs folder</p>";
}
?>