<?php
// DEBUG: Capture all output to see what's happening
ob_start();

session_start();

// Check for redirect data from payment_process.php
if (isset($_SESSION['payment_redirect_data'])) {
    $_SESSION['payment_session'] = [
        'bill_id' => $_SESSION['payment_redirect_data']['bill_id'],
        'transaction_id' => $_SESSION['payment_redirect_data']['transaction_id'],
        'created_at' => time()
    ];
    unset($_SESSION['payment_redirect_data']);
}

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
error_log("GET Data: " . print_r($_GET, true));
error_log("Session Data: " . print_r($_SESSION, true));

// Check mode parameter
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'gateway';
$show_qr_code = ($mode !== 'gateway'); // Hide QR code for gateway mode
error_log("Mode: " . $mode . ", Show QR: " . ($show_qr_code ? 'YES' : 'NO'));

// Check for auto-fill parameter (from QR scanning)
$auto_fill = isset($_GET['auto_fill']) && $_GET['auto_fill'] == '1';
$prefilled_mobile = $auto_fill ? '9800000001' : '';
$prefilled_mpin = $auto_fill ? '1234' : '';
error_log("Auto fill: " . ($auto_fill ? 'YES' : 'NO'));

// Check for scan complete parameter
$scan_complete = isset($_GET['scan_complete']) && $_GET['scan_complete'] == '1';

// IMPORTANT: If coming from QR scan with auto-fill, HIDE the QR code section
if ($auto_fill || $scan_complete) {
    $show_qr_code = false;
    error_log("QR code HIDDEN because coming from QR scan with auto-fill");
}

// DEBUG: Quick test to see if POST is working
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    error_log("=== FORM SUBMITTED ===");
    error_log("POST Data: " . print_r($_POST, true));
    error_log("Session ID: " . session_id());
    error_log("User ID: " . ($_SESSION['user_id'] ?? 'NOT SET'));
    
    // Check if credentials are valid
    $mobile = $_POST['mobile'] ?? '';
    $mpin = $_POST['mpin'] ?? '';
    error_log("Mobile: $mobile, MPIN: $mpin");
}

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

// Get bill details - UPDATED TO GET FINAL_AMOUNT
$stmt = $pdo->prepare("SELECT 
    b.*, 
    u.full_name, 
    u.phone, 
    u.email,
    u.customer_code,
    b.final_amount as amount,
    b.amount as base_amount,
    b.tax_amount,
    b.late_fee as service_charge,
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
    
    // DEBUG: Check if we're even entering this block
    error_log("Entering payment processing block...");
    
    if ($valid_customer || $valid_admin) {
        error_log("Credentials VALID - Starting transaction...");
        $is_admin = $valid_admin;
        
        try {
            error_log("Starting database transaction...");
            
            // DEBUG: Check PDO connection
            error_log("PDO connection status: " . ($pdo ? "Connected" : "NOT CONNECTED"));
            
            // Test a simple query first
            try {
                $testStmt = $pdo->query("SELECT 1 as test");
                $testResult = $testStmt->fetch();
                error_log("Database test query: " . ($testResult['test'] ?? 'FAILED'));
            } catch (Exception $e) {
                error_log("Database test query failed: " . $e->getMessage());
                throw $e;
            }
            
            // FIRST: Check payments table structure
            error_log("Checking payments table structure...");
            try {
                $checkStmt = $pdo->query("SHOW COLUMNS FROM payments");
                $columns = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
                $column_names = array_column($columns, 'Field');
                error_log("Payments table columns found: " . implode(', ', $column_names));
                
                // Log full column details
                foreach ($columns as $col) {
                    error_log("Column: " . $col['Field'] . " | Type: " . $col['Type']);
                }
            } catch (Exception $e) {
                error_log("Error checking table structure: " . $e->getMessage());
            }
            
            $pdo->beginTransaction();
            error_log("Transaction started successfully");
            
            // Get payment amount - UPDATED: Use final_amount
            $payment_amount = $bill['final_amount'] ?? $bill['amount'] ?? $bill['total_amount'] ?? 0;
            $transaction_id = $payment_session['transaction_id'];
            
            error_log("Payment Amount (FINAL): " . $payment_amount);
            error_log("Transaction ID: " . $transaction_id);
            
            // 1. Update bill status
            $stmt = $pdo->prepare("UPDATE bills SET 
                                  payment_status = 'paid', 
                                  status = 'completed', 
                                  paid_at = NOW() 
                                  WHERE id = ?");
            $update_result = $stmt->execute([$bill['id']]);
            error_log("Bill updated: " . $bill['id'] . " - Rows affected: " . $stmt->rowCount());
            
            // 2. Insert payment record - Use correct column names from your table
            $payment_id = null;
            $payment_result = false;
            
            // Based on your payments table structure, use this:
            try {
                error_log("Inserting payment record with your table structure...");
                $stmt = $pdo->prepare("INSERT INTO payments (
                                      bill_id, 
                                      user_id, 
                                      amount, 
                                      payment_method, 
                                      payment_date,
                                      transaction_id,
                                      status,
                                      created_by
                                      ) VALUES (?, ?, ?, 'esewa', CURDATE(), ?, 'completed', ?)");
                
                $payment_result = $stmt->execute([
                    $bill['id'],
                    $user_id,
                    $payment_amount,
                    $transaction_id,
                    $user_id  // created_by (using user_id)
                ]);
                
                if ($payment_result) {
                    $payment_id = $pdo->lastInsertId();
                    error_log("Payment record created with ID: " . $payment_id);
                }
            } catch (Exception $e) {
                error_log("Payment insert failed: " . $e->getMessage());
                throw $e;
            }
            
            // 3. Create admin notification
            $admin_message = "eSewa Payment Received - Amount: ₹" . number_format($payment_amount, 2) . 
                            " - From: " . $_SESSION['full_name'] . 
                            " (" . ($bill['customer_code'] ?? 'N/A') . ")" .
                            " - Bill: #" . $bill['bill_number'] . 
                            " - Transaction: " . $transaction_id .
                            ($is_admin ? " - Via: Admin QR Scan" : "");
            
            // Insert into admin_notifications
            try {
                $stmt = $pdo->prepare("INSERT INTO admin_notifications (title, message, type) VALUES (?, ?, 'payment')");
                $admin_notify_result = $stmt->execute(['Payment Received', $admin_message]);
                error_log("Admin notification created: " . ($admin_notify_result ? 'YES' : 'NO'));
            } catch (Exception $e) {
                error_log("Admin notification failed (continuing): " . $e->getMessage());
            }
            
            // 4. Create user notification
            $user_message = "Payment Successful - Amount: ₹" . number_format($payment_amount, 2) . 
                           " - Bill: #" . $bill['bill_number'] . 
                           " - Transaction: " . $transaction_id;
            
            try {
                $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, 'Payment Successful', ?, 'payment')");
                $user_notify_result = $stmt->execute([$user_id, $user_message]);
                error_log("User notification created: " . ($user_notify_result ? 'YES' : 'NO'));
            } catch (Exception $e) {
                error_log("User notification failed (continuing): " . $e->getMessage());
            }
            
            // 5. Also notify all admin users
            try {
                $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) 
                                      SELECT id, 'New Payment Received', ?, 'payment' FROM users WHERE user_type = 'admin' OR is_admin = 1");
                $all_admin_notify_result = $stmt->execute([$admin_message]);
                error_log("Admin users notified: " . ($all_admin_notify_result ? 'YES' : 'NO'));
            } catch (Exception $e) {
                error_log("Error notifying admin users (continuing): " . $e->getMessage());
                // Continue anyway, don't fail the whole transaction
            }
            
            $pdo->commit();
            error_log("Transaction committed successfully");
            
            // Store success data in session
            $_SESSION['payment_success'] = [
                'transaction_id' => $transaction_id,
                'amount' => $payment_amount,
                'bill_number' => $bill['bill_number'],
                'admin_scan' => $is_admin,
                'payment_id' => $payment_id,
                'payment_time' => date('Y-m-d H:i:s')  // Store current time
            ];
            
            // Clear payment session
            unset($_SESSION['payment_session']);
            
            error_log("Redirecting to payment_success.php");
            error_log("Payment success session data: " . print_r($_SESSION['payment_success'], true));
            
            // IMPORTANT: Flush output buffer before redirect
            if (ob_get_length()) {
                ob_end_clean();
            }
            
            // Redirect to success page
            header("Location: payment_success.php");
            exit();
            
        } catch (Exception $e) {
            if (isset($pdo)) {
                $pdo->rollBack();
            }
            $error = "Payment processing failed. Please try again.";
            error_log("Payment Error: " . $e->getMessage());
            error_log("Error Trace: " . $e->getTraceAsString());
            
            // Show error on page for debugging
            $error .= "<br><small>Error: " . htmlspecialchars($e->getMessage()) . "</small>";
        }
    } else {
        $error = "Invalid credentials. Use: Mobile: 9800000001, MPIN: 1234";
        error_log("Invalid credentials entered");
    }
}

// Generate QR code (only if we need to show it) - UPDATED: Use final_amount for QR
$qr_url = "";
if ($show_qr_code) {
    $qr_data = PaymentConfig::generateEsewaQRData($bill['amount'], $payment_session['transaction_id']);
    $qr_url = "https://chart.googleapis.com/chart?chs=250x250&cht=qr&chl=" . urlencode($qr_data);
}

// Check if admin scan mode
$admin_scan_mode = isset($_GET['admin_scan']) && $_GET['admin_scan'] == 'true';

// Check if this is a QR scan redirect
$is_qr_redirect = $scan_complete || $auto_fill;

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
        
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
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
            <?php if (!$show_qr_code): ?>
                display: none;
            <?php endif; ?>
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Debug info styling */
        #debugInfo {
            background: #f0f0f0;
            padding: 10px;
            margin: 10px;
            border: 2px solid red;
            font-family: monospace;
            font-size: 12px;
        }
        
        #debugInfo h4 {
            margin-top: 0;
            color: #d00;
        }
        
        /* Loading overlay */
        #loadingOverlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            font-size: 1.2rem;
            flex-direction: column;
        }
        
        #loadingOverlay i {
            width: 3rem;
            height: 3rem;
            margin-bottom: 1rem;
            animation: spin 1s linear infinite;
        }
        
        .mode-indicator {
            background: #ffc107;
            color: #856404;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            display: inline-block;
            margin-top: 0.5rem;
            font-size: 0.9rem;
        }
        
        .qr-toggle-btn {
            background: #53c41a;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin: 1rem auto;
        }
        
        .qr-toggle-btn:hover {
            background: #389e0d;
        }
        
        .mode-info {
            background: #e8f4fd;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
            border-left: 4px solid #3498db;
        }
        
        .auto-fill-badge {
            background: #27ae60;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            display: inline-block;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
        
        .form-field-note {
            font-size: 0.85rem;
            color: #666;
            margin-top: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        .qr-redirect-notice {
            background: #d4edda;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border-left: 4px solid #27ae60;
            display: <?php echo $is_qr_redirect ? 'block' : 'none'; ?>;
        }
        
        .bill-breakdown {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 6px;
            margin: 0.5rem 0;
            border-left: 3px solid #3498db;
        }
        
        .breakdown-item {
            display: flex;
            justify-content: space-between;
            margin: 0.3rem 0;
        }
        
        .total-payable {
            border-top: 2px solid #3498db;
            padding-top: 0.5rem;
            margin-top: 0.5rem;
            font-size: 1.1rem;
            font-weight: bold;
        }
        
        @media (max-width: 768px) {
            .payment-body {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <!-- Debug Section - Visible for testing -->
    <div id="debugInfo" style="display: <?php echo isset($_GET['debug']) ? 'block' : 'none'; ?>">
        <h4>Debug Information:</h4>
        <p>Session ID: <?php echo session_id(); ?></p>
        <p>User ID: <?php echo $_SESSION['user_id']; ?></p>
        <?php if (isset($_SESSION['payment_session'])): ?>
            <p>Bill ID: <?php echo $payment_session['bill_id']; ?></p>
            <p>Transaction ID: <?php echo $payment_session['transaction_id']; ?></p>
        <?php else: ?>
            <p>Bill ID: NO PAYMENT SESSION</p>
        <?php endif; ?>
        <p>Base Amount: ₹<?php echo number_format($bill['base_amount'] ?? $bill['amount'], 2); ?></p>
        <p>Tax Amount: ₹<?php echo number_format($bill['tax_amount'] ?? 0, 2); ?></p>
        <p>Service Charge: ₹<?php echo number_format($bill['service_charge'] ?? 0, 2); ?></p>
        <p>Final Amount: ₹<?php echo number_format($bill['amount'], 2); ?></p>
        <p>Mode: <?php echo $mode; ?></p>
        <p>Show QR Code: <?php echo $show_qr_code ? 'YES' : 'NO'; ?></p>
        <p>Auto Fill: <?php echo $auto_fill ? 'YES' : 'NO'; ?></p>
        <p>Scan Complete: <?php echo $scan_complete ? 'YES' : 'NO'; ?></p>
        <p>POST Method Used: <?php echo $_SERVER['REQUEST_METHOD']; ?></p>
        <?php if ($_SERVER['REQUEST_METHOD'] == 'POST'): ?>
            <p>Form Submitted: YES</p>
            <p>Mobile: <?php echo $_POST['mobile'] ?? 'Not set'; ?></p>
            <p>MPIN: <?php echo $_POST['mpin'] ?? 'Not set'; ?></p>
        <?php else: ?>
            <p>Form Submitted: NO</p>
        <?php endif; ?>
        <p>Error: <?php echo $error ? htmlspecialchars($error) : 'None'; ?></p>
        <p><a href="?debug=1" style="color: blue;">Refresh Debug</a> | 
           <a href="<?php echo strtok($_SERVER["REQUEST_URI"], '?'); ?>" style="color: blue;">Hide Debug</a></p>
    </div>
    
    <div class="container">
        <div class="payment-container">
            <div class="payment-header">
                <i data-lucide="smartphone" style="width: 3rem; height: 3rem; margin-bottom: 1rem;"></i>
                <h1>eSewa Payment Gateway</h1>
                <p>Secure Digital Payment</p>
                <div class="mode-indicator">
                    <i data-lucide="<?php echo $mode == 'gateway' ? 'smartphone' : 'qrcode'; ?>"></i>
                    Mode: <?php 
                        if ($mode == 'qr' && $auto_fill) {
                            echo 'QR Payment (Scanned)';
                        } elseif ($mode == 'qr') {
                            echo 'QR Payment';
                        } else {
                            echo 'Gateway Payment';
                        }
                    ?>
                    <?php if ($auto_fill): ?>
                        <div class="auto-fill-badge" style="margin-top: 0.5rem;">
                            <i data-lucide="check-circle"></i> Auto-filled from QR scan
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="payment-body">
                <!-- Left: Payment Form -->
                <div>
                    <h3>Login to Pay</h3>
                    
                    <?php if ($is_qr_redirect): ?>
                        <div class="qr-redirect-notice" id="qrRedirectNotice">
                            <h4><i data-lucide="qrcode"></i> QR Code Scanned Successfully!</h4>
                            <p>Credentials have been auto-filled from the QR code scan.</p>
                            <p><strong>Mobile:</strong> 9800000001 | <strong>MPIN:</strong> 1234</p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i data-lucide="alert-circle"></i> 
                            <strong>Payment Error:</strong> <?php echo $error; ?>
                            <?php if (strpos($error, 'Error:') !== false): ?>
                                <br><small style="font-size: 0.8em; opacity: 0.8;">Check error log for details</small>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_GET['success']) && $_GET['success'] == '1'): ?>
                        <div class="alert alert-success">
                            <i data-lucide="check-circle"></i> 
                            <strong>Payment Successful!</strong> Redirecting to receipt...
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($admin_scan_mode): ?>
                        <div class="mode-info">
                            <h4><i data-lucide="camera"></i> Admin Scanner Mode</h4>
                            <p>You are scanning a customer's QR code as an administrator.</p>
                            <p><strong>Transaction ID:</strong> <?php echo htmlspecialchars($_GET['transaction_id'] ?? $payment_session['transaction_id']); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" id="paymentForm">
                        <div class="form-group">
                            <label><i data-lucide="phone"></i> Mobile Number</label>
                            <input type="text" name="mobile" 
                                   value="<?php 
                                       if ($admin_scan_mode) {
                                           echo 'admin';
                                       } elseif ($auto_fill) {
                                           echo '9800000001';
                                       } else {
                                           echo '';
                                       }
                                   ?>" 
                                   required class="form-control" id="mobileInput">
                            <?php if ($auto_fill): ?>
                                <div class="form-field-note">
                                    <i data-lucide="info" style="width: 0.9rem; height: 0.9rem;"></i>
                                    Auto-filled from QR code scan
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label><i data-lucide="lock"></i> MPIN</label>
                            <input type="password" name="mpin" 
                                   value="<?php 
                                       if ($admin_scan_mode) {
                                           echo 'admin123';
                                       } elseif ($auto_fill) {
                                           echo '1234';
                                       } else {
                                           echo '';
                                       }
                                   ?>" 
                                   required maxlength="6" class="form-control" id="mpinInput">
                            <?php if ($auto_fill): ?>
                                <div class="form-field-note">
                                    <i data-lucide="info" style="width: 0.9rem; height: 0.9rem;"></i>
                                    Auto-filled from QR code scan
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <input type="hidden" name="simulate_payment" value="1">
                        
                        <!-- UPDATED: Show final amount in payment button -->
                        <button type="submit" name="simulate_payment" class="btn btn-success" style="width: 100%; padding: 1rem;">
                            <i data-lucide="credit-card"></i> Pay ₹<?php echo number_format($bill['amount'], 2); ?>
                        </button>
                        
                        <div style="text-align: center; margin-top: 1rem;">
                            <small style="color: #666;">
                                <i data-lucide="info"></i> 
                                <?php if ($auto_fill): ?>
                                    Click "Pay" to complete payment with auto-filled credentials
                                <?php else: ?>
                                    Click once and wait for processing
                                <?php endif; ?>
                            </small>
                        </div>
                    </form>
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
                            <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong>
                        </div>
                        <div class="payment-detail">
                            <span>Customer Code:</span>
                            <strong><?php echo htmlspecialchars($bill['customer_code'] ?? 'N/A'); ?></strong>
                        </div>
                        
                        <!-- UPDATED: Add bill breakdown -->
                        <div class="bill-breakdown">
                            <div class="breakdown-item">
                                <span>Base Amount:</span>
                                <span>₹<?php echo number_format($bill['base_amount'] ?? $bill['amount'], 2); ?></span>
                            </div>
                            <div class="breakdown-item">
                                <span>Tax (<?php echo $bill['tax_rate'] ?? '13'; ?>%):</span>
                                <span>₹<?php echo number_format($bill['tax_amount'] ?? 0, 2); ?></span>
                            </div>
                            <div class="breakdown-item">
                                <span>Service Charge:</span>
                                <span>₹<?php echo number_format($bill['service_charge'] ?? 0, 2); ?></span>
                            </div>
                            <div class="breakdown-item total-payable">
                                <span>Total Payable:</span>
                                <span style="color: #e74c3c; font-weight: bold;">₹<?php echo number_format($bill['amount'], 2); ?></span>
                            </div>
                        </div>
                        
                        <div class="payment-detail">
                            <span>Transaction ID:</span>
                            <strong><?php echo $payment_session['transaction_id']; ?></strong>
                        </div>
                        <div class="payment-detail">
                            <span>Payment Method:</span>
                            <strong>
                                <?php 
                                if ($mode == 'qr' && $auto_fill) {
                                    echo 'eSewa QR Code (Scanned)';
                                } elseif ($mode == 'qr') {
                                    echo 'eSewa QR Code';
                                } else {
                                    echo 'eSewa Gateway';
                                }
                                ?>
                            </strong>
                        </div>
                        <div class="payment-detail">
                            <span>Payment Status:</span>
                            <strong>
                                <?php 
                                $status = $bill['payment_status'] ?? 'pending';
                                $color = $status == 'paid' ? '#27ae60' : '#e74c3c';
                                ?>
                                <span style="color: <?php echo $color; ?>">
                                    <?php echo ucfirst($status); ?>
                                </span>
                            </strong>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div style="margin-top: 2rem; padding: 1rem; background: #e8f4fd; border-radius: 8px;">
                        <h4><i data-lucide="zap"></i> Quick Actions</h4>
                        <div style="display: flex; gap: 0.5rem; margin-top: 0.5rem; flex-wrap: wrap;">
                            <a href="bills.php" class="btn btn-sm" style="background: #3498db; color: white;">
                                <i data-lucide="arrow-left"></i> Back to Bills
                            </a>
                        </div>
                        
                        <?php if ($auto_fill): ?>
                            <div style="margin-top: 1rem; padding: 0.75rem; background: #d4edda; border-radius: 5px;">
                                <p style="margin: 0; font-size: 0.9rem;">
                                    <i data-lucide="check-circle" style="width: 0.9rem; height: 0.9rem; margin-right: 0.3rem; vertical-align: middle;"></i>
                                    <strong>QR Scan Complete:</strong> Credentials auto-filled
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- QR Code (Hidden for Gateway mode AND when coming from QR scan) -->
                <?php if ($show_qr_code): ?>
                <div class="qr-container" id="qrContainer">
                    <h3><i data-lucide="qrcode"></i> QR Code Payment</h3>
                    <div style="display: inline-block; padding: 15px; background: white; border: 2px solid #53c41a; border-radius: 10px;">
                        <img src="<?php echo $qr_url; ?>" alt="QR Code" style="width: 250px; height: 250px;">
                    </div>
                    <p style="margin-top: 1rem;">Scan this QR code with eSewa app</p>
                    <p><small>Alternative payment method - same transaction ID</small></p>
                    
                    <button onclick="payWithQR()" class="qr-toggle-btn">
                        <i data-lucide="smartphone"></i> Pay with QR Code
                    </button>
                    
                    <div style="margin-top: 1rem; font-size: 0.9rem; color: #666;">
                        <p><i data-lucide="info"></i> QR contains: Mobile: 9800000001, MPIN: 1234</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
<script>
    lucide.createIcons();
    
    // Show manual test button if debug mode
    if (window.location.search.includes('debug=1')) {
        document.getElementById('manualTest').style.display = 'block';
    }
    
    // Manual test function
    function manualSubmit() {
        console.log('Manual form submission...');
        document.querySelector('input[name="mobile"]').value = '9800000001';
        document.querySelector('input[name="mpin"]').value = '1234';
        document.getElementById('paymentForm').submit();
    }
    
    // Show QR code function (for gateway mode)
    function showQRCode() {
        document.getElementById('qrContainer').style.display = 'block';
    }
    
    // Pay with QR function (for QR mode)
    function payWithQR() {
        // Redirect to QR payment page with same transaction
        window.location.href = 'esewa_qr_payment.php';
    }
    
    // Auto-highlight credentials for QR redirects
    document.addEventListener('DOMContentLoaded', function() {
        <?php if ($auto_fill): ?>
            // Highlight the auto-filled fields
            const mobileInput = document.getElementById('mobileInput');
            const mpinInput = document.getElementById('mpinInput');
            
            if (mobileInput && mpinInput) {
                // Add visual feedback
                mobileInput.style.borderColor = '#27ae60';
                mobileInput.style.boxShadow = '0 0 0 2px rgba(39, 174, 96, 0.2)';
                
                mpinInput.style.borderColor = '#27ae60';
                mpinInput.style.boxShadow = '0 0 0 2px rgba(39, 174, 96, 0.2)';
                
                // Remove highlight after 3 seconds
                setTimeout(() => {
                    mobileInput.style.borderColor = '';
                    mobileInput.style.boxShadow = '';
                    mpinInput.style.borderColor = '';
                    mpinInput.style.boxShadow = '';
                }, 3000);
            }
            
            // Auto-focus the pay button
            const payButton = document.querySelector('button[type="submit"]');
            if (payButton) {
                setTimeout(() => {
                    payButton.focus();
                }, 500);
            }
            
            // Show a brief message about auto-fill
            setTimeout(() => {
                const notice = document.getElementById('qrRedirectNotice');
                if (notice) {
                    notice.style.opacity = '0.7';
                    notice.style.transition = 'opacity 1s';
                }
            }, 3000);
        <?php endif; ?>
    });
    
    // SINGLE clean form handler
    document.getElementById('paymentForm').addEventListener('submit', function(e) {
        console.log('Form submission starting...');
        
        // Disable button and show loading
        const btn = this.querySelector('button[type="submit"]');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i data-lucide="loader-2" style="animation: spin 1s linear infinite;"></i> Processing Payment...';
            // Force icon refresh
            setTimeout(() => lucide.createIcons(), 100);
        }
        
        // Add visual loading indicator
        const loadingDiv = document.createElement('div');
        loadingDiv.id = 'loadingOverlay';
        loadingDiv.innerHTML = `
            <div style="text-align: center;">
                <i data-lucide="loader-2" style="animation: spin 1s linear infinite; width: 3rem; height: 3rem;"></i>
                <p>Processing payment, please wait...</p>
                <p style="font-size: 0.9rem; opacity: 0.8;">Do not refresh or close this page</p>
            </div>
        `;
        document.body.appendChild(loadingDiv);
        
        // Allow form to submit normally
        console.log('Form submission proceeding...');
        return true;
    });
    
    // Check for form submission errors
    window.addEventListener('pageshow', function(event) {
        // If page is shown from cache (back button), re-enable submit button
        if (event.persisted) {
            const btn = document.querySelector('#paymentForm button[type="submit"]');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i data-lucide="credit-card"></i> Pay ₹<?php echo number_format($bill["amount"], 2); ?>';
                lucide.createIcons();
            }
            
            // Remove loading overlay if it exists
            const loadingOverlay = document.getElementById('loadingOverlay');
            if (loadingOverlay) {
                loadingOverlay.remove();
            }
        }
    });
</script>
<script src="js/logout.js"></script>
</body>
</html>