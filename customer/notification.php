<?php
session_start();

// DEBUG: Check session
error_log("CUSTOMER NOTIFICATION PAGE - Session user_id: " . ($_SESSION['user_id'] ?? 'NOT SET'));

// Check if user is logged in as customer
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin']) {
    header("Location: ../auth/login.php");
    exit();
}

// For customer files:
$base_path = dirname(__DIR__);
require_once $base_path . '/includes/config.php';
require_once $base_path . '/includes/db_connection.php';
require_once $base_path . '/includes/db_helper.php';

$user_id = $_SESSION['user_id'];

error_log("Customer logged in with user_id: $user_id");

// Handle marking notifications as read
if (isset($_POST['mark_as_read'])) {
    $notificationId = $_POST['notification_id'];
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$notificationId, $user_id]);
        $_SESSION['success_message'] = "Notification marked as read";
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error marking notification as read";
        error_log("Mark as read error: " . $e->getMessage());
    }
    header("Location: notification.php");
    exit();
}

// Handle marking all as read
if (isset($_POST['mark_all_read'])) {
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $_SESSION['success_message'] = "All notifications marked as read";
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error marking all notifications as read";
        error_log("Mark all as read error: " . e->getMessage());
    }
    header("Location: notification.php");
    exit();
}

// Handle clearing all notifications
if (isset($_POST['clear_all'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $_SESSION['success_message'] = "All notifications cleared";
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error clearing notifications";
        error_log("Clear all error: " . $e->getMessage());
    }
    header("Location: notification.php");
    exit();
}

// Get unread notifications count
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unreadCount = $stmt->fetch(PDO::FETCH_ASSOC)['unread_count'];
    error_log("Customer notification count query executed");
    error_log("Unread count for user_id $user_id: $unreadCount");
} catch (Exception $e) {
    $unreadCount = 0;
    error_log("Unread count error: " . $e->getMessage());
}

// Get all notifications for the customer with bill information if available
try {
    $stmt = $pdo->prepare("
        SELECT n.*, b.id as bill_id, b.bill_number, b.final_amount, b.status as bill_status, b.created_at as bill_date
        FROM notifications n
        LEFT JOIN bills b ON (n.message LIKE CONCAT('%Bill #', b.bill_number, '%') OR n.message LIKE CONCAT('%', b.bill_number, '%'))
        WHERE n.user_id = ? 
        ORDER BY n.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll();
    
    error_log("Total notifications fetched for user_id $user_id: " . count($notifications));
    
    // Show first notification if exists
    if (!empty($notifications)) {
        error_log("First notification details: " . print_r($notifications[0], true));
    }
} catch (Exception $e) {
    // If the join fails, just get notifications
    try {
        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$user_id]);
        $notifications = $stmt->fetchAll();
    } catch (Exception $e2) {
        $notifications = [];
        error_log("Notifications fetch error: " . $e2->getMessage());
    }
}

// Process notifications to add bill links
foreach ($notifications as &$notification) {
    // If notification has a bill_id, create a link
    if (!empty($notification['bill_id'])) {
        $notification['bill_link'] = "view_bill.php?bill_id=" . $notification['bill_id'];
    }
    
    // If notification has type 'bill' but no link, try to create one from message
    if (($notification['type'] == 'bill' || strpos(strtolower($notification['message']), 'bill') !== false) && empty($notification['link'])) {
        // Try to extract bill number from message
        if (preg_match('/Bill #(\w+)/', $notification['message'], $matches)) {
            $bill_number = $matches[1];
            // Try to find the bill in database
            try {
                $stmt = $pdo->prepare("SELECT id FROM bills WHERE bill_number = ? AND user_id = ?");
                $stmt->execute([$bill_number, $user_id]);
                $bill = $stmt->fetch();
                if ($bill) {
                    $notification['link'] = "view_bill.php?bill_id=" . $bill['id'];
                }
            } catch (Exception $e) {
                // Ignore error
            }
        }
    }
    
    // Format date for display
    $notification['formatted_date'] = date('M d, Y h:i A', strtotime($notification['created_at']));
    
    // Determine icon based on type
    switch ($notification['type']) {
        case 'bill':
            $notification['icon'] = 'fa-file-invoice-dollar';
            $notification['icon_color'] = 'text-primary';
            break;
        case 'payment':
            $notification['icon'] = 'fa-credit-card';
            $notification['icon_color'] = 'text-success';
            break;
        case 'warning':
            $notification['icon'] = 'fa-exclamation-triangle';
            $notification['icon_color'] = 'text-warning';
            break;
        case 'error':
            $notification['icon'] = 'fa-exclamation-circle';
            $notification['icon_color'] = 'text-danger';
            break;
        case 'success':
            $notification['icon'] = 'fa-check-circle';
            $notification['icon_color'] = 'text-success';
            break;
        case 'registration':
            $notification['icon'] = 'fa-user-plus';
            $notification['icon_color'] = 'text-info';
            break;
        default: // info
            $notification['icon'] = 'fa-info-circle';
            $notification['icon_color'] = 'text-info';
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - BillPay Pro</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <style>
        body {
            background-color: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
        }
        
        .navbar {
            background: #075B5E !important;
            padding: 0.8rem 0;
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: white !important;
        }
        
        .notification-container {
            max-width: 1000px;
            margin: 30px auto;
            padding: 20px;
        }
        
        .notification-header {
            background: white;
            border-radius: 10px;
            padding: 1.5rem 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .notification-header h2 {
            margin: 0;
            color: #075B5E;
            font-weight: 600;
        }
        
        .badge {
            background: #dc3545;
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            margin-left: 10px;
        }
        
        .notification-item {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border: 1px solid #eaeaea;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .notification-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .notification-item.unread {
            background: #f0f9ff;
            border-left: 4px solid #007bff;
        }
        
        .notification-item.read {
            opacity: 0.9;
            background: #f8f9fa;
        }
        
        .notification-item.type-bill {
            border-left-color: #28a745;
        }
        
        .notification-item.type-payment {
            border-left-color: #17a2b8;
        }
        
        .notification-item.type-warning {
            border-left-color: #ffc107;
        }
        
        .notification-item.type-error {
            border-left-color: #dc3545;
        }
        
        .notification-icon {
            font-size: 1.5rem;
            margin-right: 1rem;
            width: 50px;
            text-align: center;
        }
        
        .notification-content {
            flex: 1;
        }
        
        .notification-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #333;
        }
        
        .notification-message {
            font-size: 1rem;
            margin-bottom: 0.5rem;
            color: #555;
            line-height: 1.5;
        }
        
        .notification-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 0.5rem;
            padding-top: 0.5rem;
            border-top: 1px solid #eaeaea;
        }
        
        .notification-date {
            color: #6c757d;
            font-size: 0.85rem;
        }
        
        .notification-type-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-right: 0.5rem;
        }
        
        .type-bill {
            background: #d4edda;
            color: #155724;
        }
        
        .type-payment {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .type-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .type-error {
            background: #f8d7da;
            color: #721c24;
        }
        
        .type-info {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .type-success {
            background: #d4edda;
            color: #155724;
        }
        
        .type-registration {
            background: #e0e0ff;
            color: #4a4a9c;
        }
        
        .notification-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        
        .btn-mark-read {
            background: #28a745;
            color: white;
            border: none;
            padding: 0.4rem 0.8rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-mark-read:hover {
            background: #218838;
            color: white;
            text-decoration: none;
        }
        
        .btn-view {
            background: #007bff;
            color: white;
            border: none;
            padding: 0.4rem 0.8rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-view:hover {
            background: #0056b3;
            color: white;
            text-decoration: none;
        }
        
        .header-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-mark-all-read {
            background: #007bff;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .btn-clear-all {
            background: #6c757d;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .btn-mark-all-read:hover {
            background: #0056b3;
        }
        
        .btn-clear-all:hover {
            background: #545b62;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .empty-state-icon {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 1.5rem;
        }
        
        .empty-state h3 {
            color: #495057;
            margin-bottom: 1rem;
        }
        
        .empty-state p {
            color: #6c757d;
            max-width: 500px;
            margin: 0 auto 1.5rem;
        }
        
        .bill-info {
            background: #f8f9fa;
            padding: 0.75rem;
            border-radius: 6px;
            margin-top: 0.5rem;
            border-left: 3px solid #075B5E;
        }
        
        .bill-info p {
            margin: 0.25rem 0;
            font-size: 0.9rem;
        }
        
        .footer {
            background: #f8f9fa;
            padding: 1.5rem 0;
            margin-top: 3rem;
            border-top: 1px solid #e9ecef;
        }
        
        /* Success/Error messages */
        .alert {
            border-radius: 8px;
            border: none;
            margin-bottom: 1.5rem;
        }
        
        .read-indicator {
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #007bff;
        }
        
        .read-indicator.read {
            background: #6c757d;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <?php 
    // Determine which header to include based on your structure
    if (file_exists('../includes/header.php')) {
        include '../includes/header.php';
    } else {
        // Fallback minimal header
        echo '<nav class="navbar navbar-expand-lg navbar-dark">
            <div class="container">
                <a class="navbar-brand" href="dashboard.php">
                    <i class="fas fa-file-invoice-dollar"></i> BillPay Pro
                </a>
                <div class="navbar-nav ms-auto">
                    <a class="nav-link text-white" href="dashboard.php">Dashboard</a>
                    <a class="nav-link text-white" href="bills.php">My Bills</a>
                    <a class="nav-link text-white active" href="notification.php">Notifications</a>
                    <a class="nav-link text-white" href="logout.php">Logout</a>
                </div>
            </div>
        </nav>';
    }
    ?>

    <div class="container notification-container">
        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $_SESSION['success_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $_SESSION['error_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Header -->
        <div class="notification-header">
            <h2>
                <i class="fas fa-bell"></i> Notifications
                <?php if ($unreadCount > 0): ?>
                    <span class="badge"><?php echo $unreadCount; ?> unread</span>
                <?php endif; ?>
            </h2>
            
            <?php if (!empty($notifications)): ?>
                <div class="header-actions">
                    <?php if ($unreadCount > 0): ?>
                        <form method="POST" class="d-inline">
                            <button type="submit" name="mark_all_read" class="btn-mark-all-read">
                                <i class="fas fa-check-double"></i> Mark All as Read
                            </button>
                        </form>
                    <?php endif; ?>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to clear all notifications?');">
                        <button type="submit" name="clear_all" class="btn-clear-all">
                            <i class="fas fa-trash"></i> Clear All
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <!-- Notifications List -->
        <?php if (empty($notifications)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fas fa-bell-slash"></i>
                </div>
                <h3>No notifications yet</h3>
                <p>You'll see notifications here when new bills are generated, payments are made, or important updates occur.</p>
                <a href="dashboard.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        <?php else: ?>
            <div class="notification-list">
                <?php foreach ($notifications as $notification): ?>
                    <div class="notification-item <?php echo $notification['is_read'] ? 'read' : 'unread'; ?> type-<?php echo $notification['type']; ?>">
                        <div class="read-indicator <?php echo $notification['is_read'] ? 'read' : ''; ?>"></div>
                        
                        <div class="d-flex">
                            <div class="notification-icon <?php echo $notification['icon_color']; ?>">
                                <i class="fas <?php echo $notification['icon']; ?>"></i>
                            </div>
                            
                            <div class="notification-content">
                                <div class="notification-title">
                                    <?php echo htmlspecialchars($notification['title']); ?>
                                </div>
                                
                                <div class="notification-message">
                                    <?php echo htmlspecialchars($notification['message']); ?>
                                </div>
                                
                                <!-- Bill Information (if available) -->
                                <?php if (!empty($notification['bill_id'])): ?>
                                    <div class="bill-info">
                                        <p><strong>Bill #<?php echo $notification['bill_number']; ?></strong></p>
                                        <p><strong>Amount:</strong> ₹<?php echo number_format($notification['final_amount'], 2); ?></p>
                                        <p><strong>Status:</strong> 
                                            <span class="badge <?php echo $notification['bill_status'] == 'paid' ? 'bg-success' : 'bg-warning'; ?>">
                                                <?php echo ucfirst($notification['bill_status']); ?>
                                            </span>
                                        </p>
                                        <p><strong>Date:</strong> <?php echo date('M d, Y', strtotime($notification['bill_date'])); ?></p>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="notification-meta">
                                    <div>
                                        <span class="notification-type-badge type-<?php echo $notification['type']; ?>">
                                            <?php echo ucfirst($notification['type']); ?>
                                        </span>
                                        <span class="notification-date">
                                            <i class="far fa-clock"></i> <?php echo $notification['formatted_date']; ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="notification-actions">
                                    <?php if (!$notification['is_read']): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                            <button type="submit" name="mark_as_read" class="btn-mark-read">
                                                <i class="fas fa-check"></i> Mark as Read
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($notification['link'])): ?>
                                        <a href="<?php echo htmlspecialchars($notification['link']); ?>" class="btn-view">
                                            <i class="fas fa-external-link-alt"></i> View Details
                                        </a>
                                    <?php elseif (!empty($notification['bill_link'])): ?>
                                        <a href="<?php echo $notification['bill_link']; ?>" class="btn-view">
                                            <i class="fas fa-file-invoice"></i> View Bill
                                        </a>
                                    <?php elseif ($notification['type'] == 'bill'): ?>
                                        <a href="my_bills.php" class="btn-view">
                                            <i class="fas fa-list"></i> View All Bills
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="text-center">
                <p class="mb-0 text-muted">&copy; <?php echo date('Y'); ?> BillPay Pro - Online Billing System</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script>
        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        // Highlight unread notifications
        document.addEventListener('DOMContentLoaded', function() {
            var unreadItems = document.querySelectorAll('.notification-item.unread');
            unreadItems.forEach(function(item) {
                item.addEventListener('click', function(e) {
                    if (!e.target.closest('.btn-mark-read') && !e.target.closest('.btn-view')) {
                        var form = this.querySelector('form');
                        if (form) {
                            form.submit();
                        }
                    }
                });
            });
        });
    </script>
    <script src="js/logout.js"></script>
</body>
</html>