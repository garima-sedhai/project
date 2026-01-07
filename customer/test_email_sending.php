<?php
// test_email_sending.php
require_once 'includes/config.php';
require_once 'includes/email_functions.php';

echo "<h2>Email System Test</h2>";

// Test 1: Check configuration
echo "<h3>Current Configuration:</h3>";
echo "EMAIL_DEBUG: " . (EMAIL_DEBUG ? 'ON (Emails logged, not sent)' : 'OFF (Real emails sent)') . "<br>";
echo "SMTP_FROM_EMAIL: " . SMTP_FROM_EMAIL . "<br>";
echo "SMTP_FROM_NAME: " . SMTP_FROM_NAME . "<br>";

// Test 2: Simple PHP mail() test
echo "<h3>PHP mail() Function Test:</h3>";
$test_email = 'sedhaigarima183@gmail.com';
$test_subject = 'Test Email from BillPay Pro';
$test_message = 'This is a test email sent at ' . date('Y-m-d H:i:s');

if (mail($test_email, $test_subject, $test_message)) {
    echo "✅ PHP mail() function works!<br>";
} else {
    echo "❌ PHP mail() function FAILED!<br>";
    echo "Error: " . error_get_last()['message'] . "<br>";
}

// Test 3: Test your sendOTPEmail function
echo "<h3>sendOTPEmail() Function Test:</h3>";
$otp = '123456';
$result = sendOTPEmail($test_email, 'Test User', $otp);

if ($result) {
    echo "✅ sendOTPEmail() returned TRUE<br>";
} else {
    echo "❌ sendOTPEmail() returned FALSE<br>";
}

// Test 4: Check if your server can send emails
echo "<h3>Server Email Configuration:</h3>";
echo "PHP Version: " . phpversion() . "<br>";

// Check sendmail path
$sendmail_path = ini_get('sendmail_path');
echo "Sendmail Path: " . ($sendmail_path ? $sendmail_path : 'Not set') . "<br>";

// Check SMTP settings
echo "SMTP: " . ini_get('SMTP') . "<br>";
echo "smtp_port: " . ini_get('smtp_port') . "<br>";
?>