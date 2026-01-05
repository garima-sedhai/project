<?php
session_start();
include '../includes/config.php';
include '../includes/db_helper.php';

// Redirect if not logged in as customer
if (!isset($_SESSION['user_id']) || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user details
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        session_destroy();
        header("Location: ../auth/login.php");
        exit();
    }
    
    // Get user's billing statistics
    $stmt = $pdo->prepare("SELECT 
        COUNT(*) as total_bills,
        SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_bills,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_bills,
        SUM(total_amount) as total_spent,
        SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END) as total_paid
        FROM bills WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $billing_stats = $stmt->fetch();
    
    // Get recent bills
    $stmt = $pdo->prepare("SELECT * FROM bills WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$user_id]);
    $recent_bills = $stmt->fetchAll();
    
    // Get last login time (you might need to store this separately)
    $last_login = isset($_SESSION['last_login']) ? $_SESSION['last_login'] : $user['created_at'];
    
} catch (Exception $e) {
    error_log("Profile loading error: " . $e->getMessage());
    $billing_stats = [];
    $recent_bills = [];
}

// Get current date and time for display
date_default_timezone_set('Asia/Kathmandu');
$current_date = date('M d, Y');
$current_time = date('h:i A');
$current_datetime = date('M d, Y h:i A');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - BillPay Pro</title>
    <link rel="stylesheet" href="../css/style.css">
    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        .profile-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }
        
        .profile-header {
            background: linear-gradient(135deg, #3498db 0%, #2c3e50 100%);
            color: white;
            padding: 3rem 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
        
        .profile-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 200%;
            background: rgba(255,255,255,0.1);
            transform: rotate(30deg);
        }
        
        .user-avatar-large {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: bold;
            color: white;
            border: 5px solid white;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            margin-bottom: 1.5rem;
            position: relative;
            z-index: 2;
        }
        
        .profile-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border-left: 5px solid #3498db;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.12);
        }
        
        .stat-icon {
            width: 3rem;
            height: 3rem;
            background: #e8f4fd;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            color: #3498db;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #2c3e50;
            margin: 0.5rem 0;
        }
        
        .stat-label {
            color: #7f8c8d;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .profile-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin: 2rem 0;
        }
        
        .info-card {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .info-card h3 {
            color: #2c3e50;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #f8f9fa;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 1rem 0;
            border-bottom: 1px solid #f8f9fa;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            color: #7f8c8d;
            font-weight: 500;
        }
        
        .info-value {
            color: #2c3e50;
            font-weight: 500;
            text-align: right;
        }
        
        .info-value a {
            color: #3498db;
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .info-value a:hover {
            color: #2980b9;
            text-decoration: underline;
        }
        
        .quick-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin: 2rem 0;
        }
        
        .action-btn {
            flex: 1;
            min-width: 200px;
            padding: 1.5rem;
            background: white;
            border-radius: 12px;
            border: 2px solid #e9ecef;
            display: flex;
            align-items: center;
            gap: 1rem;
            text-decoration: none;
            color: #2c3e50;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .action-btn:hover {
            border-color: #3498db;
            background: #f8f9fa;
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(52, 152, 219, 0.1);
        }
        
        .action-icon {
            width: 3rem;
            height: 3rem;
            background: #e8f4fd;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #3498db;
        }
        
        .action-content {
            flex: 1;
        }
        
        .action-title {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 0.25rem;
        }
        
        .action-desc {
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        
        .recent-bills {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin: 2rem 0;
        }
        
        .bill-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid #f8f9fa;
            transition: background 0.3s;
        }
        
        .bill-item:hover {
            background: #f8f9fa;
        }
        
        .bill-info {
            flex: 1;
        }
        
        .bill-number {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .bill-date {
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        
        .bill-amount {
            font-weight: 600;
            color: #27ae60;
        }
        
        .bill-status {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .status-paid {
            background: #d5f4e6;
            color: #27ae60;
        }
        
        .status-pending {
            background: #ffeaa7;
            color: #e17055;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #7f8c8d;
        }
        
        .empty-state-icon {
            width: 4rem;
            height: 4rem;
            color: #bdc3c7;
            margin: 0 auto 1rem;
        }
        
        .security-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: #d5f4e6;
            color: #27ae60;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .account-status {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: #3498db;
            color: white;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .datetime-display {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 10px;
            margin: 1rem 0;
            display: flex;
            align-items: center;
            gap: 1rem;
            color: #7f8c8d;
        }
        
        @media (max-width: 768px) {
            .profile-header {
                padding: 2rem 1rem;
            }
            
            .user-avatar-large {
                width: 100px;
                height: 100px;
                font-size: 2.5rem;
            }
            
            .profile-stats-grid {
                grid-template-columns: 1fr;
            }
            
            .action-btn {
                min-width: 100%;
            }
            
            .quick-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <div class="logo">BillPay Pro</div>
                <ul class="nav-links">
                    <li><a href="index.php">Dashboard</a></li>
                    <li><a href="bills.php">My Bills</a></li>
                    <li><a href="payments.php">Payments</a></li>
                    <li><a href="profile.php" style="color: #3498db;">My Profile</a></li>
                    <li style="display: flex; align-items: center; gap: 1rem;">
                        <span>Welcome, <?php echo htmlspecialchars($user['full_name']); ?></span>
                        <a href="../auth/logout.php" class="btn btn-sm" style="background: #e74c3c; color: white;">Logout</a>
                    </li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="profile-container">
        <!-- Profile Header -->
        <div class="profile-header">
            <div style="position: relative; z-index: 2;">
                <div class="user-avatar-large">
                    <?php 
                    $names = explode(' ', $user['full_name']);
                    $initials = '';
                    foreach ($names as $n) {
                        $initials .= strtoupper(substr($n, 0, 1));
                    }
                    echo substr($initials, 0, 2);
                    ?>
                </div>
                <h1 style="margin-bottom: 0.5rem;"><?php echo htmlspecialchars($user['full_name']); ?></h1>
                <p style="opacity: 0.9; margin-bottom: 1rem;">Customer ID: <?php echo htmlspecialchars($user['customer_code']); ?></p>
                <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                    <span class="account-status">
                        <i data-lucide="check-circle" style="width: 1rem; height: 1rem;"></i>
                        Active Account
                    </span>
                    <span class="security-badge">
                        <i data-lucide="shield" style="width: 1rem; height: 1rem;"></i>
                        Verified Account
                    </span>
                </div>
            </div>
        </div>

        <!-- Current Date & Time -->
        <div class="datetime-display">
            <i data-lucide="clock" style="width: 1.2rem; height: 1.2rem;"></i>
            <div>
                <strong>Current Time:</strong> <?php echo $current_datetime; ?>
                <?php if (isset($last_login)): ?>
                    <br><small>Last login: <?php echo date('M d, Y h:i A', strtotime($last_login)); ?></small>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="edit_profile.php" class="action-btn">
                <div class="action-icon">
                    <i data-lucide="user" style="width: 1.5rem; height: 1.5rem;"></i>
                </div>
                <div class="action-content">
                    <div class="action-title">Edit Profile</div>
                    <div class="action-desc">Update your personal information</div>
                </div>
                <i data-lucide="chevron-right" style="width: 1.5rem; height: 1.5rem; color: #bdc3c7;"></i>
            </a>
            
            <a href="change_password.php" class="action-btn">
                <div class="action-icon">
                    <i data-lucide="key" style="width: 1.5rem; height: 1.5rem;"></i>
                </div>
                <div class="action-content">
                    <div class="action-title">Change Password</div>
                    <div class="action-desc">Update your account password</div>
                </div>
                <i data-lucide="chevron-right" style="width: 1.5rem; height: 1.5rem; color: #bdc3c7;"></i>
            </a>
            
            <div class="action-btn" onclick="alert('Coming soon: Download your data')">
                <div class="action-icon">
                    <i data-lucide="download" style="width: 1.5rem; height: 1.5rem;"></i>
                </div>
                <div class="action-content">
                    <div class="action-title">Download Data</div>
                    <div class="action-desc">Get your account data export</div>
                </div>
                <i data-lucide="chevron-right" style="width: 1.5rem; height: 1.5rem; color: #bdc3c7;"></i>
            </div>
            
            <a href="delete_account.php" class="action-btn" style="border-color: #e74c3c;">
                <div class="action-icon" style="background: #fdeaea; color: #e74c3c;">
                    <i data-lucide="trash-2" style="width: 1.5rem; height: 1.5rem;"></i>
                </div>
                <div class="action-content">
                    <div class="action-title" style="color: #e74c3c;">Delete Account</div>
                    <div class="action-desc">Permanently delete your account</div>
                </div>
                <i data-lucide="chevron-right" style="width: 1.5rem; height: 1.5rem; color: #e74c3c;"></i>
            </a>
        </div>

        <!-- Billing Statistics -->
        <div class="profile-stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i data-lucide="file-text" style="width: 1.5rem; height: 1.5rem;"></i>
                </div>
                <div class="stat-value"><?php echo $billing_stats['total_bills'] ?? 0; ?></div>
                <div class="stat-label">Total Bills</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i data-lucide="check-circle" style="width: 1.5rem; height: 1.5rem;"></i>
                </div>
                <div class="stat-value"><?php echo $billing_stats['paid_bills'] ?? 0; ?></div>
                <div class="stat-label">Paid Bills</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i data-lucide="clock" style="width: 1.5rem; height: 1.5rem;"></i>
                </div>
                <div class="stat-value"><?php echo $billing_stats['pending_bills'] ?? 0; ?></div>
                <div class="stat-label">Pending Bills</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i data-lucide="dollar-sign" style="width: 1.5rem; height: 1.5rem;"></i>
                </div>
                <div class="stat-value">₹<?php echo number_format($billing_stats['total_spent'] ?? 0, 2); ?></div>
                <div class="stat-label">Total Spent</div>
            </div>
        </div>

        <!-- Profile Information Grid -->
        <div class="profile-info-grid">
            <!-- Personal Information -->
            <div class="info-card">
                <h3>
                    <i data-lucide="user" style="width: 1.2rem; height: 1.2rem;"></i>
                    Personal Information
                </h3>
                <div class="info-item">
                    <span class="info-label">Full Name</span>
                    <span class="info-value"><?php echo htmlspecialchars($user['full_name']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Email Address</span>
                    <span class="info-value"><?php echo htmlspecialchars($user['email']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Phone Number</span>
                    <span class="info-value"><?php echo htmlspecialchars($user['phone']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Customer Code</span>
                    <span class="info-value"><?php echo htmlspecialchars($user['customer_code']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Account Created</span>
                    <span class="info-value"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></span>
                </div>
            </div>

            <!-- Contact & Address -->
            <div class="info-card">
                <h3>
                    <i data-lucide="map-pin" style="width: 1.2rem; height: 1.2rem;"></i>
                    Contact & Address
                </h3>
                <div class="info-item">
                    <span class="info-label">Address</span>
                    <span class="info-value"><?php echo htmlspecialchars($user['address'] ?? 'Not provided'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Email Verified</span>
                    <span class="info-value">
                        <?php if ($user['email_verified'] ?? false): ?>
                            <span style="color: #27ae60;">Verified</span>
                        <?php else: ?>
                            <a href="../auth/verify_email.php" style="color: #e74c3c;">Not Verified - Verify Now</a>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">Account Status</span>
                    <span class="info-value">
                        <?php if ($user['is_active'] ?? true): ?>
                            <span style="color: #27ae60;">Active</span>
                        <?php else: ?>
                            <span style="color: #e74c3c;">Inactive</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">Member Since</span>
                    <span class="info-value"><?php echo date('F Y', strtotime($user['created_at'])); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Last Updated</span>
                    <span class="info-value"><?php echo date('M d, Y', strtotime($user['updated_at'] ?? $user['created_at'])); ?></span>
                </div>
            </div>

            <!-- Security Information -->
            <div class="info-card">
                <h3>
                    <i data-lucide="shield" style="width: 1.2rem; height: 1.2rem;"></i>
                    Security Information
                </h3>
                <div class="info-item">
                    <span class="info-label">Password</span>
                    <span class="info-value">
                        <a href="change_password.php">Change Password</a>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">Two-Factor Auth</span>
                    <span class="info-value">
                        <a href="#" onclick="alert('Two-factor authentication coming soon')">Enable 2FA</a>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">Login Sessions</span>
                    <span class="info-value">
                        <a href="#" onclick="alert('Session management coming soon')">Manage Sessions</a>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">Privacy Settings</span>
                    <span class="info-value">
                        <a href="#" onclick="alert('Privacy settings coming soon')">Update Settings</a>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">Data & Privacy</span>
                    <span class="info-value">
                        <a href="#" onclick="alert('Data privacy information coming soon')">View Policy</a>
                    </span>
                </div>
            </div>
        </div>

        <!-- Recent Bills -->
        <div class="recent-bills">
            <h3 style="color: #2c3e50; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                <i data-lucide="receipt" style="width: 1.2rem; height: 1.2rem;"></i>
                Recent Bills
            </h3>
            
            <?php if (!empty($recent_bills)): ?>
                <?php foreach ($recent_bills as $bill): ?>
                    <div class="bill-item">
                        <div class="bill-info">
                            <div class="bill-number">Bill #<?php echo htmlspecialchars($bill['bill_number']); ?></div>
                            <div class="bill-date"><?php echo date('M d, Y', strtotime($bill['created_at'])); ?></div>
                        </div>
                        <div class="bill-amount">₹<?php echo number_format($bill['total_amount'], 2); ?></div>
                        <div class="bill-status <?php echo $bill['status'] == 'paid' ? 'status-paid' : 'status-pending'; ?>">
                            <?php echo ucfirst($bill['status']); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <div style="text-align: center; margin-top: 1.5rem;">
                    <a href="bills.php" class="btn" style="display: inline-flex; align-items: center; gap: 0.5rem;">
                        <i data-lucide="list" style="width: 1rem; height: 1rem;"></i>
                        View All Bills
                    </a>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i data-lucide="file-text" class="empty-state-icon"></i>
                    <h4>No Bills Yet</h4>
                    <p>You don't have any bills yet. Bills will appear here once generated.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Support Section -->
        <div class="info-card" style="margin-top: 2rem;">
            <h3>
                <i data-lucide="help-circle" style="width: 1.2rem; height: 1.2rem;"></i>
                Need Help?
            </h3>
            <p style="color: #7f8c8d; margin-bottom: 1.5rem;">
                Having issues with your account or need assistance? Our support team is here to help.
            </p>
            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <a href="../support/contact.php" class="btn" style="background: #3498db; color: white;">
                    <i data-lucide="mail" style="width: 1rem; height: 1rem; margin-right: 0.5rem;"></i>
                    Contact Support
                </a>
                <a href="../support/faq.php" class="btn" style="background: #f8f9fa; color: #2c3e50;">
                    <i data-lucide="help-circle" style="width: 1rem; height: 1rem; margin-right: 0.5rem;"></i>
                    View FAQ
                </a>
                <a href="../support/privacy.php" class="btn" style="background: #f8f9fa; color: #2c3e50;">
                    <i data-lucide="shield" style="width: 1rem; height: 1rem; margin-right: 0.5rem;"></i>
                    Privacy Policy
                </a>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Online Billing System - BCA Project | Tribhuvan University</p>
        </div>
    </footer>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();
        
        // Update current time every minute
        function updateCurrentTime() {
            const now = new Date();
            const options = { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                hour12: true 
            };
            const currentTime = now.toLocaleDateString('en-US', options);
            document.querySelector('.datetime-display strong').nextSibling.textContent = ' ' + currentTime;
        }
        
        // Update time every minute
        setInterval(updateCurrentTime, 60000);
        
        // Add confirmation for delete account
        document.querySelector('a[href="delete_account.php"]').addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to delete your account? This action cannot be undone.')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>