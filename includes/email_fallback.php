<?php
/**
 * Simple email fallback function using PHP's mail()
 * Only use if PHPMailer fails
 */

function send_email_fallback($to, $subject, $body, $is_html = true) {
    $headers = "MIME-Version: 1.0\r\n";
    
    if ($is_html) {
        $headers .= "Content-type:text/html;charset=UTF-8\r\n";
    }
    
    $headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">\r\n";
    $headers .= "Reply-To: " . SMTP_FROM_EMAIL . "\r\n";
    
    if (mail($to, $subject, $body, $headers)) {
        error_log("Fallback email sent to: $to");
        return true;
    } else {
        error_log("Fallback email failed to: $to");
        return false;
    }
}

/**
 * Test if mail() function works
 */
function test_mail_function() {
    $test_email = ADMIN_EMAIL;
    $test_subject = "Test Email - BillPay Pro (mail() function)";
    $test_body = "<p>Testing PHP mail() function</p>";
    
    ini_set('SMTP', 'smtp.gmail.com');
    ini_set('smtp_port', 587);
    
    return send_email_fallback($test_email, $test_subject, $test_body);
}
?>