<?php
session_start();
include '../includes/config.php';

// Redirect if not logged in as customer
if (!isset($_SESSION['user_id']) || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user details including login count
$stmt = $pdo->prepare("SELECT *, COALESCE(login_count, 0) as login_count FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION['error'] = "User not found.";
    header("Location: dashboard.php");
    exit();
}

// Get user statistics
$stmt = $pdo->prepare("SELECT COUNT(*) as total_pending, COALESCE(SUM(amount), 0) as total_amount FROM bills WHERE user_id = ? AND status = 'pending'");
$stmt->execute([$user_id]);
$pending_stats = $stmt->fetch();

// Get recent activity
$stmt = $pdo->prepare("SELECT b.*, s.service_name FROM bills b 
                      LEFT JOIN services s ON b.bill_type = s.service_type 
                      WHERE b.user_id = ? 
                      ORDER BY b.created_at DESC 
                      LIMIT 3");
$stmt->execute([$user_id]);
$recent_bills = $stmt->fetchAll();

// Get initials for header
$first_name = $_SESSION['full_name'];
$names = explode(' ', $first_name);
$initials = '';
foreach ($names as $n) {
    $initials .= strtoupper(substr($n, 0, 1));
}
$first_letter = strtoupper(substr($initials, 0, 2));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - BillPay Pro</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        .profile-main {
            padding: 2rem 0;
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            gap: 2rem;
            margin-bottom: 2rem;
            padding: 2rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #075B5E 0%, #0a7a7e 100%);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: bold;
            flex-shrink: 0;
        }
        
        .profile-info h1 {
            margin: 0 0 0.5rem 0;
            color: #075B5E;
        }
        
        .profile-info p {
            margin: 0.25rem 0;
            color: #666;
        }
        
        .profile-status {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: #d4edda;
            color: #155724;
            border-radius: 20px;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
        
        .profile-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }
        
        .profile-section {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }
        
        .profile-section h2 {
            color: #075B5E;
            margin: 0 0 1rem 0;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }
        
        .info-item {
            margin-bottom: 1rem;
        }
        
        .info-label {
            font-weight: 500;
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }
        
        .info-value {
            color: #333;
            font-size: 1rem;
        }
        
        .activity-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem 0;
            border-bottom: 1px solid #eee;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            background: #f8f9fa;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #075B5E;
            flex-shrink: 0;
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-title {
            font-weight: 500;
            margin: 0 0 0.25rem 0;
        }
        
        .activity-time {
            color: #666;
            font-size: 0.85rem;
        }
        
        .profile-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .action-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .action-btn-primary {
            background: #075B5E;
            color: white;
        }
        
        .action-btn-primary:hover {
            background: #054749;
        }
        
        .action-btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .action-btn-secondary:hover {
            background: #5a6268;
        }
        
        .action-btn-danger {
            background: #f8f9fa;
            color: #e74c3c;
            border: 1px solid #e74c3c;
        }
        
        .action-btn-danger:hover {
            background: #e74c3c;
            color: white;
        }
        
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            text-align: center;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #075B5E;
            display: block;
            line-height: 1.2;
        }
        
        .stat-label {
            font-size: 0.85rem;
            color: #666;
            display: block;
            margin-top: 0.2rem;
        }
        
        @media (max-width: 768px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .profile-header {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }
            
            .profile-avatar {
                width: 100px;
                height: 100px;
                font-size: 2rem;
            }
            
            .quick-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        /* Sidebar override for profile section */
        .sidebar-item.active[href="profile.php"] {
            background: #f0f7f7;
            border-left-color: #075B5E;
            color: #075B5E;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <!-- Profile Sidebar -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="profile-sidebar active" id="profileSidebar" style="right: 0;">
        <div class="sidebar-header">
            <div class="sidebar-avatar">
                <?php echo $first_letter; ?>
            </div>
            <div class="sidebar-user-info">
                <h3><?php echo htmlspecialchars($_SESSION['full_name']); ?></h3>
                <p>ID: <?php echo htmlspecialchars($user['customer_code'] ?? 'N/A'); ?></p>
            </div>
            <button class="sidebar-close" onclick="window.location.href='dashboard.php'">
                <i data-lucide="x"></i>
            </button>
        </div>
        
        <!-- Quick Stats -->
        <div class="sidebar-stats">
            <div class="stat-item">
                <span class="stat-value"><?php echo $pending_stats['total_pending']; ?></span>
                <span class="stat-label">Pending Bills</span>
            </div>
            <div class="stat-item">
                <span class="stat-value">₹<?php echo number_format($pending_stats['total_amount'] ?? 0, 0); ?></span>
                <span class="stat-label">Total Due</span>
            </div>
        </div>
        
        <div class="sidebar-menu">
            <a href="profile.php" class="sidebar-item active">
                <i data-lucide="user" class="sidebar-icon"></i>
                My Profile
            </a>
            <a href="edit_profile.php" class="sidebar-item">
                <i data-lucide="edit" class="sidebar-icon"></i>
                Edit Profile
            </a>
            <a href="change_password.php" class="sidebar-item">
                <i data-lucide="key" class="sidebar-icon"></i>
                Change Password
            </a>
            
            <div class="sidebar-divider"></div>
            
            <a href="bills.php" class="sidebar-item">
                <i data-lucide="file-text" class="sidebar-icon"></i>
                My Bills
            </a>
            <a href="payment.php" class="sidebar-item">
                <i data-lucide="credit-card" class="sidebar-icon"></i>
                Make Payment
            </a>
            <a href="payment_history.php" class="sidebar-item">
                <i data-lucide="history" class="sidebar-icon"></i>
                Payment History
            </a>
            
            <div class="sidebar-divider"></div>
            
            <a href="notifications.php" class="sidebar-item">
                <i data-lucide="bell" class="sidebar-icon"></i>
                Notifications
                <span class="notification-badge">3</span>
            </a>
            <a href="settings.php" class="sidebar-item">
                <i data-lucide="settings" class="sidebar-icon"></i>
                Settings
            </a>
            <a href="help.php" class="sidebar-item">
                <i data-lucide="help-circle" class="sidebar-icon"></i>
                Help & Support
            </a>
        </div>
        
        <div class="sidebar-footer">
            <div style="display: flex; align-items: center; gap: 0.8rem; margin-bottom: 1rem;">
                <i data-lucide="shield" style="width: 1rem; height: 1rem; color: #27ae60;"></i>
                <span style="font-size: 0.85rem; color: #666;">Account secured</span>
            </div>
            <a href="logout.php" class="btn" style="background: #e74c3c; color: white; width: 100%; text-align: center; padding: 0.7rem;">
                <i data-lucide="log-out" style="width: 0.9rem; height: 0.9rem; margin-right: 0.4rem;"></i>
                Logout
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content" style="margin-right: 350px;">
        <!-- Header -->
        <header class="header">
            <div class="container">
                <nav class="navbar">
                    <div class="logo">BillPay Pro</div>
                    <ul class="nav-links">
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li><a href="bills.php">My Bills</a></li>
                        <li><a href="payment_history.php">Payment History</a></li>
                        <li style="display: flex; align-items: center;">
                            <div class="profile-menu-trigger" onclick="toggleProfileSidebar()">
                                <div class="first-letter-circle">
                                    <?php echo $first_letter; ?>
                                </div>
                                <div class="user-name-display">
                                    <span><?php echo $_SESSION['full_name']; ?></span>
                                    <i data-lucide="chevron-down" style="width: 1rem; height: 1rem;"></i>
                                </div>
                            </div>
                            <a href="logout.php" style="margin-left: 15px;">Logout</a>
                        </li>
                    </ul>
                </nav>
            </div>
        </header>

        <div class="container">
            <div class="profile-main">
                <!-- Back to Dashboard -->
                <div style="margin-bottom: 1.5rem;">
                    <a href="dashboard.php" style="color: #075B5E; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem;">
                        <i data-lucide="arrow-left"></i>
                        Back to Dashboard
                    </a>
                </div>
                
                <!-- Profile Header -->
                <div class="profile-header">
                    <div class="profile-avatar">
                        <?php echo $first_letter; ?>
                    </div>
                    <div class="profile-info">
                        <h1><?php echo htmlspecialchars($user['full_name']); ?></h1>
                        <p><?php echo htmlspecialchars($user['email']); ?></p>
                        <p>Customer ID: <?php echo htmlspecialchars($user['customer_code'] ?? 'N/A'); ?></p>
                        <div class="profile-status">
                            <i data-lucide="check-circle" style="width: 16px; height: 16px;"></i>
                            Account Active
                        </div>
                    </div>
                </div>
                
                <div class="profile-grid">
                    <!-- Personal Information -->
                    <div class="profile-section">
                        <h2><i data-lucide="user"></i> Personal Information</h2>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Full Name</div>
                                <div class="info-value"><?php echo htmlspecialchars($user['full_name']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Email Address</div>
                                <div class="info-value"><?php echo htmlspecialchars($user['email']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Phone Number</div>
                                <div class="info-value"><?php echo !empty($user['phone']) ? htmlspecialchars($user['phone']) : '<span style="color: #999;">Not provided</span>'; ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Customer Code</div>
                                <div class="info-value"><?php echo htmlspecialchars($user['customer_code'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Address</div>
                                <div class="info-value"><?php echo !empty($user['address']) ? nl2br(htmlspecialchars($user['address'])) : '<span style="color: #999;">Not provided</span>'; ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Member Since</div>
                                <div class="info-value"><?php echo date('F d, Y', strtotime($user['created_at'])); ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Account Stats -->
                    <div class="profile-section">
                        <h2><i data-lucide="bar-chart"></i> Account Overview</h2>
                        <div class="quick-stats">
                            <div class="stat-card">
                                <span class="stat-value"><?php echo $user['login_count']; ?></span>
                                <span class="stat-label">Total Logins</span>
                            </div>
                            <div class="stat-card">
                                <span class="stat-value"><?php echo $pending_stats['total_pending']; ?></span>
                                <span class="stat-label">Pending Bills</span>
                            </div>
                        </div>
                        
                        <h3 style="color: #075B5E; margin: 1.5rem 0 1rem 0; font-size: 1.1rem;">
                            <i data-lucide="clock"></i> Recent Activity
                        </h3>
                        
                        <?php if (count($recent_bills) > 0): ?>
                            <ul class="activity-list">
                                <?php foreach ($recent_bills as $bill): ?>
                                    <li class="activity-item">
                                        <div class="activity-icon">
                                            <i data-lucide="file-text"></i>
                                        </div>
                                        <div class="activity-content">
                                            <div class="activity-title">
                                                <?php echo $bill['service_name'] ?? ucfirst($bill['bill_type']); ?> Bill
                                            </div>
                                            <div class="activity-time">
                                                <?php echo date('M d, Y', strtotime($bill['created_at'])); ?> • 
                                                ₹<?php echo number_format($bill['amount'], 2); ?> • 
                                                <span style="color: <?php echo $bill['status'] == 'pending' ? '#e74c3c' : '#27ae60'; ?>;">
                                                    <?php echo ucfirst($bill['status']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p style="color: #666; text-align: center; padding: 1rem;">No recent activity</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="profile-actions">
                    <a href="edit_profile.php" class="action-btn action-btn-primary">
                        <i data-lucide="edit"></i> Edit Profile
                    </a>
                    <a href="change_password.php" class="action-btn action-btn-secondary">
                        <i data-lucide="key"></i> Change Password
                    </a>
                    <a href="delete_account.php" class="action-btn action-btn-danger">
                        <i data-lucide="trash-2"></i> Delete Account
                    </a>
                </div>
            </div>
        </div>

        <footer class="footer">
            <div class="container">
                <p>&copy; <?php echo date('Y'); ?> BillPay Pro - Online Billing System</p>
            </div>
        </footer>
    </div>

    <script>
        lucide.createIcons();
        
        // Profile Sidebar Functions
        function toggleProfileSidebar() {
            const sidebar = document.getElementById('profileSidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
            
            // Adjust main content margin
            const mainContent = document.querySelector('.main-content');
            if (sidebar.classList.contains('active')) {
                mainContent.style.marginRight = '350px';
                document.body.style.overflow = 'hidden';
            } else {
                mainContent.style.marginRight = '0';
                document.body.style.overflow = 'auto';
            }
        }
        
        function closeProfileSidebar() {
            const sidebar = document.getElementById('profileSidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
            document.querySelector('.main-content').style.marginRight = '0';
            document.body.style.overflow = 'auto';
        }
        
        // Close sidebar when clicking overlay
        document.getElementById('sidebarOverlay').addEventListener('click', closeProfileSidebar);
        
        // Close sidebar with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeProfileSidebar();
            }
        });
        
        // Add click handlers to sidebar items
        document.querySelectorAll('.sidebar-item').forEach(item => {
            item.addEventListener('click', function(e) {
                if (!this.href || this.href === '#') {
                    e.preventDefault();
                }
                
                // Remove active class from all items
                document.querySelectorAll('.sidebar-item').forEach(i => {
                    i.classList.remove('active');
                });
                
                // Add active class to clicked item
                this.classList.add('active');
            });
        });
        
        // Make header higher z-index to stay on top
        document.querySelector('.header').style.zIndex = '1001';
    </script>
</body>
</html>