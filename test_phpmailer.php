<?php
// test_phpmailer.php
echo "<h2>Testing PHPMailer Installation</h2>";

// Check if files exist
$files = [
    'includes/phpmailer/PHPMailer.php',
    'includes/phpmailer/SMTP.php',
    'includes/phpmailer/Exception.php'
];

echo "<h3>1. Checking files:</h3>";
foreach ($files as $file) {
    if (file_exists($file)) {
        $size = filesize($file);
        echo "✅ $file (" . round($size/1024, 1) . " KB)<br>";
    } else {
        echo "❌ $file NOT FOUND<br>";
    }
}

// Test loading PHPMailer
echo "<h3>2. Testing PHPMailer loading:</h3>";
try {
    require_once 'includes/phpmailer/Exception.php';
    require_once 'includes/phpmailer/PHPMailer.php';
    require_once 'includes/phpmailer/SMTP.php';
    
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        echo "✅ PHPMailer class loaded successfully!<br>";
        
        // Try to create instance
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        echo "✅ PHPMailer instance created!<br>";
        
        // Test configuration
        echo "<h3>3. Testing email configuration:</h3>";
        require_once 'includes/config.php';
        
        echo "SMTP_FROM_EMAIL: " . (defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'Not set') . "<br>";
        echo "EMAIL_DEBUG: " . (defined('EMAIL_DEBUG') ? (EMAIL_DEBUG ? 'ON' : 'OFF') : 'Not set') . "<br>";
        
        if (EMAIL_DEBUG) {
            echo "⚠️ <strong>Note:</strong> EMAIL_DEBUG is ON - emails will NOT be sent, OTPs will show on screen.<br>";
            echo "To send real emails, change EMAIL_DEBUG to false in config.php<br>";
        }
        
    } else {
        echo "❌ PHPMailer class not found after loading<br>";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

// Test your sendOTPEmail function
echo "<h3>4. Testing sendOTPEmail function:</h3>";
require_once 'includes/email_functions.php';

$test_result = sendOTPEmail('test@example.com', 'Test User', '123456');
echo "sendOTPEmail returned: " . ($test_result ? '✅ TRUE' : '❌ FALSE') . "<br>";

if (defined('EMAIL_DEBUG') && EMAIL_DEBUG) {
    echo "<br><div style='background: #e8f4f8; padding: 15px; border: 1px solid #075B5E;'>
            <strong>📝 IMPORTANT:</strong> With EMAIL_DEBUG = true:<br>
            - Emails are NOT sent to Gmail<br>
            - OTPs appear in YELLOW boxes on screen<br>
            - This is for development/testing<br>
            - Change to EMAIL_DEBUG = false for production
          </div>";
}

echo "<h3>🎉 PHPMailer is ready!</h3>";
echo "Now test registration: <a href='customer/register.php'>Register a new user</a>";
?>