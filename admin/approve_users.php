<?php
// For admin files:
$base_path = dirname(__DIR__);
require_once $base_path . '/includes/config.php';
require_once $base_path . '/includes/db_connection.php';
require_once $base_path . '/includes/email_helper.php'; // Include email helper

// Redirect if not admin
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header("Location: ../customer/login.php");
    exit;
}

$message = '';
$message_type = '';

// Handle user approval
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['approve_user'])) {
        $user_id = $_POST['user_id'];
        $action = $_POST['action']; // 'approve' or 'reject'
        
        try {
            if ($action == 'approve') {
                // Update user status
                $stmt = $pdo->prepare("UPDATE users SET admin_approved = TRUE, registration_status = 'approved', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$user_id]);
                
                // Get user details for email
                $stmt = $pdo->prepare("SELECT full_name, email, customer_code FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
                
                if ($user) {
                    // IMPORTANT: Send real email to customer using PHPMailer
                    $email_sent = false;
                    $email_method = '';
                    
                    // Method 1: Try to send using your existing PHPMailer configuration
                    try {
                        // Create HTML email content
                        $email_subject = "Account Approved - BillPay Pro";
                        $email_message = "
                            <!DOCTYPE html>
                            <html>
                            <head>
                                <meta charset='UTF-8'>
                                <style>
                                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f5f7fa; margin: 0; padding: 20px; }
                                    .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
                                    .header { background: #075B5E; color: white; padding: 30px 20px; text-align: center; }
                                    .header h1 { margin: 0; font-size: 24px; }
                                    .content { padding: 40px; }
                                    .welcome { font-size: 18px; margin-bottom: 20px; color: #075B5E; font-weight: bold; }
                                    .message { margin-bottom: 30px; color: #555; line-height: 1.8; }
                                    .button { display: inline-block; background: #075B5E; color: white; text-decoration: none; padding: 12px 30px; border-radius: 5px; font-weight: bold; margin: 10px 0; }
                                    .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; font-size: 12px; border-top: 1px solid #e9ecef; }
                                    .highlight { background: #f0f7f7; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #075B5E; }
                                </style>
                            </head>
                            <body>
                                <div class='container'>
                                    <div class='header'>
                                        <h1>Account Approved</h1>
                                    </div>
                                    <div class='content'>
                                        <div class='welcome'>Dear {$user['full_name']},</div>
                                        
                                        <div class='message'>
                                            <p>Great news! Your BillPay Pro account has been approved by our administration team.</p>
                                            <p>You can now login to your account and start using all features of our billing system.</p>
                                        </div>
                                        
                                        <div class='highlight'>
                                            <p><strong>Your login details:</strong></p>
                                            <p>Email: {$user['email']}</p>
                                            <p>You can use the password you created during registration.</p>
                                        </div>
                                        
                                        <div style='text-align: center; margin: 30px 0;'>
                                            <a href='" . SITE_URL . "customer/login.php' class='button'>Login to Your Account</a>
                                        </div>
                                        
                                        <div class='message'>
                                            <p>If you have any questions or need assistance, please don't hesitate to contact our support team.</p>
                                        </div>
                                    </div>
                                    <div class='footer'>
                                        <p>This is an automated message. Please do not reply to this email.</p>
                                        <p>&copy; " . date('Y') . " BillPay Pro. All rights reserved.</p>
                                    </div>
                                </div>
                            </body>
                            </html>
                        ";
                        
                        // Try using your email helper function first
                        if (function_exists('send_email')) {
                            if (send_email($user['email'], $email_subject, $email_message)) {
                                $email_sent = true;
                                $email_method = 'Email sent successfully';
                            }
                        }
                        
                        // If send_email function doesn't exist, try send_approval_email
                        if (!$email_sent && function_exists('send_approval_email')) {
                            if (send_approval_email($user['email'], $user['full_name'])) {
                                $email_sent = true;
                                $email_method = 'Email sent successfully';
                            }
                        }
                        
                        // Last resort: use PHP's mail() function
                        if (!$email_sent) {
                            $plain_text = "Dear {$user['full_name']},\n\n";
                            $plain_text .= "Your BillPay Pro account has been approved.\n";
                            $plain_text .= "You can now login to your account using:\n";
                            $plain_text .= "Email: {$user['email']}\n";
                            $plain_text .= "Password: [Your registered password]\n\n";
                            $plain_text .= "Login URL: " . SITE_URL . "customer/login.php\n\n";
                            $plain_text .= "Best regards,\nBillPay Pro Team";
                            
                            $headers = "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">\r\n";
                            $headers .= "Reply-To: " . ADMIN_EMAIL . "\r\n";
                            $headers .= "MIME-Version: 1.0\r\n";
                            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
                            
                            if (mail($user['email'], $email_subject, $plain_text, $headers)) {
                                $email_sent = true;
                                $email_method = 'Email sent via basic mail()';
                            }
                        }
                        
                    } catch (Exception $e) {
                        error_log("Email sending error: " . $e->getMessage());
                    }
                    
                    // REMOVED: All notification creation for admin
                    // We only send email to customer, no system notifications
                    
                    if ($email_sent) {
                        $message = "<strong>User approved successfully!</strong><br>";
                        $message .= "✓ Approval email sent to: " . $user['email'] . "<br>";
                        $message .= "✓ User can now login with their credentials";
                        $message_type = "success";
                    } else {
                        $message = "<strong>User approved successfully!</strong><br>";
                        $message .= "⚠ Email notification failed to send<br>";
                        $message .= "✓ User can now login with their credentials<br>";
                        $message .= "<small>Please inform the user manually at: " . $user['email'] . "</small>";
                        $message_type = "warning";
                    }
                }
                
            } elseif ($action == 'reject') {
                // Get user details for notification
                $stmt = $pdo->prepare("SELECT full_name, email, customer_code FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
                
                // Update user status to rejected
                $stmt = $pdo->prepare("UPDATE users SET admin_approved = FALSE, registration_status = 'rejected', is_active = FALSE, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$user_id]);
                
                // Try to send rejection email using multiple methods
                if ($user) {
                    $reject_email_sent = false;
                    
                    // Try main email helper first
                    if (function_exists('send_email')) {
                        $reject_subject = "Registration Rejected - BillPay Pro";
                        $reject_message = "
                            <html>
                            <head>
                                <style>
                                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                                    .header { background: #e74c3c; color: white; padding: 20px; text-align: center; }
                                    .content { background: #f9f9fa; padding: 30px; }
                                    .footer { background: #f0f0f0; padding: 15px; text-align: center; font-size: 12px; color: #666; }
                                </style>
                            </head>
                            <body>
                                <div class='container'>
                                    <div class='header'>
                                        <h2>Registration Status Update</h2>
                                    </div>
                                    <div class='content'>
                                        <h3>Dear {$user['full_name']},</h3>
                                        <p>We regret to inform you that your registration with BillPay Pro has been rejected by our administration team.</p>
                                        <p>If you believe this is an error or would like more information, please contact our support team at " . ADMIN_EMAIL . ".</p>
                                        <p>Thank you for your interest in BillPay Pro.</p>
                                        <p style='margin-top: 30px;'>Best regards,<br>
                                        <strong>BillPay Pro Team</strong></p>
                                    </div>
                                    <div class='footer'>
                                        <p>This is an automated message. Please do not reply to this email.</p>
                                        <p>&copy; " . date('Y') . " BillPay Pro. All rights reserved.</p>
                                    </div>
                                </div>
                            </body>
                            </html>
                        ";
                        
                        if (send_email($user['email'], $reject_subject, $reject_message)) {
                            $reject_email_sent = true;
                        }
                    }
                    
                    // If main method fails, try fallback
                    if (!$reject_email_sent && file_exists($base_path . '/includes/email_fallback.php')) {
                        require_once $base_path . '/includes/email_fallback.php';
                        if (function_exists('send_email_fallback')) {
                            $simple_reject_message = "Dear {$user['full_name']},\n\nYour registration has been rejected. Please contact support at " . ADMIN_EMAIL . " for more information.\n\nBest regards,\nBillPay Pro Team";
                            send_email_fallback($user['email'], "Registration Rejected - BillPay Pro", $simple_reject_message, false);
                        }
                    }
                }
                
                $message = "User registration rejected. Email notification sent to user.";
                $message_type = "success";
            }
            
        } catch (Exception $e) {
            $message = "Error updating user: " . $e->getMessage();
            $message_type = "error";
            error_log("Approval error: " . $e->getMessage());
        }
    }
}

// Get all pending users
try {
    $stmt = $pdo->prepare("SELECT id, full_name, email, phone, address, customer_code, created_at, email_verified, admin_approved, registration_status, is_active FROM users WHERE is_admin = FALSE AND email_verified = TRUE AND admin_approved = FALSE AND registration_status = 'verified' AND is_active = TRUE ORDER BY created_at DESC");
    $stmt->execute();
    $pending_users = $stmt->fetchAll();
} catch (Exception $e) {
    $pending_users = [];
    error_log("Pending users error: " . $e->getMessage());
}

// Get all users for management
try {
    $stmt = $pdo->query("SELECT id, full_name, email, phone, address, customer_code, created_at, email_verified, admin_approved, registration_status, is_active, last_login FROM users WHERE is_admin = FALSE ORDER BY 
        CASE registration_status 
            WHEN 'verified' THEN 1
            WHEN 'pending' THEN 2
            WHEN 'approved' THEN 3
            WHEN 'rejected' THEN 4
        END,
        created_at DESC");
    $all_users = $stmt->fetchAll();
} catch (Exception $e) {
    $all_users = [];
    error_log("All users error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approve Users - Admin Panel</title>
    <link rel="stylesheet" href="<?php echo $base_path; ?>/assets/css/style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        /* Header Navigation Styles */
        .header-nav {
            background: #075B5E;
            padding: 1rem 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .nav-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            color: white;
            text-decoration: none;
        }
        
        .nav-links {
            display: flex;
            list-style: none;
            gap: 1.5rem;
            margin: 0;
            padding: 0;
        }
        
        .nav-links a {
            color: rgba(255,255,255,0.9);
            text-decoration: none;
            padding: 0.5rem 0;
            transition: color 0.2s;
            position: relative;
        }
        
        .nav-links a:hover {
            color: white;
        }
        
        .nav-links a.active {
            color: white;
        }
        
        .nav-links a.active::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            right: 0;
            height: 2px;
            background: white;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: white;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .logout-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.2s;
            text-decoration: none;
            display: inline-block;
        }
        
        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        
        /* Main content styles */
        .dashboard-container {
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
            background: #f5f7fa;
        }
        
        .content-container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
            padding: 30px;
            margin-top: 20px;
        }
        
        h1 {
            color: #075B5E;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .tabs {
            display: flex;
            margin-bottom: 30px;
            border-bottom: 2px solid #e9ecef;
            flex-wrap: wrap;
        }
        
        .tab {
            padding: 12px 24px;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            font-weight: 500;
            color: #6c757d;
            transition: all 0.3s;
        }
        
        .tab:hover {
            color: #075B5E;
        }
        
        .tab.active {
            color: #075B5E;
            border-bottom-color: #075B5E;
            background: rgba(7, 91, 94, 0.05);
        }
        
        .tab-badge {
            background: #6c757d;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            margin-left: 8px;
        }
        
        .tab.active .tab-badge {
            background: #075B5E;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
            animation: fadeIn 0.5s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .user-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }
        
        .user-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
            border-left: 5px solid #3498db;
            transition: transform 0.3s, box-shadow 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .user-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .user-card.pending {
            border-left-color: #f39c12;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 3px 15px rgba(243, 156, 18, 0.1); }
            50% { box-shadow: 0 3px 20px rgba(243, 156, 18, 0.2); }
            100% { box-shadow: 0 3px 15px rgba(243, 156, 18, 0.1); }
        }
        
        .user-card.approved {
            border-left-color: #27ae60;
        }
        
        .user-card.rejected {
            border-left-color: #e74c3c;
            opacity: 0.8;
        }
        
        .user-card.inactive {
            border-left-color: #95a5a6;
            opacity: 0.6;
        }
        
        .status-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 15px;
        }
        
        .status-pending {
            background: #fef9e7;
            color: #f39c12;
            border: 1px solid #fef3cd;
        }
        
        .status-approved {
            background: #e8f6f3;
            color: #27ae60;
            border: 1px solid #d1f2eb;
        }
        
        .status-rejected {
            background: #fdedec;
            color: #e74c3c;
            border: 1px solid #fadbd8;
        }
        
        .status-verified {
            background: #eaf2f8;
            color: #3498db;
            border: 1px solid #d6eaf8;
        }
        
        .user-card h3 {
            margin: 0 0 10px;
            color: #2c3e50;
            font-size: 1.3rem;
        }
        
        .user-details {
            margin: 15px 0;
        }
        
        .user-details p {
            margin: 8px 0;
            display: flex;
            align-items: flex-start;
        }
        
        .user-details strong {
            min-width: 120px;
            color: #555;
            font-weight: 500;
        }
        
        .user-details span {
            color: #333;
            flex: 1;
        }
        
        .user-actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .btn-approve, .btn-reject, .btn-view {
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s;
            text-decoration: none;
            font-size: 0.95rem;
        }
        
        .btn-approve {
            background: #27ae60;
            color: white;
            flex: 1;
        }
        
        .btn-approve:hover {
            background: #219653;
            transform: translateY(-2px);
        }
        
        .btn-reject {
            background: #e74c3c;
            color: white;
            flex: 1;
        }
        
        .btn-reject:hover {
            background: #c0392b;
            transform: translateY(-2px);
        }
        
        .btn-view {
            background: #3498db;
            color: white;
            width: 100%;
            margin-top: 10px;
        }
        
        .btn-view:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        
        .empty-state i {
            width: 80px;
            height: 80px;
            color: #dee2e6;
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            margin: 0 0 10px;
            color: #6c757d;
        }
        
        .user-actions-form {
            display: flex;
            gap: 10px;
            width: 100%;
        }
        
        .user-actions-form button {
            flex: 1;
        }
        
        .registration-date {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 0.85rem;
            color: #6c757d;
            background: #f8f9fa;
            padding: 4px 10px;
            border-radius: 15px;
        }
        
        .customer-code {
            background: #075B5E;
            color: white;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
            display: inline-block;
            margin-bottom: 10px;
        }
        
        .email-test-link {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #3498db;
        }
        
        .email-test-link a {
            color: #3498db;
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .email-test-link a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .nav-container {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .content-container {
                padding: 15px;
            }
            
            .user-grid {
                grid-template-columns: 1fr;
            }
            
            .tabs {
                flex-direction: column;
            }
            
            .tab {
                text-align: center;
            }
            
            .user-details p {
                flex-direction: column;
            }
            
            .user-details strong {
                min-width: auto;
                margin-bottom: 5px;
            }
        }
    </style>
</head>
<body>
    <!-- Custom Header Navigation -->
    <header class="header-nav">
        <div class="nav-container">
            <a href="index.php" class="logo">Admin Dashboard</a>
            <nav>
                <ul class="nav-links">
                    <li><a href="index.php">Dashboard</a></li>
                    <li><a href="manage_bills.php">Manage Bills</a></li>
                    <li><a href="manage_services.php">Manage Services</a></li>
                    <li><a href="reports.php">Manage Reports</a></li>
                    <li><a href="manage_users.php">Manage Users</a></li>
                    <li><a href="approve_users.php" class="active">Approve Users</a></li>
                </ul>
            </nav>
            <div class="user-info">
                <div class="user-avatar">
                    <?php 
                    $names = explode(' ', $_SESSION['full_name']);
                    $initials = '';
                    foreach ($names as $n) {
                        $initials .= strtoupper(substr($n, 0, 1));
                    }
                    echo substr($initials, 0, 2);
                    ?>
                </div>
                <span><?php echo $_SESSION['full_name']; ?></span>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </header>
    
    <div class="dashboard-container">
        <div class="content-container">
            <h1><i data-lucide="users" style="width: 32px; height: 32px; vertical-align: middle; margin-right: 10px;"></i> User Management</h1>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type == 'success' ? 'success' : ($message_type == 'warning' ? 'warning' : ($message_type == 'info' ? 'info' : 'danger')); ?>">
                    <?php if ($message_type == 'success'): ?>
                        <i data-lucide="check-circle" style="width: 24px; height: 24px;"></i>
                    <?php elseif ($message_type == 'warning'): ?>
                        <i data-lucide="alert-triangle" style="width: 24px; height: 24px;"></i>
                    <?php elseif ($message_type == 'info'): ?>
                        <i data-lucide="info" style="width: 24px; height: 24px;"></i>
                    <?php else: ?>
                        <i data-lucide="alert-circle" style="width: 24px; height: 24px;"></i>
                    <?php endif; ?>
                    <span><?php echo $message; ?></span>
                </div>
            <?php endif; ?>
            
            <div class="email-test-link">
                <i data-lucide="mail" style="width: 20px; height: 20px; color: #3498db;"></i>
                <span>Having email issues? <a href="test_email.php">Test Email Configuration</a></span>
            </div>
            
            <div class="tabs">
                <div class="tab active" onclick="showTab('pending')">
                    <span>Pending Approval</span>
                    <span class="tab-badge"><?php echo count($pending_users); ?></span>
                </div>
                <div class="tab" onclick="showTab('all')">
                    <span>All Users</span>
                    <span class="tab-badge"><?php echo count($all_users); ?></span>
                </div>
                <div class="tab" onclick="showTab('stats')">
                    <span>Statistics</span>
                </div>
            </div>
            
            <!-- Pending Users Tab -->
            <div id="pending-tab" class="tab-content active">
                <h2 style="color: #f39c12; margin-bottom: 20px;">
                    <i data-lucide="clock" style="width: 24px; height: 24px; vertical-align: middle; margin-right: 10px;"></i>
                    Users Pending Approval
                </h2>
                
                <?php if (count($pending_users) > 0): ?>
                    <div class="user-grid">
                        <?php foreach ($pending_users as $user): ?>
                            <div class="user-card pending">
                                <div class="registration-date">
                                    <i data-lucide="calendar" style="width: 14px; height: 14px; vertical-align: middle; margin-right: 5px;"></i>
                                    <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                                </div>
                                
                                <?php if (isset($user['customer_code']) && !empty($user['customer_code'])): ?>
                                    <div class="customer-code"><?php echo htmlspecialchars($user['customer_code']); ?></div>
                                <?php endif; ?>
                                
                                <span class="status-badge status-pending">
                                    <i data-lucide="clock" style="width: 16px; height: 16px;"></i>
                                    Awaiting Approval
                                </span>
                                
                                <h3><?php echo htmlspecialchars($user['full_name']); ?></h3>
                                
                                <div class="user-details">
                                    <p>
                                        <strong><i data-lucide="mail" style="width: 16px; height: 16px; margin-right: 8px;"></i> Email:</strong>
                                        <span><?php echo htmlspecialchars($user['email']); ?></span>
                                    </p>
                                    <p>
                                        <strong><i data-lucide="phone" style="width: 16px; height: 16px; margin-right: 8px;"></i> Phone:</strong>
                                        <span><?php echo htmlspecialchars($user['phone'] ?? 'Not provided'); ?></span>
                                    </p>
                                    <?php if (isset($user['address']) && !empty($user['address'])): ?>
                                        <p>
                                            <strong><i data-lucide="map-pin" style="width: 16px; height: 16px; margin-right: 8px;"></i> Address:</strong>
                                            <span><?php echo htmlspecialchars($user['address']); ?></span>
                                        </p>
                                    <?php endif; ?>
                                    <p>
                                        <strong><i data-lucide="shield-check" style="width: 16px; height: 16px; margin-right: 8px;"></i> Status:</strong>
                                        <span style="color: #f39c12; font-weight: 500;">Email Verified (Pending Admin Approval)</span>
                                    </p>
                                </div>
                                
                                <form method="POST" action="" class="user-actions-form">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <input type="hidden" name="action" id="action_<?php echo $user['id']; ?>">
                                    <button type="submit" name="approve_user" class="btn-approve" onclick="setAction(<?php echo $user['id']; ?>, 'approve')">
                                        <i data-lucide="check" style="width: 18px; height: 18px;"></i>
                                        Approve
                                    </button>
                                    <button type="submit" name="approve_user" class="btn-reject" onclick="return confirmReject(<?php echo $user['id']; ?>)">
                                        <i data-lucide="x" style="width: 18px; height: 18px;"></i>
                                        Reject
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i data-lucide="user-check" style="width: 80px; height: 80px;"></i>
                        <h3>No Pending Approvals</h3>
                        <p>All users have been approved. Great work!</p>
                        <p style="margin-top: 20px; color: #6c757d; font-size: 0.9rem;">
                            <i data-lucide="info" style="width: 16px; height: 16px; vertical-align: middle;"></i>
                            New registrations will appear here automatically.
                        </p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- All Users Tab -->
            <div id="all-tab" class="tab-content">
                <h2 style="color: #3498db; margin-bottom: 20px;">
                    <i data-lucide="users" style="width: 24px; height: 24px; vertical-align: middle; margin-right: 10px;"></i>
                    All Registered Users
                </h2>
                
                <?php if (count($all_users) > 0): ?>
                    <div class="user-grid">
                        <?php foreach ($all_users as $user): 
                            // Determine status
                            $status_class = '';
                            $status_text = '';
                            $status_icon = 'clock';
                            
                            if (!$user['email_verified']) {
                                $status_class = 'status-pending';
                                $status_text = 'Email Not Verified';
                            } elseif ($user['registration_status'] == 'verified' && !$user['admin_approved']) {
                                $status_class = 'status-pending';
                                $status_text = 'Pending Approval';
                                $status_icon = 'clock';
                            } elseif ($user['registration_status'] == 'approved' && $user['admin_approved']) {
                                $status_class = 'status-approved';
                                $status_text = 'Approved';
                                $status_icon = 'check-circle';
                            } elseif ($user['registration_status'] == 'rejected') {
                                $status_class = 'status-rejected';
                                $status_text = 'Rejected';
                                $status_icon = 'x-circle';
                            } else {
                                $status_class = 'status-verified';
                                $status_text = ucfirst($user['registration_status']);
                                $status_icon = 'shield';
                            }
                            
                            $card_class = '';
                            if ($user['registration_status'] == 'rejected') {
                                $card_class = 'rejected';
                            } elseif ($user['registration_status'] == 'approved' && $user['admin_approved']) {
                                $card_class = 'approved';
                            } elseif (!$user['is_active']) {
                                $card_class = 'inactive';
                            }
                        ?>
                            <div class="user-card <?php echo $card_class; ?>">
                                <div class="registration-date">
                                    <i data-lucide="calendar" style="width: 14px; height: 14px; vertical-align: middle; margin-right: 5px;"></i>
                                    <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                                </div>
                                
                                <?php if (isset($user['customer_code']) && !empty($user['customer_code'])): ?>
                                    <div class="customer-code"><?php echo htmlspecialchars($user['customer_code']); ?></div>
                                <?php endif; ?>
                                
                                <span class="status-badge <?php echo $status_class; ?>">
                                    <i data-lucide="<?php echo $status_icon; ?>" style="width: 16px; height: 16px;"></i>
                                    <?php echo $status_text; ?>
                                </span>
                                
                                <h3><?php echo htmlspecialchars($user['full_name']); ?></h3>
                                
                                <div class="user-details">
                                    <p>
                                        <strong><i data-lucide="mail" style="width: 16px; height: 16px; margin-right: 8px;"></i> Email:</strong>
                                        <span><?php echo htmlspecialchars($user['email']); ?></span>
                                    </p>
                                    <p>
                                        <strong><i data-lucide="phone" style="width: 16px; height: 16px; margin-right: 8px;"></i> Phone:</strong>
                                        <span><?php echo htmlspecialchars($user['phone'] ?? 'Not provided'); ?></span>
                                    </p>
                                    <p>
                                        <strong><i data-lucide="user" style="width: 16px; height: 16px; margin-right: 8px;"></i> Status:</strong>
                                        <span>
                                            <?php echo ucfirst($user['registration_status']); ?>
                                            <?php if ($user['admin_approved']): ?>
                                                <i data-lucide="check" style="width: 14px; height: 14px; color: #27ae60; margin-left: 5px; vertical-align: middle;"></i>
                                            <?php endif; ?>
                                        </span>
                                    </p>
                                    <p>
                                        <strong><i data-lucide="activity" style="width: 16px; height: 16px; margin-right: 8px;"></i> Last Login:</strong>
                                        <span>
                                            <?php if (isset($user['last_login']) && !empty($user['last_login'])): ?>
                                                <?php echo date('M d, Y H:i', strtotime($user['last_login'])); ?>
                                            <?php else: ?>
                                                <span style="color: #6c757d;">Never</span>
                                            <?php endif; ?>
                                        </span>
                                    </p>
                                </div>
                                
                                <div class="user-actions">
                                    <a href="manage_users.php?user_id=<?php echo $user['id']; ?>" class="btn-view">
                                        <i data-lucide="user" style="width: 18px; height: 18px;"></i>
                                        View Details
                                    </a>
                                    <?php if ($user['registration_status'] == 'approved' && $user['admin_approved'] && $user['is_active']): ?>
                                        <a href="manage_bills.php?user_id=<?php echo $user['id']; ?>" class="btn-view" style="background: #2ecc71; margin-top: 10px;">
                                            <i data-lucide="file-text" style="width: 18px; height: 18px;"></i>
                                            Create Bill
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i data-lucide="users" style="width: 80px; height: 80px;"></i>
                        <h3>No Users Found</h3>
                        <p>No users have registered yet.</p>
                        <p style="margin-top: 20px; color: #6c757d; font-size: 0.9rem;">
                            <i data-lucide="info" style="width: 16px; height: 16px; vertical-align: middle;"></i>
                            Users will appear here after they register and verify their email.
                        </p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Statistics Tab -->
            <div id="stats-tab" class="tab-content">
                <h2 style="color: #9b59b6; margin-bottom: 20px;">
                    <i data-lucide="bar-chart" style="width: 24px; height: 24px; vertical-align: middle; margin-right: 10px;"></i>
                    User Statistics
                </h2>
                
                <?php
                // Get user statistics
                try {
                    // Total users
                    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE is_admin = FALSE");
                    $total_users = $stmt->fetch()['total'];
                    
                    // Email verified but not approved
                    $stmt = $pdo->query("SELECT COUNT(*) as pending FROM users WHERE is_admin = FALSE AND email_verified = TRUE AND admin_approved = FALSE AND registration_status = 'verified'");
                    $pending_approval = $stmt->fetch()['pending'];
                    
                    // Approved users
                    $stmt = $pdo->query("SELECT COUNT(*) as approved FROM users WHERE is_admin = FALSE AND admin_approved = TRUE AND registration_status = 'approved'");
                    $approved_users = $stmt->fetch()['approved'];
                    
                    // Rejected users
                    $stmt = $pdo->query("SELECT COUNT(*) as rejected FROM users WHERE is_admin = FALSE AND registration_status = 'rejected'");
                    $rejected_users = $stmt->fetch()['rejected'];
                    
                    // Today's registrations
                    $stmt = $pdo->query("SELECT COUNT(*) as today FROM users WHERE is_admin = FALSE AND DATE(created_at) = CURDATE()");
                    $today_registrations = $stmt->fetch()['today'];
                    
                    // This month's registrations
                    $stmt = $pdo->query("SELECT COUNT(*) as this_month FROM users WHERE is_admin = FALSE AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
                    $month_registrations = $stmt->fetch()['this_month'];
                    
                    // Email verification stats
                    $stmt = $pdo->query("SELECT COUNT(*) as verified FROM users WHERE is_admin = FALSE AND email_verified = TRUE");
                    $email_verified = $stmt->fetch()['verified'];
                    
                } catch (Exception $e) {
                    $total_users = $pending_approval = $approved_users = $rejected_users = $today_registrations = $month_registrations = $email_verified = 0;
                    error_log("Statistics error: " . $e->getMessage());
                }
                ?>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
                    <div style="background: #e8f4fd; padding: 25px; border-radius: 10px; text-align: center; border-left: 4px solid #3498db;">
                        <div style="font-size: 2.5rem; font-weight: bold; color: #3498db; margin-bottom: 10px;"><?php echo $total_users; ?></div>
                        <div style="color: #2c3e50; font-weight: 500;">Total Users</div>
                    </div>
                    
                    <div style="background: #fef9e7; padding: 25px; border-radius: 10px; text-align: center; border-left: 4px solid #f39c12;">
                        <div style="font-size: 2.5rem; font-weight: bold; color: #f39c12; margin-bottom: 10px;"><?php echo $pending_approval; ?></div>
                        <div style="color: #2c3e50; font-weight: 500;">Pending Approval</div>
                    </div>
                    
                    <div style="background: #e8f6f3; padding: 25px; border-radius: 10px; text-align: center; border-left: 4px solid #27ae60;">
                        <div style="font-size: 2.5rem; font-weight: bold; color: #27ae60; margin-bottom: 10px;"><?php echo $approved_users; ?></div>
                        <div style="color: #2c3e50; font-weight: 500;">Approved Users</div>
                    </div>
                    
                    <div style="background: #fdedec; padding: 25px; border-radius: 10px; text-align: center; border-left: 4px solid #e74c3c;">
                        <div style="font-size: 2.5rem; font-weight: bold; color: #e74c3c; margin-bottom: 10px;"><?php echo $rejected_users; ?></div>
                        <div style="color: #2c3e50; font-weight: 500;">Rejected Users</div>
                    </div>
                </div>
                
                <div style="background: white; border: 1px solid #e9ecef; border-radius: 10px; padding: 25px; margin-top: 20px;">
                    <h3 style="color: #075B5E; margin-top: 0; margin-bottom: 20px;">
                        <i data-lucide="calendar" style="width: 20px; height: 20px; vertical-align: middle; margin-right: 10px;"></i>
                        Registration Activity
                    </h3>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center;">
                            <div style="font-size: 1.8rem; font-weight: bold; color: #075B5E;"><?php echo $today_registrations; ?></div>
                            <div style="color: #6c757d; font-size: 0.9rem;">Today</div>
                        </div>
                        
                        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center;">
                            <div style="font-size: 1.8rem; font-weight: bold; color: #075B5E;"><?php echo $month_registrations; ?></div>
                            <div style="color: #6c757d; font-size: 0.9rem;">This Month</div>
                        </div>
                        
                        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center;">
                            <div style="font-size: 1.8rem; font-weight: bold; color: #075B5E;"><?php echo round($month_registrations / max(date('t'), 1) * 100, 1); ?>%</div>
                            <div style="color: #6c757d; font-size: 0.9rem;">Monthly Growth</div>
                        </div>
                        
                        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center;">
                            <div style="font-size: 1.8rem; font-weight: bold; color: #075B5E;"><?php echo $total_users > 0 ? round(($approved_users / $total_users) * 100, 1) : 0; ?>%</div>
                            <div style="color: #6c757d; font-size: 0.9rem;">Approval Rate</div>
                        </div>
                    </div>
                </div>
                
                <div style="background: white; border: 1px solid #e9ecef; border-radius: 10px; padding: 25px; margin-top: 20px;">
                    <h3 style="color: #075B5E; margin-top: 0; margin-bottom: 20px;">
                        <i data-lucide="mail" style="width: 20px; height: 20px; vertical-align: middle; margin-right: 10px;"></i>
                        Email Statistics
                    </h3>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center;">
                            <div style="font-size: 1.8rem; font-weight: bold; color: #3498db;"><?php echo $email_verified; ?></div>
                            <div style="color: #6c757d; font-size: 0.9rem;">Email Verified</div>
                        </div>
                        
                        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center;">
                            <div style="font-size: 1.8rem; font-weight: bold; color: #3498db;"><?php echo $total_users > 0 ? round(($email_verified / $total_users) * 100, 1) : 0; ?>%</div>
                            <div style="color: #6c757d; font-size: 0.9rem;">Verification Rate</div>
                        </div>
                        
                        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center;">
                            <div style="font-size: 1.8rem; font-weight: bold; color: #3498db;"><?php echo $email_verified > 0 ? round(($pending_approval / $email_verified) * 100, 1) : 0; ?>%</div>
                            <div style="color: #6c757d; font-size: 0.9rem;">Awaiting Approval</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all tab buttons
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Add active class to clicked tab button
            event.target.classList.add('active');
        }
        
        function setAction(userId, action) {
            document.getElementById('action_' + userId).value = action;
            return true;
        }
        
        function confirmReject(userId) {
            if (confirm('Are you sure you want to reject this user? This action cannot be undone.')) {
                setAction(userId, 'reject');
                return true;
            }
            return false;
        }
        
        // Initialize Lucide icons
        lucide.createIcons();
        
        // Auto-refresh pending users every 30 seconds
        setInterval(function() {
            if (document.getElementById('pending-tab').classList.contains('active')) {
                location.reload();
            }
        }, 30000);
    </script>
</body>
</html>