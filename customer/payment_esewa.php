<?php
session_start();

// EXTREME DEBUGGING - Display all errors
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Also log errors to our project logs folder
$log_dir = __DIR__ . '/../logs/';
if (!file_exists($log_dir)) {
    mkdir($log_dir, 0777, true);
}
ini_set('log_errors', 1);
ini_set('error_log', $log_dir . 'payment_errors.log');

error_log("=== PAYMENT ESEWA PAGE LOADED ===");
error_log("Session ID: " . session_id());
error_log("POST Data: " . print_r($_POST, true));

require_once '../includes/config.php';
require_once '../includes/payment_config.php';

// Redirect if not logged in as customer
if (!isset($_SESSION['user_id']) || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'])) {
    error_log("User not logged in or is admin");
    header("Location: login.php");
    exit();
}

// Check if payment session exists
if (!isset($_SESSION['payment_session'])) {
    error_log("No payment session found");
    header("Location: payment.php?error=no_payment_session");
    exit();
}

$payment_session = $_SESSION['payment_session'];
$user_id = $_SESSION['user_id'];

error_log("Payment session found for user: $user_id");
error_log("Bill ID in session: " . ($payment_session['bill_id'] ?? 'NOT SET'));

// Get bill details - FIXED: Handle all amount fields
$stmt = $pdo->prepare("SELECT 
    b.*, 
    u.full_name, 
    u.phone, 
    u.email,
    u.customer_code,
    -- Get the first non-null amount value
    COALESCE(
        b.amount, 
        b.final_amount, 
        b.total_amount,
        0
    ) as amount,
    u.id as user_id
    FROM bills b 
    JOIN users u ON b.user_id = u.id 
    WHERE b.id = ? AND b.user_id = ? AND b.payment_status = 'pending'");
    
error_log("Executing bill query with bill_id: " . $payment_session['bill_id'] . " and user_id: $user_id");

$stmt->execute([$payment_session['bill_id'], $user_id]);
$bill = $stmt->fetch();

if (!$bill) {
    error_log("ERROR: No pending bill found");
    unset($_SESSION['payment_session']);
    header("Location: payment.php?error=invalid_bill");
    exit();
}

error_log("Bill found: #" . $bill['bill_number'] . " - Amount: " . ($bill['amount'] ?? 'NOT SET'));

$error = '';
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    error_log("=== FORM SUBMITTED ===");
    
    if (isset($_POST['simulate_payment'])) {
        $mobile = $_POST['mobile'] ?? '';
        $mpin = $_POST['mpin'] ?? '';
        
        error_log("Credentials entered - Mobile: $mobile, MPIN: $mpin");
        
        // Demo validation
        if (($mobile === '9800000001' || $mobile === '9800000002' || $mobile === '9800000003') && $mpin === '1234') {
            error_log("Valid customer credentials - Processing payment...");
            $is_admin = false;
        } elseif ($mobile === 'admin' && $mpin === 'admin123') {
            error_log("Valid admin credentials - Processing payment...");
            $is_admin = true;
        } else {
            $error = "Invalid credentials. Use demo: Mobile: 9800000001, MPIN: 1234";
            error_log("Invalid credentials entered");
        }
        
        if (!$error) {
            // Process the payment
            try {
                error_log("Starting payment transaction...");
                $pdo->beginTransaction();
                
                // Get payment amount from any available field
                $payment_amount = $bill['amount'] ?? $bill['final_amount'] ?? $bill['total_amount'] ?? 0;
                $transaction_id = $payment_session['transaction_id'];
                
                error_log("Payment Amount: $payment_amount, Transaction ID: $transaction_id");
                
                // 1. Update bill status
                error_log("Updating bill status...");
                $stmt = $pdo->prepare("UPDATE bills SET 
                                      payment_status = 'paid', 
                                      status = 'completed', 
                                      paid_at = NOW() 
                                      WHERE id = ?");
                $stmt->execute([$bill['id']]);
                error_log("Bill updated. Rows affected: " . $stmt->rowCount());
                
                // 2. Insert payment record
                error_log("Inserting payment record...");
                $stmt = $pdo->prepare("INSERT INTO payments (
                                      bill_id, 
                                      customer_id, 
                                      amount, 
                                      payment_method, 
                                      payment_date,
                                      transaction_id,
                                      status,
                                      created_by
                                      ) VALUES (?, ?, ?, 'esewa', CURDATE(), ?, 'completed', ?)");
                
                $result = $stmt->execute([
                    $bill['id'],
                    $user_id,
                    $payment_amount,
                    $transaction_id,
                    $user_id
                ]);
                
                if ($result) {
                    $payment_id = $pdo->lastInsertId();
                    error_log("✅ Payment inserted with ID: $payment_id");
                    
                    // 3. Create admin notification
                    error_log("Creating notifications...");
                    $admin_message = "eSewa Payment Received - Amount: ₹" . number_format($payment_amount, 2) . 
                                    " - From: " . $_SESSION['full_name'] . 
                                    " - Bill: #" . $bill['bill_number'] . 
                                    " - Transaction ID: " . $transaction_id;
                    
                    if ($is_admin) {
                        $admin_message .= " - Via: Admin QR Scan";
                    }
                    
                    // Insert into admin_notifications if table exists
                    try {
                        $stmt = $pdo->prepare("INSERT INTO admin_notifications (title, message, type) VALUES (?, ?, 'payment')");
                        $stmt->execute(['Payment Received via eSewa', $admin_message]);
                        error_log("Admin notification created");
                    } catch (Exception $e) {
                        error_log("Note: Could not create admin notification (table might not exist): " . $e->getMessage());
                    }
                    
                    // 4. Create user notification
                    $user_message = "Payment Successful - Amount: ₹" . number_format($payment_amount, 2) . 
                                   " - Bill: #" . $bill['bill_number'] . 
                                   " - Transaction ID: " . $transaction_id;
                    
                    try {
                        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, 'Payment Successful', ?, 'payment')");
                        $stmt->execute([$user_id, $user_message]);
                        error_log("User notification created");
                    } catch (Exception $e) {
                        error_log("Note: Could not create user notification: " . $e->getMessage());
                    }
                    
                    // Commit transaction
                    $pdo->commit();
                    error_log("✅ Transaction committed successfully!");
                    
                    // Success - redirect
                    unset($_SESSION['payment_session']);
                    $_SESSION['payment_success'] = [
                        'transaction_id' => $transaction_id,
                        'amount' => $payment_amount,
                        'bill_number' => $bill['bill_number'],
                        'admin_scan' => $is_admin
                    ];
                    
                    error_log("Redirecting to success page...");
                    
                    if ($is_admin) {
                        header("Location: payment_success.php?admin_scan=1");
                    } else {
                        header("Location: payment_success.php");
                    }
                    exit();
                    
                } else {
                    $pdo->rollBack();
                    $error = "Payment insertion failed. Please try again.";
                    error_log("❌ Payment insertion failed");
                }
                
            } catch (Exception $e) {
                if (isset($pdo) && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = "Payment processing error: " . $e->getMessage();
                error_log("❌ Payment error: " . $e->getMessage());
                error_log("Error trace: " . $e->getTraceAsString());
            }
        }
    }
}

// Generate QR code data
$qr_data = PaymentConfig::generateEsewaQRData(
    $bill['amount'] ?? $bill['final_amount'] ?? $bill['total_amount'] ?? 0,
    $payment_session['transaction_id']
);

// Generate QR code URL
$qr_url = "https://chart.googleapis.com/chart?chs=250x250&cht=qr&chl=" . 
         urlencode($qr_data) . "&choe=UTF-8&chld=L|2";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>eSewa Payment - BillPay Pro</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        .esewa-container {
            max-width: 900px;
            margin: 2rem auto;
        }
        
        .esewa-header {
            background: linear-gradient(135deg, #53c41a 0%, #389e0d 100%);
            color: white;
            padding: 2rem;
            border-radius: 10px 10px 0 0;
            text-align: center;
        }
        
        .esewa-body {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            padding: 2rem;
            background: white;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .payment-form-section {
            border-right: 1px solid #eee;
            padding-right: 2rem;
        }
        
        .payment-details-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
        }
        
        .qr-section {
            grid-column: 1 / -1;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #eee;
            text-align: center;
        }
        
        .demo-credentials {
            background: #fff3cd;
            padding: 1rem;
            border-radius: 5px;
            margin: 1rem 0;
            border-left: 4px solid #ffc107;
        }
        
        .qr-code-container {
            display: inline-block;
            padding: 15px;
            background: white;
            border-radius: 10px;
            border: 2px solid #53c41a;
            margin: 1rem 0;
        }
        
        .payment-detail-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.8rem;
            padding-bottom: 0.8rem;
            border-bottom: 1px solid #eee;
        }
        
        .header-icon {
            width: 3rem;
            height: 3rem;
            margin-bottom: 1rem;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        
        .alert-danger {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .debug-info {
            background: #f0f0f0;
            padding: 10px;
            margin: 10px 0;
            border-left: 4px solid #3498db;
            font-family: monospace;
            font-size: 12px;
        }
        
        /* Make form submission obvious */
        form:focus-within {
            border: 2px solid #3498db;
            padding: 10px;
            border-radius: 5px;
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
                    <li><a href="payment.php" style="color: #3498db;">Make Payment</a></li>
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
        <div class="esewa-container">
            <!-- eSewa Header -->
            <div class="esewa-header">
                <i data-lucide="smartphone" class="header-icon"></i>
                <h1>eSewa Payment Gateway</h1>
                <p>Secure Digital Payment - Demo Environment</p>
            </div>
            
            <!-- Main Content -->
            <div class="esewa-body">
                <!-- Left: Payment Form -->
                <div class="payment-form-section">
                    <h3>eSewa Login</h3>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i data-lucide="alert-circle" style="width: 1.2rem; height: 1.2rem; margin-right: 0.5rem; vertical-align: middle;"></i>
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" id="esewaForm">
                        <div class="form-group">
                            <label for="mobile">
                                <i data-lucide="phone" style="width: 1rem; height: 1rem; margin-right: 0.5rem; vertical-align: middle;"></i>
                                Mobile Number
                            </label>
                            <input type="text" id="mobile" name="mobile" class="form-control" 
                                   value="9800000001" placeholder="98XXXXXXXX" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="mpin">
                                <i data-lucide="lock" style="width: 1rem; height: 1rem; margin-right: 0.5rem; vertical-align: middle;"></i>
                                MPIN
                            </label>
                            <input type="password" id="mpin" name="mpin" class="form-control" 
                                   value="1234" maxlength="4" required>
                        </div>
                        
                        <button type="submit" name="simulate_payment" class="btn" style="width: 100%; padding: 1rem; background: #53c41a;">
                            <i data-lucide="credit-card" style="width: 1.2rem; height: 1.2rem; margin-right: 8px;"></i>
                            Pay ₹<?php echo number_format($bill['amount'] ?? 0, 2); ?>
                        </button>
                    </form>
                    
                    <div class="demo-credentials">
                        <h4>
                            <i data-lucide="info" style="width: 1.2rem; height: 1.2rem; margin-right: 0.5rem; vertical-align: middle;"></i>
                            Demo Credentials:
                        </h4>
                        <p><strong>Customer Mobile:</strong> 9800000001, 9800000002, 9800000003</p>
                        <p><strong>MPIN:</strong> 1234</p>
                        <p><strong>Admin Scanner:</strong> mobile: admin, mpin: admin123</p>
                    </div>
                    
                    <!-- Debug info -->
                    <div class="debug-info">
                        <strong>Debug Info:</strong><br>
                        User ID: <?php echo $user_id; ?><br>
                        Bill ID: <?php echo $bill['id']; ?><br>
                        Bill Number: <?php echo $bill['bill_number']; ?><br>
                        Transaction ID: <?php echo $payment_session['transaction_id']; ?><br>
                        Amount: ₹<?php echo number_format($bill['amount'] ?? 0, 2); ?>
                    </div>
                </div>
                
                <!-- Right: Payment Details -->
                <div class="payment-details-section">
                    <h3>
                        <i data-lucide="receipt" style="width: 1.2rem; height: 1.2rem; margin-right: 0.5rem; vertical-align: middle;"></i>
                        Payment Summary
                    </h3>
                    
                    <div class="payment-detail-item">
                        <span>Bill Number:</span>
                        <strong><?php echo $bill['bill_number']; ?></strong>
                    </div>
                    
                    <div class="payment-detail-item">
                        <span>Service:</span>
                        <strong><?php echo isset($bill['bill_type']) ? ucfirst($bill['bill_type']) : 'General'; ?></strong>
                    </div>
                    
                    <div class="payment-detail-item">
                        <span>Customer:</span>
                        <strong><?php echo $_SESSION['full_name']; ?></strong>
                    </div>
                    
                    <div class="payment-detail-item">
                        <span>Amount:</span>
                        <strong style="color: #e74c3c; font-size: 1.2em;">
                            ₹<?php echo number_format($bill['amount'] ?? 0, 2); ?>
                        </strong>
                    </div>
                    
                    <div class="payment-detail-item">
                        <span>Transaction ID:</span>
                        <strong><?php echo $payment_session['transaction_id']; ?></strong>
                    </div>
                    
                    <div class="payment-detail-item">
                        <span>Date:</span>
                        <strong><?php echo date('M d, Y h:i A'); ?></strong>
                    </div>
                    
                    <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 2px solid #53c41a;">
                        <div class="payment-detail-item" style="font-size: 1.1em;">
                            <strong>Total Payable:</strong>
                            <strong style="color: #27ae60; font-size: 1.3em;">
                                ₹<?php echo number_format($bill['amount'] ?? 0, 2); ?>
                            </strong>
                        </div>
                    </div>
                </div>
                
                <!-- QR Code Section -->
                <div class="qr-section">
                    <h3>
                        <i data-lucide="qrcode" style="width: 1.5rem; height: 1.5rem; margin-right: 10px;"></i>
                        QR Code Payment
                    </h3>
                    <p>Scan this QR code with eSewa app to pay instantly</p>
                    
                    <div class="qr-code-container">
                        <img src="<?php echo $qr_url; ?>" alt="eSewa QR Code" style="width: 250px; height: 250px;">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Online Billing System - BCA Project | Tribhuvan University</p>
        </div>
    </footer>

    <script>
        // Form submission handler with debug
        document.getElementById('esewaForm').addEventListener('submit', function(e) {
            console.log('Form submission started');
            
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i data-lucide="loader-2" style="width: 1.2rem; height: 1.2rem; margin-right: 8px; animation: spin 1s linear infinite;"></i>Processing Payment...';
                
                // Show loading message
                const loadingMsg = document.createElement('div');
                loadingMsg.id = 'loadingMessage';
                loadingMsg.style.cssText = 'background: #3498db; color: white; padding: 10px; margin: 10px 0; border-radius: 5px; text-align: center;';
                loadingMsg.innerHTML = '<i data-lucide="loader-2" style="animation: spin 1s linear infinite;"></i> Processing your payment...';
                this.parentNode.insertBefore(loadingMsg, this.nextSibling);
            }
        });
        
        // Add CSS animation for spinner
        const style = document.createElement('style');
        style.textContent = `
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
            /* Highlight form on focus */
            input:focus {
                border-color: #53c41a !important;
                box-shadow: 0 0 0 2px rgba(83, 196, 26, 0.2) !important;
            }
        `;
        document.head.appendChild(style);
        
        // Initialize icons
        lucide.createIcons();
        
        // Debug log
        console.log('Payment page loaded successfully');
        console.log('Form ready for submission');
        
        // Add click handler to test button
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('esewaForm');
            if (form) {
                console.log('Form found, adding event listeners');
                
                // Also add a test button for debugging
                const testBtn = document.createElement('button');
                testBtn.type = 'button';
                testBtn.textContent = 'Test Form Submission';
                testBtn.style.cssText = 'background: #e74c3c; color: white; padding: 10px; margin: 10px 0; border: none; border-radius: 5px;';
                testBtn.onclick = function() {
                    console.log('Test button clicked, submitting form...');
                    form.submit();
                };
                form.appendChild(testBtn);
            }
        });
    </script>
</body>
</html>