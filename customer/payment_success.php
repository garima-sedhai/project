<?php
session_start();
require_once '../includes/config.php';

// Check login
if (!isset($_SESSION['user_id']) || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'])) {
    header("Location: login.php");
    exit();
}

// Get success data from session
if (!isset($_SESSION['payment_success'])) {
    // Try to get latest payment
    $stmt = $pdo->prepare("SELECT p.*, b.bill_number, b.bill_type, u.full_name 
                          FROM payments p 
                          JOIN bills b ON p.bill_id = b.id 
                          JOIN users u ON p.customer_id = u.id 
                          WHERE p.customer_id = ? AND p.status = 'completed' 
                          ORDER BY p.created_at DESC LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $payment = $stmt->fetch();
    
    if (!$payment) {
        header("Location: payment.php");
        exit();
    }
} else {
    // Use session data
    $success_data = $_SESSION['payment_success'];
    
    // Get payment details
    $stmt = $pdo->prepare("SELECT p.*, b.bill_number, b.bill_type, u.full_name 
                          FROM payments p 
                          JOIN bills b ON p.bill_id = b.id 
                          JOIN users u ON p.customer_id = u.id 
                          WHERE p.id = ? AND p.customer_id = ?");
    $stmt->execute([$success_data['payment_id'], $_SESSION['user_id']]);
    $payment = $stmt->fetch();
    
    // Clear session
    unset($_SESSION['payment_success']);
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
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        .success-container {
            max-width: 500px;
            margin: 2rem auto;
            text-align: center;
        }
        
        .success-icon {
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
        
        .notification-badge {
            background: #3498db;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            display: inline-block;
            margin: 0.5rem 0;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="success-container">
            <i data-lucide="check-circle" class="success-icon" style="width: 4rem; height: 4rem;"></i>
            <div class="notification-badge">Payment Successful!</div>
            <h1>Thank You for Your Payment!</h1>
            <p>Your payment has been processed successfully.</p>
            
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
                    <span>Amount Paid:</span>
                    <strong>₹<?php echo number_format($payment['amount'], 2); ?></strong>
                </div>
                <div class="receipt-item">
                    <span>Payment Method:</span>
                    <strong><?php echo ucfirst($payment['payment_method']); ?></strong>
                </div>
                <div class="receipt-item">
                    <span>Payment Status:</span>
                    <strong style="color: #27ae60;">Completed</strong>
                </div>
                <div class="receipt-item">
                    <span>Payment Date:</span>
                    <strong><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></strong>
                </div>
                
                <div class="receipt-item" style="border-top: 2px solid #3498db; padding-top: 1rem;">
                    <span><i data-lucide="bell"></i> Notification:</span>
                    <strong style="color: #27ae60;">Sent to Admin</strong>
                </div>
            </div>
            
            <div style="background: #e8f4fd; padding: 1rem; border-radius: 8px; margin: 1rem 0; text-align: left;">
                <h4><i data-lucide="info"></i> What Happens Next?</h4>
                <ul>
                    <li><strong>✓ Admin Notified:</strong> Admin has been notified of your payment</li>
                    <li><strong>✓ Bill Updated:</strong> Bill marked as paid in system</li>
                    <li><strong>✓ Payment Recorded:</strong> Added to your payment history</li>
                    <li><strong>✓ Bill Removed:</strong> Bill removed from pending bills</li>
                </ul>
            </div>
            
            <div class="actions">
                <a href="dashboard.php" class="btn">Back to Dashboard</a>
                <a href="bills.php" class="btn" style="background: #3498db;">View Bills</a>
                <a href="payment_history.php" class="btn" style="background: #2ecc71;">Payment History</a>
                <button onclick="window.print()" class="btn" style="background: #95a5a6;">Print Receipt</button>
            </div>
            
            <div style="margin-top: 2rem; color: #666;">
                <p><strong>A confirmation has been sent to your registered email.</strong></p>
                <p><small>Keep transaction ID for reference: <code><?php echo $payment['transaction_id']; ?></code></small></p>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <script>
        lucide.createIcons();
        
        // Create confetti effect
        function createConfetti() {
            const colors = ['#ff0000', '#00ff00', '#0000ff', '#ffff00', '#ff00ff', '#00ffff'];
            for (let i = 0; i < 50; i++) {
                const confetti = document.createElement('div');
                confetti.style.cssText = `
                    position: fixed;
                    width: 10px;
                    height: 10px;
                    background: ${colors[Math.floor(Math.random() * colors.length)]};
                    border-radius: 50%;
                    animation: confetti-fall 5s linear forwards;
                    z-index: 1000;
                    left: ${Math.random() * 100}vw;
                    animation-delay: ${Math.random() * 5}s;
                `;
                document.body.appendChild(confetti);
                
                setTimeout(() => confetti.remove(), 6000);
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            createConfetti();
        });
    </script>
</body>
</html>