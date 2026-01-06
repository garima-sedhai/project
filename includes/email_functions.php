<?php
/**
 * Email Functions for BillPay Pro System
 */

/**
 * Send OTP email to user
 */
function sendOTPEmail($email, $name, $otp) {
    require_once 'config.php';
    
    $subject = "Your OTP Code - BillPay Pro";
    
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #075B5E; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 5px 5px; }
            .otp-code { 
                background: #075B5E; 
                color: white; 
                padding: 15px; 
                font-size: 24px; 
                font-weight: bold; 
                text-align: center; 
                letter-spacing: 5px;
                border-radius: 5px;
                margin: 20px 0;
            }
            .footer { 
                margin-top: 30px; 
                padding-top: 20px; 
                border-top: 1px solid #ddd; 
                color: #666; 
                font-size: 12px;
                text-align: center;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>BillPay Pro</h2>
            </div>
            <div class='content'>
                <h3>Hello " . htmlspecialchars($name) . ",</h3>
                <p>Thank you for registering with BillPay Pro. Please use the following OTP code to verify your email address:</p>
                
                <div class='otp-code'>" . $otp . "</div>
                
                <p>This OTP is valid for 10 minutes. Do not share this code with anyone.</p>
                
                <p><strong>Important:</strong> If you didn't request this OTP, please ignore this email.</p>
                
                <p>Best regards,<br>
                BillPay Pro Team</p>
            </div>
            <div class='footer'>
                <p>This is an automated email. Please do not reply to this message.</p>
                <p>&copy; " . date('Y') . " BillPay Pro. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // For production, use PHPMailer or SwiftMailer
    // For simplicity, we'll use mail() function but recommend using a library
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">" . "\r\n";
    $headers .= "Reply-To: " . SMTP_FROM_EMAIL . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    if (EMAIL_DEBUG) {
        // In debug mode, log instead of sending
        error_log("OTP Email Debug - To: $email, Name: $name, OTP: $otp");
        return true; // Simulate success for testing
    } else {
        // In production, actually send the email
        return mail($email, $subject, $message, $headers);
    }
}

/**
 * Send account approval notification
 */
function sendApprovalEmail($email, $name) {
    require_once 'config.php';
    
    $subject = "Account Approved - BillPay Pro";
    
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #27ae60; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 5px 5px; }
            .button { 
                display: inline-block; 
                background: #075B5E; 
                color: white; 
                padding: 12px 30px; 
                text-decoration: none; 
                border-radius: 5px; 
                margin: 20px 0;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Account Approved!</h2>
            </div>
            <div class='content'>
                <h3>Congratulations " . htmlspecialchars($name) . "!</h3>
                <p>Your account has been approved by the administrator. You can now login and access all features of BillPay Pro.</p>
                
                <p><a href='" . SITE_URL . "customer/login.php' class='button'>Login to Your Account</a></p>
                
                <p>If you have any questions, please contact our support team.</p>
                
                <p>Best regards,<br>
                BillPay Pro Team</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">" . "\r\n";
    
    if (EMAIL_DEBUG) {
        error_log("Approval Email Debug - To: $email, Name: $name");
        return true;
    } else {
        return mail($email, $subject, $message, $headers);
    }
}

/**
 * Send account rejection notification
 */
function sendRejectionEmail($email, $name, $reason = '') {
    require_once 'config.php';
    
    $subject = "Account Registration Update - BillPay Pro";
    
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #e74c3c; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 5px 5px; }
            .notice { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Registration Update</h2>
            </div>
            <div class='content'>
                <h3>Dear " . htmlspecialchars($name) . ",</h3>
                
                <div class='notice'>
                    <p>Your account registration has been reviewed and could not be approved at this time.</p>
                    " . (!empty($reason) ? "<p><strong>Reason:</strong> " . htmlspecialchars($reason) . "</p>" : "") . "
                </div>
                
                <p>If you believe this is a mistake or would like more information, please contact our support team.</p>
                
                <p>You may re-register with corrected information if applicable.</p>
                
                <p>Best regards,<br>
                BillPay Pro Team</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">" . "\r\n";
    
    if (EMAIL_DEBUG) {
        error_log("Rejection Email Debug - To: $email, Name: $name");
        return true;
    } else {
        return mail($email, $subject, $message, $headers);
    }
}
?>