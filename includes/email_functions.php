<?php
/**
 * Email Functions for BillPay Pro System
 */

/**
 * Send OTP email to user
 */
function sendOTPEmail($email, $name, $otp) {
    require_once 'config.php';
    
    if (EMAIL_DEBUG) {
        // In debug mode, log instantly and return true without delay
        error_log("[" . date('Y-m-d H:i:s') . "] OTP Email Debug - To: $email, Name: $name, OTP: $otp");
        return true;
    }
    
    $subject = "Your OTP Code - BillPay Pro";
    
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { 
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                line-height: 1.6; 
                color: #333; 
                margin: 0;
                padding: 0;
                background-color: #f5f5f5;
            }
            .container { 
                max-width: 600px; 
                margin: 0 auto; 
                padding: 0;
                background: white;
                border-radius: 10px;
                overflow: hidden;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            .header { 
                background: linear-gradient(135deg, #075B5E 0%, #0a7c80 100%); 
                color: white; 
                padding: 30px 20px; 
                text-align: center;
            }
            .header h2 {
                margin: 0;
                font-size: 24px;
                font-weight: 600;
            }
            .content { 
                padding: 40px 30px; 
            }
            .otp-code { 
                background: linear-gradient(135deg, #075B5E 0%, #0a7c80 100%); 
                color: white; 
                padding: 20px; 
                font-size: 32px; 
                font-weight: bold; 
                text-align: center; 
                letter-spacing: 8px;
                border-radius: 8px;
                margin: 30px 0;
                box-shadow: 0 4px 15px rgba(7, 91, 94, 0.2);
                font-family: 'Courier New', monospace;
            }
            .footer { 
                margin-top: 40px; 
                padding-top: 20px; 
                border-top: 1px solid #e9ecef; 
                color: #666; 
                font-size: 12px;
                text-align: center;
            }
            .note {
                background: #f8f9fa;
                padding: 15px;
                border-radius: 5px;
                margin: 20px 0;
                border-left: 4px solid #075B5E;
                font-size: 14px;
            }
            h3 {
                color: #075B5E;
                margin-top: 0;
            }
            p {
                margin: 15px 0;
                font-size: 15px;
                color: #444;
            }
            .expiry-note {
                color: #e74c3c;
                font-weight: 500;
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
                
                <p class='expiry-note'>⏰ This OTP is valid for <strong>10 minutes</strong>. Do not share this code with anyone.</p>
                
                <div class='note'>
                    <strong>🔒 Security Note:</strong> If you didn't request this OTP, please ignore this email and contact our support team immediately.
                </div>
                
                <p>Once verified, your account will be pending admin approval. You'll receive another email once your account is approved.</p>
                
                <p>Best regards,<br>
                <strong>BillPay Pro Team</strong></p>
            </div>
            <div class='footer'>
                <p>This is an automated email. Please do not reply to this message.</p>
                <p>&copy; " . date('Y') . " BillPay Pro - Online Billing System. All rights reserved.</p>
                <p style='font-size: 11px; color: #999; margin-top: 5px;'>
                    Need help? Contact support at: support@billpaypro.com
                </p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">" . "\r\n";
    $headers .= "Reply-To: " . SMTP_FROM_EMAIL . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    $headers .= "X-Priority: 1 (Highest)\r\n";
    $headers .= "Importance: High\r\n";
    
    // For production, send actual email
    return mail($email, $subject, $message, $headers);
}

/**
 * Send account approval notification
 */
function sendApprovalEmail($email, $name) {
    require_once 'config.php';
    
    if (EMAIL_DEBUG) {
        error_log("[" . date('Y-m-d H:i:s') . "] Approval Email Debug - To: $email, Name: $name");
        return true;
    }
    
    $subject = "🎉 Account Approved - BillPay Pro";
    
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { 
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                line-height: 1.6; 
                color: #333; 
                margin: 0;
                padding: 0;
                background-color: #f5f5f5;
            }
            .container { 
                max-width: 600px; 
                margin: 0 auto; 
                padding: 0;
                background: white;
                border-radius: 10px;
                overflow: hidden;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            .header { 
                background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%); 
                color: white; 
                padding: 30px 20px; 
                text-align: center;
            }
            .header h2 {
                margin: 0;
                font-size: 24px;
                font-weight: 600;
            }
            .content { 
                padding: 40px 30px; 
                text-align: center;
            }
            .button { 
                display: inline-block; 
                background: linear-gradient(135deg, #075B5E 0%, #0a7c80 100%); 
                color: white; 
                padding: 14px 35px; 
                text-decoration: none; 
                border-radius: 8px; 
                margin: 25px 0;
                font-weight: 600;
                font-size: 16px;
                border: none;
                cursor: pointer;
                transition: all 0.3s ease;
                box-shadow: 0 4px 15px rgba(7, 91, 94, 0.3);
            }
            .button:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(7, 91, 94, 0.4);
            }
            .welcome-icon {
                font-size: 48px;
                margin-bottom: 20px;
            }
            h3 {
                color: #075B5E;
                margin-top: 0;
                font-size: 22px;
            }
            p {
                margin: 15px 0;
                font-size: 15px;
                color: #444;
                line-height: 1.8;
            }
            .features {
                background: #f8f9fa;
                padding: 20px;
                border-radius: 8px;
                margin: 25px 0;
                text-align: left;
            }
            .features ul {
                margin: 0;
                padding-left: 20px;
            }
            .features li {
                margin-bottom: 10px;
                color: #555;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Account Approved! 🎉</h2>
            </div>
            <div class='content'>
                <div class='welcome-icon'>✅</div>
                <h3>Congratulations " . htmlspecialchars($name) . "!</h3>
                <p>Great news! Your account has been approved by the administrator. You can now login and access all features of BillPay Pro.</p>
                
                <a href='" . SITE_URL . "customer/login.php' class='button'>🚀 Login to Your Account</a>
                
                <div class='features'>
                    <p><strong>Now you can:</strong></p>
                    <ul>
                        <li>📊 View and manage your invoices</li>
                        <li>💳 Make secure payments online</li>
                        <li>📈 Track your payment history</li>
                        <li>🔔 Receive important notifications</li>
                        <li>⚙️ Update your profile information</li>
                    </ul>
                </div>
                
                <p>If you have any questions or need assistance, our support team is always ready to help.</p>
                
                <p>Welcome aboard!<br>
                <strong>The BillPay Pro Team</strong></p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">" . "\r\n";
    $headers .= "Reply-To: " . SMTP_FROM_EMAIL . "\r\n";
    
    return mail($email, $subject, $message, $headers);
}

/**
 * Send account rejection notification
 */
function sendRejectionEmail($email, $name, $reason = '') {
    require_once 'config.php';
    
    if (EMAIL_DEBUG) {
        error_log("[" . date('Y-m-d H:i:s') . "] Rejection Email Debug - To: $email, Name: $name, Reason: $reason");
        return true;
    }
    
    $subject = "Account Registration Update - BillPay Pro";
    
    $reason_text = !empty($reason) ? "<p><strong>Reason provided:</strong> " . htmlspecialchars($reason) . "</p>" : "";
    
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { 
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                line-height: 1.6; 
                color: #333; 
                margin: 0;
                padding: 0;
                background-color: #f5f5f5;
            }
            .container { 
                max-width: 600px; 
                margin: 0 auto; 
                padding: 0;
                background: white;
                border-radius: 10px;
                overflow: hidden;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            .header { 
                background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); 
                color: white; 
                padding: 30px 20px; 
                text-align: center;
            }
            .header h2 {
                margin: 0;
                font-size: 24px;
                font-weight: 600;
            }
            .content { 
                padding: 40px 30px; 
            }
            .notice { 
                background: #fff3cd; 
                border-left: 4px solid #ffc107; 
                padding: 20px; 
                margin: 20px 0;
                border-radius: 5px;
            }
            .next-steps {
                background: #f8f9fa;
                padding: 20px;
                border-radius: 8px;
                margin: 25px 0;
                border-left: 4px solid #075B5E;
            }
            .next-steps h4 {
                margin-top: 0;
                color: #075B5E;
            }
            h3 {
                color: #075B5E;
                margin-top: 0;
            }
            p {
                margin: 15px 0;
                font-size: 15px;
                color: #444;
                line-height: 1.8;
            }
            .support-contact {
                background: #e7f4f4;
                padding: 15px;
                border-radius: 5px;
                margin-top: 25px;
                text-align: center;
                border: 1px solid #075B5E;
            }
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
                    " . $reason_text . "
                    <p>We understand this may be disappointing and appreciate your interest in our service.</p>
                </div>
                
                <div class='next-steps'>
                    <h4>What you can do next:</h4>
                    <p>1. <strong>Contact Support</strong> - If you believe this is a mistake or need clarification</p>
                    <p>2. <strong>Review Information</strong> - Ensure all registration details were accurate</p>
                    <p>3. <strong>Re-apply</strong> - You may submit a new registration with corrected information if applicable</p>
                </div>
                
                <div class='support-contact'>
                    <p><strong>📞 Support Contact:</strong><br>
                    Email: support@billpaypro.com<br>
                    Hours: Mon-Fri, 9 AM - 6 PM</p>
                </div>
                
                <p>Thank you for your understanding.</p>
                
                <p>Sincerely,<br>
                <strong>BillPay Pro Team</strong></p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">" . "\r\n";
    $headers .= "Reply-To: " . SMTP_FROM_EMAIL . "\r\n";
    
    return mail($email, $subject, $message, $headers);
}

/**
 * Send password reset email
 */
function sendPasswordResetEmail($email, $name, $reset_token) {
    require_once 'config.php';
    
    if (EMAIL_DEBUG) {
        error_log("[" . date('Y-m-d H:i:s') . "] Password Reset Email Debug - To: $email, Name: $name, Token: $reset_token");
        return true;
    }
    
    $reset_link = SITE_URL . "customer/reset_password.php?token=" . urlencode($reset_token);
    $subject = "Password Reset Request - BillPay Pro";
    
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #075B5E; color: white; padding: 20px; text-align: center; }
            .content { padding: 30px; background: #f9f9f9; }
            .reset-button { 
                display: inline-block; 
                background: #075B5E; 
                color: white; 
                padding: 12px 30px; 
                text-decoration: none; 
                border-radius: 5px; 
                margin: 20px 0;
            }
            .warning { 
                background: #fff3cd; 
                border-left: 4px solid #ffc107; 
                padding: 15px; 
                margin: 20px 0;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Password Reset</h2>
            </div>
            <div class='content'>
                <h3>Hello " . htmlspecialchars($name) . ",</h3>
                <p>We received a request to reset your password for your BillPay Pro account.</p>
                
                <p><a href='" . $reset_link . "' class='reset-button'>Reset Your Password</a></p>
                
                <div class='warning'>
                    <p><strong>Important:</strong> This password reset link will expire in 1 hour.</p>
                    <p>If you didn't request a password reset, please ignore this email or contact support if you're concerned.</p>
                </div>
                
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
    
    return mail($email, $subject, $message, $headers);
}
?>