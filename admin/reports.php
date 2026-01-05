<?php
session_start();
include '../includes/config.php';

// Redirect if not logged in as admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: login.php");
    exit();
}

// Date range filtering
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Get report data
$revenue_stmt = $pdo->prepare("SELECT 
    COUNT(*) as total_payments,
    COALESCE(SUM(payment_amount), 0) as total_revenue,
    COALESCE(AVG(payment_amount), 0) as avg_payment
    FROM payments 
    WHERE payment_date BETWEEN ? AND ?");
$revenue_stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
$revenue_stats = $revenue_stmt->fetch();

// Bill statistics
$bill_stmt = $pdo->prepare("SELECT 
    COUNT(*) as total_bills,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_bills,
    SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_bills,
    COALESCE(SUM(amount), 0) as total_amount
    FROM bills 
    WHERE created_at BETWEEN ? AND ?");
$bill_stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
$bill_stats = $bill_stmt->fetch();

// Recent payments
$recent_payments_stmt = $pdo->prepare("SELECT p.*, u.full_name, b.bill_type 
    FROM payments p 
    JOIN users u ON p.user_id = u.id 
    JOIN bills b ON p.bill_id = b.id 
    WHERE p.payment_date BETWEEN ? AND ? 
    ORDER BY p.payment_date DESC 
    LIMIT 10");
$recent_payments_stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
$recent_payments = $recent_payments_stmt->fetchAll();

// Payment method distribution
$method_stmt = $pdo->prepare("SELECT 
    payment_method,
    COUNT(*) as count,
    SUM(payment_amount) as amount
    FROM payments 
    WHERE payment_date BETWEEN ? AND ? 
    GROUP BY payment_method");
$method_stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
$method_stats = $method_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - BillPay Pro</title>
    <link rel="stylesheet" href="../css/style.css">
    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        .report-filters {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        
        .stats-grid {
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
        
        .report-section {
            margin: 2rem 0;
        }
        
        .chart-container {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            margin: 1rem 0;
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
        
        .btn-icon {
            width: 1.2rem;
            height: 1.2rem;
            margin-right: 0.5rem;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <div class="logo">BillPay Pro</div>
                <ul class="nav-links">
                    <li><a href="index.php">Dashboard</a></li>
                    <li><a href="manage_services.php">Manage Services</a></li>
                    <li><a href="manage_bills.php">Manage Bills</a></li>
                    <li><a href="manage_users.php">Manage Users</a></li>
                    <li><a href="reports.php" style="color: #3498db;">Reports</a></li>
                    <li style="display: flex; align-items: center;">
                        <div class="user-avatar" style="background: #e74c3c;">
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
                        <span class="admin-badge">ADMIN</span>
                        <a href="logout.php" style="margin-left: 15px; color: white;">Logout</a>
                    </li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="container">
        <div class="section-header">
            <i data-lucide="bar-chart" class="icon"></i>
            <h1>Reports & Analytics</h1>
        </div>

        <!-- Date Filters -->
        <div class="card">
            <div class="section-header">
                <i data-lucide="filter" class="icon"></i>
                <h2>Filter Reports</h2>
            </div>
            <form method="GET" action="" class="report-filters">
                <div style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 1rem; align-items: end;">
                    <div class="form-group">
                        <label for="start_date">Start Date</label>
                        <input type="date" id="start_date" name="start_date" class="form-control" 
                               value="<?php echo $start_date; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="end_date">End Date</label>
                        <input type="date" id="end_date" name="end_date" class="form-control" 
                               value="<?php echo $end_date; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn">Apply Filter</button>
                        <button type="button" class="btn" style="background: #95a5a6;" 
                                onclick="window.location.href='reports.php'">
                            Reset
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Revenue Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo $revenue_stats['total_payments']; ?></h3>
                <p>Total Payments</p>
            </div>
            <div class="stat-card">
                <h3>₹<?php echo number_format($revenue_stats['total_revenue'], 2); ?></h3>
                <p>Total Revenue</p>
            </div>
            <div class="stat-card">
                <h3>₹<?php echo number_format($revenue_stats['avg_payment'], 2); ?></h3>
                <p>Average Payment</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $bill_stats['paid_bills']; ?>/<?php echo $bill_stats['total_bills']; ?></h3>
                <p>Paid Bills</p>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
            <!-- Recent Payments -->
            <div class="card">
                <div class="section-header">
                    <i data-lucide="credit-card" class="icon"></i>
                    <h2>Recent Payments</h2>
                </div>
                <?php if (count($recent_payments) > 0): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_payments as $payment): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo $payment['full_name']; ?></strong><br>
                                        <small><?php echo ucfirst($payment['bill_type']); ?></small>
                                    </td>
                                    <td>₹<?php echo number_format($payment['payment_amount'], 2); ?></td>
                                    <td><?php echo strtoupper($payment['payment_method']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No payments found in the selected period.</p>
                <?php endif; ?>
            </div>

            <!-- Payment Methods -->
            <div class="card">
                <div class="section-header">
                    <i data-lucide="smartphone" class="icon"></i>
                    <h2>Payment Methods</h2>
                </div>
                <?php if (count($method_stats) > 0): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Method</th>
                                <th>Count</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($method_stats as $method): ?>
                                <tr>
                                    <td><?php echo strtoupper($method['payment_method']); ?></td>
                                    <td><?php echo $method['count']; ?></td>
                                    <td>₹<?php echo number_format($method['amount'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No payment data available.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Export Options -->
        <div class="card">
            <div class="section-header">
                <i data-lucide="download" class="icon"></i>
                <h2>Export Reports</h2>
            </div>
            <div class="report-actions">
                <button class="btn" onclick="downloadReport('payments')" style="display: flex; align-items: center;">
                    <i data-lucide="download" class="btn-icon"></i>
                    Export Payments CSV
                </button>
                <button class="btn" style="background: #3498db; display: flex; align-items: center;" onclick="downloadReport('bills')">
                    <i data-lucide="download" class="btn-icon"></i>
                    Export Bills CSV
                </button>
                <button class="btn" style="background: #2ecc71; display: flex; align-items: center;" onclick="downloadReport('customers')">
                    <i data-lucide="download" class="btn-icon"></i>
                    Export Customers CSV
                </button>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Online Billing System - BCA Project | Tribhuvan University</p>
        </div>
    </footer>

    <script>
        function downloadReport(type) {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            
            const url = `export_report.php?type=${type}&start_date=${startDate}&end_date=${endDate}`;
            window.open(url, '_blank');
        }
        
        // Set default date range to current month
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date();
            const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            
            // Format dates as YYYY-MM-DD
            const formatDate = (date) => date.toISOString().split('T')[0];
            
            // Set default values if not already set
            if (!document.getElementById('start_date').value) {
                document.getElementById('start_date').value = formatDate(firstDay);
            }
            if (!document.getElementById('end_date').value) {
                document.getElementById('end_date').value = formatDate(lastDay);
            }
            
            // Initialize Lucide Icons
            lucide.createIcons();
        });
    </script>
    <script src="../js/script.js"></script>
</body>
</html>