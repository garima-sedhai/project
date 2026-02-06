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

// DEBUG: Let's first check what tables and columns exist
try {
    // Check if payments table exists and get its columns
    $tables_stmt = $pdo->query("SHOW TABLES LIKE 'payments'");
    $payments_table_exists = $tables_stmt->fetch();
    
    if ($payments_table_exists) {
        // Get payments table columns
        $columns_stmt = $pdo->query("DESCRIBE payments");
        $payments_columns = $columns_stmt->fetchAll(PDO::FETCH_COLUMN);
        // Uncomment for debugging:
        // echo "<pre>Payments columns: ";
        // print_r($payments_columns);
        // echo "</pre>";
    }
    
    // Check if invoices table exists
    $tables_stmt = $pdo->query("SHOW TABLES LIKE 'invoices'");
    $invoices_table_exists = $tables_stmt->fetch();
    
    // Check if bills table exists (you showed this structure earlier)
    $tables_stmt = $pdo->query("SHOW TABLES LIKE 'bills'");
    $bills_table_exists = $tables_stmt->fetch();
    
} catch (Exception $e) {
    // Silently continue
}

// Determine which table to use and what column names to use
$table_name = 'bills'; // Default to bills table based on your earlier structure
$amount_column = 'amount'; // Default column name
$date_column = 'created_at'; // Default date column

// Try to determine the correct table and columns
if (isset($payments_table_exists) && $payments_table_exists) {
    // Check what amount column exists in payments table
    if (isset($payments_columns)) {
        if (in_array('total_amount', $payments_columns)) {
            $amount_column = 'total_amount';
            $table_name = 'payments';
        } elseif (in_array('amount', $payments_columns)) {
            $amount_column = 'amount';
            $table_name = 'payments';
        } elseif (in_array('final_amount', $payments_columns)) {
            $amount_column = 'final_amount';
            $table_name = 'payments';
        }
    }
} elseif (isset($bills_table_exists) && $bills_table_exists) {
    $table_name = 'bills';
    $amount_column = 'amount'; // Based on your bills table structure
} elseif (isset($invoices_table_exists) && $invoices_table_exists) {
    $table_name = 'invoices';
    $amount_column = 'total_amount'; // Common column name for invoices
}

// Get report data - Using dynamic table and column names
try {
    $revenue_stmt = $pdo->prepare("SELECT 
        COUNT(*) as total_payments,
        COALESCE(SUM($amount_column), 0) as total_revenue,
        COALESCE(AVG($amount_column), 0) as avg_payment
        FROM $table_name 
        WHERE $date_column BETWEEN ? AND ?");
    $revenue_stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $revenue_stats = $revenue_stmt->fetch();
} catch (Exception $e) {
    // If query fails, use default values
    $revenue_stats = [
        'total_payments' => 0,
        'total_revenue' => 0,
        'avg_payment' => 0
    ];
    error_log("Revenue query error: " . $e->getMessage());
}

// Bill statistics
try {
    // Try to get payment status column name
    $status_column = 'payment_status';
    if ($table_name === 'bills') {
        $status_column = 'status';
    }
    
    $bill_stmt = $pdo->prepare("SELECT 
        COUNT(*) as total_bills,
        SUM(CASE WHEN $status_column = 'pending' THEN 1 ELSE 0 END) as pending_bills,
        SUM(CASE WHEN $status_column = 'paid' THEN 1 ELSE 0 END) as paid_bills,
        COALESCE(SUM($amount_column), 0) as total_amount
        FROM $table_name 
        WHERE $date_column BETWEEN ? AND ?");
    $bill_stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $bill_stats = $bill_stmt->fetch();
} catch (Exception $e) {
    $bill_stats = [
        'total_bills' => 0,
        'pending_bills' => 0,
        'paid_bills' => 0,
        'total_amount' => 0
    ];
    error_log("Bill stats query error: " . $e->getMessage());
}

// Recent payments
$recent_payments = [];
try {
    if ($table_name === 'payments' || $table_name === 'bills') {
        $recent_payments_stmt = $pdo->prepare("SELECT 
            t.*, 
            u.full_name
            FROM $table_name t 
            JOIN users u ON t.user_id = u.id 
            WHERE t.$date_column BETWEEN ? AND ? 
            ORDER BY t.$date_column DESC 
            LIMIT 10");
        $recent_payments_stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
        $recent_payments = $recent_payments_stmt->fetchAll();
    }
} catch (Exception $e) {
    error_log("Recent payments query error: " . $e->getMessage());
}

// Payment method distribution
$method_stats = [];
try {
    if ($table_name === 'payments' || $table_name === 'bills') {
        // Check if payment_method column exists
        $method_stmt = $pdo->prepare("SELECT 
            payment_method,
            COUNT(*) as count,
            SUM($amount_column) as amount
            FROM $table_name 
            WHERE $date_column BETWEEN ? AND ? 
            GROUP BY payment_method");
        $method_stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
        $method_stats = $method_stmt->fetchAll();
    }
} catch (Exception $e) {
    error_log("Payment method query error: " . $e->getMessage());
}

// Payment status breakdown
$status_stats = [];
try {
    if ($table_name === 'payments' || $table_name === 'bills') {
        $status_column_name = ($table_name === 'bills') ? 'status' : 'payment_status';
        $status_stmt = $pdo->prepare("SELECT 
            $status_column_name as payment_status,
            COUNT(*) as count,
            SUM($amount_column) as amount
            FROM $table_name 
            WHERE $date_column BETWEEN ? AND ? 
            GROUP BY $status_column_name");
        $status_stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
        $status_stats = $status_stmt->fetchAll();
    }
} catch (Exception $e) {
    error_log("Payment status query error: " . $e->getMessage());
}
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
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card h3 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            color: #2c3e50;
        }
        
        .stat-card.payments { border-top: 4px solid #3498db; }
        .stat-card.revenue { border-top: 4px solid #2ecc71; }
        .stat-card.average { border-top: 4px solid #9b59b6; }
        .stat-card.paid { border-top: 4px solid #e74c3c; }
        
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
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-paid { background: #d4edda; color: #155724; }
        .status-failed { background: #f8d7da; color: #721c24; }
        .status-refunded { background: #cce5ff; color: #004085; }
        .status-processing { background: #cce5ff; color: #004085; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        
        .payment-method-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background: #e9ecef;
            border-radius: 4px;
            font-size: 0.8rem;
            text-transform: uppercase;
        }
        
        .method-cash { background: #d4edda; color: #155724; }
        .method-credit_card { background: #cce5ff; color: #004085; }
        .method-debit_card { background: #d1ecf1; color: #0c5460; }
        .method-online { background: #d6d8d9; color: #1b1e21; }
        .method-bank_transfer { background: #f8d7da; color: #721c24; }
        
        .info-note {
            background: #e7f3ff;
            border-left: 4px solid #3498db;
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        
        .info-note strong {
            color: #3498db;
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
            <div class="stat-card payments">
                <h3><?php echo $revenue_stats['total_payments']; ?></h3>
                <p>Total Transactions</p>
            </div>
            <div class="stat-card revenue">
                <h3>₹<?php echo number_format($revenue_stats['total_revenue'], 2); ?></h3>
                <p>Total Amount</p>
            </div>
            <div class="stat-card average">
                <h3>₹<?php echo number_format($revenue_stats['avg_payment'], 2); ?></h3>
                <p>Average Amount</p>
            </div>
            <div class="stat-card paid">
                <h3><?php echo $bill_stats['paid_bills']; ?>/<?php echo $bill_stats['total_bills']; ?></h3>
                <p>Paid / Total</p>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
            <!-- Recent Transactions -->
            <div class="card">
                <div class="section-header">
                    <i data-lucide="credit-card" class="icon"></i>
                    <h2>Recent Transactions</h2>
                </div>
                <?php if (count($recent_payments) > 0): ?>
                    <div style="overflow-x: auto;">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Bill #</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_payments as $payment): 
                                    $status_col = ($table_name === 'bills') ? 'status' : 'payment_status';
                                    $status = $payment[$status_col] ?? 'pending';
                                    $amount = $payment[$amount_column] ?? 0;
                                    $bill_number = $payment['bill_number'] ?? 'N/A';
                                ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($payment['full_name'] ?? 'Unknown'); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($bill_number); ?></td>
                                        <td>
                                            <strong>₹<?php echo number_format($amount, 2); ?></strong>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo htmlspecialchars($status); ?>">
                                                <?php echo ucfirst($status); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $date = $payment[$date_column] ?? $payment['created_at'] ?? '';
                                            if ($date) {
                                                echo date('M d, Y', strtotime($date));
                                            } else {
                                                echo 'N/A';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; padding: 2rem; color: #666;">No transactions found in the selected period.</p>
                <?php endif; ?>
            </div>

            <!-- Payment Methods & Status -->
            <div style="display: flex; flex-direction: column; gap: 2rem;">
                <!-- Payment Methods -->
                <?php if (count($method_stats) > 0): ?>
                <div class="card">
                    <div class="section-header">
                        <i data-lucide="smartphone" class="icon"></i>
                        <h2>Payment Methods</h2>
                    </div>
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
                                    <td>
                                        <?php if (!empty($method['payment_method'])): ?>
                                            <span class="payment-method-badge method-<?php echo htmlspecialchars($method['payment_method']); ?>">
                                                <?php echo strtoupper(str_replace('_', ' ', $method['payment_method'])); ?>
                                            </span>
                                        <?php else: ?>
                                            <span>N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $method['count']; ?></td>
                                    <td><strong>₹<?php echo number_format($method['amount'], 2); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <!-- Payment Status Breakdown -->
                <?php if (count($status_stats) > 0): ?>
                <div class="card">
                    <div class="section-header">
                        <i data-lucide="activity" class="icon"></i>
                        <h2>Payment Status</h2>
                    </div>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Count</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($status_stats as $status): ?>
                                <tr>
                                    <td>
                                        <span class="status-badge status-<?php echo htmlspecialchars($status['payment_status']); ?>">
                                            <?php echo ucfirst($status['payment_status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $status['count']; ?></td>
                                    <td><strong>₹<?php echo number_format($status['amount'], 2); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Export Options -->
        <div class="card">
            <div class="section-header">
                <i data-lucide="download" class="icon"></i>
                <h2>Export Reports</h2>
            </div>
            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <button class="btn" onclick="downloadReport('transactions')" style="display: flex; align-items: center;">
                    <i data-lucide="download" class="btn-icon"></i>
                    Export Transactions CSV
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
    <script src="js/logout.js"></script>
</body>
</html>