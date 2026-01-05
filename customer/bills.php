<?php
session_start();
include '../includes/config.php';

// Redirect if not logged in as customer
if (!isset($_SESSION['user_id']) || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get all bills for the user
$stmt = $pdo->prepare("SELECT b.*, s.service_name FROM bills b 
                      LEFT JOIN services s ON b.bill_type = s.service_type 
                      WHERE b.user_id = ? 
                      ORDER BY b.due_date ASC");
$stmt->execute([$user_id]);
$bills = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bills - BillPay Pro</title>
    <link rel="stylesheet" href="../css/style.css">
    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        .status-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: bold;
        }
        
        .status-pending {
            background: #ffeaa7;
            color: #e17055;
        }
        
        .status-paid {
            background: #55efc4;
            color: #00b894;
        }
        
        .bill-actions {
            display: flex;
            gap: 0.5rem;
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
                    <li><a href="bills.php" style="color: #3498db;">My Bills</a></li>
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
            <i data-lucide="file-text" class="icon"></i>
            <h1>My Bills</h1>
        </div>
        
        <div class="card">
            <?php if (count($bills) > 0): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Bill ID</th>
                            <th>Service</th>
                            <th>Amount</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bills as $bill): ?>
                            <tr>
                                <td>#<?php echo $bill['id']; ?></td>
                                <td><?php echo $bill['service_name'] ?? ucfirst($bill['bill_type']); ?></td>
                                <td>₹<?php echo number_format($bill['amount'], 2); ?></td>
                                <td>
                                    <?php 
                                    $due_date = strtotime($bill['due_date']);
                                    $today = time();
                                    $days_left = ceil(($due_date - $today) / (60 * 60 * 24));
                                    
                                    if ($days_left < 0 && $bill['status'] == 'pending') {
                                        echo '<span style="color: #e74c3c;">' . date('M d, Y', $due_date) . ' (Overdue)</span>';
                                    } elseif ($days_left <= 3 && $bill['status'] == 'pending') {
                                        echo '<span style="color: #f39c12;">' . date('M d, Y', $due_date) . ' (' . $days_left . ' days left)</span>';
                                    } else {
                                        echo date('M d, Y', $due_date);
                                    }
                                    ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $bill['status']; ?>">
                                        <?php echo ucfirst($bill['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="bill-actions">
                                        <?php if ($bill['status'] == 'pending'): ?>
                                            <a href="payment.php?bill_id=<?php echo $bill['id']; ?>" class="btn btn-success">Pay Now</a>
                                        <?php else: ?>
                                            <span class="btn" style="background: #bdc3c7;">Paid</span>
                                        <?php endif; ?>
                                        <button class="btn" onclick="downloadBill(<?php echo $bill['id']; ?>)">Download</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Summary -->
                <div style="margin-top: 2rem; padding: 1rem; background: #f8f9fa; border-radius: 5px;">
                    <?php
                    $total_pending = 0;
                    $total_paid = 0;
                    foreach ($bills as $bill) {
                        if ($bill['status'] == 'pending') {
                            $total_pending += $bill['amount'];
                        } else {
                            $total_paid += $bill['amount'];
                        }
                    }
                    ?>
                    <h3>Bill Summary</h3>
                    <p><strong>Total Pending:</strong> ₹<?php echo number_format($total_pending, 2); ?></p>
                    <p><strong>Total Paid:</strong> ₹<?php echo number_format($total_paid, 2); ?></p>
                    <p><strong>Overall Total:</strong> ₹<?php echo number_format($total_pending + $total_paid, 2); ?></p>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 3rem;">
                    <i data-lucide="file-text" class="empty-state-icon"></i>
                    <h3>No Bills Found</h3>
                    <p>You don't have any bills at the moment.</p>
                    <p>Bills will appear here when they are generated by the admin.</p>
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
        function downloadBill(billId) {
            alert('Bill download feature will be implemented soon! Bill ID: ' + billId);
            // In next version, this will generate and download PDF
        }
        
        // Initialize Lucide Icons
        lucide.createIcons();
    </script>
    <script src="../js/script.js"></script>
</body>
</html>