<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/payment_config.php';

// Redirect if not logged in as customer
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
$stmt = $pdo->prepare("SELECT b.*, u.full_name, u.phone, u.email, u.customer_code 
                      FROM bills b 
                      JOIN users u ON b.user_id = u.id 
                      WHERE b.id = ? AND b.user_id = ? AND b.status = 'pending'");
$stmt->execute([$payment_session['bill_id'], $user_id]);
$bill = $stmt->fetch();

if (!$bill) {
    unset($_SESSION['payment_session']);
    header("Location: payment.php?error=invalid_bill");
    exit();
}

// For demo - we'll simulate the payment
$error = '';
$success = '';

// Handle form submission (demo payment simulation)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['simulate_payment'])) {
        $mobile = $_POST['mobile'] ?? '';
        $mpin = $_POST['mpin'] ?? '';
        
        // Demo validation
        $demo_credentials = PaymentConfig::getDemoCredentials()['esewa'];
        
        if (in_array($mobile, $demo_credentials['mobile']) && $mpin === '1234') {
            // Process the payment
            $success = processEsewaPayment($pdo, $payment_session, $bill, $user_id);
            
            if ($success) {
                unset($_SESSION['payment_session']);
                header("Location: payment_success.php?transaction_id=" . $payment_session['transaction_id'] . "&gateway=esewa");
                exit();
            } else {
                $error = "Payment processing failed. Please try again.";
            }
        } elseif ($mobile === 'admin' && $mpin === 'admin123') {
            // Admin QR scanning simulation
            $success = processEsewaPayment($pdo, $payment_session, $bill, $user_id, true);
            
            if ($success) {
                unset($_SESSION['payment_session']);
                header("Location: payment_success.php?transaction_id=" . $payment_session['transaction_id'] . "&gateway=esewa&admin_scan=1");
                exit();
            } else {
                $error = "Admin payment processing failed.";
            }
        } else {
            $error = "Invalid credentials. Use demo: Mobile: 9800000001, MPIN: 1234";
        }
    }
}

function processEsewaPayment($pdo, $payment_session, $bill, $user_id, $is_admin = false) {
    try {
        $pdo->beginTransaction();
        
        // Update bill status
        $stmt = $pdo->prepare("UPDATE bills SET status = 'paid', paid_at = NOW() WHERE id = ?");
        $stmt->execute([$bill['id']]);
        
        // Insert payment record
        $stmt = $pdo->prepare("INSERT INTO payments (user_id, bill_id, payment_amount, payment_method, transaction_id, status, payment_date) 
                              VALUES (?, ?, ?, 'esewa', ?, 'completed', NOW())");
        $stmt->execute([
            $user_id,
            $bill['id'],
            $bill['amount'],
            $payment_session['transaction_id']
        ]);
        
        // Create admin notification
        $admin_message = "eSewa Payment Received - Amount: ₹" . number_format($bill['amount'], 2) . 
                        " - From: " . $_SESSION['full_name'] . 
                        " - Bill: #" . $bill['bill_number'] . 
                        ($is_admin ? " - Via: Admin QR Scan" : " - Via: Customer Payment");
        
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, created_at) 
                              SELECT id, 'eSewa Payment Received', ?, 'payment', NOW() 
                              FROM users WHERE is_admin = TRUE");
        $stmt->execute([$admin_message]);
        
        // Create user notification
        $user_message = "Payment Successful - Amount: ₹" . number_format($bill['amount'], 2) . 
                       " - Bill: #" . $bill['bill_number'] . 
                       " - Transaction ID: " . $payment_session['transaction_id'];
        
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, created_at) 
                              VALUES (?, 'Payment Successful', ?, 'payment', NOW())");
        $stmt->execute([$user_id, $user_message]);
        
        $pdo->commit();
        return true;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("eSewa payment error: " . $e->getMessage());
        return false;
    }
}

// Generate QR code data
$qr_data = PaymentConfig::generateEsewaQRData(
    $bill['amount'],
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
        
        .admin-scanner-btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 10px auto;
        }
        
        .qr-scanner-preview {
            display: none;
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-top: 1rem;
            border: 2px dashed #3498db;
        }
        
        .scan-animation {
            width: 100%;
            height: 3px;
            background: #53c41a;
            margin: 10px 0;
            animation: scan 2s infinite;
        }
        
        @keyframes scan {
            0% { transform: translateY(0); }
            50% { transform: translateY(150px); }
            100% { transform: translateY(0); }
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
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i data-lucide="check-circle" style="width: 1.2rem; height: 1.2rem; margin-right: 0.5rem; vertical-align: middle;"></i>
                            <?php echo $success; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
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
                            Pay ₹<?php echo number_format($bill['amount'], 2); ?>
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
                        <strong><?php echo ucfirst($bill['bill_type']); ?></strong>
                    </div>
                    
                    <div class="payment-detail-item">
                        <span>Customer:</span>
                        <strong><?php echo $_SESSION['full_name']; ?></strong>
                    </div>
                    
                    <div class="payment-detail-item">
                        <span>Customer Code:</span>
                        <strong><?php echo $bill['customer_code']; ?></strong>
                    </div>
                    
                    <div class="payment-detail-item">
                        <span>Amount:</span>
                        <strong style="color: #e74c3c; font-size: 1.2em;">
                            ₹<?php echo number_format($bill['amount'], 2); ?>
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
                                ₹<?php echo number_format($bill['amount'], 2); ?>
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
                    
                    <div style="margin-top: 1rem;">
                        <button type="button" onclick="showAdminScanner()" class="admin-scanner-btn">
                            <i data-lucide="scan" style="width: 1.2rem; height: 1.2rem;"></i>
                            Generate Admin QR Scanner
                        </button>
                    </div>
                    
                    <!-- Admin QR Scanner Preview -->
                    <div id="adminScanner" class="qr-scanner-preview">
                        <h4>
                            <i data-lucide="camera" style="width: 1.2rem; height: 1.2rem; margin-right: 0.5rem; vertical-align: middle;"></i>
                            Admin QR Code Scanner
                        </h4>
                        <div style="position: relative; width: 250px; height: 250px; margin: 0 auto;">
                            <img src="<?php echo $qr_url; ?>" alt="Admin QR Code" style="width: 250px; height: 250px;">
                            <div class="scan-animation"></div>
                        </div>
                        <p style="margin-top: 1rem;">
                            <strong>Admin Mobile Number:</strong> <?php echo PaymentConfig::$esewa['qr_merchant_id']; ?>
                        </p>
                        <p><small>Scan this QR code with admin eSewa to receive payment</small></p>
                        
                        <div style="margin-top: 1rem;">
                            <button type="button" onclick="simulateAdminScan()" class="btn" style="background: #3498db;">
                                <i data-lucide="smartphone" style="width: 1.2rem; height: 1.2rem; margin-right: 0.5rem;"></i>
                                Simulate Admin Scan
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Real-time Notification Info -->
            <div class="card" style="margin-top: 2rem;">
                <h3>Payment Flow Information</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 1rem;">
                    <div style="text-align: center;">
                        <i data-lucide="smartphone" style="width: 2rem; height: 2rem; color: #53c41a;"></i>
                        <h4>Customer Side</h4>
                        <ul style="text-align: left;">
                            <li>Bill validation</li>
                            <li>eSewa authentication</li>
                            <li>Payment processing</li>
                            <li>Success confirmation</li>
                        </ul>
                    </div>
                    <div style="text-align: center;">
                        <i data-lucide="database" style="width: 2rem; height: 2rem; color: #3498db;"></i>
                        <h4>Database Updates</h4>
                        <ul style="text-align: left;">
                            <li>Bill status: pending → paid</li>
                            <li>Payment record created</li>
                            <li>Transaction ID stored</li>
                            <li>Timestamp recorded</li>
                        </ul>
                    </div>
                    <div style="text-align: center;">
                        <i data-lucide="bell" style="width: 2rem; height: 2rem; color: #f39c12;"></i>
                        <h4>Notifications</h4>
                        <ul style="text-align: left;">
                            <li>Admin gets payment alert</li>
                            <li>Customer gets confirmation</li>
                            <li>Real-time updates</li>
                            <li>Notification badge updates</li>
                        </ul>
                    </div>
                    <div style="text-align: center;">
                        <i data-lucide="check-circle" style="width: 2rem; height: 2rem; color: #27ae60;"></i>
                        <h4>Final Status</h4>
                        <ul style="text-align: left;">
                            <li>Bill marked as paid</li>
                            <li>Payment history updated</li>
                            <li>Customer can't pay again</li>
                            <li>Admin sees completed status</li>
                        </ul>
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
        function showAdminScanner() {
            const scanner = document.getElementById('adminScanner');
            scanner.style.display = 'block';
            
            // Animate scanner
            const scanLine = scanner.querySelector('.scan-animation');
            scanLine.style.animation = 'scan 2s infinite';
        }
        
        function simulateAdminScan() {
            // Auto-fill admin credentials and submit
            document.getElementById('mobile').value = 'admin';
            document.getElementById('mpin').value = 'admin123';
            
            // Show confirmation
            if (confirm('Simulate admin QR scan? This will process the payment to admin.')) {
                // Submit the form
                document.querySelector('form').submit();
            }
        }
        
        // Auto-refresh QR code every minute
        setInterval(() => {
            const qrImages = document.querySelectorAll('img[src*="chart.googleapis.com"]');
            qrImages.forEach(img => {
                img.src = img.src.split('&refresh=')[0] + '&refresh=' + Date.now();
            });
        }, 60000);
        
        // Initialize icons
        lucide.createIcons();
    </script>
</body>
</html>