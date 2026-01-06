<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/db_connection.php';

// Redirect if not admin
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header("Location: ../customer/login.php");
    exit;
}

// Get pending approvals count for the badge
$stmt = $pdo->prepare("SELECT COUNT(*) as pending_approvals FROM users WHERE is_admin = FALSE AND email_verified = 1 AND admin_approved = 0 AND registration_status = 'verified'");
$stmt->execute();
$pending_approvals_count = $stmt->fetch()['pending_approvals'];

// Get ONLY users pending approval
$stmt = $pdo->prepare("SELECT * FROM users WHERE is_admin = FALSE AND email_verified = 1 AND admin_approved = 0 AND registration_status = 'verified' ORDER BY created_at DESC");
$stmt->execute();
$pending_users = $stmt->fetchAll();

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_selected'])) {
        if (!empty($_POST['selected_users'])) {
            $approved_count = 0;
            foreach ($_POST['selected_users'] as $user_id) {
                $stmt = $pdo->prepare("UPDATE users SET admin_approved = 1, registration_status = 'approved', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$user_id]);
                
                // Create notification for user
                $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, created_at) VALUES (?, 'Account Approved', 'Your account has been approved by the administrator. You can now access all features.', 'account', NOW())");
                $stmt->execute([$user_id]);
                
                $approved_count++;
            }
            
            $_SESSION['success_message'] = "Approved $approved_count user(s) successfully!";
        }
    }
    
    if (isset($_POST['reject_selected'])) {
        if (!empty($_POST['selected_users'])) {
            $rejected_count = 0;
            foreach ($_POST['selected_users'] as $user_id) {
                $stmt = $pdo->prepare("UPDATE users SET admin_approved = 0, registration_status = 'rejected', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$user_id]);
                
                // Create notification for user
                $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, created_at) VALUES (?, 'Account Rejected', 'Your account registration has been rejected by the administrator. Please contact support for more information.', 'account', NOW())");
                $stmt->execute([$user_id]);
                
                $rejected_count++;
            }
            
            $_SESSION['success_message'] = "Rejected $rejected_count user(s) successfully!";
        }
    }
    
    if (isset($_POST['approve_user'])) {
        $user_id = $_POST['user_id'];
        $stmt = $pdo->prepare("UPDATE users SET admin_approved = 1, registration_status = 'approved', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$user_id]);
        
        // Create notification for user
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, created_at) VALUES (?, 'Account Approved', 'Your account has been approved by the administrator. You can now access all features.', 'account', NOW())");
        $stmt->execute([$user_id]);
        
        $_SESSION['success_message'] = "User approved successfully!";
        header("Location: approve_users.php");
        exit;
    }
    
    if (isset($_POST['reject_user'])) {
        $user_id = $_POST['user_id'];
        $stmt = $pdo->prepare("UPDATE users SET admin_approved = 0, registration_status = 'rejected', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$user_id]);
        
        // Create notification for user
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, created_at) VALUES (?, 'Account Rejected', 'Your account registration has been rejected by the administrator. Please contact support for more information.', 'account', NOW())");
        $stmt->execute([$user_id]);
        
        $_SESSION['success_message'] = "User rejected successfully!";
        header("Location: approve_users.php");
        exit;
    }
    
    // Refresh page to show updated list
    header("Location: approve_users.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approve Users - Online Service Billing System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .approve-container {
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
        
        .bulk-actions {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .bulk-checkbox {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .bulk-checkbox input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }
        
        .bulk-buttons {
            display: flex;
            gap: 10px;
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
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
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
        
        .user-info {
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
        
        .card-checkbox {
            position: absolute;
            top: 15px;
            right: 15px;
            width: 20px;
            height: 20px;
            z-index: 10;
        }
        
        .user-card {
            position: relative;
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
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="approve-container">
        <div class="header-section">
            <h1>
                <i data-lucide="user-check"></i>
                Approve Users
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
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>
        
        <!-- Bulk Actions -->
        <?php if (!empty($pending_users)): ?>
        <form method="POST" class="bulk-actions">
            <div class="bulk-checkbox">
                <input type="checkbox" id="select-all" onchange="toggleAllCheckboxes(this)">
                <label for="select-all"><strong>Select All</strong></label>
            </div>
            <div class="bulk-buttons">
                <button type="submit" name="approve_selected" class="btn btn-success" onclick="return confirmBulkAction('approve')">
                    <i data-lucide="check"></i> Approve Selected
                </button>
                <button type="submit" name="reject_selected" class="btn btn-danger" onclick="return confirmBulkAction('reject')">
                    <i data-lucide="x"></i> Reject Selected
                </button>
            </div>
        </form>
        <?php endif; ?>
        
        <!-- Pending Users List -->
        <div class="users-grid">
            <?php if (empty($pending_users)): ?>
                <div class="no-results">
                    <i data-lucide="users" style="width: 64px; height: 64px; opacity: 0.5; margin-bottom: 20px;"></i>
                    <h3>No Pending Approvals</h3>
                    <p>All users are currently approved. Check back later for new registrations.</p>
                    <a href="manage_users.php" class="btn" style="margin-top: 15px;">
                        <i data-lucide="users"></i> View All Users
                    </a>
                </div>
            <?php else: ?>
                <form method="POST" id="users-form">
                    <?php foreach ($pending_users as $customer): ?>
                        <div class="user-card">
                            <input type="checkbox" name="selected_users[]" value="<?php echo $customer['id']; ?>" class="card-checkbox" id="user_<?php echo $customer['id']; ?>">
                            
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
                                <div class="user-info">
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
                                        <?php if ($customer['phone_verified']): ?>
                                            <i data-lucide="check-circle" style="width: 1rem; height: 1rem; color: #27ae60; margin-left: 0.3rem; vertical-align: middle;"></i>
                                        <?php endif; ?>
                                    </p>
                                    <p>
                                        <i data-lucide="home" class="status-icon"></i>
                                        <strong>Address:</strong> <?php echo htmlspecialchars($customer['address'] ?? 'Not provided'); ?>
                                    </p>
                                    <p>
                                        <i data-lucide="mail-check" class="status-icon"></i>
                                        <strong>Email:</strong> 
                                        <span style="color: #27ae60;">Verified</span>
                                        <i data-lucide="check-circle" style="width: 1rem; height: 1rem; color: #27ae60; margin-left: 0.3rem; vertical-align: middle;"></i>
                                    </p>
                                    <p>
                                        <i data-lucide="shield" class="status-icon"></i>
                                        <strong>Status:</strong> 
                                        <span style="color: #f39c12; font-weight: 500;">
                                            <i data-lucide="clock" style="width: 1rem; height: 1rem; color: #f39c12; margin-right: 0.3rem; vertical-align: middle;"></i>
                                            Awaiting Admin Approval
                                        </span>
                                    </p>
                                </div>
                            </div>
                            
                            <div class="user-actions">
                                <form method="POST" style="margin: 0;">
                                    <input type="hidden" name="user_id" value="<?php echo $customer['id']; ?>">
                                    <button type="submit" name="approve_user" class="btn btn-success">
                                        <i data-lucide="check"></i> Approve
                                    </button>
                                </form>
                                
                                <form method="POST" style="margin: 0;">
                                    <input type="hidden" name="user_id" value="<?php echo $customer['id']; ?>">
                                    <button type="submit" name="reject_user" class="btn btn-danger" onclick="return confirm('Are you sure you want to reject this user?')">
                                        <i data-lucide="x"></i> Reject
                                    </button>
                                </form>
                                
                                <a href="view_user_details.php?id=<?php echo $customer['id']; ?>" class="btn btn-secondary">
                                    <i data-lucide="eye"></i> View Details
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </form>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <script>
        // Initialize Lucide icons
        lucide.createIcons();
        
        // Toggle all checkboxes
        function toggleAllCheckboxes(source) {
            const checkboxes = document.querySelectorAll('input[name="selected_users[]"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = source.checked;
            });
        }
        
        // Confirm bulk actions
        function confirmBulkAction(action) {
            const checkboxes = document.querySelectorAll('input[name="selected_users[]"]:checked');
            if (checkboxes.length === 0) {
                alert('Please select at least one user to ' + action + '.');
                return false;
            }
            
            const userCount = checkboxes.length;
            const actionText = action === 'approve' ? 'approve' : 'reject';
            return confirm(`Are you sure you want to ${actionText} ${userCount} user(s)?`);
        }
        
        // Auto-refresh the page every 30 seconds to check for new pending approvals
        setTimeout(function() {
            const checkboxes = document.querySelectorAll('input[name="selected_users[]"]:checked');
            // Only refresh if no users are selected (to avoid interrupting bulk actions)
            if (checkboxes.length === 0) {
                location.reload();
            }
        }, 30000); // 30 seconds
    </script>
</body>
</html>