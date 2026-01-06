<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Get notification count if user is logged in
$notification_count = 0;
if (isset($_SESSION['user_id'])) {
    // Include config only if needed to avoid conflicts
    if (!isset($pdo)) {
        require_once 'config.php';
        require_once 'db_connection.php';
    }
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = FALSE");
    $stmt->execute([$_SESSION['user_id']]);
    $notification_count = $stmt->fetch()['count'];
}

// Function to get user initials
function getUserInitials($name) {
    $names = explode(' ', $name);
    $initials = '';
    foreach ($names as $n) {
        $initials .= strtoupper(substr($n, 0, 1));
    }
    return substr($initials, 0, 2);
}

// Determine dashboard URL based on user type
$dashboard_url = '';
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']) {
        $dashboard_url = 'admin/index.php';
    } else {
        $dashboard_url = 'customer/dashboard.php';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Billing System</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        /* Add these styles to your CSS file or here */
        .user-avatar {
            width: 35px;
            height: 35px;
            background: #075B5E;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 8px;
            flex-shrink: 0;
        }
        
        .notification-bell {
            position: relative;
            margin-right: 15px;
        }
        
        .notification-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #e74c3c;
            color: white;
            font-size: 0.7rem;
            padding: 0.1rem 0.4rem;
            border-radius: 10px;
            min-width: 18px;
            text-align: center;
        }
        
        .admin-badge {
            background: #27ae60;
            color: white;
            font-size: 0.7rem;
            padding: 0.1rem 0.4rem;
            border-radius: 3px;
            margin-left: 5px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .nav-links li {
            display: flex;
            align-items: center;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <div class="logo">BillPay Pro</div>
                <ul class="nav-links">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php if ($dashboard_url): ?>
                            <li><a href="<?php echo $dashboard_url; ?>">Dashboard</a></li>
                        <?php endif; ?>
                        
                        <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                            <li><a href="../admin/manage_users.php">Manage Users</a></li>
                            <li><a href="../admin/approve_users.php">Approve Users</a></li>
                            <li><a href="../admin/manage_services.php">Services</a></li>
                        <?php else: ?>
                            <li><a href="../customer/bills.php">My Bills</a></li>
                            <li><a href="../customer/payment_history.php">Payment History</a></li>
                        <?php endif; ?>
                        
                        <li style="display: flex; align-items: center; gap: 15px;">
                            <div class="notification-bell">
                                <a href="<?php echo isset($_SESSION['is_admin']) && $_SESSION['is_admin'] ? '../admin/notifications.php' : '../customer/notifications.php'; ?>" 
                                   style="color: white; text-decoration: none;">
                                    🔔
                                    <?php if ($notification_count > 0): ?>
                                        <span class="notification-count"><?php echo $notification_count; ?></span>
                                    <?php endif; ?>
                                </a>
                            </div>
                            
                            <div class="user-info">
                                <div class="user-avatar">
                                    <?php echo getUserInitials($_SESSION['full_name']); ?>
                                </div>
                                
                                <div>
                                    <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                                    <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                                        <span class="admin-badge">ADMIN</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <a href="<?php echo isset($_SESSION['is_admin']) && $_SESSION['is_admin'] ? '../admin/logout.php' : '../customer/logout.php'; ?>" 
                               style="margin-left: 15px; color: white; text-decoration: none;">
                                Logout
                            </a>
                        </li>
                    <?php else: ?>
                        <li><a href="../index.php">Home</a></li>
                        <li><a href="../customer/login.php">Login</a></li>
                        <li><a href="../customer/register.php">Register</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>
    
    <!-- Main content container -->
    <div class="container">