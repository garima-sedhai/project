<?php
// For admin files:
$base_path = dirname(__DIR__);
require_once $base_path . '/includes/config.php';
require_once $base_path . '/includes/db_connection.php';
require_once $base_path . '/includes/email_functions.php';

// Redirect if not admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: ../customer/login.php");
    exit;
}

// Handle search
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? 'all';

// Base query
$query = "SELECT * FROM users WHERE user_type = 'customer'";
$params = [];

// Apply search filter
if (!empty($search)) {
    $query .= " AND (full_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $search_term = "%$search%";
    $params = [$search_term, $search_term, $search_term];
}

// Apply status filter
if ($status_filter === 'pending') {
    $query .= " AND email_verified = 1 AND admin_approved = 0 AND account_status = 'pending'";
} elseif ($status_filter === 'approved') {
    $query .= " AND admin_approved = 1 AND account_status = 'active'";
} elseif ($status_filter === 'rejected') {
    $query .= " AND registration_status = 'rejected'";
} elseif ($status_filter === 'unverified') {
    $query .= " AND (email_verified = 0 OR registration_status = 'pending')";
} elseif ($status_filter === 'verified') {
    $query .= " AND email_verified = 1 AND admin_approved = 0 AND account_status = 'pending'";
}

$query .= " ORDER BY created_at DESC";

// Get users
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Get pending approvals count for badge
$stmt = $pdo->prepare("SELECT COUNT(*) as pending_approvals FROM users WHERE user_type = 'customer' AND email_verified = 1 AND admin_approved = 0 AND account_status = 'pending'");
$stmt->execute();
$pending_approvals_count = $stmt->fetch()['pending_approvals'];

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_user'])) {
        $user_id = $_POST['user_id'];
        
        // Get user details before updating
        $stmt = $pdo->prepare("SELECT email, full_name FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        // Update user status
        $stmt = $pdo->prepare("UPDATE users SET admin_approved = 1, approved_by_admin = 1, account_status = 'active', registration_status = 'approved', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$user_id]);
        
        // Send approval email
        if ($user) {
            sendApprovalEmail($user['email'], $user['full_name']);
        }
        
        $_SESSION['success_message'] = "User approved successfully! Approval email has been sent.";
        header("Location: manage_users.php");
        exit;
    }
    
    if (isset($_POST['reject_user'])) {
        $user_id = $_POST['user_id'];
        
        // Get user details before updating
        $stmt = $pdo->prepare("SELECT email, full_name FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        $stmt = $pdo->prepare("UPDATE users SET admin_approved = 0, registration_status = 'rejected', account_status = 'inactive', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$user_id]);
        
        // Send rejection email
        if ($user) {
            sendRejectionEmail($user['email'], $user['full_name']);
        }
        
        $_SESSION['success_message'] = "User rejected successfully!";
        header("Location: manage_users.php");
        exit;
    }
    
    if (isset($_POST['delete_user'])) {
        $user_id = $_POST['user_id'];
        
        // Check if user has any bills before deleting
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) as bill_count FROM bills WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $bill_count = $stmt->fetch()['bill_count'];
            
            if ($bill_count > 0) {
                $_SESSION['error_message'] = "Cannot delete user with existing bills. Please mark the user as inactive instead.";
            } else {
                $stmt = $pdo->prepare("UPDATE users SET is_active = 0, account_status = 'inactive', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$user_id]);
                $_SESSION['success_message'] = "User marked as inactive successfully!";
            }
        } catch (Exception $e) {
            // If bills table doesn't exist, just update user status
            $stmt = $pdo->prepare("UPDATE users SET is_active = 0, account_status = 'inactive', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$user_id]);
            $_SESSION['success_message'] = "User marked as inactive successfully!";
        }
        
        header("Location: manage_users.php");
        exit;
    }
    
    if (isset($_POST['resend_verification'])) {
        $user_id = $_POST['user_id'];
        
        // Get user details
        $stmt = $pdo->prepare("SELECT email, full_name FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Generate new OTP
            $otp = rand(100000, 999999);
            $otp_expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            
            $stmt = $pdo->prepare("UPDATE users SET otp = ?, otp_expiry = ?, email_verified = 0, registration_status = 'pending', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$otp, $otp_expiry, $user_id]);
            
            // Send OTP email
            $email_sent = sendOTPEmail($user['email'], $user['full_name'], $otp);
            
            if ($email_sent) {
                $_SESSION['success_message'] = "Verification OTP resent to user successfully!";
            } else {
                $_SESSION['error_message'] = "Failed to send verification email. Please try again.";
            }
        } else {
            $_SESSION['error_message'] = "User not found.";
        }
        
        header("Location: manage_users.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - BillPay Pro</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        /* Main content styles */
        .manage-container {
            padding: 20px;
            max-width: 1400px;
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
        
        .header-section h1 {
            margin: 0;
            color: #075B5E;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .pending-badge {
            background: #e74c3c;
            color: white;
            border-radius: 20px;
            padding: 5px 15px;
            font-size: 0.9rem;
            font-weight: bold;
        }
        
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 500;
            transition: background-color 0.2s;
        }
        
        .back-btn:hover {
            background: #5a6268;
        }
        
        .filters-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .filter-form {
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 15px;
            align-items: end;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .form-group label {
            font-weight: 500;
            color: #075B5E;
        }
        
        .form-control {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: #075B5E;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .btn:hover {
            background: #0a7c80;
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
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-warning:hover {
            background: #e0a800;
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn-info {
            background: #17a2b8;
        }
        
        .btn-info:hover {
            background: #138496;
        }
        
        .users-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        
        .user-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.2s;
        }
        
        .user-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .user-header {
            background: #075B5E;
            color: white;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-avatar {
            width: 60px;
            height: 60px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        .user-header-info {
            flex: 1;
        }
        
        .user-name {
            margin: 0;
            font-size: 1.2rem;
        }
        
        .user-email {
            margin: 5px 0 0;
            opacity: 0.9;
            font-size: 0.9rem;
        }
        
        .user-body {
            padding: 20px;
        }
        
        .user-details p {
            margin: 10px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .user-details strong {
            color: #075B5E;
            min-width: 140px;
        }
        
        .status-icon {
            width: 1rem;
            height: 1rem;
        }
        
        .user-actions {
            padding: 20px;
            border-top: 1px solid #f0f0f0;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .no-results {
            grid-column: 1 / -1;
            text-align: center;
            padding: 40px 20px;
            color: #666;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-approved {
            background: #d4edda;
            color: #155724;
        }
        
        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-unverified {
            background: #e2e3e5;
            color: #383d41;
        }
        
        .status-verified {
            background: #cce5ff;
            color: #004085;
        }
        
        .status-inactive {
            background: #f8f9fa;
            color: #6c757d;
        }
        
        .status-active {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .message {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .info-message {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .email-notice {
            background: #f0f7f7;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 0.85rem;
            margin-top: 5px;
            border-left: 3px solid #075B5E;
        }
        
        .registration-time {
            font-size: 0.85rem;
            color: #666;
            margin-top: 5px;
        }
        
        .time-icon {
            width: 0.9rem;
            height: 0.9rem;
            margin-right: 5px;
            vertical-align: middle;
        }
        
        @media (max-width: 768px) {
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .header-section {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .users-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Using the header from admin/index.php -->
    <?php
    // We'll use the same header logic from admin/index.php
    ?>
    
    <!-- Header Navigation Styles -->
    <style>
        .header-nav {
            background: #075B5E;
            padding: 1rem 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
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
        
        .header-user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: white;
        }
        
        .user-avatar-small {
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
            text-decoration: none;
            display: inline-block;
            font-family: inherit;
            font-size: 1rem;
        }
        
        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        
        @media (max-width: 768px) {
            .nav-container {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .header-user-info {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
    
    <!-- Custom Header Navigation (same as index.php) -->
    <header class="header-nav">
        <div class="nav-container">
            <a href="index.php" class="logo">Admin Dashboard</a>
            <nav>
                <ul class="nav-links">
                    <li><a href="index.php">Dashboard</a></li>
                    <li><a href="manage_bills.php">Manage Bills</a></li>
                    <li><a href="manage_services.php">Manage Services</a></li>
                    <li><a href="reports.php">Manage Reports</a></li>
                    <li><a href="manage_users.php" class="active">Manage Users</a></li>
                    <li><a href="approve_users.php">Approve Users</a></li>
                </ul>
            </nav>
            <div class="header-user-info">
                <div class="user-avatar-small">
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
    
    <div class="manage-container">
        <div class="header-section">
            <h1>
                <i data-lucide="users"></i>
                Manage Users
                <?php if ($pending_approvals_count > 0): ?>
                    <span class="pending-badge"><?php echo $pending_approvals_count; ?> Pending</span>
                <?php endif; ?>
            </h1>
            <a href="index.php" class="back-btn">
                <i data-lucide="arrow-left"></i> Back to Dashboard
            </a>
        </div>
        
        <!-- Display messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="message success-message">
                <i data-lucide="check-circle" style="width: 1.2rem; height: 1.2rem; margin-right: 0.5rem; vertical-align: middle;"></i>
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="message error-message">
                <i data-lucide="alert-circle" style="width: 1.2rem; height: 1.2rem; margin-right: 0.5rem; vertical-align: middle;"></i>
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>
        
        <!-- Filters -->
        <div class="filters-section">
            <form method="GET" class="filter-form">
                <div class="form-group">
                    <label for="search">Search Users</label>
                    <input type="text" id="search" name="search" class="form-control" placeholder="Search by name, email, or phone..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="form-group">
                    <label for="status">Status Filter</label>
                    <select id="status" name="status" class="form-control">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Users</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending Approval</option>
                        <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved (Active)</option>
                        <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="unverified" <?php echo $status_filter === 'unverified' ? 'selected' : ''; ?>>Unverified</option>
                        <option value="verified" <?php echo $status_filter === 'verified' ? 'selected' : ''; ?>>Verified (Pending Admin)</option>
                    </select>
                </div>
                
                <button type="submit" class="btn">
                    <i data-lucide="search"></i> Search
                </button>
            </form>
        </div>
        
        <!-- Users List -->
        <div class="users-grid">
            <?php if (empty($users)): ?>
                <div class="no-results">
                    <i data-lucide="users" style="width: 64px; height: 64px; opacity: 0.5; margin-bottom: 20px;"></i>
                    <h3>No users found</h3>
                    <p>Try adjusting your search criteria</p>
                </div>
            <?php else: ?>
                <?php foreach ($users as $customer): ?>
                    <div class="user-card">
                        <div class="user-header">
                            <div class="user-avatar">
                                <?php 
                                $name_parts = explode(' ', $customer['full_name']);
                                $initials = '';
                                foreach ($name_parts as $part) {
                                    if (!empty($part)) {
                                        $initials .= strtoupper(substr($part, 0, 1));
                                    }
                                }
                                echo substr($initials, 0, 2);
                                ?>
                            </div>
                            <div class="user-header-info">
                                <h3 class="user-name"><?php echo htmlspecialchars($customer['full_name']); ?></h3>
                                <p class="user-email"><?php echo htmlspecialchars($customer['email']); ?></p>
                                <p class="registration-time">
                                    <i data-lucide="clock" class="time-icon"></i>
                                    Registered: <?php echo date('M d, Y h:i A', strtotime($customer['created_at'])); ?>
                                </p>
                            </div>
                        </div>
                        
                        <div class="user-body">
                            <div class="user-details">
                                <p>
                                    <i data-lucide="phone" class="status-icon"></i>
                                    <strong>Phone:</strong> <?php echo htmlspecialchars($customer['phone'] ?? 'Not provided'); ?>
                                </p>
                                <p>
                                    <i data-lucide="home" class="status-icon"></i>
                                    <strong>Address:</strong> <?php echo htmlspecialchars($customer['address'] ?? 'Not provided'); ?>
                                </p>
                                <p>
                                    <i data-lucide="calendar" class="status-icon"></i>
                                    <strong>Registered:</strong> <?php echo date('M d, Y', strtotime($customer['created_at'])); ?>
                                </p>
                                <p>
                                    <i data-lucide="mail-check" class="status-icon"></i>
                                    <strong>Email Status:</strong> 
                                    <?php if ($customer['email_verified']): ?>
                                        <span class="status-badge status-verified">Verified</span>
                                    <?php else: ?>
                                        <span class="status-badge status-unverified">Not Verified</span>
                                    <?php endif; ?>
                                </p>
                                <p>
                                    <i data-lucide="shield-check" class="status-icon"></i>
                                    <strong>Approval Status:</strong> 
                                    <?php if ($customer['admin_approved']): ?>
                                        <span class="status-badge status-approved">Approved</span>
                                    <?php elseif ($customer['email_verified'] && $customer['account_status'] == 'pending'): ?>
                                        <span class="status-badge status-pending">Pending Approval</span>
                                    <?php elseif ($customer['registration_status'] == 'rejected'): ?>
                                        <span class="status-badge status-rejected">Rejected</span>
                                    <?php else: ?>
                                        <span class="status-badge status-unverified">Not Verified</span>
                                    <?php endif; ?>
                                </p>
                                <p>
                                    <i data-lucide="user" class="status-icon"></i>
                                    <strong>Account Status:</strong> 
                                    <?php if ($customer['account_status'] == 'active'): ?>
                                        <span class="status-badge status-active">Active</span>
                                    <?php elseif ($customer['account_status'] == 'inactive'): ?>
                                        <span class="status-badge status-inactive">Inactive</span>
                                    <?php else: ?>
                                        <span class="status-badge status-pending">Pending</span>
                                    <?php endif; ?>
                                </p>
                                
                                <!-- Email notification notice for pending approval -->
                                <?php if ($customer['email_verified'] && !$customer['admin_approved'] && $customer['account_status'] == 'pending'): ?>
                                    <div class="email-notice">
                                        <i data-lucide="mail" style="width: 0.9rem; height: 0.9rem; margin-right: 0.3rem; vertical-align: middle;"></i>
                                        <small>Approval will send email notification</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="user-actions">
                            <?php if ($customer['email_verified'] && !$customer['admin_approved'] && $customer['account_status'] == 'pending'): ?>
                                <form method="POST" style="margin: 0;">
                                    <input type="hidden" name="user_id" value="<?php echo $customer['id']; ?>">
                                    <button type="submit" name="approve_user" class="btn btn-success">
                                        <i data-lucide="check"></i> Approve
                                    </button>
                                </form>
                                
                                <form method="POST" style="margin: 0;">
                                    <input type="hidden" name="user_id" value="<?php echo $customer['id']; ?>">
                                    <button type="submit" name="reject_user" class="btn btn-danger">
                                        <i data-lucide="x"></i> Reject
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <?php if (!$customer['email_verified'] && $customer['registration_status'] == 'pending'): ?>
                                <form method="POST" style="margin: 0;">
                                    <input type="hidden" name="user_id" value="<?php echo $customer['id']; ?>">
                                    <button type="submit" name="resend_verification" class="btn btn-info">
                                        <i data-lucide="mail"></i> Resend Verification
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <?php if ($customer['admin_approved'] && $customer['account_status'] == 'active'): ?>
                                <form method="POST" style="margin: 0;">
                                    <input type="hidden" name="user_id" value="<?php echo $customer['id']; ?>">
                                    <button type="submit" name="delete_user" class="btn btn-danger" onclick="return confirm('Are you sure you want to mark this user as inactive? The user will no longer be able to login.')">
                                        <i data-lucide="user-x"></i> Mark Inactive
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Initialize Lucide icons
        lucide.createIcons();
        
        // Confirm actions
        const approveForms = document.querySelectorAll('form[action*="approve_user"]');
        approveForms.forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!confirm('Are you sure you want to approve this user? An approval email will be sent.')) {
                    e.preventDefault();
                }
            });
        });
        
        const rejectForms = document.querySelectorAll('form[action*="reject_user"]');
        rejectForms.forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!confirm('Are you sure you want to reject this user? A rejection email will be sent.')) {
                    e.preventDefault();
                }
            });
        });
        
        const resendForms = document.querySelectorAll('form[action*="resend_verification"]');
        resendForms.forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!confirm('Are you sure you want to resend verification email to this user?')) {
                    e.preventDefault();
                }
            });
        });
        
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
    </script>
</body>
</html>