<?php
session_start();
include '../includes/config.php';

// Redirect if not logged in as customer
if (!isset($_SESSION['user_id']) || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get payment history - UPDATED: Get created_at instead of payment_date
$stmt = $pdo->prepare("SELECT p.*, 
                      b.bill_type, 
                      b.bill_number, 
                      b.description,
                      b.final_amount as bill_final_amount,
                      b.tax_amount,
                      b.late_fee as service_charge
                      FROM payments p 
                      JOIN bills b ON p.bill_id = b.id 
                      WHERE p.user_id = ? 
                      ORDER BY p.created_at DESC, p.payment_date DESC");
$stmt->execute([$user_id]);
$payments = $stmt->fetchAll();

// Calculate statistics
$total_paid = 0;
$total_payments = count($payments);
foreach ($payments as $payment) {
    $total_paid += $payment['amount'];
}

// Get current date and time for display
date_default_timezone_set('Asia/Kathmandu');
$current_datetime = date('M d, Y h:i A');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment History - BillPay Pro</title>
    <link rel="stylesheet" href="../css/style.css">
    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .stat-card h3 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            color: #2c3e50;
        }
        
        .payment-item {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #27ae60;
        }
        
        .payment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .payment-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .no-payments {
            text-align: center;
            padding: 3rem;
            color: #666;
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
        
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .status-completed {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-failed {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .status-refunded {
            background-color: #e2e3e5;
            color: #383d41;
        }
        
        .datetime-display {
            background: #e8f5e9;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
            font-family: monospace;
            border-left: 4px solid #27ae60;
            display: flex;
            align-items: center;
            gap: 0.5rem;
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
            .payment-details {
                grid-template-columns: 1fr;
            }
            
            .payment-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
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
                    <li><a href="payment_history.php" style="color: #3498db;">Payment History</a></li>
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
            <i data-lucide="history" class="icon"></i>
            <h1>Payment History</h1>
        </div>
        
        <!-- Current Date & Time -->
        <div class="datetime-display">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#27ae60" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <polyline points="12 6 12 12 16 14"></polyline>
            </svg>
            <strong>Current Time:</strong> <span id="currentTime"><?php echo $current_datetime; ?></span>
        </div>
        
        <!-- Statistics -->
        <div class="stats-cards">
            <div class="stat-card">
                <h3><?php echo $total_payments; ?></h3>
                <p>Total Payments</p>
            </div>
            <div class="stat-card">
                <h3>₹<?php echo number_format($total_paid, 2); ?></h3>
                <p>Total Amount Paid</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $total_payments > 0 ? '₹' . number_format($total_paid / $total_payments, 2) : '₹0.00'; ?></h3>
                <p>Average Payment</p>
            </div>
        </div>

        <div class="card">
            <div class="section-header">
                <i data-lucide="list" class="icon"></i>
                <h2>All Payments</h2>
            </div>
            
            <?php if (count($payments) > 0): ?>
                <!-- Search and Filter -->
                <div style="display: flex; gap: 1rem; margin-bottom: 1rem; flex-wrap: wrap;">
                    <input type="text" id="searchPayments" class="form-control" placeholder="Search by bill number, transaction ID...">
                    <select id="filterMethod" class="form-control" style="width: auto;">
                        <option value="">All Methods</option>
                        <option value="esewa">eSewa</option>
                        <option value="khalti">Khalti</option>
                        <option value="cash">Cash</option>
                        <option value="credit_card">Credit Card</option>
                        <option value="bank_transfer">Bank Transfer</option>
                    </select>
                    <select id="filterStatus" class="form-control" style="width: auto;">
                        <option value="">All Status</option>
                        <option value="completed">Completed</option>
                        <option value="pending">Pending</option>
                        <option value="failed">Failed</option>
                        <option value="refunded">Refunded</option>
                    </select>
                </div>
                
                <?php foreach ($payments as $payment): 
                    // Determine which timestamp to display
                    // Priority: 1. created_at, 2. payment_date with time, 3. payment_date without time
                    if (!empty($payment['created_at'])) {
                        $payment_time = date('M d, Y h:i A', strtotime($payment['created_at']));
                    } elseif (!empty($payment['payment_date']) && strpos($payment['payment_date'], ' ') !== false) {
                        // If payment_date has time component
                        $payment_time = date('M d, Y h:i A', strtotime($payment['payment_date']));
                    } else {
                        // If payment_date is date only, add default time
                        $payment_time = date('M d, Y', strtotime($payment['payment_date'])) . ' 12:00 PM';
                    }
                    
                    // Get bill details for breakdown
                    $tax_amount = $payment['tax_amount'] ?? 0;
                    $service_charge = $payment['service_charge'] ?? 0;
                    $bill_final_amount = $payment['bill_final_amount'] ?? $payment['amount'] ?? 0;
                    $base_amount = $bill_final_amount - $tax_amount - $service_charge;
                ?>
                    <div class="payment-item" 
                         data-method="<?php echo $payment['payment_method']; ?>"
                         data-status="<?php echo $payment['status']; ?>"
                         data-search="<?php echo strtolower($payment['bill_number'] . ' ' . $payment['transaction_id']); ?>">
                        <div class="payment-header">
                            <div>
                                <h3><?php echo ucfirst($payment['bill_type']); ?> Payment</h3>
                                <small>Transaction ID: <?php echo $payment['transaction_id']; ?></small>
                                <br>
                                <small>Bill No: <?php echo $payment['bill_number']; ?></small>
                            </div>
                            <div style="text-align: right;">
                                <strong style="font-size: 1.2rem; color: #27ae60;">
                                    ₹<?php echo number_format($payment['amount'], 2); ?>
                                </strong>
                                <br>
                                <small><?php echo $payment_time; ?></small>
                            </div>
                        </div>
                        
                        <!-- Bill Breakdown -->
                        <?php if ($tax_amount > 0 || $service_charge > 0): ?>
                        <div class="bill-breakdown">
                            <div class="breakdown-item">
                                <span>Base Amount:</span>
                                <span>₹<?php echo number_format($base_amount, 2); ?></span>
                            </div>
                            <?php if ($tax_amount > 0): ?>
                            <div class="breakdown-item">
                                <span>Tax:</span>
                                <span>₹<?php echo number_format($tax_amount, 2); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($service_charge > 0): ?>
                            <div class="breakdown-item">
                                <span>Service Charge:</span>
                                <span>₹<?php echo number_format($service_charge, 2); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="breakdown-item total-payable">
                                <span>Total Paid:</span>
                                <span style="color: #27ae60; font-weight: bold;">₹<?php echo number_format($payment['amount'], 2); ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="payment-details">
                            <div>
                                <strong>Payment Method:</strong> 
                                <span style="text-transform: uppercase;"><?php echo $payment['payment_method']; ?></span>
                            </div>
                            <div>
                                <strong>Status:</strong> 
                                <span class="status-badge status-<?php echo $payment['status']; ?>">
                                    <?php echo ucfirst($payment['status']); ?>
                                </span>
                            </div>
                            <div>
                                <strong>Reference No:</strong> 
                                <?php echo $payment['reference_number'] ? $payment['reference_number'] : 'N/A'; ?>
                            </div>
                            <div>
                                <strong>Payment ID:</strong> 
                                <?php echo $payment['id']; ?>
                            </div>
                            <?php if ($payment['description']): ?>
                                <div style="grid-column: 1 / -1;">
                                    <strong>Description:</strong> <?php echo $payment['description']; ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($payment['notes']): ?>
                                <div style="grid-column: 1 / -1; margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px dashed #ddd;">
                                    <strong>Notes:</strong> <?php echo $payment['notes']; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Debug info (hidden by default) -->
                        <div style="margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px dotted #eee; font-size: 0.8rem; color: #999; display: none;" class="debug-info">
                            <strong>Debug Info:</strong> 
                            Payment Date: <?php echo $payment['payment_date']; ?> | 
                            Created At: <?php echo $payment['created_at']; ?> | 
                            Display Time: <?php echo $payment_time; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-payments">
                    <i data-lucide="credit-card" class="empty-state-icon"></i>
                    <h3>No Payment History</h3>
                    <p>You haven't made any payments yet.</p>
                    <a href="payment.php" class="btn">Make Your First Payment</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Online Billing System - BCA Project | Tribhuvan University</p>
        </div>
    </footer>

    <script>
        // Update current time every minute
        function updateCurrentTime() {
            const now = new Date();
            const options = { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric',
                hour: '2-digit', 
                minute: '2-digit',
                hour12: true 
            };
            document.getElementById('currentTime').textContent = now.toLocaleDateString('en-US', options);
        }
        
        // Initial update
        updateCurrentTime();
        
        // Update every minute
        setInterval(updateCurrentTime, 60000);
        
        // Search functionality
        document.getElementById('searchPayments').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const payments = document.querySelectorAll('.payment-item');
            
            payments.forEach(payment => {
                const searchData = payment.dataset.search;
                payment.style.display = searchData.includes(searchTerm) ? '' : 'none';
            });
        });
        
        // Filter by payment method
        document.getElementById('filterMethod').addEventListener('change', function() {
            applyFilters();
        });
        
        // Filter by status
        document.getElementById('filterStatus').addEventListener('change', function() {
            applyFilters();
        });
        
        // Apply all filters
        function applyFilters() {
            const methodFilter = document.getElementById('filterMethod').value;
            const statusFilter = document.getElementById('filterStatus').value;
            const payments = document.querySelectorAll('.payment-item');
            
            payments.forEach(payment => {
                const paymentMethod = payment.dataset.method;
                const paymentStatus = payment.dataset.status;
                
                let methodMatch = !methodFilter || paymentMethod === methodFilter;
                let statusMatch = !statusFilter || paymentStatus === statusFilter;
                
                payment.style.display = (methodMatch && statusMatch) ? '' : 'none';
            });
        }
        
        // Toggle debug info with Ctrl+D
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'd') {
                e.preventDefault();
                const debugInfos = document.querySelectorAll('.debug-info');
                debugInfos.forEach(info => {
                    info.style.display = info.style.display === 'none' ? 'block' : 'none';
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