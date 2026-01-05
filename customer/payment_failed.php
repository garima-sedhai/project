<?php
session_start();
include '../includes/config.php';

// Redirect if not logged in as customer
if (!isset($_SESSION['user_id']) || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'])) {
    header("Location: login.php");
    exit();
}

$error = isset($_GET['error']) ? $_GET['error'] : 'Payment processing failed';
$transaction_id = isset($_GET['transaction_id']) ? $_GET['transaction_id'] : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Failed - BillPay Pro</title>
    <link rel="stylesheet" href="../css/style.css">
    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        .failed-container {
            max-width: 500px;
            margin: 2rem auto;
            text-align: center;
        }
        
        .failed-icon {
            font-size: 4rem;
            color: #e74c3c;
            margin-bottom: 1rem;
        }
        
        .error-details {
            background: #f8f9fa;
            padding: 2rem;
            border-radius: 8px;
            margin: 2rem 0;
            text-align: left;
        }
        
        .actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
        }
        
        .support-info {
            background: #fff3cd;
            padding: 1rem;
            border-radius: 5px;
            margin-top: 2rem;
            border-left: 4px solid #ffc107;
        }
        
        .icon {
            width: 4rem;
            height: 4rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <div class="logo">BillPay Pro</div>
                <ul class="nav-links">
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="bills.php">My Bills</a></li>
                    <li><a href="payment.php">Make Payment</a></li>
                    <li><a href="payment_history.php">Payment History</a></li>
                    <li style="display: flex; align-items: center;">
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
                        <a href="logout.php" style="margin-left: 15px; color: white;">Logout</a>
                    </li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="container">
        <div class="failed-container">
            <i data-lucide="x-circle" class="icon" style="color: #e74c3c;"></i>
            <h1>Payment Failed</h1>
            <p>We couldn't process your payment. Please try again.</p>
            
            <div class="error-details">
                <h3>Error Details</h3>
                <p><strong>Reason:</strong> <?php echo htmlspecialchars($error); ?></p>
                <?php if ($transaction_id): ?>
                    <p><strong>Transaction ID:</strong> <?php echo $transaction_id; ?></p>
                <?php endif; ?>
                <p><strong>Date & Time:</strong> <?php echo date('M d, Y h:i A'); ?></p>
            </div>
            
            <div class="support-info">
                <h4>Need Help?</h4>
                <p>If you continue to experience issues, please:</p>
                <ul style="text-align: left;">
                    <li>Check your payment method details</li>
                    <li>Ensure you have sufficient balance</li>
                    <li>Try a different payment method</li>
                    <li>Contact support if the problem persists</li>
                </ul>
            </div>
            
            <div class="actions">
                <a href="payment.php" class="btn" style="background: #3498db;">Try Again</a>
                <a href="bills.php" class="btn">View Bills</a>
                <a href="dashboard.php" class="btn" style="background: #95a5a6;">Back to Dashboard</a>
            </div>
            
            <div style="margin-top: 2rem; color: #666;">
                <p><small>If money was deducted from your account, it will be refunded within 24 hours.</small></p>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Online Billing System - BCA Project | Tribhuvan University</p>
        </div>
    </footer>

    <script>
        // Initialize Lucide Icons
        lucide.createIcons();
    </script>
</body>
</html>