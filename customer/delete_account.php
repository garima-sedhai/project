<?php
session_start();
include '../includes/config.php';

// Redirect if not logged in as customer
if (!isset($_SESSION['user_id']) || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Get current user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION['error'] = "User not found.";
    header("Location: dashboard.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $password = $_POST['password'];
    $confirm_text = $_POST['confirm_text'];
    $reason = trim($_POST['reason']);
    
    // Validate inputs
    $errors = [];
    
    // Verify password
    if (!password_verify($password, $user['password'])) {
        $errors[] = "Password is incorrect.";
    }
    
    // Verify confirmation text
    if ($confirm_text !== 'DELETE MY ACCOUNT') {
        $errors[] = "Please type 'DELETE MY ACCOUNT' exactly as shown to confirm.";
    }
    
    // Check for pending bills
    $stmt = $pdo->prepare("SELECT COUNT(*) as pending_count FROM bills WHERE user_id = ? AND status = 'pending'");
    $stmt->execute([$user_id]);
    $pending_bills = $stmt->fetch();
    
    if ($pending_bills['pending_count'] > 0) {
        $errors[] = "You cannot delete your account while you have pending bills. Please pay or cancel them first.";
    }
    
    if (empty($errors)) {
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Archive user data (optional - depends on your data retention policy)
            $archive_stmt = $pdo->prepare("INSERT INTO deleted_users_archive 
                                          SELECT *, NOW() as deleted_at, ? as deletion_reason 
                                          FROM users WHERE id = ?");
            $archive_stmt->execute([$reason, $user_id]);
            
            // Delete user's bills and payments (if cascade not set up)
            $delete_payments = $pdo->prepare("DELETE FROM payments WHERE user_id = ?");
            $delete_payments->execute([$user_id]);
            
            $delete_bills = $pdo->prepare("DELETE FROM bills WHERE user_id = ?");
            $delete_bills->execute([$user_id]);
            
            // Delete the user
            $delete_user = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $delete_user->execute([$user_id]);
            
            // Commit transaction
            $pdo->commit();
            
            // Clear session and redirect to login
            session_destroy();
            header("Location: ../index.php?message=account_deleted");
            exit();
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = "Error deleting account: " . $e->getMessage();
            $message_type = "error";
        }
    } else {
        $message = implode("<br>", $errors);
        $message_type = "error";
    }
}

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
    <title>Delete Account - BillPay Pro</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        .delete-container {
            max-width: 600px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .delete-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .warning-icon {
            width: 80px;
            height: 80px;
            background: #f8d7da;
            color: #721c24;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 1rem;
        }
        
        .delete-form {
            background: white;
            border-radius: 8px;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border: 2px solid #f8d7da;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #333;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #075B5E;
            box-shadow: 0 0 0 3px rgba(7, 91, 94, 0.1);
        }
        
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 4px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .warning-box h4 {
            color: #856404;
            margin: 0 0 0.5rem 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .danger-box {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .danger-box h4 {
            color: #721c24;
            margin: 0 0 0.5rem 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .consequences-list {
            color: #721c24;
            padding-left: 1.5rem;
            margin: 0;
        }
        
        .consequences-list li {
            margin-bottom: 0.5rem;
        }
        
        .confirm-text {
            font-family: monospace;
            background: #f8f9fa;
            padding: 0.5rem;
            border-radius: 4px;
            border: 1px solid #ddd;
            font-weight: bold;
            color: #721c24;
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #eee;
        }
        
        .btn-danger {
            background: #e74c3c;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            transition: background 0.3s;
            flex: 1;
        }
        
        .btn-danger:hover {
            background: #c0392b;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: background 0.3s;
            flex: 1;
            justify-content: center;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .account-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #eee;
        }
        
        .info-label {
            font-weight: 500;
            color: #666;
        }
        
        .info-value {
            font-weight: 500;
        }
        
        @media (max-width: 768px) {
            .form-actions {
                flex-direction: column;
            }
            
            .delete-container {
                padding: 0 0.5rem;
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
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="bills.php">My Bills</a></li>
                    <li><a href="payment_history.php">Payment History</a></li>
                    <li style="display: flex; align-items: center;">
                        <div class="profile-menu-trigger" onclick="window.location.href='profile.php'">
                            <div class="first-letter-circle">
                                <?php echo $first_letter; ?>
                            </div>
                            <div class="user-name-display">
                                <span><?php echo $_SESSION['full_name']; ?></span>
                            </div>
                        </div>
                        <a href="logout.php" style="margin-left: 15px;">Logout</a>
                    </li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="container">
        <div class="delete-container">
            <!-- Back Navigation - UPDATED: Now links to dashboard.php -->
            <div style="margin-bottom: 1.5rem;">
                <a href="dashboard.php" style="color: #075B5E; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem;">
                    <i data-lucide="arrow-left"></i>
                    Back to Dashboard
                </a>
            </div>
            
            <!-- Delete Header -->
            <div class="delete-header">
                <div class="warning-icon">
                    <i data-lucide="alert-triangle"></i>
                </div>
                <h1 style="color: #721c24;">Delete Account</h1>
                <p style="color: #666;">This action cannot be undone</p>
            </div>
            
            <!-- Messages -->
            <?php if ($message): ?>
                <div class="alert alert-error">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <!-- Account Information -->
            <div class="account-info">
                <h3 style="color: #075B5E; margin-bottom: 1rem;">Account Information</h3>
                <div class="info-item">
                    <span class="info-label">Name:</span>
                    <span class="info-value"><?php echo htmlspecialchars($user['full_name']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Email:</span>
                    <span class="info-value"><?php echo htmlspecialchars($user['email']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Customer Code:</span>
                    <span class="info-value"><?php echo htmlspecialchars($user['customer_code'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Member Since:</span>
                    <span class="info-value"><?php echo date('F d, Y', strtotime($user['created_at'])); ?></span>
                </div>
            </div>
            
            <!-- Warning Box -->
            <div class="danger-box">
                <h4><i data-lucide="alert-circle"></i> Permanent Account Deletion</h4>
                <p>You are about to permanently delete your BillPay Pro account. Please read the following carefully:</p>
                <ul class="consequences-list">
                    <li>All your personal information will be permanently removed</li>
                    <li>Your bill history and payment records will be deleted</li>
                    <li>You will lose access to all services immediately</li>
                    <li>This action cannot be undone or recovered</li>
                    <li>Any pending bills must be settled before deletion</li>
                </ul>
            </div>
            
            <!-- Delete Account Form -->
            <form method="POST" action="" class="delete-form" id="deleteForm">
                <div class="form-group">
                    <label for="password">Enter Your Password *</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="confirm_text">
                        Type <span class="confirm-text">DELETE MY ACCOUNT</span> to confirm *
                    </label>
                    <input type="text" id="confirm_text" name="confirm_text" class="form-control" required
                           placeholder="Type exactly as shown above">
                </div>
                
                <div class="form-group">
                    <label for="reason">Reason for Leaving (Optional)</label>
                    <select id="reason" name="reason" class="form-control">
                        <option value="">Select a reason...</option>
                        <option value="found_better_service">Found a better service</option>
                        <option value="too_expensive">Too expensive</option>
                        <option value="missing_features">Missing features I need</option>
                        <option value="technical_issues">Technical issues</option>
                        <option value="customer_service">Customer service issues</option>
                        <option value="no_longer_need">No longer need the service</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                
                <div class="warning-box">
                    <h4><i data-lucide="help-circle"></i> Before You Go...</h4>
                    <p>If you're having issues, consider these alternatives instead of deleting your account:</p>
                    <ul style="padding-left: 1.5rem; margin: 0.5rem 0; color: #666;">
                        <li><a href="settings.php" style="color: #075B5E;">Update your settings</a></li>
                        <li><a href="help.php" style="color: #075B5E;">Contact support for help</a></li>
                        <li><a href="edit_profile.php" style="color: #075B5E;">Update your profile information</a></li>
                    </ul>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-danger" onclick="return confirmDelete()">
                        <i data-lucide="trash-2"></i> Permanently Delete Account
                    </button>
                    <a href="dashboard.php" class="btn-secondary">
                        <i data-lucide="x"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> BillPay Pro - Online Billing System</p>
        </div>
    </footer>

    <script>
        lucide.createIcons();
        
        function confirmDelete() {
            const confirmText = document.getElementById('confirm_text').value;
            const password = document.getElementById('password').value;
            
            if (confirmText !== 'DELETE MY ACCOUNT') {
                alert('Please type "DELETE MY ACCOUNT" exactly as shown to confirm deletion.');
                return false;
            }
            
            if (!password) {
                alert('Please enter your password to confirm deletion.');
                return false;
            }
            
            return confirm('⚠️ FINAL WARNING: This will permanently delete your account and all associated data. This action cannot be undone. Are you absolutely sure?');
        }
        
        // Form validation
        document.getElementById('deleteForm').addEventListener('submit', function(e) {
            if (!confirmDelete()) {
                e.preventDefault();
                return false;
            }
            return true;
        });
    </script>
</body>
</html>