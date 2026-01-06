<?php
// Start output buffering
ob_start();

// For customer files:
$base_path = dirname(__DIR__);
require_once $base_path . '/includes/config.php';

// Check if we should skip email sending for testing
$skip_email = isset($_GET['skip_email']) || (defined('EMAIL_DEBUG') && EMAIL_DEBUG);

session_start();
require_once $base_path . '/includes/db_connection.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']) {
        ob_end_clean();
        header("Location: ../admin/index.php");
    } else {
        ob_end_clean();
        header("Location: dashboard.php");
    }
    exit();
}

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate inputs
    $errors = [];
    
    if (empty($full_name)) {
        $errors[] = "Full name is required.";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }
    
    if (empty($phone)) {
        $errors[] = "Phone number is required.";
    } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {
        $errors[] = "Please enter a valid 10-digit phone number.";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long.";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }
    
    // Check if email or phone already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR phone = ?");
    $stmt->execute([$email, $phone]);
    if ($stmt->rowCount() > 0) {
        $errors[] = "Email or phone number is already registered.";
    }
    
    if (empty($errors)) {
        try {
            // Generate unique customer code
            $customer_code = 'CUST' . date('YmdHis') . rand(100, 999);
            
            // Generate 6-digit OTP
            $otp = rand(100000, 999999);
            $otp_expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user with pending status
            $sql = "INSERT INTO users (email, password, full_name, phone, address, customer_code, 
                    is_admin, phone_verified, is_active, admin_approved, email_verified, otp, otp_expiry, 
                    registration_status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, 0, 0, 1, 0, 0, ?, ?, 'pending', NOW())";
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([
                $email, $hashed_password, $full_name, $phone, $address, $customer_code,
                $otp, $otp_expiry
            ]);
            
            if ($result) {
                $user_id = $pdo->lastInsertId();
                
                // Store user info in session for OTP verification
                $_SESSION['temp_user_id'] = $user_id;
                $_SESSION['temp_email'] = $email;
                $_SESSION['temp_full_name'] = $full_name;
                
                // Send actual OTP email
                require_once $base_path . '/includes/email_functions.php';
                $email_sent = sendOTPEmail($email, $full_name, $otp);
                
                if ($email_sent) {
                    // Create admin notification for new registration
                    $admin_message = "New customer registration: " . $full_name . 
                                   " (" . $email . ") - Please review and approve.";
                    
                    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, created_at) 
                                          SELECT id, 'New Registration', ?, 'registration', NOW() 
                                          FROM users WHERE is_admin = TRUE");
                    $stmt->execute([$admin_message]);
                    
                    // Redirect to OTP verification
                    header("Location: verify_otp.php?email=" . urlencode($email));
                    exit();
                } else {
                    $error = "Failed to send OTP email. Please try again or contact support.";
                    // Delete the user record since email failed
                    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
                }
                
            } else {
                $error = "Registration failed. Please try again.";
            }
            
        } catch (Exception $e) {
            $error = "Database error: " . $e->getMessage();
        }
    } else {
        $error = implode("<br>", $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - BillPay Pro</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        .register-container {
            max-width: 500px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .register-icon {
            width: 80px;
            height: 80px;
            background: #075B5E;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 1rem;
        }
        
        .registration-steps {
            display: flex;
            justify-content: space-between;
            margin: 2rem 0;
            position: relative;
        }
        
        .registration-steps::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 10%;
            right: 10%;
            height: 2px;
            background: #e9ecef;
            z-index: 1;
        }
        
        .step {
            text-align: center;
            position: relative;
            z-index: 2;
            flex: 1;
        }
        
        .step-number {
            width: 40px;
            height: 40px;
            background: #e9ecef;
            color: #666;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.5rem;
            font-weight: bold;
        }
        
        .step.active .step-number {
            background: #075B5E;
            color: white;
        }
        
        .step-label {
            font-size: 0.85rem;
            color: #666;
        }
        
        .form-note {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 5px;
            margin: 1rem 0;
            font-size: 0.9rem;
        }
        
        .password-requirements {
            background: #f0f7f7;
            padding: 1rem;
            border-radius: 5px;
            margin: 1rem 0;
            border-left: 4px solid #075B5E;
        }
        
        .requirement {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        
        .requirement.met {
            color: #27ae60;
        }
        
        .requirement.unmet {
            color: #666;
        }
        
        .login-prompt {
            text-align: center;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid #e9ecef;
        }
        
        .email-note {
            background: #e7f4f4;
            padding: 1rem;
            border-radius: 5px;
            margin: 1rem 0;
            border-left: 4px solid #075B5E;
            font-size: 0.9rem;
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
            <!-- Registration Header -->
            <div class="register-header">
                <div class="register-icon">
                    <i data-lucide="user-plus"></i>
                </div>
                <h1>Create Account</h1>
                <p>Register to manage your bills and payments</p>
            </div>
            
            <!-- Registration Steps -->
            <div class="registration-steps">
                <div class="step active">
                    <div class="step-number">1</div>
                    <div class="step-label">Register</div>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <div class="step-label">Verify OTP</div>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <div class="step-label">Admin Approval</div>
                </div>
                <div class="step">
                    <div class="step-number">4</div>
                    <div class="step-label">Complete</div>
                </div>
            </div>
            
            <!-- Messages -->
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i data-lucide="alert-circle" style="width: 1.2rem; height: 1.2rem; margin-right: 0.5rem; vertical-align: middle;"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i data-lucide="check-circle" style="width: 1.2rem; height: 1.2rem; margin-right: 0.5rem; vertical-align: middle;"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <!-- Email Note -->
            <div class="email-note">
                <i data-lucide="mail" style="width: 1rem; height: 1rem; margin-right: 0.5rem; vertical-align: middle;"></i>
                <strong>Important:</strong> A verification OTP will be sent to your email. Please check your inbox (and spam folder) after registration.
            </div>
            
            <!-- Registration Form -->
            <form method="POST" action="" class="register-form" id="registerForm">
                <div class="form-group">
                    <label for="full_name">
                        <i data-lucide="user" style="width: 1rem; height: 1rem; margin-right: 0.5rem; vertical-align: middle;"></i>
                        Full Name *
                    </label>
                    <input type="text" id="full_name" name="full_name" class="form-control" 
                           value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>" 
                           required>
                </div>
                
                <div class="form-group">
                    <label for="email">
                        <i data-lucide="mail" style="width: 1rem; height: 1rem; margin-right: 0.5rem; vertical-align: middle;"></i>
                        Email Address *
                    </label>
                    <input type="email" id="email" name="email" class="form-control" 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                           required>
                    <small class="form-note">We'll send a verification OTP to this email</small>
                </div>
                
                <div class="form-group">
                    <label for="phone">
                        <i data-lucide="phone" style="width: 1rem; height: 1rem; margin-right: 0.5rem; vertical-align: middle;"></i>
                        Phone Number *
                    </label>
                    <input type="tel" id="phone" name="phone" class="form-control" 
                           value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" 
                           pattern="[0-9]{10}" placeholder="98XXXXXXXX" required>
                </div>
                
                <div class="form-group">
                    <label for="address">
                        <i data-lucide="map-pin" style="width: 1rem; height: 1rem; margin-right: 0.5rem; vertical-align: middle;"></i>
                        Address
                    </label>
                    <textarea id="address" name="address" class="form-control" rows="3"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                </div>
                
                <div class="password-requirements">
                    <strong>Password Requirements:</strong>
                    <div class="requirement" id="reqLength">
                        <i data-lucide="circle" style="width: 1rem; height: 1rem;"></i>
                        At least 6 characters
                    </div>
                    <div class="requirement" id="reqMatch">
                        <i data-lucide="circle" style="width: 1rem; height: 1rem;"></i>
                        Passwords match
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">
                        <i data-lucide="lock" style="width: 1rem; height: 1rem; margin-right: 0.5rem; vertical-align: middle;"></i>
                        Password *
                    </label>
                    <div style="position: relative;">
                        <input type="password" id="password" name="password" class="form-control" required>
                        <button type="button" onclick="togglePassword('password')" 
                                style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); 
                                       background: none; border: none; cursor: pointer;">
                            <i data-lucide="eye" style="width: 1.2rem; height: 1.2rem;"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">
                        <i data-lucide="lock" style="width: 1rem; height: 1rem; margin-right: 0.5rem; vertical-align: middle;"></i>
                        Confirm Password *
                    </label>
                    <div style="position: relative;">
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                        <button type="button" onclick="togglePassword('confirm_password')" 
                                style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); 
                                       background: none; border: none; cursor: pointer;">
                            <i data-lucide="eye" style="width: 1.2rem; height: 1.2rem;"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-note">
                    <i data-lucide="info" style="width: 1rem; height: 1rem; margin-right: 0.5rem; vertical-align: middle;"></i>
                    <strong>Registration Process:</strong>
                    <ol style="margin: 0.5rem 0 0 1.5rem; font-size: 0.9rem;">
                        <li>Complete this registration form</li>
                        <li>Verify your email with OTP (sent to your email)</li>
                        <li>Wait for admin approval (you'll be notified)</li>
                        <li>Login and access your account</li>
                    </ol>
                </div>
                
                <button type="submit" class="btn" style="width: 100%; margin-top: 1.5rem;">
                    <i data-lucide="user-plus" style="width: 1.2rem; height: 1.2rem; margin-right: 0.5rem;"></i>
                    Register Account
                </button>
            </form>
            
            <div class="login-prompt">
                <p>Already have an account? <a href="login.php">Login here</a></p>
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
        
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = field.nextElementSibling.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.setAttribute('data-lucide', 'eye-off');
            } else {
                field.type = 'password';
                icon.setAttribute('data-lucide', 'eye');
            }
            lucide.createIcons();
        }
        
        // Real-time password validation
        document.getElementById('password').addEventListener('input', validatePassword);
        document.getElementById('confirm_password').addEventListener('input', validatePassword);
        
        function validatePassword() {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;
            
            // Check length
            const lengthReq = document.getElementById('reqLength');
            const lengthIcon = lengthReq.querySelector('i');
            if (password.length >= 6) {
                lengthReq.className = 'requirement met';
                lengthIcon.setAttribute('data-lucide', 'check-circle');
            } else {
                lengthReq.className = 'requirement unmet';
                lengthIcon.setAttribute('data-lucide', 'circle');
            }
            
            // Check match
            const matchReq = document.getElementById('reqMatch');
            const matchIcon = matchReq.querySelector('i');
            if (confirm && password === confirm) {
                matchReq.className = 'requirement met';
                matchIcon.setAttribute('data-lucide', 'check-circle');
            } else if (confirm) {
                matchReq.className = 'requirement unmet';
                matchIcon.setAttribute('data-lucide', 'x-circle');
            } else {
                matchReq.className = 'requirement unmet';
                matchIcon.setAttribute('data-lucide', 'circle');
            }
            
            lucide.createIcons();
        }
        
        // Form submission validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;
            
            if (password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long.');
                return false;
            }
            
            if (password !== confirm) {
                e.preventDefault();
                alert('Passwords do not match.');
                return false;
            }
            
            return true;
        });
        
        // Initialize validation
        validatePassword();
    </script>
</body>
</html>