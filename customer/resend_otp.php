<?php
session_start();

define('BASE_PATH', dirname(dirname(__FILE__)));
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/db_connection.php';
require_once BASE_PATH . '/includes/email_functions.php';

header('Content-Type: application/json');

// Check if user is in OTP verification process
if (!isset($_SESSION['temp_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please register again.']);
    exit();
}

$user_id = $_SESSION['temp_user_id'];

// Get user details
$stmt = $pdo->prepare("SELECT email, full_name FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not found.']);
    exit();
}

// Generate new OTP
$new_otp = rand(100000, 999999);
$otp_expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));

// Update OTP in database
$stmt = $pdo->prepare("UPDATE users SET otp = ?, otp_expiry = ? WHERE id = ?");
$update_result = $stmt->execute([$new_otp, $otp_expiry, $user_id]);

if ($update_result) {
    // Send new OTP email
    $email_sent = sendOTPEmail($user['email'], $user['full_name'], $new_otp);
    
    if ($email_sent) {
        echo json_encode(['success' => true, 'message' => 'OTP resent successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send email.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update OTP.']);
}
?>