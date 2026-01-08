<?php
// DEBUG: Capture all output to see what's happening
ob_start();

session_start();

// EXTREME DEBUGGING
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Log file
$log_file = __DIR__ . '/../logs/payment_debug_' . date('Y-m-d') . '.log';
ini_set('log_errors', 1);
ini_set('error_log', $log_file);

error_log("========== PAYMENT PAGE ACCESSED ==========");
error_log("Time: " . date('Y-m-d H:i:s'));
error_log("Session ID: " . session_id());
error_log("User ID in session: " . ($_SESSION['user_id'] ?? 'NOT SET'));
error_log("POST Data: " . print_r($_POST, true));
error_log("GET Data: " . print_r($_GET, true));
error_log("Session Data: " . print_r($_SESSION, true));

// Log errors
$log_dir = __DIR__ . '/../logs/';
if (!file_exists($log_dir)) mkdir($log_dir, 0777, true);

require_once '../includes/config.php';
require_once '../includes/payment_config.php';

// Check if user is logged in as customer
if (!isset($_SESSION['user_id']) || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'])) {
    header("Location: login.php");
    exit();
}

// Check if payment session exists
if (!isset($_SESSION['payment_session'])) {
    header("Location: payment.php?error=no_payment_session");
    exit();
}

$payment_session = $_SESSION['payment_session'];
$user_id = $_SESSION['user_id'];

// Get bill details
$stmt = $pdo->prepare("SELECT 
    b.*, 
    u.full_name, 
    u.phone, 
    u.email,
    u.customer_code,
    COALESCE(b.amount, b.final_amount, b.total_amount, 0) as amount,
    u.id as user_id
    FROM bills b 
    JOIN users u ON b.user_id = u.id 
    WHERE b.id = ? AND b.user_id = ? AND b.payment_status = 'pending'");
    
$stmt->execute([$payment_session['bill_id'], $user_id]);
$bill = $stmt->fetch();

if (!$bill) {
    unset($_SESSION['payment_session']);
    header("Location: payment.php?error=invalid_bill");
    exit();
}

$error = '';
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simulate_payment'])) {
    error_log("==== FORM SUBMISSION DETECTED ====");
    error_log("Mobile: " . ($_POST['mobile'] ?? 'empty'));
    error_log("MPIN: " . ($_POST['mpin'] ?? 'empty'));
    
    $mobile = $_POST['mobile'] ?? '';
    $mpin = $_POST['mpin'] ?? '';
    
    // Validate credentials
    $valid_customer = in_array($mobile, ['9800000001', '9800000002', '9800000003']) && $mpin === '1234';
    $valid_admin = $mobile === 'admin' && $mpin === 'admin123';
    
    error_log("Valid customer: " . ($valid_customer ? 'YES' : 'NO'));
    error_log("Valid admin: " . ($valid_admin ? 'YES' : 'NO'));
    
    if ($valid_customer || $valid_admin) {
        $is_admin = $valid_admin;
        
        try {
            error_log("Starting database transaction...");
            $pdo->beginTransaction();
            
            // Get payment amount
            $payment_amount = $bill['amount'] ?? $bill['final_amount'] ?? $bill['total_amount'] ?? 0;
            $transaction_id = $payment_session['transaction_id'];
            
            error_log("Payment Amount: " . $payment_amount);
            error_log("Transaction ID: " . $transaction_id);
            
            // 1. Update bill status
            $stmt = $pdo->prepare("UPDATE bills SET 
                                  payment_status = 'paid', 
                                  status = 'completed', 
                                  paid_at = NOW() 
                                  WHERE id = ?");
            $stmt->execute([$bill['id']]);
            error_log("Bill updated: " . $bill['id']);
            
            // 2. Insert payment record
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
            
            $stmt->execute([
                $bill['id'],
                $user_id,
                $payment_amount,
                $transaction_id,
                $user_id
            ]);
            
            $payment_id = $pdo->lastInsertId();
            error_log("Payment record created: " . $payment_id);
            
            // 3. Create admin notification
            $admin_message = "eSewa Payment Received - Amount: ₹" . number_format($payment_amount, 2) . 
                            " - From: " . $_SESSION['full_name'] . 
                            " (" . ($bill['customer_code'] ?? 'N/A') . ")" .
                            " - Bill: #" . $bill['bill_number'] . 
                            " - Transaction: " . $transaction_id .
                            ($is_admin ? " - Via: Admin QR Scan" : "");
            
            // Insert into admin_notifications
            $stmt = $pdo->prepare("INSERT INTO admin_notifications (title, message, type) VALUES (?, ?, 'payment')");
            $stmt->execute(['Payment Received', $admin_message]);
            error_log("Admin notification created");
            
            // 4. Create user notification
            $user_message = "Payment Successful - Amount: ₹" . number_format($payment_amount, 2) . 
                           " - Bill: #" . $bill['bill_number'] . 
                           " - Transaction: " . $transaction_id;
            
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, 'Payment Successful', ?, 'payment')");
            $stmt->execute([$user_id, $user_message]);
            error_log("User notification created");
            
            // 5. Also notify all admin users
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) 
                                  SELECT id, 'New Payment Received', ?, 'payment' FROM users WHERE user_type = 'admin'");
            $stmt->execute([$admin_message]);
            error_log("Admin users notified");
            
            $pdo->commit();
            error_log("Transaction committed successfully");
            
            // Store success data in session
            $_SESSION['payment_success'] = [
                'transaction_id' => $transaction_id,
                'amount' => $payment_amount,
                'bill_number' => $bill['bill_number'],
                'admin_scan' => $is_admin,
                'payment_id' => $payment_id
            ];
            
            // Clear payment session
            unset($_SESSION['payment_session']);
            
            error_log("Redirecting to payment_success.php");
            
            // Redirect to success page
            header("Location: payment_success.php");
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Payment processing failed. Please try again.";
            error_log("Payment Error: " . $e->getMessage());
            error_log("Error Trace: " . $e->getTraceAsString());
        }
    } else {
        $error = "Invalid credentials. Use: Mobile: 9800000001, MPIN: 1234";
        error_log("Invalid credentials entered");
    }
}

// Generate QR code
$qr_data = PaymentConfig::generateEsewaQRData($bill['amount'], $payment_session['transaction_id']);
$qr_url = "https://chart.googleapis.com/chart?chs=250x250&cht=qr&chl=" . urlencode($qr_data);

// Flush debug buffer
ob_end_flush();
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
        .payment-container {
            max-width: 900px;
            margin: 2rem auto;
        }
        
        .payment-header {
            background: linear-gradient(135deg, #53c41a 0%, #389e0d 100%);
            color: white;
            padding: 2rem;
            border-radius: 10px 10px 0 0;
            text-align: center;
        }
        
        .payment-body {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            padding: 2rem;
            background: white;
            border-radius: 0 0 10px 10px;
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
        
        .payment-detail {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.8rem;
            padding-bottom: 0.8rem;
            border-bottom: 1px solid #eee;
        }
        
        .qr-container {
            text-align: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #eee;
            grid-column: 1 / -1;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <!-- Debug Section -->
    <div style="background: #f0f0f0; padding: 10px; margin: 10px; border: 2px solid red; display: none;" id="debugInfo">
        <h4>Debug Information:</h4>
        <p>Session ID: <?php echo session_id(); ?></p>
        <p>User ID: <?php echo $_SESSION['user_id']; ?></p>
        <p>Bill ID: <?php echo $payment_session['bill_id']; ?></p>
        <p>Transaction ID: <?php echo $payment_session['transaction_id']; ?></p>
        <p>Bill Amount: <?php echo $bill['amount']; ?></p>
        <p>POST Method Used: <?php echo $_SERVER['REQUEST_METHOD']; ?></p>
        <?php if ($_SERVER['REQUEST_METHOD'] == 'POST'): ?>
            <p>Form Submitted: YES</p>
            <p>Mobile: <?php echo $_POST['mobile'] ?? 'Not set'; ?></p>
            <p>MPIN: <?php echo $_POST['mpin'] ?? 'Not set'; ?></p>
        <?php else: ?>
            <p>Form Submitted: NO</p>
        <?php endif; ?>
    </div>
    
    <div class="container">
        <div class="payment-container">
            <div class="payment-header">
                <i data-lucide="smartphone" style="width: 3rem; height: 3rem; margin-bottom: 1rem;"></i>
                <h1>eSewa Payment Gateway</h1>
                <p>Secure Digital Payment</p>
            </div>
            
            <div class="payment-body">
                <!-- Left: Payment Form -->
                <div>
                    <h3>Login to Pay</h3>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i data-lucide="alert-circle"></i> <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" id="paymentForm">
                        <div class="form-group">
                            <label><i data-lucide="phone"></i> Mobile Number</label>
                            <input type="text" name="mobile" value="9800000001" required class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label><i data-lucide="lock"></i> MPIN</label>
                            <input type="password" name="mpin" value="1234" required maxlength="4" class="form-control">
                        </div>
                        
                        <button type="submit" name="simulate_payment" class="btn btn-success" style="width: 100%; padding: 1rem;">
                            <i data-lucide="credit-card"></i> Pay ₹<?php echo number_format($bill['amount'], 2); ?>
                        </button>
                    </form>
                    
                    <div style="background: #fff3cd; padding: 1rem; border-radius: 5px; margin-top: 1rem;">
                        <h4><i data-lucide="info"></i> Demo Credentials</h4>
                        <p><strong>Customer:</strong> 9800000001 / 1234</p>
                        <p><strong>Admin:</strong> admin / admin123</p>
                    </div>
                </div>
                
                <!-- Right: Payment Details -->
                <div>
                    <h3><i data-lucide="receipt"></i> Payment Summary</h3>
                    <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px;">
                        <div class="payment-detail">
                            <span>Bill Number:</span>
                            <strong><?php echo $bill['bill_number']; ?></strong>
                        </div>
                        <div class="payment-detail">
                            <span>Service:</span>
                            <strong><?php echo ucfirst($bill['bill_type'] ?? 'General'); ?></strong>
                        </div>
                        <div class="payment-detail">
                            <span>Customer:</span>
                            <strong><?php echo $_SESSION['full_name']; ?></strong>
                        </div>
                        <div class="payment-detail">
                            <span>Customer Code:</span>
                            <strong><?php echo $bill['customer_code'] ?? 'N/A'; ?></strong>
                        </div>
                        <div class="payment-detail">
                            <span>Amount:</span>
                            <strong style="color: #e74c3c;">₹<?php echo number_format($bill['amount'], 2); ?></strong>
                        </div>
                        <div class="payment-detail">
                            <span>Transaction ID:</span>
                            <strong><?php echo $payment_session['transaction_id']; ?></strong>
                        </div>
                    </div>
                </div>
                
                <!-- QR Code -->
                <div class="qr-container">
                    <h3><i data-lucide="qrcode"></i> QR Code Payment</h3>
                    <div style="display: inline-block; padding: 15px; background: white; border: 2px solid #53c41a; border-radius: 10px;">
                        <img src="<?php echo $qr_url; ?>" alt="QR Code" style="width: 250px; height: 250px;">
                    </div>
                    <p style="margin-top: 1rem;">Scan this QR code with eSewa app</p>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <script>
        lucide.createIcons();
        
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            console.log('Payment form submit triggered (legacy handler)');
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.innerHTML = '<i data-lucide="loader-2" style="animation: spin 1s linear infinite;"></i> Processing Payment...';
            
            // Add loading indicator
            const loading = document.createElement('div');
            loading.id = 'loadingIndicator';
            loading.style.cssText = 'background: #3498db; color: white; padding: 10px; margin: 10px 0; border-radius: 5px; text-align: center;';
            loading.innerHTML = '<i data-lucide="loader-2" style="animation: spin 1s linear infinite;"></i> Processing your payment, please wait...';
            this.parentNode.insertBefore(loading, this.nextSibling);
        });
    </script>
    
    <script>
        // Debug: Check if form is submitting
        console.log('Payment page loaded');

        // Check if form exists
        const form = document.getElementById('paymentForm');
        if (form) {
            console.log('Form found, adding event listeners');
            
            // Add multiple event listeners
            form.addEventListener('submit', function(e) {
                console.log('Form submit event triggered');
                
                const btn = this.querySelector('button[type="submit"]');
                if (btn) {
                    console.log('Button found, disabling...');
                    btn.disabled = true;
                    btn.innerHTML = '<i data-lucide="loader-2"></i> Processing...';
                    
                    // Force icon refresh
                    setTimeout(() => {
                        lucide.createIcons();
                    }, 100);
                }
                
                // Don't prevent default
                console.log('Form submission proceeding...');
            });
            
            // Also try direct form.onsubmit
            form.onsubmit = function() {
                console.log('onsubmit triggered');
                return true; // Allow form submission
            };
        } else {
            console.error('Form not found!');
        }

        // Check for any JavaScript errors
        window.onerror = function(msg, url, line) {
            console.error('JavaScript Error:', msg, 'at', url, ':', line);
            
            // Show error on page
            const errorDiv = document.createElement('div');
            errorDiv.style.cssText = 'background: #721c24; color: white; padding: 10px; margin: 10px; border-radius: 5px;';
            errorDiv.innerHTML = '<strong>JavaScript Error:</strong> ' + msg + ' at line ' + line;
            document.body.prepend(errorDiv);
            
            return false;
        };
    </script>
</body>
</html>