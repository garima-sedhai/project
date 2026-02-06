<?php
session_start();
include '../includes/config.php';
include '../includes/payment_config.php';

// Redirect if not logged in as customer
if (!isset($_SESSION['user_id']) || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'])) {
    header("Location: login.php");
    exit();
}

// Check if payment session exists
if (!isset($_SESSION['payment_session'])) {
    header("Location: payment.php");
    exit();
}

$payment_session = $_SESSION['payment_session'];
$user_id = $_SESSION['user_id'];

// Verify payment session is still valid (5 minutes)
if (time() - $payment_session['created_at'] > 300) {
    unset($_SESSION['payment_session']);
    header("Location: payment.php?error=session_expired");
    exit();
}

// Get bill details
$stmt = $pdo->prepare("SELECT b.*, u.full_name, u.phone, u.email FROM bills b 
                      JOIN users u ON b.user_id = u.id 
                      WHERE b.id = ? AND b.user_id = ?");
$stmt->execute([$payment_session['bill_id'], $user_id]);
$bill = $stmt->fetch();

if (!$bill) {
    unset($_SESSION['payment_session']);
    header("Location: payment.php?error=invalid_bill");
    exit();
}

// Handle payment initiation
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['confirm_payment'])) {
        $gateway = $payment_session['payment_method'];
        
        if ($gateway == 'esewa') {
            // Store pending payment record
            storePendingPayment($pdo, $payment_session, $bill, $user_id);
            
            // FIXED: Instead of redirecting to non-existent esewa_demo.php,
            // directly set up the session for payment_esewa.php
            $_SESSION['payment_redirect_data'] = [
                'bill_id' => $bill['id'],
                'user_id' => $user_id,
                'transaction_id' => $payment_session['transaction_id'],
                'payment_method' => 'esewa'
            ];
            
            // Redirect directly to the working payment page
            header("Location: payment_esewa.php");
            exit();
        } elseif ($gateway == 'khalti') {
            // Store pending payment record
            storePendingPayment($pdo, $payment_session, $bill, $user_id);
            
            // For Khalti, still use the verification flow
            header("Location: khalti_demo.php");
            exit();
        } elseif ($gateway == 'esewa_qr') {
            // Store pending payment record
            storePendingPayment($pdo, $payment_session, $bill, $user_id);
            
            // For QR payments, redirect to QR page
            $_SESSION['payment_redirect_data'] = [
                'bill_id' => $bill['id'],
                'user_id' => $user_id,
                'transaction_id' => $payment_session['transaction_id'],
                'payment_method' => 'esewa_qr'
            ];
            header("Location: esewa_qr_payment.php");
            exit();
        }
    }
}

// Store pending payment record
function storePendingPayment($pdo, $payment_session, $bill, $user_id) {
    $stmt = $pdo->prepare("INSERT INTO payments (user_id, bill_id, payment_amount, payment_method, transaction_id, status, payment_date) 
                          VALUES (?, ?, ?, ?, ?, 'pending', NOW())
                          ON DUPLICATE KEY UPDATE status = 'pending', payment_date = NOW()");
    return $stmt->execute([
        $user_id, 
        $bill['id'], 
        $bill['amount'], 
        $payment_session['payment_method'],
        $payment_session['transaction_id']
    ]);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Payment - BillPay Pro</title>
    <link rel="stylesheet" href="../css/style.css">
    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        .payment-process {
            max-width: 800px;
            margin: 2rem auto;
        }
        
        .payment-details {
            background: #f8f9fa;
            padding: 2rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        
        .payment-steps {
            display: flex;
            justify-content: space-between;
            margin: 2rem 0;
            position: relative;
        }
        
        .payment-step {
            text-align: center;
            flex: 1;
            position: relative;
            z-index: 2;
        }
        
        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #3498db;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.5rem;
            font-weight: bold;
        }
        
        .payment-steps::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 20px;
            right: 20px;
            height: 2px;
            background: #ddd;
            z-index: 1;
        }
        
        .gateway-instructions {
            background: #e8f4fd;
            padding: 1.5rem;
            border-radius: 8px;
            margin: 1rem 0;
        }
        
        .demo-credentials {
            background: #fff3cd;
            padding: 1rem;
            border-radius: 5px;
            margin: 1rem 0;
            border-left: 4px solid #ffc107;
        }
        
        .method-highlight {
            background: linear-gradient(135deg, #53c41a 0%, #389e0d 100%);
            color: white;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
            margin: 1rem 0;
        }
        
        .security-badges {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin: 1rem 0;
            flex-wrap: wrap;
        }
        
        .security-badge {
            background: #27ae60;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
        }
        
        .payment-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
            flex-wrap: wrap;
        }
        
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 1rem 0;
        }
        
        .feature-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #3498db;
        }
        
        .feature-icon {
            width: 2rem;
            height: 2rem;
            margin: 0 auto 1rem;
            color: #3498db;
        }
        
        .status-icon {
            width: 2rem;
            height: 2rem;
            margin: 0 auto 0.5rem;
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
        <div class="payment-process">
            <h1 style="text-align: center;">Complete Payment</h1>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <!-- Payment Steps -->
            <div class="payment-steps">
                <div class="payment-step">
                    <div class="step-number">1</div>
                    <small>Select Bill</small>
                </div>
                <div class="payment-step">
                    <div class="step-number">2</div>
                    <small>Choose Method</small>
                </div>
                <div class="payment-step">
                    <div class="step-number" style="background: #2ecc71;">3</div>
                    <small>Make Payment</small>
                </div>
            </div>

            <div class="card">
                <div class="payment-details">
                    <h3>Payment Details</h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin: 1.5rem 0;">
                        <div>
                            <strong>Bill Number:</strong><br>
                            <?php echo $bill['bill_number']; ?>
                        </div>
                        <div>
                            <strong>Service:</strong><br>
                            <?php echo ucfirst($bill['bill_type']); ?>
                        </div>
                        <div>
                            <strong>Amount:</strong><br>
                            ₹<?php echo number_format($bill['amount'], 2); ?>
                        </div>
                        <div>
                            <strong>Payment Method:</strong><br>
                            <?php echo strtoupper($payment_session['payment_method']); ?>
                        </div>
                    </div>
                    
                    <div style="background: white; padding: 1rem; border-radius: 5px; margin-top: 1rem; text-align: center;">
                        <strong>Transaction ID:</strong> 
                        <code style="background: #f8f9fa; padding: 0.5rem 1rem; border-radius: 5px; font-family: monospace;">
                            <?php echo $payment_session['transaction_id']; ?>
                        </code>
                    </div>
                </div>

                <!-- Security Badges -->
                <div class="security-badges">
                    <div class="security-badge">SSL Secure</div>
                    <div class="security-badge">PCI Compliant</div>
                    <div class="security-badge">Fraud Protection</div>
                </div>

                <?php if ($payment_session['payment_method'] == 'esewa'): ?>
                    <!-- eSewa Payment -->
                    <div class="method-highlight">
                        <h3>eSewa Demo Payment</h3>
                        <p>Complete eSewa Payment Simulation Environment</p>
                    </div>
                    
                    <div class="gateway-instructions">
                        <h3>eSewa Payment Process</h3>
                        <p>You will be redirected to our <strong>eSewa Demo Environment</strong> where you can simulate payment with full functionality.</p>
                        
                        <div class="demo-credentials">
                            <h4>Demo Credentials (for testing):</h4>
                            <p><strong>Customer Mobile:</strong> 9800000001, 9800000002, 9800000003<br>
                            <strong>Customer Password:</strong> 1234<br>
                            <strong>Admin Login:</strong> mobile: admin, password: admin123</p>
                        </div>
                        
                        <div class="feature-grid">
                            <div class="feature-card">
                                <i data-lucide="shield" class="feature-icon"></i>
                                <h4>Secure Login</h4>
                                <p>Demo eSewa authentication</p>
                            </div>
                            <div class="feature-card">
                                <i data-lucide="credit-card" class="feature-icon"></i>
                                <h4>Payment Processing</h4>
                                <p>Simulate payment to admin</p>
                            </div>
                            <div class="feature-card">
                                <i data-lucide="smartphone" class="feature-icon"></i>
                                <h4>QR Code Support</h4>
                                <p>Scan and pay option</p>
                            </div>
                            <div class="feature-card">
                                <i data-lucide="bell" class="feature-icon"></i>
                                <h4>Instant Notifications</h4>
                                <p>Both parties notified</p>
                            </div>
                        </div>
                        
                        <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px; margin: 1rem 0;">
                            <h4>What to Expect:</h4>
                            <ol>
                                <li><strong>Login Page:</strong> Enter demo credentials</li>
                                <li><strong>Payment Summary:</strong> Review bill details</li>
                                <li><strong>QR Code Option:</strong> Alternative payment method</li>
                                <li><strong>Admin Scanner:</strong> For admin verification</li>
                                <li><strong>Payment Processing:</strong> Simulated transaction</li>
                                <li><strong>Confirmation:</strong> Success page with receipt</li>
                            </ol>
                        </div>
                    </div>
                    
                <?php elseif ($payment_session['payment_method'] == 'khalti'): ?>
                    <!-- Khalti Payment -->
                    <div class="method-highlight">
                        <h3>Khalti Demo Payment</h3>
                        <p>Simulated Khalti Payment Environment</p>
                    </div>
                    
                    <div class="gateway-instructions">
                        <h3>Khalti Payment Process</h3>
                        <p>You will be redirected to our <strong>Khalti Demo Environment</strong> where you can simulate payment.</p>
                        
                        <div class="demo-credentials">
                            <h4>Demo Credentials (for testing):</h4>
                            <p><strong>Mobile Number:</strong> 9800000001<br>
                            <strong>MPIN:</strong> 1111<br>
                            <strong>OTP:</strong> 123456</p>
                        </div>
                        
                        <div style="text-align: center; padding: 2rem;">
                            <i data-lucide="smartphone" style="width: 3rem; height: 3rem; margin-bottom: 1rem; color: #3498db;"></i>
                            <p><strong>You'll be redirected to Khalti Demo</strong></p>
                            <p>Complete the payment in the Khalti demo interface</p>
                        </div>
                    </div>
                    
                <?php elseif ($payment_session['payment_method'] == 'esewa_qr'): ?>
                    <!-- eSewa QR Payment -->
                    <div class="method-highlight">
                        <h3>eSewa QR Code Payment</h3>
                        <p>Instant QR Code Payment Solution</p>
                    </div>
                    
                    <div class="gateway-instructions">
                        <h3>QR Code Payment Process</h3>
                        <p>You will be redirected to our <strong>QR Code Payment Environment</strong> with both customer and admin scanning options.</p>
                        
                        <div class="demo-credentials">
                            <h4>How QR Payment Works:</h4>
                            <p><strong>Option 1:</strong> Customer scans QR with eSewa app<br>
                            <strong>Option 2:</strong> Admin scans to receive payment<br>
                            <strong>Admin Credentials:</strong> mobile: admin, password: admin123</p>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div style="text-align: center; margin-top: 2rem; padding: 2rem; background: #f8f9fa; border-radius: 8px;">
                        <h3>Ready to Proceed?</h3>
                        <p><strong>Click the button below to enter the payment environment</strong></p>
                        
                        <div class="payment-actions">
                            <button type="submit" name="confirm_payment" class="btn" style="background: #27ae60; padding: 1rem 2rem; font-size: 1.1rem;">
                                Enter <?php echo strtoupper($payment_session['payment_method']); ?> Demo
                            </button>
                            <a href="payment.php" class="btn" style="background: #95a5a6; padding: 1rem 2rem;">
                                Change Payment Method
                            </a>
                        </div>
                        
                        <div style="margin-top: 1rem; color: #666;">
                            <small>You will be redirected to our secure demo payment environment</small>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Payment Instructions -->
            <div class="card" style="margin-top: 2rem;">
                <h3>Demo Payment Features</h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-top: 1rem;">
                    <div>
                        <h4>Complete Payment Flow</h4>
                        <ul>
                            <li>Bill validation and verification</li>
                            <li>Secure demo authentication</li>
                            <li>Payment processing simulation</li>
                            <li>Real-time status updates</li>
                            <li>Instant notifications</li>
                            <li>Transaction history recording</li>
                        </ul>
                    </div>
                    <div>
                        <h4>What You'll Experience</h4>
                        <ul>
                            <li>Realistic payment interface</li>
                            <li>Demo credentials authentication</li>
                            <li>QR code payment options</li>
                            <li>Admin payment verification</li>
                            <li>Success confirmation</li>
                            <li>Receipt generation</li>
                        </ul>
                    </div>
                </div>
                
                <div class="demo-credentials" style="margin-top: 1rem; grid-column: 1 / -1;">
                    <h4>Important Demo Information:</h4>
                    <ul>
                        <li>This is a <strong>COMPLETE DEMO ENVIRONMENT</strong> - no real money involved</li>
                        <li>All payments are simulated using test credentials</li>
                        <li>Full payment tracking and notification system</li>
                        <li>Admin receives instant payment notifications</li>
                        <li>Bill status updates automatically upon payment</li>
                        <li>Transaction recorded in payment history</li>
                    </ul>
                </div>
            </div>
            
            <!-- Real-time Status -->
            <div class="card" style="margin-top: 2rem;">
                <h3>Payment Status Tracking</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 1rem;">
                    <div style="text-align: center; padding: 1.5rem; background: #e8f4fd; border-radius: 8px;">
                        <i data-lucide="file-text" class="status-icon" style="color: #27ae60;"></i>
                        <strong>Bill Selected</strong>
                        <p>#<?php echo $bill['bill_number']; ?></p>
                        <small style="color: #27ae60;">Validated</small>
                    </div>
                    <div style="text-align: center; padding: 1.5rem; background: #fff3cd; border-radius: 8px;">
                        <i data-lucide="credit-card" class="status-icon" style="color: #f39c12;"></i>
                        <strong>Payment Method</strong>
                        <p><?php echo strtoupper($payment_session['payment_method']); ?></p>
                        <small style="color: #f39c12;">Ready</small>
                    </div>
                    <div style="text-align: center; padding: 1.5rem; background: #f8f9fa; border-radius: 8px;">
                        <i data-lucide="shield" class="status-icon" style="color: #27ae60;"></i>
                        <strong>Security</strong>
                        <p>Encrypted</p>
                        <small style="color: #27ae60;">Verified</small>
                    </div>
                    <div style="text-align: center; padding: 1.5rem; background: #e8f4fd; border-radius: 8px;">
                        <i data-lucide="bell" class="status-icon" style="color: #3498db;"></i>
                        <strong>Notifications</strong>
                        <p>Ready</p>
                        <small style="color: #3498db;">Active</small>
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
        // Add loading state to payment button
        document.addEventListener('DOMContentLoaded', function() {
            const paymentForm = document.querySelector('form');
            if (paymentForm) {
                paymentForm.addEventListener('submit', function() {
                    const submitButton = this.querySelector('button[type="submit"]');
                    if (submitButton) {
                        submitButton.disabled = true;
                        submitButton.innerHTML = 'Opening Demo Environment...';
                    }
                });
            }
        });
        
        // Initialize Lucide Icons
        lucide.createIcons();
    </script>
    <script src="../js/script.js"></script>
    <script src="js/logout.js"></script>
</body>
</html>