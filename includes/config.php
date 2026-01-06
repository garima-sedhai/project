<?php
// ============================================
// SESSION & SECURITY SETTINGS
// ============================================
// Only set session ini if session hasn't started yet
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
}

// ============================================
// DEBUG MODE SETTINGS
// ============================================
define('DEBUG_MODE', true); // Set to false in production
define('EMAIL_DEBUG', true); // Set to false to send real emails

// Error reporting based on debug mode
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// ============================================
// DATABASE CONFIGURATION
// ============================================
$host = 'localhost';
$dbname = 'online_billing_system';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    
    // Enhanced error handling and security
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    $pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Set character set
    $pdo->exec("SET NAMES utf8mb4");
    $pdo->exec("SET CHARACTER SET utf8mb4");
    
} catch(PDOException $e) {
    // Log error instead of displaying to user
    error_log("Database connection failed: " . $e->getMessage());
    
    // User-friendly error message
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['error'] = "Database connection error. Please try again later.";
    
    // Don't expose database details
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        die("Connection failed: " . $e->getMessage());
    } else {
        die("System temporarily unavailable. Please try again later.");
    }
}

// ============================================
// EMAIL CONFIGURATION FOR OTP & NOTIFICATIONS
// ============================================

// Email Configuration for Gmail
define('SMTP_HOST', 'smtp.gmail.com');          // Gmail SMTP server
define('SMTP_PORT', 587);                       // Port for TLS
define('SMTP_USERNAME', 'sedhaigarima183@gmail.com'); // Your Gmail
define('SMTP_PASSWORD', 'syrd gvea pgai xbdr');   // Your 16-digit App Password
define('SMTP_FROM_EMAIL', 'sedhaigarima183@gmail.com'); // Same as SMTP_USERNAME
define('SMTP_FROM_NAME', 'BillPay Pro System'); // Display name for emails

// Site Configuration
define('SITE_URL', 'http://localhost/project/'); // Your site URL

// Timezone
date_default_timezone_set('Asia/Kathmandu'); // Change to your timezone
?>