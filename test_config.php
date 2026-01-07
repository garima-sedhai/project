<?php
// test_config.php - Corrected path
echo "<h2>Testing Config File</h2>";

// Use 'includes' instead of 'include'
$config_path = __DIR__ . '/includes/config.php';

echo "<p><strong>Looking for:</strong> $config_path</p>";

if (file_exists($config_path)) {
    echo "<p style='color:green'>✅ File found!</p>";
    
    try {
        require_once $config_path;
        echo "<p style='color:green'>✅ config.php loaded successfully!</p>";
        
        // Test database connection
        $stmt = $pdo->query("SELECT DATABASE() as db");
        $result = $stmt->fetch();
        
        echo "<p><strong>Connected to database:</strong> {$result['db']}</p>";
        
        // Check users table
        $stmt = $pdo->query("SELECT COUNT(*) as user_count FROM users");
        $result = $stmt->fetch();
        echo "<p><strong>Total users:</strong> {$result['user_count']}</p>";
        
        // Check admin
        $stmt = $pdo->query("SELECT email, user_type FROM users WHERE email = 'admin@billpay.com'");
        $admin = $stmt->fetch();
        
        if ($admin) {
            echo "<div style='background:#d4edda; padding:10px; border-radius:5px;'>";
            echo "<p style='color:green'>✅ Admin user found!</p>";
            echo "<p><strong>Email:</strong> {$admin['email']}</p>";
            echo "<p><strong>Type:</strong> {$admin['user_type']}</p>";
            echo "<p><strong>Login:</strong> admin@billpay.com / password</p>";
            echo "</div>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color:red'>❌ Error: " . $e->getMessage() . "</p>";
    }
    
} else {
    echo "<p style='color:red'>❌ File NOT FOUND!</p>";
    
    // Show directory structure
    echo "<h3>Project Structure:</h3>";
    echo "<pre>";
    echo "Current directory: " . __DIR__ . "\n\n";
    
    // List all files and directories
    $items = scandir(__DIR__);
    foreach ($items as $item) {
        if ($item != '.' && $item != '..') {
            $type = is_dir(__DIR__ . '/' . $item) ? '[DIR]  ' : '[FILE] ';
            echo $type . $item . "\n";
        }
    }
    echo "</pre>";
}
?>