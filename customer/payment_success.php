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
    // Try to get latest payment - FIXED: using user_id instead of customer_id
    $stmt = $pdo->prepare("SELECT p.*, b.bill_number, b.bill_type, u.full_name 
                          FROM payments p 
                          JOIN bills b ON p.bill_id = b.id 
                          JOIN users u ON p.user_id = u.id 
                          WHERE p.user_id = ? AND p.status = 'completed' 
                          ORDER BY p.created_at DESC LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $payment = $stmt->fetch();
    
    if (!$payment) {
        // Alternative query if user_id column doesn't exist
        $stmt = $pdo->prepare("SELECT p.*, b.bill_number, b.bill_type, u.full_name 
                              FROM payments p 
                              JOIN bills b ON p.bill_id = b.id 
                              JOIN users u ON b.user_id = u.id 
                              WHERE b.user_id = ? AND p.status = 'completed' 
                              ORDER BY p.created_at DESC LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $payment = $stmt->fetch();
        
        if (!$payment) {
            header("Location: payment.php");
            exit();
        }
    }
} else {
    // Use session data
    $success_data = $_SESSION['payment_success'];
    
    // Get payment details - FIXED: using user_id instead of customer_id
    $stmt = $pdo->prepare("SELECT p.*, b.bill_number, b.bill_type, u.full_name 
                          FROM payments p 
                          JOIN bills b ON p.bill_id = b.id 
                          JOIN users u ON p.user_id = u.id 
                          WHERE p.id = ? AND p.user_id = ?");
    $stmt->execute([$success_data['payment_id'], $_SESSION['user_id']]);
    $payment = $stmt->fetch();
    
    if (!$payment) {
        // Try alternative query
        $stmt = $pdo->prepare("SELECT p.*, b.bill_number, b.bill_type, u.full_name 
                              FROM payments p 
                              JOIN bills b ON p.bill_id = b.id 
                              JOIN users u ON b.user_id = u.id 
                              WHERE p.id = ? AND b.user_id = ?");
        $stmt->execute([$success_data['payment_id'], $_SESSION['user_id']]);
        $payment = $stmt->fetch();
    }
    
    // Clear session
    unset($_SESSION['payment_success']);
}

// Clear any payment session
if (isset($_SESSION['payment_session'])) {
    unset($_SESSION['payment_session']);
}

// Debug: Check what we got
error_log("Payment success page - Payment data: " . print_r($payment, true));
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
            max-width: 800px;
            margin: 2rem auto;
        }
        
        .success-header {
            background: linear-gradient(135deg, #53c41a 0%, #389e0d 100%);
            color: white;
            padding: 2.5rem;
            border-radius: 10px 10px 0 0;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .success-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: rgba(255, 255, 255, 0.3);
        }
        
        .success-body {
            background: white;
            padding: 2rem;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .success-icon {
            color: white;
            margin-bottom: 1rem;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
        }
        
        .receipt {
            background: #f8f9fa;
            padding: 2rem;
            border-radius: 8px;
            margin: 2rem 0;
            text-align: left;
            border: 1px solid #e9ecef;
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
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 0.5rem 1.5rem;
            border-radius: 20px;
            display: inline-block;
            margin: 0.5rem 0;
            font-weight: 500;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .debug-info {
            background: #f0f0f0;
            padding: 10px;
            margin: 10px;
            border: 2px solid #3498db;
            font-family: monospace;
            font-size: 12px;
            display: none;
        }
        
        .success-message {
            margin: 1rem 0;
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .whats-next {
            background: linear-gradient(135deg, #e8f4fd 0%, #f0f7ff 100%);
            padding: 1.5rem;
            border-radius: 8px;
            margin: 1rem 0;
            text-align: left;
            border-left: 4px solid #3498db;
        }
        
        .whats-next h4 {
            color: #3498db;
            margin-top: 0;
        }
        
        .whats-next ul {
            margin: 0.5rem 0;
            padding-left: 1.2rem;
        }
        
        .whats-next li {
            margin-bottom: 0.5rem;
            color: #2c3e50;
        }
        
        .confirmation-note {
            background: #fff3cd;
            padding: 1rem;
            border-radius: 8px;
            margin: 2rem 0;
            border-left: 4px solid #ffc107;
        }
        
        @media (max-width: 768px) {
            .success-header {
                padding: 1.5rem;
            }
            
            .success-body {
                padding: 1.5rem;
            }
            
            .actions {
                flex-direction: column;
                align-items: center;
            }
            
            .actions .btn {
                width: 100%;
                max-width: 300px;
                margin-bottom: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <!-- Debug Info (visible with ?debug=1) -->
    <div class="debug-info" style="display: <?php echo isset($_GET['debug']) ? 'block' : 'none'; ?>">
        <h4>Debug Information:</h4>
        <p>Payment ID: <?php echo $payment['id'] ?? 'N/A'; ?></p>
        <p>Bill ID: <?php echo $payment['bill_id'] ?? 'N/A'; ?></p>
        <p>User ID: <?php echo $_SESSION['user_id']; ?></p>
        <p>Payment Method: <?php echo $payment['payment_method'] ?? 'N/A'; ?></p>
        <p>Status: <?php echo $payment['status'] ?? 'N/A'; ?></p>
    </div>
    
    <div class="container">
        <div class="success-container">
            <!-- Green Header Section -->
            <div class="success-header">
                <i data-lucide="check-circle" class="success-icon" style="width: 4rem; height: 4rem;"></i>
                <div class="notification-badge">
                    <i data-lucide="check" style="width: 1.2rem; height: 1.2rem; margin-right: 0.5rem; vertical-align: middle;"></i>
                    Payment Successful!
                </div>
                <h1 style="margin: 1rem 0; font-weight: 600;">Thank You for Your Payment!</h1>
                <p class="success-message">Your payment has been processed successfully and confirmed.</p>
            </div>
            
            <div class="success-body">
                <div class="receipt">
                    <h3 style="color: #2c3e50; margin-top: 0; border-bottom: 2px solid #53c41a; padding-bottom: 0.5rem;">
                        <i data-lucide="receipt" style="width: 1.2rem; height: 1.2rem; margin-right: 0.5rem; vertical-align: middle; color: #53c41a;"></i>
                        Payment Receipt
                    </h3>
                    <div class="receipt-item">
                        <span>Transaction ID:</span>
                        <strong><?php echo htmlspecialchars($payment['transaction_id'] ?? 'N/A'); ?></strong>
                    </div>
                    <div class="receipt-item">
                        <span>Bill Number:</span>
                        <strong><?php echo htmlspecialchars($payment['bill_number'] ?? 'N/A'); ?></strong>
                    </div>
                    <div class="receipt-item">
                        <span>Amount Paid:</span>
                        <strong style="color: #e74c3c; font-size: 1.1rem;">₹<?php echo number_format($payment['amount'] ?? 0, 2); ?></strong>
                    </div>
                    <div class="receipt-item">
                        <span>Payment Method:</span>
                        <strong><?php echo ucfirst($payment['payment_method'] ?? 'esewa'); ?></strong>
                    </div>
                    <div class="receipt-item">
                        <span>Payment Status:</span>
                        <strong style="color: #27ae60; background: #d4edda; padding: 0.25rem 0.75rem; border-radius: 20px;">
                            <?php echo ucfirst($payment['status'] ?? 'completed'); ?>
                        </strong>
                    </div>
                    <div class="receipt-item">
                        <span>Payment Date:</span>
                        <strong>
                            <?php 
                            if (isset($payment['payment_date']) && $payment['payment_date']) {
                                echo date('M d, Y', strtotime($payment['payment_date']));
                            } elseif (isset($payment['created_at'])) {
                                echo date('M d, Y', strtotime($payment['created_at']));
                            } else {
                                echo date('M d, Y');
                            }
                            ?>
                        </strong>
                    </div>
                    
                    <div class="receipt-item" style="border-top: 2px solid #3498db; padding-top: 1rem; margin-top: 1rem;">
                        <span><i data-lucide="bell" style="color: #3498db;"></i> Notification:</span>
                        <strong style="color: #27ae60;">Sent to Admin</strong>
                    </div>
                </div>
                
                
                
                <!--<div class="confirmation-note">
                    <h4><i data-lucide="mail" style="width: 1.2rem; height: 1.2rem; margin-right: 0.5rem; vertical-align: middle;"></i> Confirmation Sent</h4>
                    <p style="margin: 0.5rem 0 0 0;"><strong>A confirmation has been sent to your registered email.</strong></p>
                    <p style="margin: 0.5rem 0; color: #666; font-size: 0.9rem;">
                        Keep transaction ID for reference: <code style="background: #f8f9fa; padding: 0.25rem 0.5rem; border-radius: 4px; font-family: monospace;"><?php echo htmlspecialchars($payment['transaction_id'] ?? 'N/A'); ?></code>
                    </p>
                </div>-->
                
                <div class="actions">
                    <a href="dashboard.php" class="btn" style="background: #53c41a;">
                        <i data-lucide="home" style="width: 1.2rem; height: 1.2rem; margin-right: 0.5rem;"></i> Back to Dashboard
                    </a>
                    <a href="bills.php" class="btn" style="background: #3498db;">
                        <i data-lucide="file-text" style="width: 1.2rem; height: 1.2rem; margin-right: 0.5rem;"></i> View Bills
                    </a>
                    <a href="payment_history.php" class="btn" style="background: #2ecc71;">
                        <i data-lucide="history" style="width: 1.2rem; height: 1.2rem; margin-right: 0.5rem;"></i> Payment History
                    </a>
                    <button onclick="window.print()" class="btn" style="background: #95a5a6;">
                        <i data-lucide="printer" style="width: 1.2rem; height: 1.2rem; margin-right: 0.5rem;"></i> Print Receipt
                    </button>
                    <!--<a href="?debug=1" class="btn" style="background: #f39c12;">
                        <i data-lucide="bug" style="width: 1.2rem; height: 1.2rem; margin-right: 0.5rem;"></i> Debug Info
                    </a>-->
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <script>
        lucide.createIcons();
        
        // Create confetti effect
        function createConfetti() {
            const colors = ['#53c41a', '#389e0d', '#27ae60', '#2ecc71', '#3498db', '#2980b9'];
            for (let i = 0; i < 60; i++) {
                const confetti = document.createElement('div');
                confetti.style.cssText = `
                    position: fixed;
                    width: ${Math.random() * 10 + 5}px;
                    height: ${Math.random() * 10 + 5}px;
                    background: ${colors[Math.floor(Math.random() * colors.length)]};
                    border-radius: ${Math.random() > 0.5 ? '50%' : '4px'};
                    animation: confetti-fall ${Math.random() * 3 + 2}s linear forwards;
                    z-index: 1000;
                    left: ${Math.random() * 100}vw;
                    animation-delay: ${Math.random() * 2}s;
                    opacity: 0.8;
                `;
                document.body.appendChild(confetti);
                
                setTimeout(() => confetti.remove(), 6000);
            }
        }
        
        // Add CSS for confetti animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes confetti-fall {
                0% {
                    transform: translateY(-100px) rotate(0deg);
                    opacity: 1;
                }
                100% {
                    transform: translateY(100vh) rotate(${Math.random() * 360}deg);
                    opacity: 0;
                }
            }
            
            .success-header {
                animation: fadeIn 0.8s ease-out;
            }
            
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(-20px); }
                to { opacity: 1; transform: translateY(0); }
            }
        `;
        document.head.appendChild(style);
        
        document.addEventListener('DOMContentLoaded', function() {
            createConfetti();
            
            // Add success sound effect (optional)
            const audio = new Audio('data:audio/wav;base64,UklGRigAAABXQVZFZm10IBIAAAABAAEARKwAAIhYAQACABAAZGF0YQQAAAAAAA==');
            audio.volume = 0.3;
            audio.play().catch(e => console.log("Audio play failed:", e));
        });
        
        // Add print styles
        const printStyle = document.createElement('style');
        printStyle.media = 'print';
        printStyle.textContent = `
            @media print {
                .header, .footer, .actions, .debug-info {
                    display: none !important;
                }
                
                .success-header {
                    background: #53c41a !important;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
                
                .success-container {
                    margin: 0;
                    max-width: 100%;
                }
                
                body {
                    background: white !important;
                }
            }
        `;
        document.head.appendChild(printStyle);
    </script>
    <script src="js/logout.js"></script>
</body>
</html>