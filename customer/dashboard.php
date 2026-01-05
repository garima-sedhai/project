<?php
session_start();
include '../includes/config.php';

// Redirect if not logged in as customer
if (!isset($_SESSION['user_id']) || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Update login count and get user statistics
$stmt = $pdo->prepare("UPDATE users SET login_count = COALESCE(login_count, 0) + 1, last_login = NOW() WHERE id = ?");
$stmt->execute([$user_id]);

// Get user details including login count
$stmt = $pdo->prepare("SELECT *, COALESCE(login_count, 0) as login_count FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Determine welcome message based on login count
if ($user['login_count'] <= 1) {
    $welcome_message = "Welcome to BillPay Pro, " . $_SESSION['full_name'] . "!";
    $welcome_subtitle = "We're excited to have you onboard. Let's get started with your billing dashboard.";
} else {
    $welcome_message = "Welcome back, " . $_SESSION['full_name'] . "!";
    $welcome_subtitle = "Here's your billing overview and quick actions";
}

// Get user statistics
// Total pending bills
$stmt = $pdo->prepare("SELECT COUNT(*) as total_pending, COALESCE(SUM(amount), 0) as total_amount FROM bills WHERE user_id = ? AND status = 'pending'");
$stmt->execute([$user_id]);
$pending_stats = $stmt->fetch();

// Total paid bills
$stmt = $pdo->prepare("SELECT COUNT(*) as total_paid, COALESCE(SUM(amount), 0) as paid_amount FROM bills WHERE user_id = ? AND status = 'paid'");
$stmt->execute([$user_id]);
$paid_stats = $stmt->fetch();

// Recent bills (last 5)
$stmt = $pdo->prepare("SELECT b.*, s.service_name FROM bills b 
                      LEFT JOIN services s ON b.bill_type = s.service_type 
                      WHERE b.user_id = ? 
                      ORDER BY b.due_date ASC 
                      LIMIT 5");
$stmt->execute([$user_id]);
$recent_bills = $stmt->fetchAll();

// Recent payments (last 3)
$stmt = $pdo->prepare("SELECT p.*, b.bill_type FROM payments p 
                      JOIN bills b ON p.bill_id = b.id 
                      WHERE p.user_id = ? 
                      ORDER BY p.payment_date DESC 
                      LIMIT 3");
$stmt->execute([$user_id]);
$recent_payments = $stmt->fetchAll();

// Check if this is first login (for special first-time message)
$is_first_login = ($user['login_count'] == 1);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - BillPay Pro</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <div class="logo">BillPay Pro</div>
                <ul class="nav-links">
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="bills.php">My Bills</a></li>
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
                        <a href="logout.php" style="margin-left: 15px;">Logout</a>
                    </li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="container">
        <!-- Welcome Section -->
        <div class="card" style="background: #075B5E; color: white;">
            <h1><?php echo $welcome_message; ?></h1>
            <p class="mt-1"><?php echo $welcome_subtitle; ?></p>
            <?php if ($is_first_login): ?>
                <div style="background: #FFE6E1; color: #075B5E; padding: 1rem; border-radius: 4px; margin-top: 1rem;">
                    <strong>First Time Here!</strong>
                    <p class="mt-1" style="margin: 0;">Welcome to our platform! Get started by viewing your bills.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Payment Alert -->
        <?php if ($pending_stats['total_pending'] > 0): ?>
        <div class="alert alert-success">
            <strong>Action Required:</strong> You have <?php echo $pending_stats['total_pending']; ?> pending bill(s) totaling ₹<?php echo number_format($pending_stats['total_amount'], 2); ?>
            <a href="payment.php" class="btn" style="margin-left: 1rem;">Pay Now</a>
        </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="dashboard-cards">
            <div class="dashboard-card">
                <h3><?php echo $pending_stats['total_pending']; ?></h3>
                <p>Pending Bills</p>
            </div>
            <div class="dashboard-card">
                <h3>₹<?php echo number_format($pending_stats['total_amount'], 2); ?></h3>
                <p>Total Due</p>
            </div>
            <div class="dashboard-card">
                <h3><?php echo $paid_stats['total_paid']; ?></h3>
                <p>Paid Bills</p>
            </div>
            <div class="dashboard-card">
                <h3>₹<?php echo number_format($paid_stats['paid_amount'], 2); ?></h3>
                <p>Total Paid</p>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="dashboard-cards">
            <a href="bills.php" class="dashboard-card" style="text-decoration: none; color: inherit; display: block;">
                <h3 style="color: #075B5E;">View Bills</h3>
                <p>Check all your bills</p>
            </a>
            <a href="payment.php" class="dashboard-card" style="text-decoration: none; color: inherit; display: block;">
                <h3 style="color: #075B5E;">Make Payment</h3>
                <p>Pay pending bills</p>
            </a>
            <a href="payment_history.php" class="dashboard-card" style="text-decoration: none; color: inherit; display: block;">
                <h3 style="color: #075B5E;">Payment History</h3>
                <p>View past transactions</p>
            </a>
        </div>

        <!-- Recent Activity -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-top: 2rem;">
            <!-- Recent Bills -->
            <div class="card">
                <h2 style="color: #075B5E; margin-bottom: 1rem;">Recent Bills</h2>
                <?php if (count($recent_bills) > 0): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th>Amount</th>
                                <th>Due Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_bills as $bill): ?>
                                <tr>
                                    <td><?php echo $bill['service_name'] ?? ucfirst($bill['bill_type']); ?></td>
                                    <td>₹<?php echo number_format($bill['amount'], 2); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($bill['due_date'])); ?></td>
                                    <td>
                                        <?php if ($bill['status'] == 'pending'): ?>
                                            <span class="status-pending">Pending</span>
                                        <?php else: ?>
                                            <span class="status-completed">Paid</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="text-center mt-2">
                        <a href="bills.php" class="btn">View All Bills</a>
                    </div>
                <?php else: ?>
                    <p>No bills found.</p>
                <?php endif; ?>
            </div>

            <!-- Recent Payments -->
            <div class="card">
                <h2 style="color: #075B5E; margin-bottom: 1rem;">Recent Payments</h2>
                <?php if (count($recent_payments) > 0): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Service</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_payments as $payment): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                                    <td><?php echo ucfirst($payment['bill_type']); ?></td>
                                    <td>₹<?php echo number_format($payment['payment_amount'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="text-center mt-2">
                        <a href="payment_history.php" class="btn">View Full History</a>
                    </div>
                <?php else: ?>
                    <p>No payments yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> BillPay Pro - Online Billing System</p>
        </div>
    </footer>
</body>
</html>