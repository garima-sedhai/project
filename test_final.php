<?php
// test_final.php - Final email system test
echo "<h2>🎯 Final Email System Test</h2>";

// Test 1: Check file locations
echo "<h3>1. File Location Check:</h3>";
$files_to_check = [
    'includes/config.php' => 'Config file',
    'includes/email_functions.php' => 'Email functions',
    'includes/phpmailer/PHPMailer.php' => 'PHPMailer',
    'includes/phpmailer/SMTP.php' => 'PHPMailer SMTP',
    'includes/phpmailer/Exception.php' => 'PHPMailer Exception'
];

foreach ($files_to_check as $file => $description) {
    if (file_exists($file)) {
        echo "✅ $description: FOUND<br>";
    } else {
        echo "❌ $description: MISSING<br>";
    }
}

// Test 2: Load config and check settings
echo "<h3>2. Configuration Check:</h3>";
require_once 'includes/config.php';

echo "EMAIL_DEBUG: " . (EMAIL_DEBUG ? 'ON (debug)' : 'OFF (production)') . "<br>";
echo "DEBUG_MODE: " . (DEBUG_MODE ? 'ON' : 'OFF') . "<br>";
echo "SMTP_USERNAME: " . SMTP_USERNAME . "<br>";
echo "SMTP_PASSWORD length: " . strlen(SMTP_PASSWORD) . " chars<br>";

if (strlen(SMTP_PASSWORD) === 16) {
    echo "✅ Password is 16 characters (correct)<br>";
} else {
    echo "❌ Password should be 16 characters<br>";
}

// Test 3: Test email_functions.php
echo "<h3>3. Testing Email Function:</h3>";
require_once 'includes/email_functions.php';

// Test with a simple email
$test_email = 'sedhaigarima183@gmail.com';
$test_name = 'Test User';
$test_otp = '123456';

echo "Testing sendOTPEmail() with:<br>";
echo "Email: $test_email<br>";
echo "Name: $test_name<br>";
echo "OTP: $test_otp<br><br>";

$result = sendOTPEmail($test_email, $test_name, $test_otp);

if ($result === true) {
    echo "<div style='background: lightgreen; padding: 15px;'>
            ✅ Email sent successfully!<br>
            Check your Gmail: $test_email
          </div>";
} else {
    echo "<div style='background: pink; padding: 15px;'>
            ❌ Email sending failed!<br>
            Error details in error_log
          </div>";
}

// Check error log
echo "<h3>4. Error Log Check:</h3>";
$error_log = ini_get('error_log');
echo "PHP error_log: " . ($error_log ? $error_log : 'Default location') . "<br>";

// Test 5: Check if registration would work
echo "<h3>5. Registration Test Simulation:</h3>";
echo "To test registration:<br>";
echo "1. Go to: <a href='customer/register.php'>Register Page</a><br>";
echo "2. Enter details and submit<br>";
echo "3. Check email for OTP<br>";
echo "4. Enter OTP in verification page<br><br>";

if (EMAIL_DEBUG) {
    echo "<div style='background: orange; padding: 15px;'>
            ⚠️ WARNING: EMAIL_DEBUG is ON<br>
            Emails won't be sent - OTPs will show on screen<br>
            Change to EMAIL_DEBUG = false for real emails
          </div>";
} else {
    echo "<div style='background: lightblue; padding: 15px;'>
            ✅ PRODUCTION MODE<br>
            Emails will be sent to users' email addresses
          </div>";
}

// Test 6: Quick PHPMailer connection test
echo "<h3>6. Quick SMTP Connection Test:</h3>";
try {
    require_once 'includes/phpmailer/Exception.php';
    require_once 'includes/phpmailer/PHPMailer.php';
    require_once 'includes/phpmailer/SMTP.php';
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USERNAME;
    $mail->Password = SMTP_PASSWORD;
    $mail->SMTPSecure = SMTP_SECURE;
    $mail->Port = SMTP_PORT;
    $mail->Timeout = 10;
    
    if ($mail->smtpConnect()) {
        echo "✅ SMTP Connection: SUCCESS<br>";
        $mail->smtpClose();
    } else {
        echo "❌ SMTP Connection: FAILED<br>";
    }
} catch (Exception $e) {
    echo "❌ Connection Error: " . $e->getMessage() . "<br>";
}

echo "<hr><h2>🎉 Ready for Production!</h2>";
echo "If all tests pass, your system is ready for real registrations.";
?>