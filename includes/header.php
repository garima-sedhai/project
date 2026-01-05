<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Get notification count if user is logged in
$notification_count = 0;
if (isset($_SESSION['user_id'])) {
    include 'config.php';
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Billing System</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <div class="logo">BillPay</div>
                <ul class="nav-links">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li><a href="bills.php">My Bills</a></li>
                        <li><a href="payment_history.php">Payment History</a></li>
                        
                        <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                            <li><a href="admin/">Admin Panel</a></li>
                        <?php endif; ?>
                        
                        <li style="display: flex; align-items: center;">
                            <div class="notification-bell">
                                <a href="notifications.php" style="color: white; text-decoration: none;">
                                    🔔
                                    <?php if ($notification_count > 0): ?>
                                        <span class="notification-count"><?php echo $notification_count; ?></span>
                                    <?php endif; ?>
                                </a>
                            </div>
                            
                            <div class="user-avatar">
                                <?php echo getUserInitials($_SESSION['full_name']); ?>
                            </div>
                            
                            <div>
                                <?php echo $_SESSION['full_name']; ?>
                                <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                                    <span class="admin-badge">ADMIN</span>
                                <?php endif; ?>
                            </div>
                            
                            <a href="logout.php" style="margin-left: 15px; color: white;">Logout</a>
                        </li>
                    <?php else: ?>
                        <li><a href="index.php">Home</a></li>
                        <li><a href="login.php">Login</a></li>
                        <li><a href="register.php">Register</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>