<?php
// For customer files:
$base_path = dirname(__DIR__);
require_once $base_path . '/includes/config.php';
require_once $base_path . '/includes/db_connection.php';
require_once $base_path . '/includes/email_functions.php';

$page_title = "Customer Registration";
$hide_sidebar = true;
$hide_footer = true;

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']) {
        header("Location: ../admin/index.php");
        exit;
    } else {
        header("Location: dashboard.php");
        exit;
    }
}

$errors = [];
$success = false;
$form_data = [];

// Generate username from email
function generate_username($email) {
    $username = strtok($email, '@');
    $username = preg_replace('/[^a-zA-Z0-9]/', '', $username);
    
    // Check if username already exists and append number if needed
    global $pdo;
    $base_username = $username;
    $counter = 1;
    
    while (true) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        
        if (!$stmt->fetch()) {
            return $username;
        }
        
        $username = $base_username . $counter;
        $counter++;
        
        if ($counter > 100) {
            return $base_username . time();
        }
    }
}

// Process registration form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input
    $form_data = array_map(function($value) {
        return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    }, $_POST);
    
    // Validate required fields
    $required_fields = ['full_name', 'email', 'phone', 'password', 'confirm_password', 'address'];
    
    foreach ($required_fields as $field) {
        if (empty($form_data[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . " is required.";
        }
    }
    
    // Validate email
    if (!empty($form_data['email']) && !filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }
    
    // Check if email already exists AND account is active
    if (empty($errors) && !empty($form_data['email'])) {
        try {
            $stmt = $pdo->prepare("SELECT id, email_verified, admin_approved, deleted_at FROM users WHERE email = ?");
            $stmt->execute([$form_data['email']]);
            $existing_user = $stmt->fetch();
            
            if ($existing_user) {
                // Check if account is deactivated (has deletion timestamp)
                if (empty($existing_user['deleted_at'])) {
                    // Account exists and is NOT deactivated - check if active
                    if ($existing_user['email_verified'] && $existing_user['admin_approved']) {
                        $errors[] = "This email is already registered with an active account. Please use a different email or try to login.";
                    } else {
                        $errors[] = "This email is already registered but not fully activated. Please check your email for verification or wait for admin approval.";
                    }
                }
                // If account has deleted_at timestamp, allow re-registration (continue)
            }
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
    
    // Validate password
    if (!empty($form_data['password']) && !empty($form_data['confirm_password'])) {
        if (strlen($form_data['password']) < 6) {
            $errors[] = "Password must be at least 6 characters long.";
        }
        
        if ($form_data['password'] !== $form_data['confirm_password']) {
            $errors[] = "Passwords do not match.";
        }
    }
    
    // Validate terms agreement
    if (!isset($form_data['terms'])) {
        $errors[] = "You must agree to the Terms and Conditions.";
    }
    
    // If no errors, proceed with registration
    if (empty($errors)) {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Check if email exists as deactivated account
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND deleted_at IS NOT NULL");
            $stmt->execute([$form_data['email']]);
            $deactivated_user = $stmt->fetch();
            
            if ($deactivated_user) {
                // UPDATE existing deactivated user instead of inserting new
                $user_id = $deactivated_user['id'];
                
                // Generate username from email
                $username = generate_username($form_data['email']);
                
                // Hash password
                $hashed_password = password_hash($form_data['password'], PASSWORD_DEFAULT);
                
                // Generate OTP
                $otp = rand(100000, 999999);
                $otp_expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                
                // Update the deactivated user with new information
                $update_stmt = $pdo->prepare("
                    UPDATE users SET 
                        username = ?,
                        full_name = ?,
                        password = ?,
                        phone = ?,
                        address = ?,
                        user_type = 'customer',
                        account_status = 'pending',
                        email_verified = 0,
                        approved_by_admin = 0,
                        admin_approved = 0,
                        registration_status = 'pending',
                        is_admin = 0,
                        is_active = 1,
                        deleted_at = NULL,
                        deletion_reason = NULL,
                        updated_at = NOW(),
                        created_at = NOW()
                    WHERE id = ?
                ");
                
                $update_stmt->execute([
                    $username,
                    $form_data['full_name'],
                    $hashed_password,
                    $form_data['phone'],
                    $form_data['address'],
                    $user_id
                ]);
                
                // Also delete any old OTP records for this email
                $delete_otp_stmt = $pdo->prepare("DELETE FROM verification_otps WHERE email = ?");
                $delete_otp_stmt->execute([$form_data['email']]);
                
            } else {
                // INSERT new user (normal registration)
                $username = generate_username($form_data['email']);
                
                // Hash password
                $hashed_password = password_hash($form_data['password'], PASSWORD_DEFAULT);
                
                // Generate OTP
                $otp = rand(100000, 999999);
                $otp_expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                
                // Insert user data
                $insert_stmt = $pdo->prepare("
                    INSERT INTO users (
                        username, full_name, email, password, phone, address, 
                        user_type, account_status, email_verified, 
                        approved_by_admin, admin_approved, registration_status,
                        is_admin, is_active, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, 'customer', 
                             'pending', 0, 0, 0, 'pending', 0, 1, NOW())
                ");
                
                $insert_stmt->execute([
                    $username,
                    $form_data['full_name'],
                    $form_data['email'],
                    $hashed_password,
                    $form_data['phone'],
                    $form_data['address']
                ]);
                
                $user_id = $pdo->lastInsertId();
            }
            
            // Store OTP in verification_otps table
            $otp_stmt = $pdo->prepare("
                INSERT INTO verification_otps (email, otp, type, expires_at) 
                VALUES (?, ?, 'registration', ?)
                ON DUPLICATE KEY UPDATE 
                    otp = VALUES(otp),
                    expires_at = VALUES(expires_at),
                    created_at = NOW()
            ");
            $otp_stmt->execute([$form_data['email'], $otp, $otp_expiry]);
            
            // Send OTP email using your existing function
            $email_sent = sendOTPEmail($form_data['email'], $form_data['full_name'], $otp);
            
            if (!$email_sent) {
                throw new Exception("Failed to send OTP email. Please try again.");
            }
            
            // Commit transaction
            $pdo->commit();
            
            // Store in session for OTP verification
            $_SESSION['temp_user_id'] = $user_id;
            $_SESSION['temp_email'] = $form_data['email'];
            $_SESSION['temp_full_name'] = $form_data['full_name'];
            
            // Redirect to OTP verification
            header("Location: verify_otp.php");
            exit();
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Registration failed: " . $e->getMessage();
            error_log("Registration error: " . $e->getMessage());
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = $e->getMessage();
            error_log("Registration error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Registration - BillPay Pro</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        .register-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .register-header h1 {
            color: #075B5E;
            margin-bottom: 0.5rem;
        }
        
        .register-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .card-header {
            background: #075B5E;
            color: white;
            padding: 1.5rem;
            text-align: center;
        }
        
        .card-body {
            padding: 2rem;
        }
        
        .form-row {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .form-group {
            flex: 1;
            margin-bottom: 1rem;
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
            border-color: #075B5E;
            outline: none;
            box-shadow: 0 0 0 3px rgba(7, 91, 94, 0.1);
        }
        
        .required::after {
            content: " *";
            color: #e74c3c;
        }
        
        .btn {
            background: #075B5E;
            color: white;
            border: none;
            padding: 1rem 2rem;
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
            background: #0a7c80;
        }
        
        .btn-block {
            width: 100%;
            justify-content: center;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .registration-process {
            background: #f0f7f7;
            padding: 1.5rem;
            border-radius: 5px;
            margin-top: 2rem;
        }
        
        .process-steps {
            list-style: none;
            padding: 0;
        }
        
        .process-steps li {
            margin-bottom: 1rem;
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
        }
        
        .process-steps li i {
            color: #075B5E;
            margin-top: 0.25rem;
        }
        
        .login-link {
            text-align: center;
            margin-top: 1rem;
            color: #666;
        }
        
        .login-link a {
            color: #075B5E;
            text-decoration: none;
            font-weight: 500;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        .terms-checkbox {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            margin: 1.5rem 0;
        }
        
        .terms-checkbox input[type="checkbox"] {
            margin-top: 0.25rem;
        }
        
        .terms-checkbox label {
            margin: 0;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <div class="logo">BillPay Pro</div>
                <ul class="nav-links">
                    <li><a href="../index.php">Home</a></li>
                    <li><a href="login.php">Login</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="container">
        <div class="register-container">
            <!-- Header -->
            <div class="register-header">
                <h1><i data-lucide="user-plus"></i> Customer Registration</h1>
                <p>Create your account to start using BillPay Pro</p>
            </div>
            
            <!-- Messages -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <i data-lucide="alert-circle" style="width: 1.2rem; height: 1.2rem; margin-right: 0.5rem; vertical-align: middle;"></i>
                    <strong>Please fix the following errors:</strong>
                    <ul style="margin: 0.5rem 0 0 1.5rem;">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i data-lucide="check-circle" style="width: 1.2rem; height: 1.2rem; margin-right: 0.5rem; vertical-align: middle;"></i>
                    <strong>Registration Successful!</strong>
                    <p>Your account has been created. Please check your email for the OTP to verify your account.</p>
                </div>
            <?php endif; ?>
            
            <!-- Registration Form -->
            <div class="register-card">
                <div class="card-header">
                    <h2 style="margin: 0;">Create Your Account</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="full_name" class="required">Full Name</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" 
                                       value="<?php echo $form_data['full_name'] ?? ''; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="email" class="required">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo $form_data['email'] ?? ''; ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="phone" class="required">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?php echo $form_data['phone'] ?? ''; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="password" class="required">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <small style="color: #666;">Minimum 6 characters</small>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="confirm_password" class="required">Confirm Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="address" class="required">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="2" required><?php echo $form_data['address'] ?? ''; ?></textarea>
                        </div>
                        
                        <div class="terms-checkbox">
                            <input type="checkbox" id="terms" name="terms" required>
                            <label for="terms">
                                I agree to the <a href="<?php echo SITE_URL; ?>terms.php" target="_blank">Terms and Conditions</a> and 
                                <a href="<?php echo SITE_URL; ?>privacy.php" target="_blank">Privacy Policy</a>
                            </label>
                        </div>
                        
                        <button type="submit" class="btn btn-block">
                            <i data-lucide="user-plus"></i> Create Account
                        </button>
                    </form>
                    
                    <div class="login-link">
                        <p>Already have an account? <a href="login.php">Login here</a></p>
                    </div>
                </div>
            </div>
            
            <!-- Registration Process Info -->
            <div class="registration-process">
                <h3><i data-lucide="info"></i> Registration Process</h3>
                <ul class="process-steps">
                    <li>
                        <i data-lucide="check-circle"></i>
                        <div>
                            <strong>Step 1: Fill Registration Form</strong>
                            <p>Provide your details in the form above</p>
                        </div>
                    </li>
                    <li>
                        <i data-lucide="mail"></i>
                        <div>
                            <strong>Step 2: Verify Email</strong>
                            <p>Check your email for OTP and verify your account</p>
                        </div>
                    </li>
                    <li>
                        <i data-lucide="shield-check"></i>
                        <div>
                            <strong>Step 3: Admin Approval</strong>
                            <p>Wait for admin to approve your account (usually within 24 hours)</p>
                        </div>
                    </li>
                    <li>
                        <i data-lucide="log-in"></i>
                        <div>
                            <strong>Step 4: Login & Access</strong>
                            <p>Once approved, login and access your dashboard</p>
                        </div>
                    </li>
                </ul>
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
        
        // Password strength indicator
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            const confirmPassword = confirmPasswordInput.value;
            
            // Check if passwords match
            if (confirmPassword && password !== confirmPassword) {
                confirmPasswordInput.style.borderColor = '#e74c3c';
            } else if (confirmPassword) {
                confirmPasswordInput.style.borderColor = '#27ae60';
            }
        });
        
        confirmPasswordInput.addEventListener('input', function() {
            const password = passwordInput.value;
            const confirmPassword = this.value;
            
            if (password && confirmPassword && password !== confirmPassword) {
                this.style.borderColor = '#e74c3c';
            } else if (password && confirmPassword) {
                this.style.borderColor = '#27ae60';
            } else {
                this.style.borderColor = '#ddd';
            }
        });
        
        // Form submission validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            
            if (password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long.');
                passwordInput.focus();
                return false;
            }
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match. Please check and try again.');
                confirmPasswordInput.focus();
                return false;
            }
            
            return true;
        });
    </script>
</body>
</html>