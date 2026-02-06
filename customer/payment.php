<?php
session_start();
include '../includes/config.php';

// Redirect if not logged in as customer
if (!isset($_SESSION['user_id']) || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$bill_id = isset($_GET['bill_id']) ? $_GET['bill_id'] : null;

// Get bill details if bill_id is provided
$bill = null;
if ($bill_id) {
    $stmt = $pdo->prepare("SELECT b.*, s.service_name, 
                          b.final_amount as total_payable, 
                          b.tax_amount, b.late_fee as service_charge
                          FROM bills b 
                          LEFT JOIN services s ON b.bill_type = s.service_type 
                          WHERE b.id = ? AND b.user_id = ?");
    $stmt->execute([$bill_id, $user_id]);
    $bill = $stmt->fetch();
    
    if ($bill) {
        $bill['formatted_date'] = date('M d, Y h:i A', strtotime($bill['created_at']));
        // Calculate breakdown
        $bill['base_amount'] = $bill['amount'];
        $bill['total_with_tax'] = $bill['amount'] + $bill['tax_amount'];
        $bill['final_total'] = $bill['final_amount'];
    }
}

// Get all pending bills for the user
$stmt = $pdo->prepare("SELECT b.*, s.service_name, 
                      b.final_amount as total_payable, 
                      b.tax_amount, b.late_fee as service_charge
                      FROM bills b 
                      LEFT JOIN services s ON b.bill_type = s.service_type 
                      WHERE b.user_id = ? AND b.status = 'pending' 
                      ORDER BY b.due_date ASC");
$stmt->execute([$user_id]);
$pending_bills = $stmt->fetchAll();

// Format and calculate for all pending bills
foreach ($pending_bills as &$pending_bill) {
    if (isset($pending_bill['created_at'])) {
        $pending_bill['formatted_date'] = date('M d, Y h:i A', strtotime($pending_bill['created_at']));
    }
    $pending_bill['base_amount'] = $pending_bill['amount'];
    $pending_bill['total_with_tax'] = $pending_bill['amount'] + $pending_bill['tax_amount'];
    $pending_bill['final_total'] = $pending_bill['final_amount'];
}
unset($pending_bill); // Break the reference

$message = '';
$message_type = '';

// Handle payment initiation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['initiate_payment'])) {
    $selected_bill_id = $_POST['bill_id'];
    $payment_method = $_POST['payment_method'];
    
    // Verify bill belongs to user and is pending
    $stmt = $pdo->prepare("SELECT * FROM bills WHERE id = ? AND user_id = ? AND status = 'pending'");
    $stmt->execute([$selected_bill_id, $user_id]);
    $selected_bill = $stmt->fetch();
    
    if ($selected_bill) {
        // Generate transaction ID
        $transaction_id = 'TXN' . date('YmdHis') . rand(100, 999);
        
        // Store payment session
        $_SESSION['payment_session'] = [
            'bill_id' => $selected_bill_id,
            'amount' => $selected_bill['final_amount'], // Use final_amount which includes tax and service charge
            'payment_method' => $payment_method,
            'transaction_id' => $transaction_id,
            'created_at' => time()
        ];
        
        // Redirect based on payment method
        if ($payment_method == 'esewa') {
            // Redirect to eSewa gateway payment (no QR code initially)
            header("Location: payment_esewa.php?mode=gateway");
            exit();
        } elseif ($payment_method == 'esewa_qr') {
            // Redirect to QR code payment
            header("Location: esewa_qr_payment.php");
            exit();
        } else {
            // For other payment methods (if any)
            header("Location: payment_process.php");
            exit();
        }
    } else {
        $message = "Invalid bill selected";
        $message_type = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Make Payment - BillPay Pro</title>
    <link rel="stylesheet" href="../css/style.css">
    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        .payment-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-top: 2rem;
        }
        
        .bill-selection {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
        }
        
        .bill-item {
            background: white;
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .bill-item.selected {
            border-color: #3498db;
            background-color: #f0f8ff;
        }
        
        .bill-item.overdue {
            border-color: #e74c3c;
            background-color: #ffeaa7;
        }
        
        .payment-methods {
            margin-top: 1.5rem;
        }
        
        .payment-method {
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .payment-method.selected {
            border-color: #3498db;
            background-color: #f0f8ff;
        }
        
        .payment-features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .feature-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .method-icon {
            width: 2.5rem;
            height: 2.5rem;
            margin-bottom: 1rem;
            color: #3498db;
        }
        
        .feature-icon {
            width: 2rem;
            height: 2rem;
            margin: 0 auto 1rem;
            color: #3498db;
        }
        
        .section-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .icon {
            width: 1.2rem;
            height: 1.2rem;
        }
        
        .empty-state-icon {
            width: 3rem;
            height: 3rem;
            color: #6c757d;
            margin: 0 auto 1rem auto;
            display: block;
        }
        
        .esewa-option {
            border-color: #53c41a;
            background: #f8fff9;
        }
        
        .esewa-option.selected {
            border-color: #389e0d;
            background: #e8f5e8;
        }
        
        /* Fix for form submission */
        form#paymentForm {
            margin: 0;
            padding: 0;
        }
        
        .pay-button-container {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #eee;
        }
        
        .method-description {
            font-size: 0.9rem;
            color: #666;
            margin-top: 0.5rem;
        }
        
        .method-highlight {
            background: #fff3cd;
            padding: 0.5rem;
            border-radius: 4px;
            margin-top: 0.5rem;
            border-left: 3px solid #ffc107;
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
        <div class="section-header">
            <i data-lucide="credit-card" class="icon"></i>
            <h1>Make Payment</h1>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type == 'success' ? 'success' : 'danger'; ?>">
                <?php if ($message_type == 'success'): ?>
                    <i data-lucide="check-circle" style="width: 1.2rem; height: 1.2rem; margin-right: 0.5rem; vertical-align: middle;"></i>
                <?php else: ?>
                    <i data-lucide="alert-circle" style="width: 1.2rem; height: 1.2rem; margin-right: 0.5rem; vertical-align: middle;"></i>
                <?php endif; ?>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if (count($pending_bills) > 0): ?>
            <div class="payment-container">
                <!-- Bill Selection -->
                <div class="card">
                    <div class="section-header">
                        <i data-lucide="file-text" class="icon"></i>
                        <h2>Select Bill to Pay</h2>
                    </div>
                    <form method="POST" action="" id="paymentForm">
                        <input type="hidden" name="initiate_payment" value="1">
                        
                        <div class="bill-selection">
                            <?php foreach ($pending_bills as $pending_bill): 
                                $due_date = strtotime($pending_bill['due_date']);
                                $today = time();
                                $is_overdue = $due_date < $today;
                                $is_selected = ($bill && $bill['id'] == $pending_bill['id']) || (!$bill && $pending_bill === reset($pending_bills));
                            ?>
                                <div class="bill-item <?php echo $is_overdue ? 'overdue' : ''; ?> <?php echo $is_selected ? 'selected' : ''; ?>" 
                                     onclick="selectBill(<?php echo $pending_bill['id']; ?>, <?php echo $pending_bill['final_amount']; ?>)">
                                    <input type="radio" name="bill_id" value="<?php echo $pending_bill['id']; ?>" 
                                           <?php echo $is_selected ? 'checked' : ''; ?> style="display: none;" required>
                                    <h4><?php echo $pending_bill['service_name'] ?? ucfirst($pending_bill['bill_type']); ?></h4>
                                    <p><strong>Base Amount:</strong> ₹<?php echo number_format($pending_bill['amount'], 2); ?></p>
                                    <div class="bill-breakdown">
                                        <div class="breakdown-item">
                                            <span>Tax:</span>
                                            <span>₹<?php echo number_format($pending_bill['tax_amount'], 2); ?></span>
                                        </div>
                                        <div class="breakdown-item">
                                            <span>Service Charge:</span>
                                            <span>₹<?php echo number_format($pending_bill['service_charge'], 2); ?></span>
                                        </div>
                                        <div class="breakdown-item total-payable">
                                            <span>Total Payable:</span>
                                            <span style="color: #e74c3c; font-weight: bold;">₹<?php echo number_format($pending_bill['final_amount'], 2); ?></span>
                                        </div>
                                    </div>
                                    <p><strong>Due Date:</strong> 
                                        <?php echo date('M d, Y', $due_date); ?>
                                        <?php if ($is_overdue): ?>
                                            <span style="color: #e74c3c; font-weight: bold;">(Overdue)</span>
                                        <?php endif; ?>
                                    </p>
                                    <?php if (isset($pending_bill['formatted_date'])): ?>
                                        <p><strong>Bill Generated:</strong> <?php echo $pending_bill['formatted_date']; ?></p>
                                    <?php endif; ?>
                                    <?php if ($pending_bill['description']): ?>
                                        <p><strong>Description:</strong> <?php echo $pending_bill['description']; ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Payment Methods -->
                        <div class="payment-methods">
                            <div class="section-header">
                                <i data-lucide="smartphone" class="icon"></i>
                                <h3>Select Payment Method</h3>
                            </div>
                            
                            <!-- eSewa Gateway Option -->
                            <div class="payment-method esewa-option selected" onclick="selectPaymentMethod('esewa')">
                                <input type="radio" name="payment_method" value="esewa" checked style="display: none;" required>
                                <div style="display: flex; align-items: center;">
                                    <i data-lucide="smartphone" class="method-icon"></i>
                                    <div style="margin-left: 15px;">
                                        <h4>eSewa Gateway</h4>
                                        <p>Pay with eSewa Wallet, Mobile Banking, or Connect IPS</p>
                                        <div class="method-description">
                                            <p>Direct payment through eSewa interface</p>
                                            <div class="method-highlight">
                                                <i data-lucide="shield"></i> QR code section will be hidden
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- eSewa QR Option -->
                            <div class="payment-method esewa-option" onclick="selectPaymentMethod('esewa_qr')">
                                <input type="radio" name="payment_method" value="esewa_qr" style="display: none;" required>
                                <div style="display: flex; align-items: center;">
                                    <i data-lucide="qrcode" class="method-icon"></i>
                                    <div style="margin-left: 15px;">
                                        <h4>eSewa QR Code</h4>
                                        <p>Scan QR code with eSewa app or use admin scanner</p>
                                        <div class="method-description">
                                            <p>First shows QR code, then option to switch to gateway</p>
                                            <div class="method-highlight">
                                                <i data-lucide="camera"></i> QR code shown first with "Pay with QR code" button
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="pay-button-container">
                            <button type="submit" name="submit_payment" id="payButton" class="btn" style="width: 100%; padding: 1rem; font-size: 1.1rem;">
                                <i data-lucide="arrow-right" style="width: 1.2rem; height: 1.2rem; margin-right: 8px;"></i>
                                Proceed to eSewa Payment
                            </button>
                            <p style="text-align: center; margin-top: 0.5rem; color: #666; font-size: 0.9rem;">
                                <i data-lucide="info" style="width: 1rem; height: 1rem; margin-right: 0.3rem; vertical-align: middle;"></i>
                                Click to enter eSewa payment environment
                            </p>
                        </div>
                    </form>
                </div>

                <!-- Payment Summary -->
                <div class="card">
                    <div class="section-header">
                        <i data-lucide="receipt" class="icon"></i>
                        <h2>Payment Summary</h2>
                    </div>
                    <div id="paymentSummary">
                        <?php if ($bill || count($pending_bills) > 0): 
                            $selected_bill = $bill ?: $pending_bills[0];
                            $due_date = strtotime($selected_bill['due_date']);
                            $today = time();
                            $is_overdue = $due_date < $today;
                        ?>
                            <div style="padding: 1.5rem; background: #f8f9fa; border-radius: 8px;">
                                <h3><?php echo $selected_bill['service_name'] ?? ucfirst($selected_bill['bill_type']); ?></h3>
                                <div style="display: flex; justify-content: space-between; margin: 1rem 0;">
                                    <span>Bill Number:</span>
                                    <strong><?php echo $selected_bill['bill_number']; ?></strong>
                                </div>
                                <div class="bill-breakdown">
                                    <div class="breakdown-item">
                                        <span>Base Amount:</span>
                                        <span>₹<?php echo number_format($selected_bill['amount'], 2); ?></span>
                                    </div>
                                    <div class="breakdown-item">
                                        <span>Tax (<?php echo $selected_bill['tax_rate'] ?? '13'; ?>%):</span>
                                        <span>₹<?php echo number_format($selected_bill['tax_amount'], 2); ?></span>
                                    </div>
                                    <div class="breakdown-item">
                                        <span>Service Charge:</span>
                                        <span>₹<?php echo number_format($selected_bill['service_charge'], 2); ?></span>
                                    </div>
                                    <div class="breakdown-item total-payable">
                                        <span>Total Payable:</span>
                                        <span style="color: #e74c3c; font-weight: bold;">₹<?php echo number_format($selected_bill['final_amount'], 2); ?></span>
                                    </div>
                                </div>
                                <div style="display: flex; justify-content: space-between; margin: 1rem 0;">
                                    <span>Due Date:</span>
                                    <span style="color: <?php echo $is_overdue ? '#e74c3c' : '#27ae60'; ?>">
                                        <?php echo date('M d, Y', $due_date); ?>
                                        <?php if ($is_overdue): ?>
                                            (Overdue)
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <?php if (isset($selected_bill['formatted_date'])): ?>
                                <div style="display: flex; justify-content: space-between; margin: 1rem 0;">
                                    <span>Bill Generated:</span>
                                    <span><?php echo $selected_bill['formatted_date']; ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($selected_bill['description']): ?>
                                    <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #ddd;">
                                        <strong>Description:</strong>
                                        <p><?php echo $selected_bill['description']; ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Payment Features -->
                            <div class="payment-features">
                                <div class="feature-card">
                                    <i data-lucide="shield" class="feature-icon"></i>
                                    <h4>Secure</h4>
                                    <p>Bank-level security</p>
                                </div>
                                <div class="feature-card">
                                    <i data-lucide="zap" class="feature-icon"></i>
                                    <h4>Instant</h4>
                                    <p>Real-time processing</p>
                                </div>
                                <div class="feature-card">
                                    <i data-lucide="bell" class="feature-icon"></i>
                                    <h4>Confirmed</h4>
                                    <p>Instant notifications</p>
                                </div>
                            </div>
                            
                            <div style="margin-top: 2rem; text-align: center;">
                                <p><small>By proceeding, you agree to our terms and conditions.</small></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Payment Method Info -->
            <div class="card" style="margin-top: 2rem;">
                <h2>eSewa Payment Information</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem; margin-top: 1rem;">
                    <div>
                        <h4><i data-lucide="smartphone" style="width: 1.2rem; height: 1.2rem; margin-right: 0.5rem;"></i>eSewa Gateway</h4>
                        <ul>
                            <li>Direct eSewa payment interface</li>
                            <li>No QR code section shown initially</li>
                            <li>Pay with demo credentials</li>
                            <li>Secure payment processing</li>
                            <li>Instant confirmation</li>
                        </ul>
                    </div>
                    <div>
                        <h4><i data-lucide="qrcode" style="width: 1.2rem; height: 1.2rem; margin-right: 0.5rem;"></i>eSewa QR Code</h4>
                        <ul>
                            <li>First shows large QR code for scanning</li>
                            <li>Includes "Pay with QR code" button</li>
                            <li>Alternative admin scanner option</li>
                            <li>Option to switch to gateway payment</li>
                            <li>Instant payment confirmation</li>
                        </ul>
                    </div>
                    <div>
                        <h4><i data-lucide="shield-check" style="width: 1.2rem; height: 1.2rem; margin-right: 0.5rem;"></i>Common Features</h4>
                        <ul>
                            <li>Bill validation before payment</li>
                            <li>Transaction ID tracking</li>
                            <li>Admin notification system</li>
                            <li>Payment history recording</li>
                            <li>Demo credentials for testing</li>
                        </ul>
                    </div>
                </div>
                
                <div class="demo-credentials" style="background: #fff3cd; padding: 1rem; border-radius: 5px; margin-top: 1rem; border-left: 4px solid #ffc107;">
                    <h4><i data-lucide="info" style="width: 1.2rem; height: 1.2rem; margin-right: 0.5rem;"></i>Demo Credentials</h4>
                    <p><strong>Customer Mobile:</strong> 9800000001, 9800000002, 9800000003</p>
                    <p><strong>MPIN:</strong> 1234</p>
                    <p><strong>Admin Scanner:</strong> mobile: admin, mpin: admin123</p>
                    <p><small>These are demo credentials for testing only. No real money involved.</small></p>
                </div>
            </div>
        <?php else: ?>
            <div class="card" style="text-align: center; padding: 3rem;">
                <i data-lucide="check-circle" class="empty-state-icon" style="color: #27ae60;"></i>
                <h3>No Pending Bills</h3>
                <p>You don't have any pending bills to pay at the moment.</p>
                <p>All your bills are up to date!</p>
                <a href="bills.php" class="btn">View All Bills</a>
                <a href="dashboard.php" class="btn" style="background: #3498db; margin-left: 1rem;">Back to Dashboard</a>
            </div>
        <?php endif; ?>
    </div>

    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Online Billing System - BCA Project | Tribhuvan University</p>
        </div>
    </footer>

    <script>
        let currentBillAmount = <?php echo isset($selected_bill) ? $selected_bill['final_amount'] : '0'; ?>;
        let currentPaymentMethod = 'esewa';
        
        function selectBill(billId, billAmount) {
            // Remove selected class from all bills
            document.querySelectorAll('.bill-item').forEach(item => {
                item.classList.remove('selected');
            });
            
            // Add selected class to clicked bill
            const selectedBill = document.querySelector(`.bill-item input[value="${billId}"]`).parentElement;
            selectedBill.classList.add('selected');
            
            // Update the radio button
            document.querySelector(`.bill-item input[value="${billId}"]`).checked = true;
            
            // Update current bill amount
            currentBillAmount = billAmount;
            
            // Update payment summary
            updatePaymentSummary(billId, billAmount);
        }
        
        function selectPaymentMethod(method) {
            // Remove selected class from all methods
            document.querySelectorAll('.payment-method').forEach(item => {
                item.classList.remove('selected');
            });
            
            // Add selected class to clicked method
            const selectedMethod = document.querySelector(`.payment-method input[value="${method}"]`).parentElement;
            selectedMethod.classList.add('selected');
            
            // Update the radio button
            document.querySelector(`.payment-method input[value="${method}"]`).checked = true;
            
            // Update current payment method
            currentPaymentMethod = method;
            
            // Update button text based on selection
            const payButton = document.getElementById('payButton');
            if (method === 'esewa_qr') {
                payButton.innerHTML = '<i data-lucide="qrcode" style="width: 1.2rem; height: 1.2rem; margin-right: 8px;"></i>Proceed to QR Payment';
            } else {
                payButton.innerHTML = '<i data-lucide="arrow-right" style="width: 1.2rem; height: 1.2rem; margin-right: 8px;"></i>Proceed to eSewa Gateway';
            }
        }
        
        function updatePaymentSummary(billId, billAmount) {
            // Show loading state
            document.getElementById('paymentSummary').innerHTML = `
                <div style="text-align: center; padding: 2rem;">
                    <i data-lucide="loader-2" style="width: 2rem; height: 2rem; animation: spin 1s linear infinite; color: #3498db;"></i>
                    <p>Loading bill details...</p>
                </div>
            `;
            
            // Fetch bill details via AJAX
            fetch(`../api/validate_bill.php?bill_id=${billId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.valid) {
                        const bill = data.bill;
                        const dueDate = new Date(bill.due_date);
                        const today = new Date();
                        const isOverdue = dueDate < today;
                        const formattedDate = bill.created_at ? new Date(bill.created_at).toLocaleDateString('en-US', { 
                            year: 'numeric', month: 'short', day: 'numeric',
                            hour: '2-digit', minute: '2-digit', hour12: true 
                        }) : 'N/A';
                        
                        document.getElementById('paymentSummary').innerHTML = `
                            <div style="padding: 1.5rem; background: #f8f9fa; border-radius: 8px;">
                                <h3>${bill.service_name || bill.bill_type}</h3>
                                <div style="display: flex; justify-content: space-between; margin: 1rem 0;">
                                    <span>Bill Number:</span>
                                    <strong>${bill.bill_number}</strong>
                                </div>
                                <div class="bill-breakdown">
                                    <div class="breakdown-item">
                                        <span>Base Amount:</span>
                                        <span>₹${parseFloat(bill.amount).toLocaleString('en-IN', {minimumFractionDigits: 2})}</span>
                                    </div>
                                    <div class="breakdown-item">
                                        <span>Tax (${bill.tax_rate || 13}%):</span>
                                        <span>₹${parseFloat(bill.tax_amount).toLocaleString('en-IN', {minimumFractionDigits: 2})}</span>
                                    </div>
                                    <div class="breakdown-item">
                                        <span>Service Charge:</span>
                                        <span>₹${parseFloat(bill.service_charge).toLocaleString('en-IN', {minimumFractionDigits: 2})}</span>
                                    </div>
                                    <div class="breakdown-item total-payable">
                                        <span>Total Payable:</span>
                                        <span style="color: #e74c3c; font-weight: bold;">₹${parseFloat(bill.final_amount).toLocaleString('en-IN', {minimumFractionDigits: 2})}</span>
                                    </div>
                                </div>
                                <div style="display: flex; justify-content: space-between; margin: 1rem 0;">
                                    <span>Due Date:</span>
                                    <span style="color: ${isOverdue ? '#e74c3c' : '#27ae60'}">
                                        ${dueDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}
                                        ${isOverdue ? '(Overdue)' : ''}
                                    </span>
                                </div>
                                <div style="display: flex; justify-content: space-between; margin: 1rem 0;">
                                    <span>Bill Generated:</span>
                                    <span>${formattedDate}</span>
                                </div>
                            </div>
                            
                            <div class="payment-features">
                                <div class="feature-card">
                                    <i data-lucide="shield" class="feature-icon"></i>
                                    <h4>Secure</h4>
                                    <p>Bank-level security</p>
                                </div>
                                <div class="feature-card">
                                    <i data-lucide="zap" class="feature-icon"></i>
                                    <h4>Instant</h4>
                                    <p>Real-time processing</p>
                                </div>
                                <div class="feature-card">
                                    <i data-lucide="bell" class="feature-icon"></i>
                                    <h4>Confirmed</h4>
                                    <p>Instant notifications</p>
                                </div>
                            </div>
                            
                            <div style="margin-top: 2rem; text-align: center;">
                                <p><small>By proceeding, you agree to our terms and conditions.</small></p>
                            </div>
                        `;
                    } else {
                        document.getElementById('paymentSummary').innerHTML = `
                            <div style="text-align: center; padding: 2rem; color: #e74c3c;">
                                <i data-lucide="alert-circle" style="width: 2rem; height: 2rem; margin-bottom: 1rem;"></i>
                                <h4>Bill Not Found</h4>
                                <p>Selected bill could not be validated.</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    document.getElementById('paymentSummary').innerHTML = `
                        <div style="text-align: center; padding: 2rem; color: #e74c3c;">
                            <i data-lucide="alert-triangle" style="width: 2rem; height: 2rem; margin-bottom: 1rem;"></i>
                            <h4>Validation Error</h4>
                            <p>Unable to validate bill at this time.</p>
                        </div>
                    `;
                });
        }
        
        // Initialize first bill as selected
        document.addEventListener('DOMContentLoaded', function() {
            const firstBill = document.querySelector('.bill-item');
            if (firstBill) {
                const billId = firstBill.querySelector('input').value;
                const billAmount = <?php echo isset($pending_bills[0]) ? $pending_bills[0]['final_amount'] : '0'; ?>;
                updatePaymentSummary(billId, billAmount);
            }
            
            // Debug: Log form submission
            const form = document.getElementById('paymentForm');
            if (form) {
                console.log('Payment form found:', form);
                
                form.addEventListener('submit', function(e) {
                    console.log('Form submit triggered');
                    
                    const selectedBill = form.querySelector('input[name="bill_id"]:checked');
                    const selectedMethod = form.querySelector('input[name="payment_method"]:checked');
                    
                    console.log('Selected bill:', selectedBill ? selectedBill.value : 'none');
                    console.log('Selected method:', selectedMethod ? selectedMethod.value : 'none');
                    
                    if (!selectedBill || !selectedMethod) {
                        e.preventDefault();
                        alert('Please select both a bill and payment method');
                        console.log('Validation failed: missing selection');
                    } else {
                        // Show loading state on button
                        const submitBtn = form.querySelector('button[type="submit"]');
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i data-lucide="loader-2" style="width: 1.2rem; height: 1.2rem; margin-right: 8px; animation: spin 1s linear infinite;"></i>Processing...';
                        console.log('Form submitted successfully');
                    }
                });
            } else {
                console.error('Payment form not found!');
            }
            
            // Add CSS animation for spinner
            const style = document.createElement('style');
            style.textContent = `
                @keyframes spin {
                    from { transform: rotate(0deg); }
                    to { transform: rotate(360deg); }
                }
            `;
            document.head.appendChild(style);
            
            // Initialize Lucide Icons
            lucide.createIcons();
        });
    </script>
    <script src="../js/script.js"></script>
    <script src="js/logout.js"></script>
</body>
</html>