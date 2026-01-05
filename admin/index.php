<?php
session_start();
include '../includes/config.php';

// Redirect if not logged in as admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: login.php");
    exit();
}

// Get notification count for admin
$notification_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = FALSE");
$notification_stmt->execute([$_SESSION['user_id']]);
$notification_count = $notification_stmt->fetch()['count'];

// Get admin statistics
// Total customers
$stmt = $pdo->query("SELECT COUNT(*) as total_customers FROM users WHERE is_admin = FALSE");
$total_customers = $stmt->fetch()['total_customers'];

// Total pending bills
$stmt = $pdo->query("SELECT COUNT(*) as total_pending, COALESCE(SUM(amount), 0) as pending_amount FROM bills WHERE status = 'pending'");
$pending_stats = $stmt->fetch();

// Total paid bills
$stmt = $pdo->query("SELECT COUNT(*) as total_paid, COALESCE(SUM(amount), 0) as paid_amount FROM bills WHERE status = 'paid'");
$paid_stats = $stmt->fetch();

// Total revenue (from payments)
$stmt = $pdo->query("SELECT COALESCE(SUM(payment_amount), 0) as total_revenue FROM payments WHERE status = 'completed'");
$revenue = $stmt->fetch()['total_revenue'];

// Recent payments
$stmt = $pdo->prepare("SELECT p.*, u.full_name, u.phone, b.bill_type, b.bill_number 
                      FROM payments p 
                      JOIN users u ON p.user_id = u.id 
                      JOIN bills b ON p.bill_id = b.id 
                      WHERE p.status = 'completed'
                      ORDER BY p.payment_date DESC 
                      LIMIT 5");
$stmt->execute();
$recent_payments = $stmt->fetchAll();

// Recent bills
$stmt = $pdo->prepare("SELECT b.*, u.full_name, u.phone 
                      FROM bills b 
                      JOIN users u ON b.user_id = u.id 
                      ORDER BY b.created_at DESC 
                      LIMIT 5");
$stmt->execute();
$recent_bills = $stmt->fetchAll();

// Recent notifications
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$_SESSION['user_id']]);
$recent_notifications = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - BillPay Pro</title>
    <link rel="stylesheet" href="../css/style.css">
    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <div class="logo">BillPay Pro</div>
                <ul class="nav-links">
                    <li><a href="index.php">Dashboard</a></li>
                    <li><a href="manage_services.php">Services</a></li>
                    <li><a href="manage_bills.php">Bills</a></li>
                    <li><a href="manage_users.php">Users</a></li>
                    <li><a href="reports.php">Reports</a></li>
                    <li><a href="notifications.php">Notifications</a></li>
                    <li style="display: flex; align-items: center; gap: 15px;">
                        <!-- Notification Bell -->
                        <a href="notifications.php" style="position: relative; color: white; text-decoration: none;">
                            <i data-lucide="bell" style="width: 1.5rem; height: 1.5rem;"></i>
                            <?php if ($notification_count > 0): ?>
                                <span style="position: absolute; top: -5px; right: -5px; background: #e74c3c; color: white; border-radius: 50%; width: 20px; height: 20px; font-size: 0.8rem; display: flex; align-items: center; justify-content: center; font-weight: bold;">
                                    <?php echo $notification_count; ?>
                                </span>
                            <?php endif; ?>
                        </a>
                        
                        <!-- User info -->
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
        <!-- Welcome Section -->
        <div class="card" style="background: #075B5E; color: white;">
            <h1>Welcome, <?php echo $_SESSION['full_name']; ?>!</h1>
            <p class="mt-1">System Administrator Dashboard</p>
            <?php if ($notification_count > 0): ?>
                <div style="background: #FFE6E1; color: #075B5E; padding: 0.8rem; border-radius: 4px; margin-top: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i data-lucide="bell" style="width: 1.2rem; height: 1.2rem;"></i>
                    <strong>You have <?php echo $notification_count; ?> unread notification<?php echo $notification_count > 1 ? 's' : ''; ?></strong>
                    <a href="notifications.php" style="margin-left: auto; color: #e74c3c; font-weight: bold;">View Notifications</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Statistics Cards -->
        <div class="dashboard-cards">
            <div class="dashboard-card">
                <h3><?php echo $total_customers; ?></h3>
                <p>Total Customers</p>
            </div>
            <div class="dashboard-card">
                <h3><?php echo $pending_stats['total_pending']; ?></h3>
                <p>Pending Bills</p>
            </div>
            <div class="dashboard-card">
                <h3>₹<?php echo number_format($pending_stats['pending_amount'], 2); ?></h3>
                <p>Pending Amount</p>
            </div>
            <div class="dashboard-card">
                <h3>₹<?php echo number_format($revenue, 2); ?></h3>
                <p>Total Revenue</p>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="dashboard-cards">
            <a href="manage_services.php" class="dashboard-card" style="text-decoration: none; color: inherit; display: block;">
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                    <i data-lucide="settings" style="width: 1.5rem; height: 1.5rem; color: #075B5E;"></i>
                    <h3 style="color: #075B5E; margin: 0;">Manage Services</h3>
                </div>
                <p style="margin: 0;">Add, edit, or remove services</p>
            </a>
            <a href="manage_bills.php" class="dashboard-card" style="text-decoration: none; color: inherit; display: block;">
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                    <i data-lucide="file-text" style="width: 1.5rem; height: 1.5rem; color: #075B5E;"></i>
                    <h3 style="color: #075B5E; margin: 0;">Manage Bills</h3>
                </div>
                <p style="margin: 0;">Create and manage customer bills</p>
            </a>
            <a href="manage_users.php" class="dashboard-card" style="text-decoration: none; color: inherit; display: block;">
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                    <i data-lucide="users" style="width: 1.5rem; height: 1.5rem; color: #075B5E;"></i>
                    <h3 style="color: #075B5E; margin: 0;">Manage Users</h3>
                </div>
                <p style="margin: 0;">View and manage customers</p>
            </a>
            <a href="notifications.php" class="dashboard-card" style="text-decoration: none; color: inherit; display: block;">
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                    <i data-lucide="bell" style="width: 1.5rem; height: 1.5rem; color: #075B5E;"></i>
                    <h3 style="color: #075B5E; margin: 0;">Notifications</h3>
                    <?php if ($notification_count > 0): ?>
                        <span style="background: #e74c3c; color: white; border-radius: 50%; width: 22px; height: 22px; font-size: 0.8rem; display: flex; align-items: center; justify-content: center; font-weight: bold;">
                            <?php echo $notification_count; ?>
                        </span>
                    <?php endif; ?>
                </div>
                <p style="margin: 0;">View payment notifications</p>
            </a>
        </div>

        <!-- Recent Activity -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-top: 2rem;">
            <!-- Recent Payments -->
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <h2 style="color: #075B5E; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                        <i data-lucide="credit-card" style="width: 1.5rem; height: 1.5rem;"></i>
                        Recent Payments
                    </h2>
                    <a href="reports.php" style="font-size: 0.9rem; color: #3498db;">View All</a>
                </div>
                <?php if (count($recent_payments) > 0): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Service</th>
                                <th>Amount</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_payments as $payment): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo $payment['full_name']; ?></strong><br>
                                        <small><?php echo $payment['phone']; ?></small>
                                    </td>
                                    <td>
                                        <?php echo ucfirst($payment['bill_type']); ?><br>
                                        <small>Bill #<?php echo $payment['bill_number']; ?></small>
                                    </td>
                                    <td>₹<?php echo number_format($payment['payment_amount'], 2); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div style="text-align: center; padding: 2rem; color: #666;">
                        <i data-lucide="credit-card" style="width: 3rem; height: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                        <p>No recent payments.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recent Bills -->
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <h2 style="color: #075B5E; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                        <i data-lucide="file-text" style="width: 1.5rem; height: 1.5rem;"></i>
                        Recent Bills
                    </h2>
                    <a href="manage_bills.php" style="font-size: 0.9rem; color: #3498db;">View All</a>
                </div>
                <?php if (count($recent_bills) > 0): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Service</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_bills as $bill): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo $bill['full_name']; ?></strong><br>
                                        <small><?php echo $bill['phone']; ?></small>
                                    </td>
                                    <td><?php echo ucfirst($bill['bill_type']); ?></td>
                                    <td>₹<?php echo number_format($bill['amount'], 2); ?></td>
                                    <td>
                                        <?php if ($bill['status'] == 'paid'): ?>
                                            <span class="status-completed" style="display: flex; align-items: center; gap: 0.3rem;">
                                                <i data-lucide="check-circle" style="width: 1rem; height: 1rem;"></i>
                                                Paid
                                            </span>
                                        <?php else: ?>
                                            <span class="status-pending" style="display: flex; align-items: center; gap: 0.3rem;">
                                                <i data-lucide="clock" style="width: 1rem; height: 1rem;"></i>
                                                Pending
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div style="text-align: center; padding: 2rem; color: #666;">
                        <i data-lucide="file-text" style="width: 3rem; height: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                        <p>No recent bills.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Notifications -->
        <?php if (count($recent_notifications) > 0): ?>
        <div class="card" style="margin-top: 2rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h2 style="color: #075B5E; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                    <i data-lucide="bell" style="width: 1.5rem; height: 1.5rem;"></i>
                    Recent Notifications
                </h2>
                <a href="notifications.php" style="font-size: 0.9rem; color: #3498db;">View All</a>
            </div>
            <div style="max-height: 300px; overflow-y: auto;">
                <?php foreach ($recent_notifications as $notification): ?>
                    <div class="notification-item" style="background: <?php echo $notification['is_read'] ? '#f8f9fa' : '#f0f8ff'; ?>; 
                         border-left: 4px solid <?php echo $notification['is_read'] ? '#3498db' : '#e74c3c'; ?>;
                         padding: 1rem; margin-bottom: 0.5rem; border-radius: 4px;">
                        <div style="display: flex; justify-content: space-between; align-items: start;">
                            <div style="flex: 1;">
                                <strong><?php echo $notification['title']; ?></strong>
                                <p style="margin: 0.5rem 0 0 0; color: #666;"><?php echo $notification['message']; ?></p>
                            </div>
                            <?php if (!$notification['is_read']): ?>
                                <span style="background: #e74c3c; color: white; padding: 0.2rem 0.5rem; border-radius: 10px; font-size: 0.7rem; font-weight: bold;">
                                    NEW
                                </span>
                            <?php endif; ?>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.5rem;">
                            <span style="font-size: 0.8rem; color: #999;">
                                <i data-lucide="clock" style="width: 0.9rem; height: 0.9rem; margin-right: 0.3rem; vertical-align: middle;"></i>
                                <?php echo date('M d, Y h:i A', strtotime($notification['created_at'])); ?>
                            </span>
                            <span style="font-size: 0.8rem; color: #666; background: #e9ecef; padding: 0.2rem 0.5rem; border-radius: 10px;">
                                <?php echo ucfirst($notification['type']); ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- System Status -->
        <div class="card" style="margin-top: 2rem;">
            <h2 style="color: #075B5E; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                <i data-lucide="activity" style="width: 1.5rem; height: 1.5rem;"></i>
                System Status
            </h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <div style="text-align: center; padding: 1rem; background: #f8f9fa; border-radius: 8px;">
                    <i data-lucide="database" style="width: 2rem; height: 2rem; color: #27ae60; margin-bottom: 0.5rem;"></i>
                    <h4 style="margin: 0;">Database</h4>
                    <p style="margin: 0.5rem 0 0 0; color: #27ae60;">Connected</p>
                </div>
                <div style="text-align: center; padding: 1rem; background: #f8f9fa; border-radius: 8px;">
                    <i data-lucide="credit-card" style="width: 2rem; height: 2rem; color: #3498db; margin-bottom: 0.5rem;"></i>
                    <h4 style="margin: 0;">eSewa Gateway</h4>
                    <p style="margin: 0.5rem 0 0 0; color: #3498db;">Demo Mode</p>
                </div>
                <div style="text-align: center; padding: 1rem; background: #f8f9fa; border-radius: 8px;">
                    <i data-lucide="bell" style="width: 2rem; height: 2rem; color: #f39c12; margin-bottom: 0.5rem;"></i>
                    <h4 style="margin: 0;">Notifications</h4>
                    <p style="margin: 0.5rem 0 0 0; color: #f39c12;">Active</p>
                </div>
                <div style="text-align: center; padding: 1rem; background: #f8f9fa; border-radius: 8px;">
                    <i data-lucide="users" style="width: 2rem; height: 2rem; color: #9b59b6; margin-bottom: 0.5rem;"></i>
                    <h4 style="margin: 0;">Users Online</h4>
                    <p style="margin: 0.5rem 0 0 0; color: #9b59b6;"><?php echo $total_customers; ?> Registered</p>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> BillPay Pro - Online Billing System</p>
        </div>
    </footer>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();
        
        // Auto-refresh notifications every 30 seconds
        setInterval(() => {
            const notificationBell = document.querySelector('a[href="notifications.php"]');
            if (notificationBell) {
                // You could add AJAX here to check for new notifications
                // For now, just refresh the badge if needed
                console.log('Checking for new notifications...');
            }
        }, 30000);
        
        // Add click animation for notification bell
        const notificationBell = document.querySelector('a[href="notifications.php"] i');
        if (notificationBell) {
            notificationBell.parentElement.addEventListener('click', function() {
                notificationBell.style.transform = 'scale(1.2)';
                setTimeout(() => {
                    notificationBell.style.transform = 'scale(1)';
                }, 300);
            });
        }
    </script>
</body>
</html>