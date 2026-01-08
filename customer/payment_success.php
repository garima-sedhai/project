<?php
session_start();
include '../includes/config.php';

// Redirect if not logged in as customer
if (!isset($_SESSION['user_id']) || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'])) {
    header("Location: login.php");
    exit();
}

// Check if we have success data in session
if (!isset($_SESSION['payment_success'])) {
    // Try to get from URL
    $transaction_id = isset($_GET['transaction_id']) ? $_GET['transaction_id'] : null;
    $admin_scan = isset($_GET['admin_scan']) ? $_GET['admin_scan'] : false;
    
    if ($transaction_id) {
        // Get payment details from database
        $stmt = $pdo->prepare("SELECT p.*, b.bill_number, b.bill_type, u.full_name 
                              FROM payments p 
                              JOIN bills b ON p.bill_id = b.id 
                              JOIN users u ON p.customer_id = u.id 
                              WHERE p.transaction_id = ? AND p.customer_id = ?");
        $stmt->execute([$transaction_id, $_SESSION['user_id']]);
        $payment = $stmt->fetch();
        
        if ($payment && $admin_scan) {
            $payment['admin_scan'] = true;
        }
    } else {
        header("Location: payment.php");
        exit();
    }
} else {
    // Use session data
    $success_data = $_SESSION['payment_success'];
    
    // Get basic payment info
    $stmt = $pdo->prepare("SELECT p.*, b.bill_number, b.bill_type, u.full_name 
                          FROM payments p 
                          JOIN bills b ON p.bill_id = b.id 
                          JOIN users u ON p.customer_id = u.id 
                          WHERE p.transaction_id = ? AND p.customer_id = ?
                          ORDER BY p.created_at DESC LIMIT 1");
    $stmt->execute([$success_data['transaction_id'], $_SESSION['user_id']]);
    $payment = $stmt->fetch();
    
    if ($payment && isset($success_data['admin_scan'])) {
        $payment['admin_scan'] = true;
    }
    
    // Clear session
    unset($_SESSION['payment_success']);
}

if (!$payment) {
    // If no payment found, try to get the latest successful payment
    $stmt = $pdo->prepare("SELECT p.*, b.bill_number, b.bill_type, u.full_name 
                          FROM payments p 
                          JOIN bills b ON p.bill_id = b.id 
                          JOIN users u ON p.customer_id = u.id 
                          WHERE p.customer_id = ? AND p.status = 'completed' 
                          ORDER BY p.created_at DESC 
                          LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $payment = $stmt->fetch();
    
    if (!$payment) {
        header("Location: payment.php");
        exit();
    }
}

// Clear any payment session
if (isset($_SESSION['payment_session'])) {
    unset($_SESSION['payment_session']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful - BillPay Pro</title>
    <link rel="stylesheet" href="../css/style.css">
    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        .success-container {
            max-width: 500px;
            margin: 2rem auto;
            text-align: center;
        }
        
        .success-icon {
            font-size: 4rem;
            color: #27ae60;
            margin-bottom: 1rem;
        }
        
        .receipt {
            background: #f8f9fa;
            padding: 2rem;
            border-radius: 8px;
            margin: 2rem 0;
            text-align: left;
        }
        
        .receipt-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #ddd;
        }
        
        .actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
            flex-wrap: wrap;
        }
        
        .confetti {
            position: fixed;
            width: 10px;
            height: 10px;
            background: #ff0;
            border-radius: 50%;
            animation: confetti-fall 5s linear forwards;
            z-index: 1000;
        }
        
        .success-badge {
            background: #27ae60;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            display: inline-block;
            margin-bottom: 1rem;
        }
        
        .icon {
            width: 4rem;
            height: 4rem;
            margin-bottom: 1rem;
        }
        
        .admin-badge {
            background: #3498db;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            display: inline-block;
            margin-left: 0.5rem;
        }
        
        @keyframes confetti-fall {
            0% {
                transform: translateY(-100px) rotate(0deg);
                opacity: 1;
            }
            100% {
                transform: translateY(100vh) rotate(360deg);
                opacity: 0;
            }
        }
        
        .notification-info {
            background: #e8f4fd;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
            text-align: left;
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
        <div class="success-container">
            <i data-lucide="check-circle" class="icon" style="color: #27ae60;"></i>
            <div class="success-badge">Payment Successful!</div>
            <?php if (isset($payment['admin_scan']) && $payment['admin_scan']): ?>
                <div class="admin-badge">Admin QR Scan</div>
            <?php endif; ?>
            <h1>Thank You for Your Payment!</h1>
            <p>Your payment has been processed successfully.</p>
            
            <?php if (isset($payment['admin_scan']) && $payment['admin_scan']): ?>
                <div class="notification-info">
                    <h4><i data-lucide="scan" style="width: 1.2rem; height: 1.2rem; margin-right: 0.5rem;"></i>Admin QR Scan Payment</h4>
                    <p>This payment was processed via Admin QR Code Scanner. The admin has been notified and payment is confirmed.</p>
                </div>
            <?php endif; ?>
            
            <div class="receipt">
                <h3>Payment Receipt</h3>
                <div class="receipt-item">
                    <span>Transaction ID:</span>
                    <strong><?php echo $payment['transaction_id']; ?></strong>
                </div>
                <div class="receipt-item">
                    <span>Bill Number:</span>
                    <strong><?php echo $payment['bill_number']; ?></strong>
                </div>
                <div class="receipt-item">
                    <span>Service:</span>
                    <strong><?php echo isset($payment['bill_type']) ? ucfirst($payment['bill_type']) : 'General'; ?></strong>
                </div>
                <div class="receipt-item">
                    <span>Amount Paid:</span>
                    <strong>₹<?php echo number_format($payment['amount'], 2); ?></strong>
                </div>
                <div class="receipt-item">
                    <span>Payment Method:</span>
                    <strong><?php echo ucfirst($payment['payment_method']); ?></strong>
                </div>
                <div class="receipt-item">
                    <span>Payment Status:</span>
                    <strong style="color: #27ae60;"><?php echo ucfirst($payment['status']); ?></strong>
                </div>
                <div class="receipt-item">
                    <span>Payment Date:</span>
                    <strong><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></strong>
                </div>
                <?php if (isset($payment['notes']) && !empty($payment['notes'])): ?>
                    <div class="receipt-item">
                        <span>Notes:</span>
                        <span><?php echo $payment['notes']; ?></span>
                    </div>
                <?php endif; ?>
                
                <!-- Notification Status -->
                <div class="receipt-item" style="border-top: 2px solid #3498db; padding-top: 1rem;">
                    <span><i data-lucide="bell" style="width: 1rem; height: 1rem; margin-right: 0.5rem;"></i>Notification Status:</span>
                    <strong style="color: #27ae60;">Sent to Admin</strong>
                </div>
            </div>
            
            <div class="notification-info">
                <h4><i data-lucide="info" style="width: 1.2rem; height: 1.2rem; margin-right: 0.5rem;"></i>What Happens Next?</h4>
                <ul>
                    <li><strong>✓ Admin Notification:</strong> Admin has been notified of your payment</li>
                    <li><strong>✓ Bill Status Updated:</strong> Bill marked as paid in the system</li>
                    <li><strong>✓ Payment Recorded:</strong> Payment added to your history</li>
                    <li><strong>✓ Email Confirmation:</strong> Receipt sent to your email (if configured)</li>
                </ul>
            </div>
            
            <div class="actions">
                <a href="dashboard.php" class="btn">Back to Dashboard</a>
                <a href="bills.php" class="btn" style="background: #3498db;">View Bills</a>
                <a href="payment_history.php" class="btn" style="background: #2ecc71;">Payment History</a>
                <button onclick="window.print()" class="btn" style="background: #95a5a6;">Print Receipt</button>
            </div>
            
            <div style="margin-top: 2rem; color: #666;">
                <p><strong>A confirmation has been sent to your registered email and phone number.</strong></p>
                <p><small>Keep this transaction ID for future reference: <code><?php echo $payment['transaction_id']; ?></code></small></p>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Online Billing System - BCA Project | Tribhuvan University</p>
        </div>
    </footer>

    <script>
        // Create confetti effect
        function createConfetti() {
            const colors = ['#ff0000', '#00ff00', '#0000ff', '#ffff00', '#ff00ff', '#00ffff'];
            for (let i = 0; i < 50; i++) {
                const confetti = document.createElement('div');
                confetti.className = 'confetti';
                confetti.style.left = Math.random() * 100 + 'vw';
                confetti.style.background = colors[Math.floor(Math.random() * colors.length)];
                confetti.style.animationDelay = Math.random() * 5 + 's';
                document.body.appendChild(confetti);
                
                // Remove confetti after animation
                setTimeout(() => {
                    confetti.remove();
                }, 6000);
            }
        }
        
        // Create confetti on page load
        document.addEventListener('DOMContentLoaded', function() {
            createConfetti();
            // Initialize Lucide Icons
            lucide.createIcons();
        });
    </script>
</body>
</html>