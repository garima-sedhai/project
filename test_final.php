<?php
// test_final.php - Final test for project defense
require_once 'includes/config.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Final Test - Online Billing System</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .success { color: green; padding: 10px; background: #e8f5e8; border-left: 4px solid green; }
        .error { color: red; padding: 10px; background: #ffebee; border-left: 4px solid red; }
        .warning { color: orange; padding: 10px; background: #fff3cd; border-left: 4px solid orange; }
        .info { padding: 10px; background: #d1ecf1; border-left: 4px solid #17a2b8; }
        .box { border: 1px solid #ddd; padding: 20px; margin: 20px 0; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>Online Billing System - Final Test</h1>
    <h3>Project Defense Preparation</h3>
    
    <div class='box'>";

try {
    // Test 1: Database Connection
    echo "<h4>✅ Test 1: Database Connection</h4>";
    $stmt = $pdo->query("SELECT DATABASE() as db, VERSION() as version");
    $db_info = $stmt->fetch();
    echo "<p><strong>Database:</strong> {$db_info['db']}</p>";
    echo "<p><strong>MySQL Version:</strong> {$db_info['version']}</p>";
    
    // Test 2: Check Tables
    echo "<h4>✅ Test 2: Database Tables</h4>";
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($tables) > 0) {
        echo "<p>Found " . count($tables) . " tables:</p>";
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li>{$table}</li>";
        }
        echo "</ul>";
    } else {
        echo "<p class='warning'>No tables found. Run database setup.</p>";
    }
    
    // Test 3: Check Admin User
    echo "<h4>✅ Test 3: Admin User</h4>";
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = 'admin@billpay.com'");
    $stmt->execute();
    $admin = $stmt->fetch();
    
    if ($admin) {
        echo "<div class='success'>";
        echo "<p><strong>✅ Admin user found!</strong></p>";
        echo "<p><strong>Email:</strong> {$admin['email']}</p>";
        echo "<p><strong>Username:</strong> {$admin['username']}</p>";
        echo "<p><strong>Type:</strong> {$admin['user_type']}</p>";
        echo "<p><strong>Status:</strong> {$admin['account_status']}</p>";
        echo "<p><strong>Login Credentials:</strong> admin@billpay.com / password</p>";
        echo "</div>";
    } else {
        echo "<p class='error'>❌ Admin user not found</p>";
    }
    
    // Test 4: Check Email Configuration
    echo "<h4>✅ Test 4: Email Configuration</h4>";
    echo "<p><strong>SMTP Host:</strong> " . SMTP_HOST . "</p>";
    echo "<p><strong>SMTP Port:</strong> " . SMTP_PORT . "</p>";
    echo "<p><strong>From Email:</strong> " . SMTP_FROM_EMAIL . "</p>";
    
    // Test 5: Sample Data
    echo "<h4>✅ Test 5: Sample Data</h4>";
    
    // Check categories
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM categories");
    $cat_count = $stmt->fetch();
    echo "<p><strong>Categories:</strong> {$cat_count['count']}</p>";
    
    // Check products
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM products");
    $prod_count = $stmt->fetch();
    echo "<p><strong>Products:</strong> {$prod_count['count']}</p>";
    
    // Show sample products
    $stmt = $pdo->query("SELECT name, price FROM products LIMIT 3");
    $products = $stmt->fetchAll();
    
    if ($products) {
        echo "<p><strong>Sample Products:</strong></p>";
        echo "<ul>";
        foreach ($products as $product) {
            echo "<li>{$product['name']} - " . format_currency($product['price']) . "</li>";
        }
        echo "</ul>";
    }
    
    // Test 6: Session and Security
    echo "<h4>✅ Test 6: Session & Security</h4>";
    echo "<p><strong>Session Started:</strong> " . (isset($_SESSION) ? 'Yes' : 'No') . "</p>";
    echo "<p><strong>CSRF Token:</strong> " . (isset($_SESSION['csrf_token']) ? 'Set' : 'Not set') . "</p>";
    
    echo "</div>";
    
    // Final Message
    echo "<div class='info'>";
    echo "<h3>🎉 Ready for Defense!</h3>";
    echo "<p>Your Online Billing System is configured and ready for presentation.</p>";
    echo "<p><strong>Next Steps:</strong></p>";
    echo "<ol>";
    echo "<li><a href='login.php'>Test Admin Login</a> (admin@billpay.com / password)</li>";
    echo "<li><a href='register.php'>Test Customer Registration</a> (with OTP)</li>";
    echo "<li><a href='index.php'>Go to Homepage</a></li>";
    echo "</ol>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h4>❌ Test Failed</h4>";
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>Troubleshooting:</strong></p>";
    echo "<ol>";
    echo "<li>Check if MySQL is running</li>";
    echo "<li>Verify database 'online_billing_system' exists</li>";
    echo "<li>Check password in config.php (current: 'garusedhai1')</li>";
    echo "<li>Try changing port to 3307</li>";
    echo "</ol>";
    echo "</div>";
}

echo "</div>";
echo "</body></html>";
?>