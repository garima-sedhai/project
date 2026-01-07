<?php
// test_db.php - Test database connection
require_once 'include/config.php';

echo "<h2>Database Connection Test</h2>";

try {
    // Test basic connection
    echo "<p style='color:green'>✅ Connected to MySQL!</p>";
    
    // Count users
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch();
    echo "<p>Total users in database: {$result['count']}</p>";
    
    // Check admin
    $stmt = $pdo->query("SELECT * FROM users WHERE email = 'admin@billpay.com'");
    $admin = $stmt->fetch();
    
    if ($admin) {
        echo "<div style='background:#d4edda; padding:15px; border-radius:5px;'>";
        echo "<h3>✅ Admin User Found!</h3>";
        echo "<p><strong>Email:</strong> {$admin['email']}</p>";
        echo "<p><strong>User Type:</strong> {$admin['user_type']}</p>";
        echo "<p><strong>Status:</strong> {$admin['account_status']}</p>";
        echo "<p><strong>Login:</strong> admin@billpay.com / password</p>";
        echo "</div>";
    } else {
        echo "<p style='color:red'>❌ Admin user not found</p>";
    }
    
    // Check other tables
    $tables = ['categories', 'products', 'email_verifications'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
        $result = $stmt->fetch();
        echo "<p>Table '$table': {$result['count']} records</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='login.php'>Go to Login Page</a></p>";
?>