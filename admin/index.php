<?php
// For admin files:
$base_path = dirname(__DIR__);
require_once $base_path . '/includes/config.php';
// session_start(); // REMOVE THIS LINE - config.php already starts session
require_once $base_path . '/includes/db_connection.php';

// Redirect if not admin
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header("Location: ../customer/login.php");
    exit;
}

// Get statistics
$stats = [];

// Get total customers
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE is_admin = FALSE");
$stmt->execute();
$stats['total_customers'] = $stmt->fetch()['count'];

// Get total services (skip if table doesn't exist)
$stats['total_services'] = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM services");
    $stmt->execute();
    $stats['total_services'] = $stmt->fetch()['count'] ?? 0;
} catch (Exception $e) {
    // services table doesn't exist, use products instead
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM products");
    $stmt->execute();
    $stats['total_services'] = $stmt->fetch()['count'] ?? 0;
}

// Get total invoices (check if table exists first)
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM invoices");
    $stmt->execute();
    $stats['total_invoices'] = $stmt->fetch()['count'] ?? 0;
} catch (Exception $e) {
    $stats['total_invoices'] = 0;
}

// Get pending payments (check if table exists first)
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM invoices WHERE payment_status IN ('pending', 'due')");
    $stmt->execute();
    $stats['pending_payments'] = $stmt->fetch()['count'] ?? 0;
} catch (Exception $e) {
    $stats['pending_payments'] = 0;
}

// Get total revenue (check if table exists first)
try {
    $stmt = $pdo->prepare("SELECT SUM(amount) as total FROM invoices WHERE payment_status = 'paid'");
    $stmt->execute();
    $stats['total_revenue'] = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {
    $stats['total_revenue'] = 0;
}

// Get recent invoices (check if table exists first)
$recent_invoices = [];
try {
    $stmt = $pdo->prepare("
        SELECT i.*, u.full_name 
        FROM invoices i 
        JOIN users u ON i.user_id = u.id 
        ORDER BY i.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $recent_invoices = $stmt->fetchAll();
} catch (Exception $e) {
    $recent_invoices = [];
}

// Get pending approvals count
$stmt = $pdo->prepare("SELECT COUNT(*) as pending_approvals FROM users WHERE is_admin = FALSE AND email_verified = 1 AND admin_approved = 0 AND registration_status = 'verified'");
$stmt->execute();
$pending_approvals = $stmt->fetch()['pending_approvals'];

// Get system notifications
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE type = 'system' ORDER BY created_at DESC LIMIT 5");
$stmt->execute();
$notifications = $stmt->fetchAll();

// Check for new notifications
$stmt = $pdo->prepare("SELECT COUNT(*) as new_notifications FROM notifications WHERE type = 'system' AND is_read = FALSE");
$stmt->execute();
$new_notifications_count = $stmt->fetch()['new_notifications'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Online Service Billing System</title>
    <link rel="stylesheet" href="<?php echo $base_path; ?>/assets/css/style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .dashboard-container {
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .welcome-section {
            background: linear-gradient(135deg, #075B5E 0%, #0a7c80 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .welcome-section h1 {
            margin: 0;
            font-size: 2rem;
        }
        
        .welcome-section p {
            margin: 10px 0 0;
            opacity: 0.9;
            font-size: 1.1rem;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .dashboard-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        
        .dashboard-card:hover {
            transform: translateY(-2px);
        }
        
        .dashboard-card h3 {
            font-size: 2rem;
            margin: 0;
            color: #075B5E;
        }
        
        .dashboard-card p {
            margin: 10px 0 0;
            color: #666;
        }
        
        .recent-section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .section-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .section-title h2 {
            margin: 0;
            color: #075B5E;
        }
        
        .btn {
            display: inline-block;
            padding: 8px 20px;
            background: #075B5E;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 500;
            transition: background-color 0.2s;
        }
        
        .btn:hover {
            background: #0a7c80;
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #075B5E;
            border-bottom: 2px solid #f0f0f0;
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .status-paid {
            background: #d4edda;
            color: #155724;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-due {
            background: #f8d7da;
            color: #721c24;
        }
        
        .notification-badge {
            background: #e74c3c;
            color: white;
            border-radius: 50%;
            width: 22px;
            height: 22px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: bold;
            margin-left: 8px;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .quick-action-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-decoration: none;
            color: inherit;
            display: block;
            transition: transform 0.2s;
        }
        
        .quick-action-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .quick-action-card h3 {
            margin: 0 0 10px;
            color: #075B5E;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .quick-action-card p {
            margin: 0;
            color: #666;
            font-size: 0.9rem;
        }
        
        .notification-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .notification-item:last-child {
            border-bottom: none;
        }
        
        .notification-content {
            flex: 1;
        }
        
        .notification-time {
            font-size: 0.8rem;
            color: #999;
            margin-top: 5px;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }
        
        /* Header Navigation Styles */
        .header-nav {
            background: #075B5E;
            padding: 1rem 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .nav-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            color: white;
            text-decoration: none;
        }
        
        .nav-links {
            display: flex;
            list-style: none;
            gap: 1.5rem;
            margin: 0;
            padding: 0;
        }
        
        .nav-links a {
            color: rgba(255,255,255,0.9);
            text-decoration: none;
            padding: 0.5rem 0;
            transition: color 0.2s;
            position: relative;
        }
        
        .nav-links a:hover {
            color: white;
        }
        
        .nav-links a.active {
            color: white;
        }
        
        .nav-links a.active::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            right: 0;
            height: 2px;
            background: white;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: white;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .logout-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }
    </style>
</head>
<body>
    <!-- Custom Header Navigation -->
    <header class="header-nav">
        <div class="nav-container">
            <a href="index.php" class="logo">Admin Dashboard</a>
            <nav>
                <ul class="nav-links">
                    <li><a href="index.php" class="active">Dashboard</a></li>
                    <li><a href="manage_bills.php">Manage Bills</a></li>
                    <li><a href="manage_services.php">Manage Services</a></li>
                    <li><a href="reports.php">Manage Reports</a></li>
                    <li><a href="manage_users.php">Manage Users</a></li>
                    <li><a href="approve_users.php">Approve Users</a></li>
                </ul>
            </nav>
            <div class="user-info">
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
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </header>
    
    <div class="dashboard-container">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <h1>Welcome, Admin!</h1>
            <p>Admin Dashboard - Manage your billing system efficiently</p>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="approve_users.php" class="dashboard-card quick-action-card">
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                    <i data-lucide="user-check" style="width: 1.5rem; height: 1.5rem; color: #075B5E;"></i>
                    <h3 style="color: #075B5E; margin: 0;">Approve Users</h3>
                    <?php if ($pending_approvals > 0): ?>
                        <span style="background: #e74c3c; color: white; border-radius: 50%; width: 22px; height: 22px; font-size: 0.8rem; display: flex; align-items: center; justify-content: center; font-weight: bold;">
                            <?php echo $pending_approvals; ?>
                        </span>
                    <?php endif; ?>
                </div>
                <p style="margin: 0;">Review and approve new registrations</p>
            </a>
            
            <a href="manage_services.php" class="dashboard-card quick-action-card">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                    <i data-lucide="settings" style="width: 1.5rem; height: 1.5rem; color: #075B5E;"></i>
                    <h3 style="color: #075B5E; margin: 0;">Manage Services</h3>
                </div>
                <p style="margin: 0;">Add, edit, or remove services</p>
            </a>
            
            <a href="manage_users.php" class="dashboard-card quick-action-card">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                    <i data-lucide="users" style="width: 1.5rem; height: 1.5rem; color: #075B5E;"></i>
                    <h3 style="color: #075B5E; margin: 0;">Manage Users</h3>
                </div>
                <p style="margin: 0;">View and manage all users</p>
            </a>

            <a href="manage_bills.php" class="dashboard-card quick-action-card">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                    <i data-lucide="file-text" style="width: 1.5rem; height: 1.5rem; color: #075B5E;"></i>
                    <h3 style="color: #075B5E; margin: 0;">Manage Bills</h3>
                </div>
                <p style="margin: 0;">Create and manage customer bills</p>
            </a>

            <a href="reports.php" class="dashboard-card quick-action-card">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                    <i data-lucide="bar-chart" style="width: 1.5rem; height: 1.5rem; color: #075B5E;"></i>
                    <h3 style="color: #075B5E; margin: 0;">Manage Reports</h3>
                </div>
                <p style="margin: 0;">View analytics and generate reports</p>
            </a>

            <a href="manage_invoices.php" class="dashboard-card quick-action-card">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                    <i data-lucide="receipt" style="width: 1.5rem; height: 1.5rem; color: #075B5E;"></i>
                    <h3 style="color: #075B5E; margin: 0;">Manage Invoices</h3>
                </div>
                <p style="margin: 0;">View and manage all invoices</p>
            </a>
        </div>
        
        <!-- Statistics -->
        <div class="dashboard-grid">
            <div class="dashboard-card">
                <h3><?php echo $stats['total_customers']; ?></h3>
                <p>Total Customers</p>
            </div>
            
            <div class="dashboard-card">
                <h3><?php echo $stats['total_services']; ?></h3>
                <p>Total Services</p>
            </div>
            
            <div class="dashboard-card">
                <h3><?php echo $stats['total_invoices']; ?></h3>
                <p>Total Invoices</p>
            </div>
            
            <div class="dashboard-card">
                <h3><?php echo $stats['pending_payments']; ?></h3>
                <p>Pending Payments</p>
            </div>
            
            <div class="dashboard-card">
                <h3>$<?php echo number_format($stats['total_revenue'], 2); ?></h3>
                <p>Total Revenue</p>
            </div>
            
            <div class="dashboard-card">
                <h3><?php echo $pending_approvals; ?></h3>
                <p>Pending Approvals</p>
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">
            <!-- Recent Invoices -->
            <div class="recent-section">
                <div class="section-title">
                    <h2>Recent Invoices</h2>
                    <a href="manage_invoices.php" class="btn">View All</a>
                </div>
                
                <?php if (empty($recent_invoices)): ?>
                    <div class="empty-state">
                        <i data-lucide="file-text" style="width: 48px; height: 48px; opacity: 0.5; margin-bottom: 15px;"></i>
                        <p>No invoices found</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Invoice #</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_invoices as $invoice): ?>
                                    <tr>
                                        <td>#<?php echo $invoice['invoice_number']; ?></td>
                                        <td><?php echo htmlspecialchars($invoice['full_name']); ?></td>
                                        <td>$<?php echo number_format($invoice['amount'], 2); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $invoice['payment_status']; ?>">
                                                <?php echo ucfirst($invoice['payment_status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($invoice['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Notifications -->
            <div class="recent-section">
                <div class="section-title">
                    <h2>Notifications</h2>
                    <?php if ($new_notifications_count > 0): ?>
                        <span class="notification-badge"><?php echo $new_notifications_count; ?></span>
                    <?php endif; ?>
                </div>
                
                <?php if (empty($notifications)): ?>
                    <div class="empty-state">
                        <i data-lucide="bell" style="width: 48px; height: 48px; opacity: 0.5; margin-bottom: 15px;"></i>
                        <p>No notifications</p>
                    </div>
                <?php else: ?>
                    <div>
                        <?php foreach ($notifications as $notification): ?>
                            <div class="notification-item">
                                <i data-lucide="bell" style="width: 20px; height: 20px; color: #075B5E; margin-top: 3px;"></i>
                                <div class="notification-content">
                                    <strong><?php echo htmlspecialchars($notification['title']); ?></strong>
                                    <p style="margin: 5px 0 0; font-size: 0.9rem;"><?php echo htmlspecialchars($notification['message']); ?></p>
                                    <div class="notification-time">
                                        <?php echo date('M d, Y h:i A', strtotime($notification['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php include $base_path . '/includes/footer.php'; ?>
    
    <script>
        // Initialize Lucide icons
        lucide.createIcons();
        
        // Auto-refresh dashboard every 5 minutes
        setTimeout(function() {
            location.reload();
        }, 300000);
        
        // Highlight current page in navigation
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = window.location.pathname.split('/').pop();
            const navLinks = document.querySelectorAll('.nav-links a');
            
            navLinks.forEach(link => {
                const linkPage = link.getAttribute('href');
                if (linkPage === currentPage) {
                    link.classList.add('active');
                } else {
                    link.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>