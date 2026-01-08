<?php
/**
 * Email Helper Functions for BillPay Pro with PHPMailer Support
 */

// First, load config to get constants
if (!defined('SMTP_ENABLED')) {
    require_once __DIR__ . '/config.php';
}

// Include PHPMailer classes from local includes/phpmailer folder
require_once __DIR__ . '/phpmailer/Exception.php';
require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Send email using PHPMailer with SMTP
 */
function send_email($to, $subject, $body, $is_html = true) {
    if (!SMTP_ENABLED) {
        error_log("Email not sent (SMTP disabled): To: $to, Subject: $subject");
        return true; // Return true for testing
    }
    
    try {
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;
        
        // Enable debug if needed
        if (defined('DEBUG_MODE') && DEBUG_MODE && defined('EMAIL_DEBUG') && EMAIL_DEBUG) {
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;
            $mail->Debugoutput = function($str, $level) {
                error_log("PHPMailer: $str");
            };
        } else {
            $mail->SMTPDebug = SMTP::DEBUG_OFF;
        }
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to);
        $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        
        // Content
        $mail->isHTML($is_html);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        
        if (!$is_html) {
            $mail->AltBody = strip_tags($body);
        }
        
        // Send email
        if ($mail->send()) {
            log_email_activity($to, $subject, 'SUCCESS');
            return true;
        } else {
            log_email_activity($to, $subject, 'FAILED', $mail->ErrorInfo);
            return false;
        }
        
    } catch (Exception $e) {
        error_log("PHPMailer Exception: " . $e->getMessage());
        log_email_activity($to, $subject, 'ERROR', $e->getMessage());
        return false;
    }
}

/**
 * Send approval email
 */
function send_approval_email($email, $full_name) {
    $subject = "Account Approved - BillPay Pro";
    
    $message = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Account Approved - BillPay Pro</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                line-height: 1.6;
                color: #333;
                margin: 0;
                padding: 0;
                background-color: #f4f4f4;
            }
            .container {
                max-width: 600px;
                margin: 20px auto;
                background: #ffffff;
                border-radius: 10px;
                overflow: hidden;
                box-shadow: 0 0 20px rgba(0,0,0,0.1);
            }
            .header {
                background: #27ae60;
                color: white;
                padding: 30px;
                text-align: center;
            }
            .header h1 {
                margin: 0;
                font-size: 28px;
            }
            .content {
                padding: 40px 30px;
            }
            .success-icon {
                text-align: center;
                font-size: 60px;
                color: #27ae60;
                margin-bottom: 20px;
            }
            h2 {
                color: #2c3e50;
                margin-top: 0;
            }
            p {
                margin-bottom: 20px;
                font-size: 16px;
            }
            .login-button {
                display: inline-block;
                background: #3498db;
                color: white;
                padding: 12px 30px;
                text-decoration: none;
                border-radius: 5px;
                font-weight: bold;
                margin: 20px 0;
                text-align: center;
            }
            .login-button:hover {
                background: #2980b9;
            }
            .details-box {
                background: #f8f9fa;
                border-left: 4px solid #3498db;
                padding: 20px;
                margin: 25px 0;
                border-radius: 4px;
            }
            .footer {
                background: #f1f1f1;
                padding: 20px;
                text-align: center;
                font-size: 14px;
                color: #666;
                border-top: 1px solid #ddd;
            }
            .contact-info {
                background: #f8f9fa;
                padding: 15px;
                border-radius: 5px;
                margin-top: 20px;
                font-size: 14px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🎉 Account Approved!</h1>
                <p>Welcome to BillPay Pro</p>
            </div>
            
            <div class="content">
                <div class="success-icon">✓</div>
                
                <h2>Dear ' . htmlspecialchars($full_name) . ',</h2>
                
                <p>Great news! Your account registration has been <strong>approved</strong> by our administration team.</p>
                
                <p>You can now access all the features of BillPay Pro and start managing your billing efficiently.</p>
                
                <div class="details-box">
                    <p><strong>Your Account Details:</strong></p>
                    <p>📧 Email: ' . htmlspecialchars($email) . '</p>
                    <p>🔗 Login URL: <a href="' . SITE_URL . 'customer/login.php">' . SITE_URL . 'customer/login.php</a></p>
                </div>
                
                <div style="text-align: center; margin: 30px 0;">
                    <a href="' . SITE_URL . 'customer/login.php" class="login-button">Login to Your Account</a>
                </div>
                
                <p><strong>What you can do now:</strong></p>
                <ul>
                    <li>View and pay your bills</li>
                    <li>Track payment history</li>
                    <li>Update your profile information</li>
                    <li>Receive notifications</li>
                </ul>
                
                <div class="contact-info">
                    <p><strong>Need Help?</strong></p>
                    <p>If you have any questions or need assistance, please contact our support team:</p>
                    <p>📧 Email: ' . ADMIN_EMAIL . '</p>
                </div>
                
                <p style="margin-top: 30px;">Best regards,<br>
                <strong>The BillPay Pro Team</strong></p>
            </div>
            
            <div class="footer">
                <p>This is an automated message. Please do not reply to this email.</p>
                <p>&copy; ' . date('Y') . ' BillPay Pro. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>';
    
    return send_email($email, $subject, $message);
}

/**
 * Send verification email
 */
function send_verification_email($email, $full_name, $verification_code) {
    $subject = "Verify Your Email - BillPay Pro";
    
    $message = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Verify Your Email - BillPay Pro</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                line-height: 1.6;
                color: #333;
                margin: 0;
                padding: 0;
                background-color: #f4f4f4;
            }
            .container {
                max-width: 600px;
                margin: 20px auto;
                background: #ffffff;
                border-radius: 10px;
                overflow: hidden;
                box-shadow: 0 0 20px rgba(0,0,0,0.1);
            }
            .header {
                background: #075B5E;
                color: white;
                padding: 30px;
                text-align: center;
            }
            .header h1 {
                margin: 0;
                font-size: 28px;
            }
            .content {
                padding: 40px 30px;
            }
            .verification-code {
                background: #f8f9fa;
                border: 2px dashed #075B5E;
                padding: 25px;
                text-align: center;
                font-size: 36px;
                font-weight: bold;
                letter-spacing: 5px;
                margin: 25px 0;
                border-radius: 8px;
                color: #075B5E;
            }
            .expiry-note {
                background: #fff3cd;
                border: 1px solid #ffeaa7;
                padding: 15px;
                border-radius: 5px;
                color: #856404;
                margin: 20px 0;
            }
            .footer {
                background: #f1f1f1;
                padding: 20px;
                text-align: center;
                font-size: 14px;
                color: #666;
                border-top: 1px solid #ddd;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>📧 Verify Your Email</h1>
                <p>BillPay Pro Registration</p>
            </div>
            
            <div class="content">
                <h2>Hello ' . htmlspecialchars($full_name) . ',</h2>
                
                <p>Thank you for registering with BillPay Pro!</p>
                <p>To complete your registration, please enter the following verification code:</p>
                
                <div class="verification-code">' . $verification_code . '</div>
                
                <div class="expiry-note">
                    <p><strong>⚠️ Important:</strong> This verification code will expire in 10 minutes.</p>
                </div>
                
                <p>If you didn\'t create an account with us, please ignore this email.</p>
                
                <p style="margin-top: 30px;">Best regards,<br>
                <strong>The BillPay Pro Team</strong></p>
            </div>
            
            <div class="footer">
                <p>This is an automated message. Please do not reply to this email.</p>
                <p>&copy; ' . date('Y') . ' BillPay Pro. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>';
    
    return send_email($email, $subject, $message);
}

/**
 * Log email activity
 */
function log_email_activity($to, $subject, $status, $error = '') {
    $log_dir = __DIR__ . '/../logs/';
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0777, true);
    }
    
    $log_file = $log_dir . 'email.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] TO:$to SUBJECT:$subject STATUS:$status";
    
    if ($error) {
        $log_entry .= " ERROR:" . substr(str_replace(["\r", "\n"], ' ', $error), 0, 200);
    }
    
    $log_entry .= PHP_EOL;
    
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

/**
 * Test email configuration
 */
function test_email_configuration() {
    if (!is_email_configured()) {
        return "Email configuration is incomplete. Check SMTP settings in config.php";
    }
    
    try {
        $test_email = ADMIN_EMAIL;
        $test_subject = "Test Email - BillPay Pro";
        $test_body = "<p>This is a test email from BillPay Pro system.</p><p>Time sent: " . date('Y-m-d H:i:s') . "</p>";
        
        if (send_email($test_email, $test_subject, $test_body)) {
            return "Test email sent successfully to " . $test_email;
        } else {
            return "Failed to send test email. Check error logs.";
        }
    } catch (Exception $e) {
        return "Test email error: " . $e->getMessage();
    }
}

/**
 * Check if email service is configured
 */
function is_email_configured() {
    return defined('SMTP_ENABLED') && SMTP_ENABLED && 
           defined('SMTP_HOST') && !empty(SMTP_HOST) && 
           defined('SMTP_USERNAME') && !empty(SMTP_USERNAME) && 
           defined('SMTP_PASSWORD') && !empty(SMTP_PASSWORD) &&
           defined('SMTP_FROM_EMAIL') && !empty(SMTP_FROM_EMAIL) &&
           defined('SMTP_FROM_NAME') && !empty(SMTP_FROM_NAME);
}

// Test email configuration on load (only in debug mode)
if (defined('DEBUG_MODE') && DEBUG_MODE && defined('EMAIL_DEBUG') && EMAIL_DEBUG) {
    error_log("Email helper loaded. Configuration status: " . (is_email_configured() ? "OK" : "INCOMPLETE"));
}
?>