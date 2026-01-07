<?php
// REMOVE THIS LINE: session_start();
require_once '../includes/config.php';
require_once '../includes/db_connection.php';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['is_admin']) {
        header("Location: ../admin/index.php");
    } else {
        header("Location: dashboard.php");
    }
    exit();
}
// ... rest of your register.php code

$error = '';
$success = '';
$step = isset($_GET['step']) ? $_GET['step'] : 1;
$email = isset($_SESSION['register_email']) ? $_SESSION['register_email'] : '';

// Handle registration step 1: Basic info
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register_step1'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $address = trim($_POST['address']);
    
    // Validation
    if (empty($full_name) || empty($email) || empty($phone) || empty($password)) {
        $error = "Please fill all required fields";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long";
    } else {
        try {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = "Email already registered. Please login or use a different email.";
            } else {
                // Generate verification code
                $verification_code = rand(100000, 999999);
                $verification_expiry = date('Y-m-d H:i:s', time() + 600); // 10 minutes expiry
                
                // Generate customer code
                $customer_code = 'CUST' . date('Ymd') . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
                
                // Save to session for next step
                $_SESSION['register_data'] = [
                    'full_name' => $full_name,
                    'email' => $email,
                    'phone' => $phone,
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'address' => $address,
                    'verification_code' => $verification_code,
                    'verification_expiry' => $verification_expiry,
                    'customer_code' => $customer_code
                ];
                $_SESSION['register_email'] = $email;
                
                // Send verification email
                $subject = "Verify Your Email - BillPay Pro";
                $message = "
                    <html>
                    <head>
                        <style>
                            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                            .header { background: #075B5E; color: white; padding: 20px; text-align: center; }
                            .content { background: #f9f9f9; padding: 20px; }
                            .verification-code { 
                                background: #075B5E; 
                                color: white; 
                                padding: 15px; 
                                text-align: center; 
                                font-size: 24px; 
                                font-weight: bold; 
                                letter-spacing: 5px;
                                margin: 20px 0;
                                border-radius: 5px;
                            }
                            .footer { background: #eee; padding: 10px; text-align: center; font-size: 12px; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h2>BillPay Pro</h2>
                            </div>
                            <div class='content'>
                                <h3>Dear $full_name,</h3>
                                <p>Thank you for registering with BillPay Pro!</p>
                                <p>Your verification code is:</p>
                                <div class='verification-code'>$verification_code</div>
                                <p>Enter this code on the verification page to complete your registration.</p>
                                <p><strong>Note:</strong> This code will expire in 10 minutes.</p>
                                <p>If you didn't create an account with us, please ignore this email.</p>
                            </div>
                            <div class='footer'>
                                <p>This is an automated message from BillPay Pro System.</p>
                            </div>
                        </div>
                    </body>
                    </html>
                ";
                
                $headers = "MIME-Version: 1.0" . "\r\n";
                $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                $headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">" . "\r\n";
                $headers .= "Reply-To: " . SMTP_FROM_EMAIL . "\r\n";
                
                if (mail($email, $subject, $message, $headers)) {
                    // Redirect to verification step
                    header("Location: register.php?step=2");
                    exit();
                } else {
                    $error = "Failed to send verification email. Please try again.";
                }
            }
        } catch (Exception $e) {
            $error = "Registration error. Please try again.";
            error_log("Registration error: " . $e->getMessage());
        }
    }
}

// Handle verification step
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verify_email'])) {
    $entered_code = trim($_POST['verification_code']);
    $register_data = $_SESSION['register_data'] ?? null;
    
    if (!$register_data) {
        $error = "Session expired. Please start registration again.";
        $step = 1;
    } elseif (empty($entered_code)) {
        $error = "Please enter verification code";
    } elseif ($entered_code != $register_data['verification_code']) {
        $error = "Invalid verification code";
    } elseif (strtotime($register_data['verification_expiry']) < time()) {
        $error = "Verification code has expired. Please register again.";
        unset($_SESSION['register_data'], $_SESSION['register_email']);
        $step = 1;
    } else {
        try {
            // Create user in database
            $stmt = $pdo->prepare("INSERT INTO users (
                full_name, email, phone, password, address, customer_code,
                email_verified, verification_code, verification_expiry,
                admin_approved, registration_status, is_admin, is_active
            ) VALUES (?, ?, ?, ?, ?, ?, TRUE, NULL, NULL, FALSE, 'verified', FALSE, TRUE)");
            
            $success = $stmt->execute([
                $register_data['full_name'],
                $register_data['email'],
                $register_data['phone'],
                $register_data['password'],
                $register_data['address'],
                $register_data['customer_code']
            ]);
            
            if ($success) {
                $user_id = $pdo->lastInsertId();
                
                // Send notification to admin
                $admin_message = "New user registered: " . $register_data['full_name'] . " (" . $register_data['email'] . "). Please review and approve.";
                $stmt = $pdo->prepare("INSERT INTO notifications (type, title, message) VALUES ('system', 'New User Registration', ?)");
                $stmt->execute([$admin_message]);
                
                // Clear session data
                unset($_SESSION['register_data'], $_SESSION['register_email']);
                
                // Set success message in session
                $_SESSION['registration_success'] = true;
                
                // Redirect to login page
                header("Location: login.php?success=registered");
                exit();
            } else {
                $error = "Failed to create account. Please try again.";
            }
        } catch (Exception $e) {
            $error = "Account creation error. Please try again.";
            error_log("Account creation error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - BillPay Pro</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .register-container {
            width: 100%;
            max-width: 500px;
            padding: 20px;
        }
        
        .register-box {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            padding: 40px;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo h1 {
            color: #075B5E;
            margin: 0;
            font-size: 28px;
        }
        
        .logo p {
            color: #666;
            margin: 5px 0 0;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }
        
        .step {
            display: flex;
            align-items: center;
            margin: 0 10px;
            color: #999;
        }
        
        .step.active {
            color: #075B5E;
        }
        
        .step-number {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #eee;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            font-weight: bold;
        }
        
        .step.active .step-number {
            background: #075B5E;
            color: white;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: 500;
        }
        
        .form-group label .required {
            color: #e74c3c;
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #075B5E;
            box-shadow: 0 0 0 2px rgba(7, 91, 94, 0.1);
        }
        
        .btn {
            width: 100%;
            padding: 12px;
            background: #075B5E;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .btn:hover {
            background: #0a7c80;
        }
        
        .btn-secondary {
            background: #95a5a6;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .verification-info {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .verification-info p {
            margin: 10px 0;
            color: #666;
        }
        
        .verification-code-inputs {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 20px 0;
        }
        
        .verification-code-inputs input {
            width: 50px;
            height: 60px;
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            border: 2px solid #ddd;
            border-radius: 5px;
        }
        
        .verification-code-inputs input:focus {
            border-color: #075B5E;
            outline: none;
        }
        
        .login-link {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
        }
        
        .login-link a {
            color: #075B5E;
            text-decoration: none;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        .password-strength {
            margin-top: 5px;
            font-size: 12px;
        }
        
        .strength-weak { color: #e74c3c; }
        .strength-medium { color: #f39c12; }
        .strength-strong { color: #27ae60; }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-box">
            <div class="logo">
                <h1>BillPay Pro</h1>
                <p>Create Your Account</p>
            </div>
            
            <div class="step-indicator">
                <div class="step <?php echo $step == 1 ? 'active' : ''; ?>">
                    <div class="step-number">1</div>
                    <span>Basic Info</span>
                </div>
                <div class="step <?php echo $step == 2 ? 'active' : ''; ?>">
                    <div class="step-number">2</div>
                    <span>Verify Email</span>
                </div>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($step == 1): ?>
                <!-- Registration Form Step 1 -->
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="full_name">Full Name <span class="required">*</span></label>
                        <input type="text" id="full_name" name="full_name" class="form-control" 
                               value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address <span class="required">*</span></label>
                        <input type="email" id="email" name="email" class="form-control" 
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number <span class="required">*</span></label>
                        <input type="tel" id="phone" name="phone" class="form-control" 
                               value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" class="form-control" rows="3"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password <span class="required">*</span></label>
                        <input type="password" id="password" name="password" class="form-control" required>
                        <div id="password-strength" class="password-strength"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                        <div id="password-match" class="password-strength"></div>
                    </div>
                    
                    <button type="submit" name="register_step1" class="btn">Continue</button>
                    
                    <div class="login-link">
                        Already have an account? <a href="login.php">Login here</a>
                    </div>
                </form>
                
                <script>
                    // Password strength checker
                    const passwordInput = document.getElementById('password');
                    const confirmInput = document.getElementById('confirm_password');
                    const strengthText = document.getElementById('password-strength');
                    const matchText = document.getElementById('password-match');
                    
                    passwordInput.addEventListener('input', function() {
                        const password = this.value;
                        let strength = '';
                        let color = '';
                        
                        if (password.length === 0) {
                            strength = '';
                        } else if (password.length < 6) {
                            strength = 'Weak (at least 6 characters)';
                            color = 'strength-weak';
                        } else if (password.length < 8) {
                            strength = 'Medium';
                            color = 'strength-medium';
                        } else {
                            // Check for complexity
                            const hasUpper = /[A-Z]/.test(password);
                            const hasLower = /[a-z]/.test(password);
                            const hasNumbers = /\d/.test(password);
                            const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(password);
                            
                            const complexity = [hasUpper, hasLower, hasNumbers, hasSpecial].filter(Boolean).length;
                            
                            if (complexity >= 3) {
                                strength = 'Strong';
                                color = 'strength-strong';
                            } else if (complexity >= 2) {
                                strength = 'Medium';
                                color = 'strength-medium';
                            } else {
                                strength = 'Weak (add variety)';
                                color = 'strength-weak';
                            }
                        }
                        
                        strengthText.textContent = strength;
                        strengthText.className = 'password-strength ' + color;
                        
                        // Check password match
                        checkPasswordMatch();
                    });
                    
                    confirmInput.addEventListener('input', checkPasswordMatch);
                    
                    function checkPasswordMatch() {
                        const password = passwordInput.value;
                        const confirm = confirmInput.value;
                        
                        if (confirm.length === 0) {
                            matchText.textContent = '';
                        } else if (password === confirm) {
                            matchText.textContent = '✓ Passwords match';
                            matchText.className = 'password-strength strength-strong';
                        } else {
                            matchText.textContent = '✗ Passwords do not match';
                            matchText.className = 'password-strength strength-weak';
                        }
                    }
                </script>
                
            <?php elseif ($step == 2): ?>
                <!-- Verification Form Step 2 -->
                <div class="verification-info">
                    <h3>Verify Your Email</h3>
                    <p>We've sent a 6-digit verification code to:</p>
                    <p><strong><?php echo htmlspecialchars($email); ?></strong></p>
                    <p>Enter the code below to complete your registration.</p>
                    <p><em>Note: The code expires in 10 minutes</em></p>
                </div>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="verification_code">Verification Code <span class="required">*</span></label>
                        <input type="text" id="verification_code" name="verification_code" class="form-control" 
                               maxlength="6" pattern="\d{6}" required 
                               placeholder="Enter 6-digit code">
                    </div>
                    
                    <button type="submit" name="verify_email" class="btn">Verify & Create Account</button>
                    
                    <div style="margin-top: 20px; text-align: center;">
                        <a href="register.php?step=1" class="btn btn-secondary">Back to Registration</a>
                    </div>
                </form>
                
                <script>
                    // Auto-focus on verification code field
                    document.getElementById('verification_code').focus();
                    
                    // Auto-tab between digits (if you want to implement 6 separate inputs)
                    // For now, using single input for simplicity
                </script>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>