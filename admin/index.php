<?php
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
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

// DEBUG: Check if notifications are being created
error_log("=== ADMIN DASHBOARD DEBUG ===");
error_log("User ID: " . $_SESSION['user_id']);
error_log("Is Admin: " . ($_SESSION['is_admin'] ? 'YES' : 'NO'));

// Check notifications directly
try {
    $debugStmt = $pdo->query("SELECT COUNT(*) as total, SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread FROM notifications");
    $debugResult = $debugStmt->fetch();
    error_log("Total notifications: " . $debugResult['total']);
    error_log("Unread notifications: " . $debugResult['unread']);
    
    // Check payment notifications specifically
    $debugStmt2 = $pdo->query("SELECT * FROM notifications WHERE type = 'payment' ORDER BY created_at DESC LIMIT 3");
    $paymentNotifs = $debugStmt2->fetchAll();
    error_log("Payment notifications found: " . count($paymentNotifs));
    foreach ($paymentNotifs as $notif) {
        error_log(" - " . $notif['title'] . ": " . $notif['message']);
    }
} catch (Exception $e) {
    error_log("Debug query failed: " . $e->getMessage());
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

// Get payment statistics (ADD THIS)
try {
    // Today's payments
    $stmt = $pdo->prepare("SELECT COUNT(*) as count, SUM(amount) as total 
                          FROM payments 
                          WHERE DATE(created_at) = CURDATE() AND status = 'completed'");
    $stmt->execute();
    $today_payments = $stmt->fetch();
    $stats['today_payments'] = $today_payments['count'] ?? 0;
    $stats['today_revenue'] = $today_payments['total'] ?? 0;
    
    // This month's payments
    $stmt = $pdo->prepare("SELECT COUNT(*) as count, SUM(amount) as total 
                          FROM payments 
                          WHERE MONTH(created_at) = MONTH(CURDATE()) 
                          AND YEAR(created_at) = YEAR(CURDATE())
                          AND status = 'completed'");
    $stmt->execute();
    $month_payments = $stmt->fetch();
    $stats['month_payments'] = $month_payments['count'] ?? 0;
    $stats['month_revenue'] = $month_payments['total'] ?? 0;
    
    // All payments
    $stmt = $pdo->prepare("SELECT COUNT(*) as count, SUM(amount) as total 
                          FROM payments WHERE status = 'completed'");
    $stmt->execute();
    $all_payments = $stmt->fetch();
    $stats['all_payments'] = $all_payments['count'] ?? 0;
    $stats['all_revenue'] = $all_payments['total'] ?? 0;
    
} catch (Exception $e) {
    // If payments table doesn't exist yet, set defaults
    $stats['today_payments'] = 0;
    $stats['today_revenue'] = 0;
    $stats['month_payments'] = 0;
    $stats['month_revenue'] = 0;
    $stats['all_payments'] = 0;
    $stats['all_revenue'] = 0;
    error_log("Payment stats error: " . $e->getMessage());
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

// Get ALL notifications (including payment notifications) - FIXED
$stmt = $pdo->prepare("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 10");
$stmt->execute();
$notifications = $stmt->fetchAll();

// Get unread notifications for header count
$stmt = $pdo->prepare("SELECT COUNT(*) as new_notifications FROM notifications WHERE is_read = FALSE");
$stmt->execute();
$new_notifications_count = $stmt->fetch()['new_notifications'];

// Get unread admin notifications count for header bell icon
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as unread_count FROM admin_notifications WHERE is_read = 0");
    $stmt->execute();
    $unread_admin_notifications = $stmt->fetch()['unread_count'] ?? 0;
} catch (Exception $e) {
    $unread_admin_notifications = 0;
}

// Get recent notifications for dropdown (last 5)
$stmt_recent = $pdo->prepare("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 5");
$stmt_recent->execute();
$recent_notifications = $stmt_recent->fetchAll();
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
        
        .dashboard-card small {
            display: block;
            margin-top: 5px;
            color: #27ae60;
            font-weight: bold;
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
        
        .notification-type {
            background: #f0f0f0;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 0.7rem;
            margin-left: 5px;
        }
        
        .notification-new {
            background: #e74c3c;
            color: white;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 0.7rem;
            margin-left: 5px;
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
            position: relative;
            z-index: 1000;
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
            position: relative;
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
        
        /* NEW: Notification Dropdown Styles */
        .notification-dropdown {
            position: relative;
            margin-right: 10px;
        }
        
        .notification-trigger {
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            padding: 8px;
            border-radius: 50%;
            transition: background-color 0.2s;
            cursor: pointer;
            position: relative;
            width: 40px;
            height: 40px;
        }
        
        .notification-trigger:hover {
            background: rgba(255,255,255,0.1);
        }
        
        .notification-count {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #e74c3c;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            z-index: 11;
        }
        
        .notification-panel {
            position: absolute;
            top: 100%;
            right: 0;
            width: 400px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            margin-top: 10px;
            z-index: 1001;
            display: none;
            overflow: hidden;
            max-height: 500px;
            animation: fadeIn 0.2s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .notification-panel.active {
            display: block;
        }
        
        .notification-panel-header {
            padding: 15px 20px;
            background: #075B5E;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .notification-panel-header h3 {
            margin: 0;
            font-size: 1.1rem;
        }
        
        .notification-panel-body {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .notification-panel-footer {
            padding: 15px 20px;
            background: #f8f9fa;
            border-top: 1px solid #eee;
            text-align: center;
        }
        
        .notification-dropdown-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 15px 20px;
            border-bottom: 1px solid #f0f0f0;
            transition: background-color 0.2s;
            cursor: pointer;
        }
        
        .notification-dropdown-item:hover {
            background: #f8f9fa;
        }
        
        .notification-dropdown-item.unread {
            background: #e8f4fd;
        }
        
        .notification-dropdown-item.read {
            opacity: 0.8;
        }
        
        .notification-dropdown-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-top: 2px;
        }
        
        .notification-payment {
            background: #d4edda;
            color: #155724;
        }
        
        .notification-system {
            background: #fff3cd;
            color: #856404;
        }
        
        .notification-user {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .notification-dropdown-content {
            flex: 1;
            min-width: 0;
        }
        
        .notification-dropdown-title {
            font-weight: 600;
            margin: 0 0 5px 0;
            font-size: 0.95rem;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        
        .notification-dropdown-message {
            color: #666;
            font-size: 0.85rem;
            margin: 0 0 5px 0;
            line-height: 1.4;
        }
        
        .notification-dropdown-time {
            color: #999;
            font-size: 0.75rem;
        }
        
        .notification-badge-small {
            background: #e74c3c;
            color: white;
            padding: 1px 6px;
            border-radius: 10px;
            font-size: 0.7rem;
            margin-left: 8px;
        }
        
        .view-all-link {
            color: #075B5E;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .view-all-link:hover {
            text-decoration: underline;
        }
        
        .mark-read-btn {
            background: transparent;
            border: 1px solid #ddd;
            color: #666;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 0.75rem;
            cursor: pointer;
            margin-top: 5px;
        }
        
        .mark-read-btn:hover {
            background: #f0f0f0;
        }
        
        /* Close dropdown when clicking outside */
        .notification-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 1000;
            display: none;
        }
        
        .notification-overlay.active {
            display: block;
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
                <!-- NEW: Notification Dropdown -->
                <div class="notification-dropdown">
                    <div class="notification-trigger" id="notificationTrigger">
                        <i data-lucide="bell"></i>
                        <?php if ($new_notifications_count > 0): ?>
                            <span class="notification-count" id="notificationCount"><?php echo $new_notifications_count; ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Notification Dropdown Panel -->
                    <div class="notification-panel" id="notificationPanel">
                        <div class="notification-panel-header">
                            <h3>Notifications</h3>
                            <div>
                                <?php if ($new_notifications_count > 0): ?>
                                    <span style="font-size: 0.9rem;"><?php echo $new_notifications_count; ?> new</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="notification-panel-body">
                            <?php if (empty($recent_notifications)): ?>
                                <div style="padding: 40px 20px; text-align: center; color: #999;">
                                    <i data-lucide="bell-off" style="width: 40px; height: 40px; opacity: 0.5; margin-bottom: 10px;"></i>
                                    <p>No notifications</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($recent_notifications as $notification): ?>
                                    <div class="notification-dropdown-item <?php echo $notification['is_read'] ? 'read' : 'unread'; ?>" data-id="<?php echo $notification['id']; ?>">
                                        <div class="notification-dropdown-icon notification-<?php echo $notification['type']; ?>">
                                            <?php if ($notification['type'] == 'payment'): ?>
                                                <i data-lucide="credit-card" style="width: 16px; height: 16px;"></i>
                                            <?php elseif ($notification['type'] == 'system'): ?>
                                                <i data-lucide="bell" style="width: 16px; height: 16px;"></i>
                                            <?php elseif ($notification['type'] == 'user'): ?>
                                                <i data-lucide="user" style="width: 16px; height: 16px;"></i>
                                            <?php else: ?>
                                                <i data-lucide="info" style="width: 16px; height: 16px;"></i>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="notification-dropdown-content">
                                            <div class="notification-dropdown-title">
                                                <span><?php echo htmlspecialchars($notification['title']); ?></span>
                                                <?php if (!$notification['is_read']): ?>
                                                    <span class="notification-badge-small">New</span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="notification-dropdown-message">
                                                <?php echo htmlspecialchars($notification['message']); ?>
                                            </p>
                                            <div class="notification-dropdown-time">
                                                <?php echo date('M d, Y h:i A', strtotime($notification['created_at'])); ?>
                                            </div>
                                            <?php if (!$notification['is_read']): ?>
                                                <button class="mark-read-btn" data-id="<?php echo $notification['id']; ?>">Mark as read</button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <div class="notification-panel-footer">
                            <a href="notifications.php" class="view-all-link">
                                <i data-lucide="list"></i> View all notifications
                            </a>
                        </div>
                    </div>
                    
                    <!-- Overlay to close dropdown when clicking outside -->
                    <div class="notification-overlay" id="notificationOverlay"></div>
                </div>
                
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
            
            <!-- ADD THESE PAYMENT STATS -->
            <div class="dashboard-card">
                <h3><?php echo $stats['today_payments']; ?></h3>
                <p>Today's Payments</p>
                <small>₹<?php echo number_format($stats['today_revenue'], 2); ?></small>
            </div>
            
            <div class="dashboard-card">
                <h3><?php echo $stats['month_payments']; ?></h3>
                <p>This Month</p>
                <small>₹<?php echo number_format($stats['month_revenue'], 2); ?></small>
            </div>
            
            <div class="dashboard-card">
                <h3><?php echo $stats['all_payments']; ?></h3>
                <p>Total Payments</p>
                <small>₹<?php echo number_format($stats['all_revenue'], 2); ?></small>
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
                    <h2>Recent Notifications</h2>
                    <a href="notifications.php" class="btn">View All</a>
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
                                <?php if ($notification['type'] == 'payment'): ?>
                                    <i data-lucide="credit-card" style="width: 20px; height: 20px; color: #27ae60; margin-top: 3px;"></i>
                                <?php elseif ($notification['type'] == 'system'): ?>
                                    <i data-lucide="bell" style="width: 20px; height: 20px; color: #075B5E; margin-top: 3px;"></i>
                                <?php elseif ($notification['type'] == 'user'): ?>
                                    <i data-lucide="user" style="width: 20px; height: 20px; color: #3498db; margin-top: 3px;"></i>
                                <?php else: ?>
                                    <i data-lucide="info" style="width: 20px; height: 20px; color: #f39c12; margin-top: 3px;"></i>
                                <?php endif; ?>
                                <div class="notification-content">
                                    <strong><?php echo htmlspecialchars($notification['title']); ?></strong>
                                    <p style="margin: 5px 0 0; font-size: 0.9rem;"><?php echo htmlspecialchars($notification['message']); ?></p>
                                    <div class="notification-time">
                                        <?php echo date('M d, Y h:i A', strtotime($notification['created_at'])); ?>
                                        <?php if ($notification['type']): ?>
                                            <span class="notification-type">
                                                <?php echo ucfirst($notification['type']); ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!$notification['is_read']): ?>
                                            <span class="notification-new">New</span>
                                        <?php endif; ?>
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

        // NEW: Notification Dropdown Functionality
        document.addEventListener('DOMContentLoaded', function() {
            const notificationTrigger = document.getElementById('notificationTrigger');
            const notificationPanel = document.getElementById('notificationPanel');
            const notificationOverlay = document.getElementById('notificationOverlay');
            
            if (notificationTrigger && notificationPanel) {
                console.log('✅ Notification dropdown system initialized');
                
                // Toggle dropdown when clicking bell
                notificationTrigger.addEventListener('click', function(e) {
                    e.stopPropagation();
                    notificationPanel.classList.toggle('active');
                    notificationOverlay.classList.toggle('active');
                    console.log('🔔 Notification panel toggled');
                });
                
                // Close dropdown when clicking overlay
                notificationOverlay.addEventListener('click', function() {
                    notificationPanel.classList.remove('active');
                    notificationOverlay.classList.remove('active');
                    console.log('📌 Notification panel closed (overlay)');
                });
                
                // Close dropdown when clicking outside
                document.addEventListener('click', function(e) {
                    if (!notificationPanel.contains(e.target) && !notificationTrigger.contains(e.target)) {
                        notificationPanel.classList.remove('active');
                        notificationOverlay.classList.remove('active');
                    }
                });
                
                // Mark as read functionality
                const markReadButtons = document.querySelectorAll('.mark-read-btn');
                markReadButtons.forEach(button => {
                    button.addEventListener('click', function(e) {
                        e.stopPropagation();
                        const notificationId = this.dataset.id;
                        const notificationItem = this.closest('.notification-dropdown-item');
                        
                        console.log('Marking notification as read:', notificationId);
                        
                        // AJAX call to mark as read
                        fetch('mark_notification_read.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({ id: notificationId })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                console.log('✅ Notification marked as read');
                                
                                // Update UI
                                notificationItem.classList.remove('unread');
                                notificationItem.classList.add('read');
                                this.remove();
                                
                                // Remove "New" badge
                                const newBadge = notificationItem.querySelector('.notification-badge-small');
                                if (newBadge) {
                                    newBadge.remove();
                                }
                                
                                // Update notification count
                                const notificationCount = document.getElementById('notificationCount');
                                if (notificationCount) {
                                    const currentCount = parseInt(notificationCount.textContent);
                                    if (currentCount > 1) {
                                        notificationCount.textContent = currentCount - 1;
                                    } else {
                                        notificationCount.remove();
                                    }
                                }
                                
                                // Update panel header count
                                const panelHeaderCount = notificationPanel.querySelector('.notification-panel-header span');
                                if (panelHeaderCount) {
                                    const headerCount = parseInt(panelHeaderCount.textContent.split(' ')[0]);
                                    if (headerCount > 1) {
                                        panelHeaderCount.textContent = (headerCount - 1) + ' new';
                                    } else {
                                        panelHeaderCount.remove();
                                    }
                                }
                            }
                        })
                        .catch(error => {
                            console.error('Error marking notification as read:', error);
                            alert('Failed to mark notification as read. Please try again.');
                        });
                    });
                });
                
                // Click on notification item
                const notificationItems = document.querySelectorAll('.notification-dropdown-item');
                notificationItems.forEach(item => {
                    item.addEventListener('click', function(e) {
                        if (!e.target.classList.contains('mark-read-btn')) {
                            console.log('📝 Notification item clicked');
                            // You could redirect to a specific notification page here
                            // window.location.href = 'notifications.php?id=' + this.dataset.id;
                        }
                    });
                });
                
                // Mark all as read button (optional - add if you want)
                const markAllReadBtn = document.createElement('button');
                markAllReadBtn.textContent = 'Mark all as read';
                markAllReadBtn.style.cssText = 'background: #28a745; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer; font-size: 0.8rem; margin-left: 10px;';
                markAllReadBtn.addEventListener('click', function() {
                    if (confirm('Mark all notifications as read?')) {
                        window.location.href = 'notifications.php?mark_all_read=1';
                    }
                });
                
                // Add to panel header if you want this feature
                // notificationPanel.querySelector('.notification-panel-header').appendChild(markAllReadBtn);
            } else {
                console.error('❌ Notification dropdown elements not found!');
            }
            
            // Debug: Log elements
            setTimeout(function() {
                console.log('=== DEBUG: Notification System ===');
                console.log('Trigger:', notificationTrigger);
                console.log('Panel:', notificationPanel);
                console.log('Overlay:', notificationOverlay);
                console.log('Recent notifications count:', <?php echo count($recent_notifications); ?>);
            }, 1000);
        });
    </script>
    <script>
    // Back button handling for admin login - Allows going back to index.php
    (function() {
        // Check if we came from logout
        const fromLogout = sessionStorage.getItem('admin_logout_redirect') === 'allow_back';
        
        if (fromLogout) {
            // Clear the flag
            sessionStorage.removeItem('admin_logout_redirect');
        }
        
        // Set up history state to allow back navigation to index.php
        history.replaceState({page: 'admin_login', fromLogout: fromLogout}, '', window.location.href);
        
        // When back button is pressed, go to index.php
        window.addEventListener('popstate', function(event) {
            if (event.state && event.state.page === 'admin_login') {
                // Go to main index.php
                window.location.href = '../index.php';
            }
        });
        
        // Add a state to ensure back button works
        setTimeout(function() {
            history.pushState({page: 'admin_login'}, '');
        }, 100);
        
        // Add "Back to Home" link after the form
        const backToHomeDiv = document.createElement('div');
        backToHomeDiv.innerHTML = `
            <div style="text-align: center; margin-top: 20px; padding-top: 15px; border-top: 1px solid #eee;">
                <a href="../index.php" style="color: #075B5E; text-decoration: none; font-size: 0.85rem; display: inline-flex; align-items: center; justify-content: center; gap: 5px;">
                    <i data-lucide="home" style="width: 1rem; height: 1rem;"></i>
                    Back to Home
                </a>
            </div>
        `;
        
        // Insert at the end of the card
        const card = document.querySelector('.card');
        if (card) {
            card.appendChild(backToHomeDiv);
        }
        
        // Re-initialize icons
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    })();
</script>
    <script src="js/logout.js"></script>
</body>
</html>