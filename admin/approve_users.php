<?php
session_start();
include '../includes/config.php';

// Redirect if not logged in as admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Handle approval/rejection
if (isset($_GET['action']) && isset($_GET['user_id'])) {
    $user_id = $_GET['user_id'];
    $action = $_GET['action'];
    
    try {
        if ($action == 'approve') {
            $stmt = $pdo->prepare("UPDATE users SET admin_approved = 1, registration_status = 'approved' WHERE id = ?");
            $stmt->execute([$user_id]);
            
            // Get user info for notification
            $stmt = $pdo->prepare("SELECT email, full_name FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            // Create user notification
            $user_message = "Your account has been approved! You can now login and access all services.";
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, created_at) 
                                  VALUES (?, 'Account Approved', ?, 'system', NOW())");
            $stmt->execute([$user_id, $user_message]);
            
            $message = "User approved successfully! They can now login.";
            $message_type = "success";
            
        } elseif ($action == 'reject') {
            $stmt = $pdo->prepare("UPDATE users SET admin_approved = 0, is_active = 0, registration_status = 'rejected' WHERE id = ?");
            $stmt->execute([$user_id]);
            
            // Get user info for notification
            $stmt = $pdo->prepare("SELECT email, full_name FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            // Create user notification
            $user_message = "Your account registration has been rejected. Please contact support for more information.";
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, created_at) 
                                  VALUES (?, 'Account Rejected', ?, 'system', NOW())");
            $stmt->execute([$user_id, $user_message]);
            
            $message = "User rejected successfully.";
            $message_type = "success";
        }
        
        // Refresh the page
        header("Location: approve_users.php?message=" . urlencode($message) . "&type=" . $message_type);
        exit();
        
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "error";
    }
}

// Get pending users (email verified but not admin approved)
$stmt = $pdo->prepare("SELECT * FROM users WHERE is_admin = FALSE AND email_verified = 1 AND admin_approved = 0 AND registration_status = 'verified' ORDER BY created_at DESC");
$stmt->execute();
$pending_users = $stmt->fetchAll();

// Get recently approved users (last 7 days)
$stmt = $pdo->prepare("SELECT * FROM users WHERE is_admin = FALSE AND admin_approved = 1 AND registration_status = 'approved' AND updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) ORDER BY updated_at DESC");
$stmt->execute();
$recent_approved = $stmt->fetchAll();

// Count pending users for notification badge
$pending_count = count($pending_users);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approve Users - BillPay Pro</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        .approval-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .section-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e9ecef;
        }
        
        .section-icon {
            width: 2rem;
            height: 2rem;
            color: #075B5E;
        }
        
        .user-grid {
            display: grid;
            gap: 1.5rem;
            margin-top: 1rem;
        }
        
        .user-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #f39c12;
        }
        
        .user-card.approved {
            border-left-color: #27ae60;
            opacity: 0.8;
        }
        
        .user-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }
        
        .btn-approve {
            background: #27ae60;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-reject {
            background: #e74c3c;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-view {
            background: #3498db;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .user-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 1rem 0;
        }
        
        .info-item {
            padding: 0.5rem;
            background: #f8f9fa;
            border-radius: 4px;
        }
        
        .info-label {
            font-weight: 500;
            color: #666;
            font-size: 0.9rem;
        }
        
        .info-value {
            display: block;
            margin-top: 0.25rem;
            font-weight: 500;
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
        
        .pending-badge {
            background: #f39c12;
            color: white;
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            font-size: 0.8rem;
            margin-left: 0.5rem;
        }
        
        .approved-badge {
            background: #27ae60;
            color: white;
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            font-size: 0.8rem;
            margin-left: 0.5rem;
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
                    <li><a href="approve_users.php" style="color: #3498db;">Approve Users</a></li>
                    <li><a href="reports.php">Reports</a></li>
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
        <div class="approval-container">
            <!-- Messages -->
            <?php if (isset($_GET['message'])): ?>
                <div class="<?php echo $_GET['type'] == 'error' ? 'alert alert-danger' : 'alert alert-success'; ?>">
                    <?php if ($_GET['type'] == 'success'): ?>
                        <i data-lucide="check-circle" style="width: 1.2rem; height: 1.2rem; margin-right: 0.5rem; vertical-align: middle;"></i>
                    <?php else: ?>
                        <i data-lucide="alert-circle" style="width: 1.2rem; height: 1.2rem; margin-right: 0.5rem; vertical-align: middle;"></i>
                    <?php endif; ?>
                    <?php echo htmlspecialchars($_GET['message']); ?>
                </div>
            <?php endif; ?>

            <!-- Pending Users Section -->
            <div class="card">
                <div class="section-header">
                    <i data-lucide="users" class="section-icon"></i>
                    <h2>Pending Approvals
                        <?php if ($pending_count > 0): ?>
                            <span class="pending-badge"><?php echo $pending_count; ?> pending</span>
                        <?php endif; ?>
                    </h2>
                </div>
                
                <?php if (count($pending_users) > 0): ?>
                    <div class="user-grid">
                        <?php foreach ($pending_users as $user): ?>
                            <div class="user-card">
                                <div style="display: flex; justify-content: space-between; align-items: start;">
                                    <div style="flex: 1;">
                                        <h3><?php echo htmlspecialchars($user['full_name']); ?></h3>
                                        <div class="user-info-grid">
                                            <div class="info-item">
                                                <span class="info-label">Email</span>
                                                <span class="info-value"><?php echo htmlspecialchars($user['email']); ?></span>
                                            </div>
                                            <div class="info-item">
                                                <span class="info-label">Phone</span>
                                                <span class="info-value"><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></span>
                                            </div>
                                            <div class="info-item">
                                                <span class="info-label">Customer Code</span>
                                                <span class="info-value"><?php echo htmlspecialchars($user['customer_code'] ?? 'N/A'); ?></span>
                                            </div>
                                            <div class="info-item">
                                                <span class="info-label">Registered</span>
                                                <span class="info-value"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></span>
                                            </div>
                                        </div>
                                        <?php if ($user['address']): ?>
                                            <div class="info-item" style="grid-column: 1 / -1;">
                                                <span class="info-label">Address</span>
                                                <span class="info-value"><?php echo htmlspecialchars($user['address']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="user-actions">
                                    <a href="?action=approve&user_id=<?php echo $user['id']; ?>" class="btn-approve" 
                                       onclick="return confirm('Approve this user? They will be able to login immediately.')">
                                        <i data-lucide="check" style="width: 1rem; height: 1rem;"></i>
                                        Approve
                                    </a>
                                    <a href="?action=reject&user_id=<?php echo $user['id']; ?>" class="btn-reject"
                                       onclick="return confirm('Reject this user? Their account will be deactivated.')">
                                        <i data-lucide="x" style="width: 1rem; height: 1rem;"></i>
                                        Reject
                                    </a>
                                    <a href="manage_users.php?search=<?php echo urlencode($user['email']); ?>" class="btn-view">
                                        <i data-lucide="eye" style="width: 1rem; height: 1rem;"></i>
                                        View Details
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i data-lucide="check-circle" class="empty-state-icon"></i>
                        <h3>No Pending Approvals</h3>
                        <p>All registered users have been approved.</p>
                        <p>New registrations will appear here automatically.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recently Approved Users -->
            <?php if (count($recent_approved) > 0): ?>
            <div class="card" style="margin-top: 2rem;">
                <div class="section-header">
                    <i data-lucide="history" class="section-icon"></i>
                    <h2>Recently Approved
                        <span class="approved-badge"><?php echo count($recent_approved); ?> users</span>
                    </h2>
                </div>
                
                <div class="user-grid">
                    <?php foreach ($recent_approved as $user): ?>
                        <div class="user-card approved">
                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                <div style="flex: 1;">
                                    <h3><?php echo htmlspecialchars($user['full_name']); ?></h3>
                                    <div class="user-info-grid">
                                        <div class="info-item">
                                            <span class="info-label">Email</span>
                                            <span class="info-value"><?php echo htmlspecialchars($user['email']); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Approved On</span>
                                            <span class="info-value"><?php echo date('M d, Y', strtotime($user['updated_at'])); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Status</span>
                                            <span class="info-value" style="color: #27ae60;">Active</span>
                                        </div>
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <span style="background: #27ae60; color: white; padding: 0.3rem 0.8rem; border-radius: 15px; font-size: 0.8rem;">
                                        <i data-lucide="check" style="width: 0.8rem; height: 0.8rem; margin-right: 0.3rem;"></i>
                                        Approved
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Quick Stats -->
            <div class="card" style="margin-top: 2rem;">
                <div class="section-header">
                    <i data-lucide="bar-chart-3" class="section-icon"></i>
                    <h2>Approval Statistics</h2>
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <div style="text-align: center; padding: 1.5rem; background: #f8f9fa; border-radius: 8px;">
                        <div style="font-size: 2rem; font-weight: bold; color: #f39c12;"><?php echo $pending_count; ?></div>
                        <div>Pending Approvals</div>
                    </div>
                    <?php 
                    // Get total approved users
                    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE is_admin = FALSE AND admin_approved = 1");
                    $total_approved = $stmt->fetch()['total'];
                    ?>
                    <div style="text-align: center; padding: 1.5rem; background: #f8f9fa; border-radius: 8px;">
                        <div style="font-size: 2rem; font-weight: bold; color: #27ae60;"><?php echo $total_approved; ?></div>
                        <div>Total Approved Users</div>
                    </div>
                    <?php 
                    // Get today's registrations
                    $stmt = $pdo->query("SELECT COUNT(*) as today FROM users WHERE DATE(created_at) = CURDATE() AND is_admin = FALSE");
                    $today_registrations = $stmt->fetch()['today'];
                    ?>
                    <div style="text-align: center; padding: 1.5rem; background: #f8f9fa; border-radius: 8px;">
                        <div style="font-size: 2rem; font-weight: bold; color: #3498db;"><?php echo $today_registrations; ?></div>
                        <div>Today's Registrations</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Online Billing System - BCA Project | Tribhuvan University</p>
        </div>
    </footer>

    <script>
        lucide.createIcons();
        
        // Auto-refresh page every 30 seconds if there are pending users
        <?php if ($pending_count > 0): ?>
        setInterval(() => {
            window.location.reload();
        }, 30000);
        <?php endif; ?>
    </script>
</body>
</html>