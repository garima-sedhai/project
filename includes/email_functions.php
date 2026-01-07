<?php
/**
 * Email Functions for BillPay Pro System
 */

/**
 * Send OTP email to user
 */
function sendOTPEmail($email, $name, $otp) {
    require_once 'config.php';
    
    // Always try to send real email in production
    try {
        // Load PHPMailer from includes/phpmailer/
        require_once __DIR__ . '/phpmailer/Exception.php';
        require_once __DIR__ . '/phpmailer/PHPMailer.php';
        require_once __DIR__ . '/phpmailer/SMTP.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // SMTP Configuration for Gmail
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_FROM_EMAIL;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Debug output (only if DEBUG_MODE is true)
        if (DEBUG_MODE) {
            $mail->SMTPDebug = 2;
            $mail->Debugoutput = function($str, $level) {
                error_log("SMTP Debug: $str");
            };
        }
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($email, $name);
        
        // Email content
        $mail->isHTML(true);
        $mail->Subject = 'Your OTP Code - BillPay Pro';
        
        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; }
                .otp { font-size: 32px; font-weight: bold; color: #075B5E; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <h2>Hello " . htmlspecialchars($name) . ",</h2>
                <p>Your OTP code is: <span class='otp'>" . $otp . "</span></p>
                <p>This OTP is valid for 10 minutes.</p>
                <p>Do not share this code with anyone.</p>
                <p>Best regards,<br>BillPay Pro Team</p>
            </div>
        </body>
        </html>
        ";
        
        $mail->AltBody = "Hello $name,\n\nYour OTP code is: $otp\n\nThis OTP is valid for 10 minutes.\n\nDo not share this code with anyone.\n\nBest regards,\nBillPay Pro Team";
        
        // Send email
        if ($mail->send()) {
            error_log("[" . date('Y-m-d H:i:s') . "] OTP email sent to: $email");
            return true;
        } else {
            error_log("[" . date('Y-m-d H:i:s') . "] Failed to send OTP email to: $email");
            return false;
        }
        
    } catch (Exception $e) {
        error_log("[" . date('Y-m-d H:i:s') . "] PHPMailer Error for $email: " . $e->getMessage());
        return false;
    }
}

/**
 * Send account approval notification
 */
function sendApprovalEmail($email, $name) {
    // Similar structure as sendOTPEmail
    // ... (keep your existing code)
    return true;
}

/**
 * Send account rejection notification
 */
function sendRejectionEmail($email, $name, $reason = '') {
    // ... (keep your existing code)
    return true;
}

/**
 * Send password reset email
 */
function sendPasswordResetEmail($email, $name, $reset_token) {
    // ... (keep your existing code)
    return true;
}
?>