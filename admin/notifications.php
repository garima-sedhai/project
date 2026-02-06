<?php
session_start();
include '../includes/config.php';

// Redirect if not logged in as admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['user_id'];

// Mark all notifications as read when visiting
if (isset($_GET['mark_all_read'])) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$admin_id]);
    header("Location: notifications.php");
    exit();
}

// Mark single notification as read
if (isset($_GET['mark_read'])) {
    $notification_id = $_GET['mark_read'];
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$notification_id, $admin_id]);
    header("Location: notifications.php");
    exit();
}

// Get all notifications for admin
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$admin_id]);
$notifications = $stmt->fetchAll();

// Get unread count
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->execute([$admin_id]);
$unread_count = $stmt->fetch()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - BillPay Pro</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        .notification-container {
            max-width: 800px;
            margin: 2rem auto;
        }
        
        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e9ecef;
        }
        
        .notification-item {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #3498db;
            transition: all 0.3s;
        }
        
        .notification-item.unread {
            background: #f0f8ff;
            border-left-color: #e74c3c;
        }
        
        .notification-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }
        
        .notification-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
            font-size: 0.9rem;
            color: #666;
        }
        
        .notification-actions {
            display: flex;
            gap: 1rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #666;
        }
        
        .empty-state-icon {
            width: 4rem;
            height: 4rem;
            color: #6c757d;
            margin: 0 auto 1rem auto;
            display: block;
        }
        
        .notification-type {
            display: inline-block;
            padding: 0.2rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            margin-bottom: 0.5rem;
        }
        
        .type-payment {
            background: #d4edda;
            color: #155724;
        }
        
        .type-system {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .type-bill {
            background: #fff3cd;
            color: #856404;
        }
        
        .mark-read-btn {
            background: none;
            border: none;
            color: #3498db;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.9rem;
        }
        
        .mark-all-read-btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
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
                    <li><a href="manage_services.php">Services</a></li>
                    <li><a href="manage_bills.php">Bills</a></li>
                    <li><a href="manage_users.php">Users</a></li>
                    <li><a href="reports.php">Reports</a></li>
                    <li><a href="notifications.php" style="color: #3498db;">Notifications</a></li>
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
        <div class="notification-container">
            <div class="notification-header">
                <div>
                    <h1>
                        <i data-lucide="bell" style="width: 2rem; height: 2rem; margin-right: 0.5rem; vertical-align: middle;"></i>
                        Notifications
                    </h1>
                    <?php if ($unread_count > 0): ?>
                        <p style="color: #e74c3c; display: flex; align-items: center; gap: 0.5rem;">
                            <i data-lucide="alert-circle" style="width: 1.2rem; height: 1.2rem;"></i>
                            <strong><?php echo $unread_count; ?> unread notification<?php echo $unread_count > 1 ? 's' : ''; ?></strong>
                        </p>
                    <?php else: ?>
                        <p style="color: #27ae60; display: flex; align-items: center; gap: 0.5rem;">
                            <i data-lucide="check-circle" style="width: 1.2rem; height: 1.2rem;"></i>
                            All notifications read
                        </p>
                    <?php endif; ?>
                </div>
                
                <?php if ($unread_count > 0): ?>
                    <a href="?mark_all_read=1" class="mark-all-read-btn">
                        <i data-lucide="check" style="width: 1.2rem; height: 1.2rem;"></i>
                        Mark All as Read
                    </a>
                <?php endif; ?>
            </div>
            
            <?php if (count($notifications) > 0): ?>
                <div class="notification-list">
                    <?php foreach ($notifications as $notification): ?>
                        <div class="notification-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?>">
                            <div>
                                <span class="notification-type type-<?php echo $notification['type']; ?>">
                                    <?php echo ucfirst($notification['type']); ?>
                                </span>
                                <h3><?php echo $notification['title']; ?></h3>
                                <p><?php echo nl2br($notification['message']); ?></p>
                            </div>
                            
                            <div class="notification-meta">
                                <span>
                                    <i data-lucide="clock" style="width: 1rem; height: 1rem; margin-right: 0.3rem; vertical-align: middle;"></i>
                                    <?php echo date('M d, Y h:i A', strtotime($notification['created_at'])); ?>
                                </span>
                                
                                <?php if (!$notification['is_read']): ?>
                                    <div class="notification-actions">
                                        <a href="?mark_read=<?php echo $notification['id']; ?>" class="mark-read-btn">
                                            <i data-lucide="check" style="width: 1rem; height: 1rem;"></i>
                                            Mark as Read
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i data-lucide="bell-off" class="empty-state-icon"></i>
                    <h3>No Notifications</h3>
                    <p>You don't have any notifications yet.</p>
                    <p>Notifications will appear here when customers make payments.</p>
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
        // Auto-mark as read when clicking on notification
        document.querySelectorAll('.notification-item.unread').forEach(item => {
            item.addEventListener('click', function() {
                const markReadBtn = this.querySelector('.mark-read-btn');
                if (markReadBtn) {
                    window.location.href = markReadBtn.href;
                }
            });
        });
        
        // Initialize icons
        lucide.createIcons();
    </script>
    <script src="js/logout.js"></script>
</body>
</html>