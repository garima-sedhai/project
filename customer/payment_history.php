<?php
session_start();
include '../includes/config.php';

// Redirect if not logged in as customer
if (!isset($_SESSION['user_id']) || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get payment history
$stmt = $pdo->prepare("SELECT p.*, b.bill_type, b.bill_number, b.description 
                      FROM payments p 
                      JOIN bills b ON p.bill_id = b.id 
                      WHERE p.user_id = ? 
                      ORDER BY p.payment_date DESC");
$stmt->execute([$user_id]);
$payments = $stmt->fetchAll();

// Calculate statistics
$total_paid = 0;
$total_payments = count($payments);
foreach ($payments as $payment) {
    $total_paid += $payment['payment_amount'];
}
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
                <h3><?php echo $total_payments > 0 ? round($total_paid / $total_payments, 2) : 0; ?></h3>
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
                <div style="display: flex; gap: 1rem; margin-bottom: 1rem;">
                    <input type="text" id="searchPayments" class="form-control" placeholder="Search payments...">
                    <select id="filterMethod" class="form-control" style="width: auto;">
                        <option value="">All Methods</option>
                        <option value="esewa">eSewa</option>
                        <option value="khalti">Khalti</option>
                    </select>
                </div>
                
                <?php foreach ($payments as $payment): ?>
                    <div class="payment-item" data-method="<?php echo $payment['payment_method']; ?>">
                        <div class="payment-header">
                            <div>
                                <h3><?php echo ucfirst($payment['bill_type']); ?> Payment</h3>
                                <small>Transaction ID: <?php echo $payment['transaction_id']; ?></small>
                            </div>
                            <div style="text-align: right;">
                                <strong style="font-size: 1.2rem; color: #27ae60;">
                                    ₹<?php echo number_format($payment['payment_amount'], 2); ?>
                                </strong>
                                <br>
                                <small><?php echo date('M d, Y h:i A', strtotime($payment['payment_date'])); ?></small>
                            </div>
                        </div>
                        
                        <div class="payment-details">
                            <div>
                                <strong>Bill Number:</strong> <?php echo $payment['bill_number']; ?>
                            </div>
                            <div>
                                <strong>Payment Method:</strong> 
                                <span style="text-transform: uppercase;"><?php echo $payment['payment_method']; ?></span>
                            </div>
                            <?php if ($payment['description']): ?>
                                <div style="grid-column: 1 / -1;">
                                    <strong>Description:</strong> <?php echo $payment['description']; ?>
                                </div>
                            <?php endif; ?>
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
        // Search functionality
        document.getElementById('searchPayments').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const payments = document.querySelectorAll('.payment-item');
            
            payments.forEach(payment => {
                const text = payment.textContent.toLowerCase();
                payment.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
        
        // Filter by payment method
        document.getElementById('filterMethod').addEventListener('change', function() {
            const method = this.value;
            const payments = document.querySelectorAll('.payment-item');
            
            payments.forEach(payment => {
                if (!method || payment.dataset.method === method) {
                    payment.style.display = '';
                } else {
                    payment.style.display = 'none';
                }
            });
        });
        
        // Initialize Lucide Icons
        lucide.createIcons();
    </script>
    <script src="../js/script.js"></script>
</body>
</html>