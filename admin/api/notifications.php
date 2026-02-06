<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/db_connection.php';

// Redirect if not admin
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header("Location: ../customer/login.php");
    exit;
}

// Mark all as read if requested
if (isset($_GET['mark_all_read']) && $_GET['mark_all_read'] == '1') {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1");
    $stmt->execute();
    
    // Also update admin_notifications
    try {
        $stmt = $pdo->prepare("UPDATE admin_notifications SET is_read = 1");
        $stmt->execute();
    } catch (Exception $e) {
        // Table might not exist
    }
    
    header("Location: notifications.php?success=all_marked_read");
    exit;
}

// Mark single notification as read
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $notification_id = $_GET['mark_read'];
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
    $stmt->execute([$notification_id]);
    
    header("Location: notifications.php?success=marked_read");
    exit;
}

// Delete notification
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $notification_id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ?");
    $stmt->execute([$notification_id]);
    
    header("Location: notifications.php?success=deleted");
    exit;
}

// Get all notifications
$stmt = $pdo->prepare("SELECT * FROM notifications ORDER BY created_at DESC");
$stmt->execute();
$notifications = $stmt->fetchAll();

// Get unread count
$stmt = $pdo->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE is_read = FALSE");
$stmt->execute();
$unread_count = $stmt->fetch()['unread_count'];

// Get admin notifications if table exists
$admin_notifications = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM admin_notifications ORDER BY created_at DESC");
    $stmt->execute();
    $admin_notifications = $stmt->fetchAll();
} catch (Exception $e) {
    // Table doesn't exist, that's okay
}

// Get notification statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_read = FALSE THEN 1 ELSE 0 END) as unread,
        SUM(CASE WHEN type = 'payment' THEN 1 ELSE 0 END) as payment_count,
        SUM(CASE WHEN type = 'system' THEN 1 ELSE 0 END) as system_count,
        SUM(CASE WHEN type = 'user' THEN 1 ELSE 0 END) as user_count
    FROM notifications
");
$stmt->execute();
$stats = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Admin Dashboard</title>
    <link rel="stylesheet" href="<?php echo $base_path; ?>/assets/css/style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .notifications-container {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-card h3 {
            font-size: 2rem;
            margin: 0;
            color: #075B5E;
        }
        
        .stat-card p {
            margin: 5px 0 0;
            color: #666;
        }
        
        .notification-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .notification-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
        }
        
        .tab-btn {
            padding: 8px 20px;
            background: #f8f9fa;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .tab-btn.active {
            background: #075B5E;
            color: white;
        }
        
        .tab-btn:hover {
            background: #0a7c80;
            color: white;
        }
        
        .notification-list {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .notification-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 20px;
            border-bottom: 1px solid #f0f0f0;
            transition: background-color 0.2s;
        }
        
        .notification-item:hover {
            background: #f8f9fa;
        }
        
        .notification-item.unread {
            background: #e8f4fd;
            border-left: 4px solid #3498db;
        }
        
        .notification-item.read {
            opacity: 0.8;
        }
        
        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .payment-icon {
            background: #d4edda;
            color: #155724;
        }
        
        .system-icon {
            background: #fff3cd;
            color: #856404;
        }
        
        .user-icon {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .notification-content {
            flex: 1;
        }
        
        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 5px;
        }
        
        .notification-title {
            font-weight: 600;
            font-size: 1.1rem;
            margin: 0;
        }
        
        .notification-time {
            color: #999;
            font-size: 0.85rem;
        }
        
        .notification-message {
            color: #666;
            margin: 5px 0 10px;
            line-height: 1.5;
        }
        
        .notification-actions-small {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .notification-type {
            display: inline-block;
            padding: 3px 8px;
            background: #f0f0f0;
            border-radius: 10px;
            font-size: 0.8rem;
            color: #666;
            margin-right: 10px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .empty-state-icon {
            width: 60px;
            height: 60px;
            opacity: 0.5;
            margin-bottom: 20px;
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
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .btn:hover {
            background: #0a7c80;
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn-success {
            background: #28a745;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .btn-danger {
            background: #dc3545;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 0.8rem;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }
        
        .filter-badges {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filter-badge {
            padding: 5px 15px;
            background: #f8f9fa;
            border-radius: 20px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .filter-badge.active {
            background: #075B5E;
            color: white;
        }
        
        .filter-badge:hover {
            background: #0a7c80;
            color: white;
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
                    <li><a href="index.php">Dashboard</a></li>
                    <li><a href="manage_bills.php">Manage Bills</a></li>
                    <li><a href="manage_services.php">Manage Services</a></li>
                    <li><a href="reports.php">Manage Reports</a></li>
                    <li><a href="manage_users.php">Manage Users</a></li>
                    <li><a href="approve_users.php">Approve Users</a></li>
                    <li><a href="notifications.php" class="active">Notifications</a></li>
                </ul>
            </nav>
            <div class="user-info">
                <!-- Notification Bell -->
                <div class="notification-bell">
                    <a href="notifications.php" title="Notifications" id="notificationBell">
                        <i data-lucide="bell"></i>
                        <?php if ($unread_count > 0): ?>
                            <span class="notification-count" id="notificationCount"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </a>
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
    
    <div class="notifications-container">
        <!-- Header Section -->
        <div class="header-section">
            <div>
                <h1>Notifications</h1>
                <p>Manage and view all system notifications</p>
            </div>
            <div class="notification-actions">
                <a href="?mark_all_read=1" class="btn btn-success" onclick="return confirm('Mark all notifications as read?')">
                    <i data-lucide="check-circle"></i> Mark All as Read
                </a>
                <a href="notifications.php" class="btn btn-secondary">
                    <i data-lucide="refresh-cw"></i> Refresh
                </a>
            </div>
        </div>
        
        <!-- Success Messages -->
        <?php if (isset($_GET['success'])): ?>
            <div class="success-message">
                <?php if ($_GET['success'] == 'all_marked_read'): ?>
                    <i data-lucide="check-circle"></i> All notifications have been marked as read.
                <?php elseif ($_GET['success'] == 'marked_read'): ?>
                    <i data-lucide="check-circle"></i> Notification marked as read.
                <?php elseif ($_GET['success'] == 'deleted'): ?>
                    <i data-lucide="check-circle"></i> Notification deleted successfully.
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistics Cards -->
        <div class="stats-cards">
            <div class="stat-card">
                <h3><?php echo $stats['total'] ?? 0; ?></h3>
                <p>Total Notifications</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $stats['unread'] ?? 0; ?></h3>
                <p>Unread Notifications</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $stats['payment_count'] ?? 0; ?></h3>
                <p>Payment Notifications</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $stats['system_count'] ?? 0; ?></h3>
                <p>System Notifications</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $stats['user_count'] ?? 0; ?></h3>
                <p>User Notifications</p>
            </div>
        </div>
        
        <!-- Filter Badges -->
        <div class="filter-badges">
            <a href="?type=all" class="filter-badge <?php echo (!isset($_GET['type']) || $_GET['type'] == 'all') ? 'active' : ''; ?>">
                All Notifications
            </a>
            <a href="?type=unread" class="filter-badge <?php echo (isset($_GET['type']) && $_GET['type'] == 'unread') ? 'active' : ''; ?>">
                Unread Only
            </a>
            <a href="?type=payment" class="filter-badge <?php echo (isset($_GET['type']) && $_GET['type'] == 'payment') ? 'active' : ''; ?>">
                Payment Notifications
            </a>
            <a href="?type=system" class="filter-badge <?php echo (isset($_GET['type']) && $_GET['type'] == 'system') ? 'active' : ''; ?>">
                System Notifications
            </a>
        </div>
        
        <!-- Notifications List -->
        <div class="notification-list">
            <?php if (empty($notifications)): ?>
                <div class="empty-state">
                    <i data-lucide="bell-off" class="empty-state-icon"></i>
                    <h3>No Notifications</h3>
                    <p>You don't have any notifications at the moment.</p>
                </div>
            <?php else: ?>
                <?php 
                // Filter notifications based on type
                $filtered_notifications = $notifications;
                if (isset($_GET['type'])) {
                    switch ($_GET['type']) {
                        case 'unread':
                            $filtered_notifications = array_filter($notifications, function($n) {
                                return !$n['is_read'];
                            });
                            break;
                        case 'payment':
                            $filtered_notifications = array_filter($notifications, function($n) {
                                return $n['type'] == 'payment';
                            });
                            break;
                        case 'system':
                            $filtered_notifications = array_filter($notifications, function($n) {
                                return $n['type'] == 'system';
                            });
                            break;
                    }
                }
                
                if (empty($filtered_notifications)): ?>
                    <div class="empty-state">
                        <i data-lucide="filter" class="empty-state-icon"></i>
                        <h3>No Matching Notifications</h3>
                        <p>No notifications match your current filter.</p>
                        <a href="notifications.php" class="btn">Clear Filter</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($filtered_notifications as $notification): ?>
                        <div class="notification-item <?php echo $notification['is_read'] ? 'read' : 'unread'; ?>" data-id="<?php echo $notification['id']; ?>">
                            <div class="notification-icon <?php echo $notification['type'] . '-icon'; ?>">
                                <?php if ($notification['type'] == 'payment'): ?>
                                    <i data-lucide="credit-card"></i>
                                <?php elseif ($notification['type'] == 'system'): ?>
                                    <i data-lucide="bell"></i>
                                <?php elseif ($notification['type'] == 'user'): ?>
                                    <i data-lucide="user"></i>
                                <?php else: ?>
                                    <i data-lucide="info"></i>
                                <?php endif; ?>
                            </div>
                            
                            <div class="notification-content">
                                <div class="notification-header">
                                    <h4 class="notification-title">
                                        <?php echo htmlspecialchars($notification['title']); ?>
                                        <span class="notification-type"><?php echo ucfirst($notification['type']); ?></span>
                                        <?php if (!$notification['is_read']): ?>
                                            <span style="background: #e74c3c; color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.7rem; margin-left: 10px;">New</span>
                                        <?php endif; ?>
                                    </h4>
                                    <span class="notification-time">
                                        <?php echo date('M d, Y h:i A', strtotime($notification['created_at'])); ?>
                                    </span>
                                </div>
                                
                                <p class="notification-message">
                                    <?php echo htmlspecialchars($notification['message']); ?>
                                </p>
                                
                                <div class="notification-actions-small">
                                    <?php if (!$notification['is_read']): ?>
                                        <a href="?mark_read=<?php echo $notification['id']; ?>" class="btn btn-sm btn-success">
                                            <i data-lucide="check"></i> Mark as Read
                                        </a>
                                    <?php endif; ?>
                                    <a href="?delete=<?php echo $notification['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this notification?')">
                                        <i data-lucide="trash-2"></i> Delete
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <!-- Admin Notifications Section (if exists) -->
        <?php if (!empty($admin_notifications)): ?>
            <div style="margin-top: 40px;">
                <h2 style="margin-bottom: 20px;">Admin Notifications</h2>
                <div class="notification-list">
                    <?php foreach ($admin_notifications as $notification): ?>
                        <div class="notification-item">
                            <div class="notification-icon system-icon">
                                <i data-lucide="shield"></i>
                            </div>
                            <div class="notification-content">
                                <div class="notification-header">
                                    <h4 class="notification-title">
                                        <?php echo htmlspecialchars($notification['title']); ?>
                                        <span class="notification-type">Admin</span>
                                        <?php if (!$notification['is_read']): ?>
                                            <span style="background: #e74c3c; color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.7rem; margin-left: 10px;">New</span>
                                        <?php endif; ?>
                                    </h4>
                                    <span class="notification-time">
                                        <?php echo date('M d, Y h:i A', strtotime($notification['created_at'])); ?>
                                    </span>
                                </div>
                                <p class="notification-message">
                                    <?php echo htmlspecialchars($notification['message']); ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Initialize Lucide icons
        lucide.createIcons();
        
        // AJAX for marking as read without page reload
        document.addEventListener('DOMContentLoaded', function() {
            const notificationItems = document.querySelectorAll('.notification-item[data-id]');
            
            notificationItems.forEach(item => {
                const markAsReadBtn = item.querySelector('a[href*="mark_read"]');
                if (markAsReadBtn) {
                    markAsReadBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        
                        const notificationId = item.dataset.id;
                        const url = this.getAttribute('href');
                        
                        // AJAX call to mark as read
                        fetch(url)
                            .then(response => response.text())
                            .then(() => {
                                // Update UI without page reload
                                item.classList.remove('unread');
                                item.classList.add('read');
                                
                                // Remove "New" badge
                                const newBadge = item.querySelector('.notification-title span[style*="background: #e74c3c"]');
                                if (newBadge) {
                                    newBadge.remove();
                                }
                                
                                // Remove the "Mark as Read" button
                                this.remove();
                                
                                // Update notification count in header
                                const notificationCount = document.getElementById('notificationCount');
                                if (notificationCount) {
                                    const currentCount = parseInt(notificationCount.textContent);
                                    if (currentCount > 0) {
                                        notificationCount.textContent = currentCount - 1;
                                        if (currentCount - 1 === 0) {
                                            notificationCount.style.display = 'none';
                                        }
                                    }
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                // Fallback: redirect normally
                                window.location.href = url;
                            });
                    });
                }
                
                // Delete confirmation with AJAX
                const deleteBtn = item.querySelector('a[href*="delete"]');
                if (deleteBtn) {
                    deleteBtn.addEventListener('click', function(e) {
                        if (!confirm('Are you sure you want to delete this notification?')) {
                            e.preventDefault();
                            return false;
                        }
                        
                        e.preventDefault();
                        const url = this.getAttribute('href');
                        
                        fetch(url)
                            .then(response => response.text())
                            .then(() => {
                                // Remove the notification item from UI
                                item.style.opacity = '0.5';
                                setTimeout(() => {
                                    item.remove();
                                    
                                    // Update notification count in header
                                    const notificationCount = document.getElementById('notificationCount');
                                    if (notificationCount && !item.classList.contains('read')) {
                                        const currentCount = parseInt(notificationCount.textContent);
                                        if (currentCount > 0) {
                                            notificationCount.textContent = currentCount - 1;
                                            if (currentCount - 1 === 0) {
                                                notificationCount.style.display = 'none';
                                            }
                                        }
                                    }
                                    
                                    // Check if list is empty
                                    const notificationList = document.querySelector('.notification-list');
                                    if (notificationList.querySelectorAll('.notification-item').length === 0) {
                                        notificationList.innerHTML = `
                                            <div class="empty-state">
                                                <i data-lucide="bell-off" class="empty-state-icon"></i>
                                                <h3>No Notifications</h3>
                                                <p>You don't have any notifications at the moment.</p>
                                            </div>
                                        `;
                                        lucide.createIcons();
                                    }
                                }, 300);
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                // Fallback: redirect normally
                                window.location.href = url;
                            });
                    });
                }
            });
            
            // Update notification count in real-time
            function updateNotificationCount() {
                fetch('get_notification_count.php')
                    .then(response => response.json())
                    .then(data => {
                        const notificationCount = document.getElementById('notificationCount');
                        if (data.count > 0) {
                            if (notificationCount) {
                                notificationCount.textContent = data.count;
                                notificationCount.style.display = 'flex';
                            } else {
                                // Create badge if it doesn't exist
                                const bellLink = document.querySelector('#notificationBell');
                                if (bellLink) {
                                    const countBadge = document.createElement('span');
                                    countBadge.className = 'notification-count';
                                    countBadge.id = 'notificationCount';
                                    countBadge.textContent = data.count;
                                    bellLink.appendChild(countBadge);
                                }
                            }
                        } else if (notificationCount) {
                            notificationCount.style.display = 'none';
                        }
                    })
                    .catch(error => console.error('Error updating notification count:', error));
            }
            
            // Update count every 30 seconds
            setInterval(updateNotificationCount, 30000);
        });
    </script>
</body>
</html>