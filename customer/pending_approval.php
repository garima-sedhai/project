<?php
// For customer files:
$base_path = dirname(__DIR__);
require_once $base_path . '/includes/config.php';
session_start();
require_once $base_path . '/includes/db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Only show this page if user is not approved yet
if ($_SESSION['admin_approved'] || $_SESSION['is_admin']) {
    header("Location: dashboard.php");
    exit();
}

// Get user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Approval - BillPay Pro</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .approval-container {
            max-width: 600px;
            margin: 2rem auto;
            padding: 0 1rem;
            text-align: center;
        }
        
        .approval-icon {
            width: 100px;
            height: 100px;
            background: #fff3cd;
            color: #f39c12;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            margin: 0 auto 2rem;
        }
        
        .status-timeline {
            background: #f8f9fa;
            padding: 2rem;
            border-radius: 10px;
            margin: 2rem 0;
        }
        
        .timeline-step {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .timeline-step:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .step-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .step-complete .step-icon {
            background: #27ae60;
            color: white;
        }
        
        .step-current .step-icon {
            background: #f39c12;
            color: white;
        }
        
        .step-pending .step-icon {
            background: #e9ecef;
            color: #666;
        }
        
        .step-content {
            flex: 1;
            text-align: left;
        }
        
        .logout-btn {
            display: inline-block;
            padding: 10px 20px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <div class="logo">BillPay Pro</div>
                <ul class="nav-links">
                    <li><a href="../index.php">Home</a></li>
                    <li><a href="logout.php">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="container">
        <div class="approval-container">
            <div class="approval-icon">
                <i data-lucide="clock"></i>
            </div>
            
            <h1>Account Pending Approval</h1>
            <p>Hello, <?php echo htmlspecialchars($user['full_name']); ?>!</p>
            <p>Your account is currently pending admin approval. This process typically takes 24-48 hours.</p>
            
            <div class="status-timeline">
                <div class="timeline-step step-complete">
                    <div class="step-icon">
                        <i data-lucide="check" style="width: 1.2rem; height: 1.2rem;"></i>
                    </div>
                    <div class="step-content">
                        <h4>Registration Complete</h4>
                        <p>You have successfully registered with email: <?php echo htmlspecialchars($user['email']); ?></p>
                        <small><?php echo date('M d, Y h:i A', strtotime($user['created_at'])); ?></small>
                    </div>
                </div>
                
                <div class="timeline-step step-complete">
                    <div class="step-icon">
                        <i data-lucide="check" style="width: 1.2rem; height: 1.2rem;"></i>
                    </div>
                    <div class="step-content">
                        <h4>Email Verified</h4>
                        <p>Your email has been successfully verified</p>
                        <small>Status: Verified</small>
                    </div>
                </div>
                
                <div class="timeline-step step-current">
                    <div class="step-icon">
                        <i data-lucide="clock" style="width: 1.2rem; height: 1.2rem;"></i>
                    </div>
                    <div class="step-content">
                        <h4>Admin Approval</h4>
                        <p>Waiting for administrator to review and approve your account</p>
                        <small>Status: Pending</small>
                    </div>
                </div>
                
                <div class="timeline-step step-pending">
                    <div class="step-icon">4</div>
                    <div class="step-content">
                        <h4>Account Activation</h4>
                        <p>Once approved, you'll have full access to your account</p>
                        <small>Status: Not Started</small>
                    </div>
                </div>
            </div>
            
            <div style="background: #f0f7f7; padding: 1.5rem; border-radius: 10px; margin: 2rem 0;">
                <h4><i data-lucide="info" style="width: 1.2rem; height: 1.2rem; margin-right: 0.5rem; vertical-align: middle;"></i>What happens next?</h4>
                <ul style="text-align: left; margin: 1rem 0 0 1.5rem;">
                    <li>Administrator will review your registration details</li>
                    <li>You will receive an email notification once approved</li>
                    <li>After approval, you can login and access all features</li>
                    <li>If rejected, you'll receive an email with reasons</li>
                </ul>
            </div>
            
            <p>You will be automatically redirected to your dashboard once your account is approved.</p>
            <p><a href="logout.php" class="logout-btn"><i data-lucide="log-out" style="width: 1rem; height: 1rem; margin-right: 0.5rem;"></i>Logout</a></p>
            
            <script>
                // Auto-refresh page every 60 seconds to check approval status
                setTimeout(function() {
                    location.reload();
                }, 60000);
            </script>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> BillPay Pro - Online Billing System</p>
        </div>
    </footer>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>