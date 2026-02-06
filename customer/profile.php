<?php
// For customer files:
$base_path = dirname(__DIR__);
require_once $base_path . '/includes/config.php';
require_once $base_path . '/includes/db_connection.php';

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

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    
    // Validate inputs
    $errors = [];
    
    if (empty($full_name)) {
        $errors[] = "Full name is required.";
    }
    
    if (empty($phone)) {
        $errors[] = "Phone number is required.";
    }
    
    if (empty($address)) {
        $errors[] = "Address is required.";
    }
    
    // Check if email is being changed (if provided)
    if (isset($_POST['email']) && !empty($_POST['email'])) {
        $new_email = trim($_POST['email']);
        if ($new_email !== $user['email']) {
            // Check if new email already exists
            $check_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check_stmt->execute([$new_email, $user_id]);
            if ($check_stmt->fetch()) {
                $errors[] = "Email is already registered by another user.";
            } else {
                // Email can be updated
                $email = $new_email;
            }
        }
    }
    
    if (empty($errors)) {
        try {
            // Update user profile
            $update_stmt = $pdo->prepare("UPDATE users SET 
                full_name = ?, 
                phone = ?, 
                address = ?,
                updated_at = NOW()
                WHERE id = ?");
            
            $update_stmt->execute([
                $full_name,
                $phone,
                $address,
                $user_id
            ]);
            
            // Update email if changed
            if (isset($email) && $email !== $user['email']) {
                $email_stmt = $pdo->prepare("UPDATE users SET 
                    email = ?,
                    email_verified = 0,  // Require re-verification if email changed
                    updated_at = NOW()
                    WHERE id = ?");
                $email_stmt->execute([$email, $user_id]);
                
                // Update session email
                $_SESSION['email'] = $email;
                
                $message = "Profile updated successfully! Please verify your new email address.";
            } else {
                $message = "Profile updated successfully!";
            }
            
            $message_type = "success";
            
            // Update session full name
            $_SESSION['full_name'] = $full_name;
            
            // Refresh user data
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
        } catch (PDOException $e) {
            $message = "Error updating profile: " . $e->getMessage();
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
    <title>My Profile - BillPay Pro</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        .profile-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .profile-avatar {
            width: 100px;
            height: 100px;
            background: #075B5E;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: bold;
            border: 4px solid white;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        
        .profile-info h1 {
            margin: 0 0 0.5rem;
            color: #075B5E;
        }
        
        .profile-info p {
            margin: 0;
            color: #666;
        }
        
        .profile-tabs {
            display: flex;
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 2rem;
        }
        
        .profile-tab {
            padding: 1rem 2rem;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            font-weight: 500;
            color: #6c757d;
            transition: all 0.3s;
        }
        
        .profile-tab:hover {
            color: #075B5E;
        }
        
        .profile-tab.active {
            color: #075B5E;
            border-bottom-color: #075B5E;
        }
        
        .profile-content {
            display: none;
            animation: fadeIn 0.5s;
        }
        
        .profile-content.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .profile-card {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
        }
        
        .profile-card h3 {
            color: #075B5E;
            margin-top: 0;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
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
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #075B5E;
            box-shadow: 0 0 0 3px rgba(7, 91, 94, 0.1);
        }
        
        .form-control:disabled {
            background: #f8f9fa;
            cursor: not-allowed;
        }
        
        .btn {
            background: #075B5E;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 5px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn:hover {
            background: #054749;
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn-block {
            width: 100%;
            justify-content: center;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .info-item {
            display: flex;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .info-label {
            min-width: 150px;
            font-weight: 500;
            color: #555;
        }
        
        .info-value {
            flex: 1;
            color: #333;
        }
        
        .verification-status {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .verified {
            background: #d4edda;
            color: #155724;
        }
        
        .not-verified {
            background: #fff3cd;
            color: #856404;
        }
        
        .account-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .stat-box {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #075B5E;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .email-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 1rem;
            margin-top: 1rem;
            color: #856404;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            
            .profile-tabs {
                flex-direction: column;
            }
            
            .profile-tab {
                text-align: center;
            }
            
            .info-item {
                flex-direction: column;
                gap: 0.25rem;
            }
            
            .info-label {
                min-width: auto;
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
        <div class="profile-container">
            <!-- Back Navigation -->
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
                    <p style="margin-top: 0.5rem;">
                        <span class="verification-status <?php echo $user['email_verified'] ? 'verified' : 'not-verified'; ?>">
                            <i data-lucide="<?php echo $user['email_verified'] ? 'check-circle' : 'alert-circle'; ?>" style="width: 16px; height: 16px;"></i>
                            <?php echo $user['email_verified'] ? 'Email Verified' : 'Email Not Verified'; ?>
                        </span>
                        
                        <span class="verification-status <?php echo $user['admin_approved'] ? 'verified' : 'not-verified'; ?>" style="margin-left: 0.5rem;">
                            <i data-lucide="<?php echo $user['admin_approved'] ? 'check-circle' : 'alert-circle'; ?>" style="width: 16px; height: 16px;"></i>
                            <?php echo $user['admin_approved'] ? 'Account Approved' : 'Pending Approval'; ?>
                        </span>
                    </p>
                </div>
            </div>
            
            <!-- Messages -->
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <i data-lucide="<?php echo $message_type == 'success' ? 'check-circle' : 'alert-circle'; ?>" style="width: 1.2rem; height: 1.2rem;"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <!-- Profile Tabs -->
            <div class="profile-tabs">
                <div class="profile-tab active" onclick="showProfileTab('info')">
                    <i data-lucide="user" style="width: 1.2rem; height: 1.2rem; margin-right: 0.5rem; vertical-align: middle;"></i>
                    Personal Info
                </div>
                <div class="profile-tab" onclick="showProfileTab('account')">
                    <i data-lucide="shield" style="width: 1.2rem; height: 1.2rem; margin-right: 0.5rem; vertical-align: middle;"></i>
                    Account Info
                </div>
                <div class="profile-tab" onclick="showProfileTab('security')">
                    <i data-lucide="lock" style="width: 1.2rem; height: 1.2rem; margin-right: 0.5rem; vertical-align: middle;"></i>
                    Security
                </div>
            </div>
            
            <!-- Personal Info Tab -->
            <div id="info-tab" class="profile-content active">
                <form method="POST" action="" class="profile-card">
                    <h3><i data-lucide="user-edit"></i> Edit Personal Information</h3>
                    
                    <div class="form-group">
                        <label for="full_name">Full Name *</label>
                        <input type="text" id="full_name" name="full_name" class="form-control" 
                               value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" class="form-control" 
                               value="<?php echo htmlspecialchars($user['email']); ?>" required
                               <?php echo $user['email_verified'] ? '' : 'disabled'; ?>>
                        <?php if (!$user['email_verified']): ?>
                            <div class="email-warning">
                                <i data-lucide="alert-triangle" style="width: 16px; height: 16px; margin-right: 0.5rem; vertical-align: middle;"></i>
                                Email not verified. Please verify your email before changing it.
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number *</label>
                        <input type="tel" id="phone" name="phone" class="form-control" 
                               value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Address *</label>
                        <textarea id="address" name="address" class="form-control" rows="3" required><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-block">
                        <i data-lucide="save"></i> Save Changes
                    </button>
                </form>
            </div>
            
            <!-- Account Info Tab -->
            <div id="account-tab" class="profile-content">
                <div class="profile-card">
                    <h3><i data-lucide="user-check"></i> Account Information</h3>
                    
                    <div class="info-item">
                        <div class="info-label">Account Status</div>
                        <div class="info-value">
                            <?php if ($user['admin_approved']): ?>
                                <span class="verification-status verified">
                                    <i data-lucide="check-circle" style="width: 16px; height: 16px;"></i>
                                    Active
                                </span>
                            <?php else: ?>
                                <span class="verification-status not-verified">
                                    <i data-lucide="alert-circle" style="width: 16px; height: 16px;"></i>
                                    Pending Admin Approval
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Email Status</div>
                        <div class="info-value">
                            <?php if ($user['email_verified']): ?>
                                <span class="verification-status verified">
                                    <i data-lucide="check-circle" style="width: 16px; height: 16px;"></i>
                                    Verified
                                </span>
                            <?php else: ?>
                                <span class="verification-status not-verified">
                                    <i data-lucide="alert-circle" style="width: 16px; height: 16px;"></i>
                                    Not Verified
                                </span>
                                <a href="verify_email.php" style="margin-left: 1rem; color: #075B5E; text-decoration: none;">
                                    <i data-lucide="mail" style="width: 16px; height: 16px; vertical-align: middle;"></i>
                                    Verify Now
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Customer Code</div>
                        <div class="info-value"><?php echo htmlspecialchars($user['customer_code'] ?? 'N/A'); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Member Since</div>
                        <div class="info-value"><?php echo date('F d, Y', strtotime($user['created_at'])); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Last Login</div>
                        <div class="info-value">
                            <?php if (!empty($user['last_login'])): ?>
                                <?php echo date('F d, Y h:i A', strtotime($user['last_login'])); ?>
                            <?php else: ?>
                                Never logged in
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="account-stats">
                        <div class="stat-box">
                            <div class="stat-value">
                                <?php
                                // Get total bills count
                                try {
                                    $bills_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM bills WHERE customer_id = ?");
                                    $bills_stmt->execute([$user_id]);
                                    $bills_count = $bills_stmt->fetch()['count'];
                                    echo $bills_count;
                                } catch (Exception $e) {
                                    echo "0";
                                }
                                ?>
                            </div>
                            <div class="stat-label">Total Bills</div>
                        </div>
                        
                        <div class="stat-box">
                            <div class="stat-value">
                                <?php
                                // Get pending bills count
                                try {
                                    $pending_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM bills WHERE customer_id = ? AND status IN ('pending', 'due', 'overdue')");
                                    $pending_stmt->execute([$user_id]);
                                    $pending_count = $pending_stmt->fetch()['count'];
                                    echo $pending_count;
                                } catch (Exception $e) {
                                    echo "0";
                                }
                                ?>
                            </div>
                            <div class="stat-label">Pending Bills</div>
                        </div>
                        
                        <div class="stat-box">
                            <div class="stat-value">
                                <?php
                                // Get total payments count
                                try {
                                    $payments_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM payments WHERE user_id = ?");
                                    $payments_stmt->execute([$user_id]);
                                    $payments_count = $payments_stmt->fetch()['count'];
                                    echo $payments_count;
                                } catch (Exception $e) {
                                    echo "0";
                                }
                                ?>
                            </div>
                            <div class="stat-label">Total Payments</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Security Tab -->
            <div id="security-tab" class="profile-content">
                <div class="profile-card">
                    <h3><i data-lucide="shield"></i> Account Security</h3>
                    
                    <div class="info-item">
                        <div class="info-label">Password</div>
                        <div class="info-value">
                            <p>Last changed: 
                                <?php if (!empty($user['updated_at'])): ?>
                                    <?php echo date('F d, Y', strtotime($user['updated_at'])); ?>
                                <?php else: ?>
                                    Never changed
                                <?php endif; ?>
                            </p>
                            <a href="change_password.php" class="btn" style="margin-top: 0.5rem;">
                                <i data-lucide="key"></i> Change Password
                            </a>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Two-Factor Authentication</div>
                        <div class="info-value">
                            <p>Not enabled</p>
                            <button class="btn btn-secondary" style="margin-top: 0.5rem;" disabled>
                                <i data-lucide="smartphone"></i> Enable 2FA
                            </button>
                            <p style="margin-top: 0.5rem; font-size: 0.85rem; color: #666;">
                                <i data-lucide="info" style="width: 16px; height: 16px; vertical-align: middle;"></i>
                                Two-factor authentication adds an extra layer of security to your account.
                            </p>
                        </div>
                    </div>
                    
                    <div class="info-item" style="border-bottom: none;">
                        <div class="info-label">Sessions</div>
                        <div class="info-value">
                            <p>Current session started: <?php echo date('F d, Y h:i A', $_SESSION['last_activity'] ?? time()); ?></p>
                            <a href="logout.php" class="btn btn-secondary" style="margin-top: 0.5rem;">
                                <i data-lucide="log-out"></i> Logout All Devices
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="profile-card" style="border: 2px solid #f8d7da;">
                    <h3 style="color: #721c24;"><i data-lucide="alert-triangle"></i> Danger Zone</h3>
                    
                    <div class="info-item">
                        <div class="info-label">Delete Account</div>
                        <div class="info-value">
                            <p>Permanently delete your account and all associated data.</p>
                            <p style="font-size: 0.85rem; color: #666; margin-bottom: 1rem;">
                                <i data-lucide="alert-circle" style="width: 16px; height: 16px; vertical-align: middle;"></i>
                                This action cannot be undone. All your data will be permanently removed.
                            </p>
                            <a href="delete_account.php" class="btn" style="background: #e74c3c;">
                                <i data-lucide="trash-2"></i> Delete My Account
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> BillPay Pro - Online Billing System</p>
        </div>
    </footer>

    <script>
        lucide.createIcons();
        
        function showProfileTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.profile-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all tab buttons
            document.querySelectorAll('.profile-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Add active class to clicked tab button
            event.target.classList.add('active');
        }
        
        // Phone number validation
        document.getElementById('phone').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9+-\s]/g, '');
        });
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const phone = document.getElementById('phone').value;
            const address = document.getElementById('address').value;
            
            if (phone.length < 10) {
                e.preventDefault();
                alert('Please enter a valid phone number (at least 10 digits).');
                document.getElementById('phone').focus();
                return false;
            }
            
            if (address.trim().length < 10) {
                e.preventDefault();
                alert('Please enter a complete address (at least 10 characters).');
                document.getElementById('address').focus();
                return false;
            }
            
            return true;
        });
        
        // Auto-save feature (optional)
        let saveTimeout;
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('input', function() {
                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(() => {
                    // You could implement auto-save here if needed
                }, 2000);
            });
        });
    </script>
    <script src="js/logout.js"></script>
</body>
</html>