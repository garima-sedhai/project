<?php
session_start();
include '../includes/config.php';

// Redirect if not logged in as customer
if (!isset($_SESSION['user_id']) || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Update login count and get user statistics
$stmt = $pdo->prepare("UPDATE users SET login_count = COALESCE(login_count, 0) + 1, last_login = NOW() WHERE id = ?");
$stmt->execute([$user_id]);

// Get user details including login count
$stmt = $pdo->prepare("SELECT *, COALESCE(login_count, 0) as login_count FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Determine welcome message based on login count
if ($user['login_count'] <= 1) {
    $welcome_message = "Welcome to BillPay Pro, " . $_SESSION['full_name'] . "!";
    $welcome_subtitle = "We're excited to have you onboard. Let's get started with your billing dashboard.";
} else {
    $welcome_message = "Welcome back, " . $_SESSION['full_name'] . "!";
    $welcome_subtitle = "Here's your billing overview and quick actions";
}

// Get user statistics
// Total pending bills
$stmt = $pdo->prepare("SELECT COUNT(*) as total_pending, COALESCE(SUM(amount), 0) as total_amount FROM bills WHERE user_id = ? AND status = 'pending'");
$stmt->execute([$user_id]);
$pending_stats = $stmt->fetch();

// Total paid bills
$stmt = $pdo->prepare("SELECT COUNT(*) as total_paid, COALESCE(SUM(amount), 0) as paid_amount FROM bills WHERE user_id = ? AND status = 'paid'");
$stmt->execute([$user_id]);
$paid_stats = $stmt->fetch();

// Recent bills (last 5)
$stmt = $pdo->prepare("SELECT b.*, s.service_name FROM bills b 
                      LEFT JOIN services s ON b.bill_type = s.service_type 
                      WHERE b.user_id = ? 
                      ORDER BY b.due_date ASC 
                      LIMIT 5");
$stmt->execute([$user_id]);
$recent_bills = $stmt->fetchAll();

// Recent payments (last 3)
$stmt = $pdo->prepare("SELECT p.*, b.bill_type FROM payments p 
                      JOIN bills b ON p.bill_id = b.id 
                      WHERE p.user_id = ? 
                      ORDER BY p.payment_date DESC 
                      LIMIT 3");
$stmt->execute([$user_id]);
$recent_payments = $stmt->fetchAll();

// Check if this is first login (for special first-time message)
$is_first_login = ($user['login_count'] == 1);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - BillPay Pro</title>
    <link rel="stylesheet" href="../css/style.css">
    <!-- Lucide Icons for Profile Sidebar -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        /* Profile Sidebar Styles - Fixed position below header */
        .profile-sidebar {
            position: fixed;
            top: 70px; /* Position below header */
            right: -400px;
            width: 350px;
            height: calc(100vh - 70px); /* Full height minus header */
            background: white;
            box-shadow: -5px 0 25px rgba(0,0,0,0.1);
            transition: right 0.3s ease;
            z-index: 1000;
            overflow-y: auto;
            border-top: 1px solid #e9ecef;
        }
        
        .profile-sidebar.active {
            right: 0;
        }
        
        .sidebar-overlay {
            position: fixed;
            top: 70px; /* Start below header */
            left: 0;
            width: 100%;
            height: calc(100vh - 70px); /* Full height minus header */
            background: rgba(0,0,0,0.5);
            z-index: 999;
            display: none;
        }
        
        .sidebar-overlay.active {
            display: block;
        }
        
        .sidebar-header {
            background: linear-gradient(135deg, #075B5E 0%, #0a7a7e 100%);
            color: white;
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            position: sticky;
            top: 0;
            z-index: 1;
        }
        
        .sidebar-avatar {
            width: 50px;
            height: 50px;
            background: white;
            color: #075B5E;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            font-weight: bold;
            flex-shrink: 0;
        }
        
        .sidebar-user-info h3 {
            margin: 0;
            font-size: 1.1rem;
            line-height: 1.3;
        }
        
        .sidebar-user-info p {
            margin: 0.2rem 0 0 0;
            opacity: 0.8;
            font-size: 0.85rem;
        }
        
        .sidebar-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .sidebar-menu {
            padding: 1rem 0;
        }
        
        .sidebar-item {
            display: flex;
            align-items: center;
            padding: 0.9rem 1.5rem;
            color: #333;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 4px solid transparent;
            font-size: 0.95rem;
        }
        
        .sidebar-item:hover {
            background: #f8f9fa;
            border-left-color: #075B5E;
            padding-left: 1.8rem;
        }
        
        .sidebar-item.active {
            background: #f0f7f7;
            border-left-color: #075B5E;
            color: #075B5E;
            font-weight: 500;
        }
        
        .sidebar-icon {
            width: 1.1rem;
            height: 1.1rem;
            margin-right: 0.8rem;
            color: #666;
            flex-shrink: 0;
        }
        
        .sidebar-item:hover .sidebar-icon {
            color: #075B5E;
        }
        
        .sidebar-item.active .sidebar-icon {
            color: #075B5E;
        }
        
        .sidebar-divider {
            height: 1px;
            background: #e9ecef;
            margin: 0.8rem 1.5rem;
        }
        
        .sidebar-footer {
            padding: 1.2rem 1.5rem;
            background: #f8f9fa;
            margin-top: auto;
            border-top: 1px solid #e9ecef;
            position: sticky;
            bottom: 0;
        }
        
        .sidebar-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.8rem;
            padding: 0 1.5rem;
            margin-bottom: 1.2rem;
        }
        
        .stat-item {
            text-align: center;
            padding: 0.8rem;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        
        .stat-value {
            font-size: 1.3rem;
            font-weight: bold;
            color: #075B5E;
            display: block;
            line-height: 1.2;
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: #666;
            display: block;
            margin-top: 0.2rem;
        }
        
        /* Existing styles remain unchanged */
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
            margin-right: 0.5rem;
            flex-shrink: 0;
        }
        
        .nav-links li {
            display: flex;
            align-items: center;
        }
        
        .profile-menu-trigger {
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.8rem;
            border-radius: 4px;
            transition: background 0.3s;
            flex-shrink: 0;
        }
        
        .profile-menu-trigger:hover {
            background: rgba(7, 91, 94, 0.1);
        }
        
        .notification-badge {
            background: #e74c3c;
            color: white;
            font-size: 0.7rem;
            padding: 0.1rem 0.4rem;
            border-radius: 10px;
            margin-left: auto;
            min-width: 18px;
            text-align: center;
        }
        
        /* Header z-index fix */
        .header {
            position: relative;
            z-index: 1001; /* Higher than sidebar */
        }
        
        @media (max-width: 768px) {
            .profile-sidebar {
                width: 100%;
                right: -100%;
                top: 60px;
                height: calc(100vh - 60px);
            }
            
            .sidebar-overlay {
                top: 60px;
                height: calc(100vh - 60px);
            }
        }
        
        /* Scrollbar styling */
        .profile-sidebar::-webkit-scrollbar {
            width: 6px;
        }
        
        .profile-sidebar::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        .profile-sidebar::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }
        
        .profile-sidebar::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
    </style>
</head>
<body>
    <!-- Profile Sidebar -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="profile-sidebar" id="profileSidebar">
        <div class="sidebar-header">
            <div class="sidebar-avatar">
                <?php 
                $names = explode(' ', $_SESSION['full_name']);
                $initials = '';
                foreach ($names as $n) {
                    $initials .= strtoupper(substr($n, 0, 1));
                }
                echo substr($initials, 0, 2);
                ?>
            </div>
            <div class="sidebar-user-info">
                <h3><?php echo htmlspecialchars($_SESSION['full_name']); ?></h3>
                <p>ID: <?php echo htmlspecialchars($user['customer_code'] ?? 'N/A'); ?></p>
            </div>
            <button class="sidebar-close" onclick="closeProfileSidebar()">
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
                            <i data-lucide="chevron-down" style="width: 1rem; height: 1rem;"></i>
                        </div>
                        <a href="logout.php" style="margin-left: 15px;">Logout</a>
                    </li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="container">
        <!-- Welcome Section -->
        <div class="card" style="background: #075B5E; color: white;">
            <h1><?php echo $welcome_message; ?></h1>
            <p class="mt-1"><?php echo $welcome_subtitle; ?></p>
            <?php if ($is_first_login): ?>
                <div style="background: #FFE6E1; color: #075B5E; padding: 1rem; border-radius: 4px; margin-top: 1rem;">
                    <strong>First Time Here!</strong>
                    <p class="mt-1" style="margin: 0;">Welcome to our platform! Get started by viewing your bills.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Payment Alert -->
        <?php if ($pending_stats['total_pending'] > 0): ?>
        <div class="alert alert-success">
            <strong>Action Required:</strong> You have <?php echo $pending_stats['total_pending']; ?> pending bill(s) totaling ₹<?php echo number_format($pending_stats['total_amount'], 2); ?>
            <a href="payment.php" class="btn" style="margin-left: 1rem;">Pay Now</a>
        </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="dashboard-cards">
            <div class="dashboard-card">
                <h3><?php echo $pending_stats['total_pending']; ?></h3>
                <p>Pending Bills</p>
            </div>
            <div class="dashboard-card">
                <h3>₹<?php echo number_format($pending_stats['total_amount'], 2); ?></h3>
                <p>Total Due</p>
            </div>
            <div class="dashboard-card">
                <h3><?php echo $paid_stats['total_paid']; ?></h3>
                <p>Paid Bills</p>
            </div>
            <div class="dashboard-card">
                <h3>₹<?php echo number_format($paid_stats['paid_amount'], 2); ?></h3>
                <p>Total Paid</p>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="dashboard-cards">
            <a href="bills.php" class="dashboard-card" style="text-decoration: none; color: inherit; display: block;">
                <h3 style="color: #075B5E;">View Bills</h3>
                <p>Check all your bills</p>
            </a>
            <a href="payment.php" class="dashboard-card" style="text-decoration: none; color: inherit; display: block;">
                <h3 style="color: #075B5E;">Make Payment</h3>
                <p>Pay pending bills</p>
            </a>
            <a href="payment_history.php" class="dashboard-card" style="text-decoration: none; color: inherit; display: block;">
                <h3 style="color: #075B5E;">Payment History</h3>
                <p>View past transactions</p>
            </a>
        </div>

        <!-- Recent Activity -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-top: 2rem;">
            <!-- Recent Bills -->
            <div class="card">
                <h2 style="color: #075B5E; margin-bottom: 1rem;">Recent Bills</h2>
                <?php if (count($recent_bills) > 0): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th>Amount</th>
                                <th>Due Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_bills as $bill): ?>
                                <tr>
                                    <td><?php echo $bill['service_name'] ?? ucfirst($bill['bill_type']); ?></td>
                                    <td>₹<?php echo number_format($bill['amount'], 2); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($bill['due_date'])); ?></td>
                                    <td>
                                        <?php if ($bill['status'] == 'pending'): ?>
                                            <span class="status-pending">Pending</span>
                                        <?php else: ?>
                                            <span class="status-completed">Paid</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="text-center mt-2">
                        <a href="bills.php" class="btn">View All Bills</a>
                    </div>
                <?php else: ?>
                    <p>No bills found.</p>
                <?php endif; ?>
            </div>

            <!-- Recent Payments -->
            <div class="card">
                <h2 style="color: #075B5E; margin-bottom: 1rem;">Recent Payments</h2>
                <?php if (count($recent_payments) > 0): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Service</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_payments as $payment): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                                    <td><?php echo ucfirst($payment['bill_type']); ?></td>
                                    <td>₹<?php echo number_format($payment['payment_amount'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="text-center mt-2">
                        <a href="payment_history.php" class="btn">View Full History</a>
                    </div>
                <?php else: ?>
                    <p>No payments yet.</p>
                <?php endif; ?>
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
        
        // Profile Sidebar Functions
        function toggleProfileSidebar() {
            const sidebar = document.getElementById('profileSidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
            
            // Prevent body scroll when sidebar is open
            if (sidebar.classList.contains('active')) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = 'auto';
            }
        }
        
        function closeProfileSidebar() {
            const sidebar = document.getElementById('profileSidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
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
                
                // Close sidebar after clicking (for mobile)
                if (window.innerWidth < 768) {
                    setTimeout(closeProfileSidebar, 300);
                }
            });
        });
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('profileSidebar');
            const trigger = document.querySelector('.profile-menu-trigger');
            
            if (sidebar.classList.contains('active') && 
                window.innerWidth < 768 && 
                !sidebar.contains(event.target) && 
                !trigger.contains(event.target)) {
                closeProfileSidebar();
            }
        });
        
        // Make header higher z-index to stay on top
        document.querySelector('.header').style.zIndex = '1001';
    </script>
</body>
</html>