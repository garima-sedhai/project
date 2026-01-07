<?php
// For customer files:
$base_path = dirname(__DIR__);
require_once $base_path . '/includes/config.php';
//session_start();
require_once $base_path . '/includes/db_connection.php';

// If already logged in, redirect to appropriate dashboard
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['is_admin']) {
        header("Location: ../admin/index.php");
    } else {
        header("Location: dashboard.php");
    }
    exit();
}

$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    // Validate inputs
    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
    } else {
        // Check user credentials
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Check if account is approved
                if (!$user['is_admin'] && !$user['admin_approved']) {
                    if ($user['email_verified'] && $user['registration_status'] == 'verified') {
                        $error = "Your account is pending admin approval. Please wait for approval or contact support.";
                    } elseif (!$user['email_verified']) {
                        $error = "Please verify your email first. Check your email for OTP.";
                    } elseif ($user['registration_status'] == 'rejected') {
                        $error = "Your account registration has been rejected. Please contact support.";
                    } else {
                        $error = "Your account is not yet active. Please complete the registration process.";
                    }
                } elseif ($user['is_deleted']) {
                    $error = "This account has been deactivated. Please contact support.";
                } else {
                    // Login successful - set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['is_admin'] = $user['is_admin'];
                    $_SESSION['admin_approved'] = $user['admin_approved'];
                    $_SESSION['email_verified'] = $user['email_verified'];
                    
                    // Update last login
                    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW(), login_count = COALESCE(login_count, 0) + 1 WHERE id = ?");
                    $stmt->execute([$user['id']]);
                    
                    // Redirect based on user type
                    if ($user['is_admin']) {
                        header("Location: ../admin/index.php");
                    } else {
                        header("Location: dashboard.php");
                    }
                    exit();
                }
            } else {
                $error = "Invalid email or password.";
            }
        } else {
            $error = "Invalid email or password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - BillPay Pro</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .login-container {
            max-width: 400px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .login-icon {
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
        
        .registration-info {
            background: #f0f7f7;
            padding: 1rem;
            border-radius: 5px;
            margin: 1rem 0;
            border-left: 4px solid #075B5E;
        }
        
        .registration-info h4 {
            margin-top: 0;
            color: #075B5E;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .status-steps {
            margin-top: 1rem;
        }
        
        .status-step {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        
        .status-step i {
            width: 1rem;
            height: 1rem;
        }
        
        .step-complete {
            color: #27ae60;
        }
        
        .step-pending {
            color: #f39c12;
        }
        
        .forgot-password {
            text-align: right;
            margin-top: 0.5rem;
        }
        
        .demo-credentials {
            background: #fff3cd;
            padding: 1rem;
            border-radius: 5px;
            margin: 1rem 0;
            border-left: 4px solid #ffc107;
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
                    <li><a href="register.php">Register</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="container">
        <div class="login-container">
            <!-- Login Header -->
            <div class="login-header">
                <div class="login-icon">
                    <i data-lucide="log-in"></i>
                </div>
                <h1>Welcome Back</h1>
                <p>Login to manage your bills and payments</p>
            </div>
            
            <!-- Display messages -->
            <?php if (isset($_SESSION['registration_success'])): ?>
                <div class="alert alert-success">
                    <i data-lucide="check-circle" style="width: 1.2rem; height: 1.2rem; margin-right: 0.5rem; vertical-align: middle;"></i>
                    <?php echo $_SESSION['registration_success']; unset($_SESSION['registration_success']); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i data-lucide="alert-circle" style="width: 1.2rem; height: 1.2rem; margin-right: 0.5rem; vertical-align: middle;"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <!-- Registration Info -->
            <div class="registration-info">
                <h4><i data-lucide="info"></i> Registration Status</h4>
                <p>New to BillPay Pro? The registration process includes:</p>
                <div class="status-steps">
                    <div class="status-step">
                        <i data-lucide="circle" class="step-pending"></i>
                        <span>Email Verification (OTP)</span>
                    </div>
                    <div class="status-step">
                        <i data-lucide="circle" class="step-pending"></i>
                        <span>Admin Approval (Required)</span>
                    </div>
                    <div class="status-step">
                        <i data-lucide="circle" class="step-pending"></i>
                        <span>Account Activation</span>
                    </div>
                </div>
                <p style="margin-top: 0.5rem; font-size: 0.9rem;">
                    <a href="register.php">Click here to register</a>
                </p>
            </div>
            
            <!-- Demo Credentials -->
            <div class="demo-credentials">
                <h4><i data-lucide="key"></i> Demo Credentials</h4>
                <p><strong>Admin:</strong> admin@billpay.com / admin123</p>
                <p><strong>Customer:</strong> customer@billpay.com / customer123</p>
                <p><small>For testing purposes only</small></p>
            </div>
            
            <!-- Login Form -->
            <form method="POST" action="" class="login-form">
                <div class="form-group">
                    <label for="email">
                        <i data-lucide="mail" style="width: 1rem; height: 1rem; margin-right: 0.5rem; vertical-align: middle;"></i>
                        Email Address
                    </label>
                    <input type="email" id="email" name="email" class="form-control" 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                           required>
                </div>
                
                <div class="form-group">
                    <label for="password">
                        <i data-lucide="lock" style="width: 1rem; height: 1rem; margin-right: 0.5rem; vertical-align: middle;"></i>
                        Password
                    </label>
                    <div style="position: relative;">
                        <input type="password" id="password" name="password" class="form-control" required>
                        <button type="button" onclick="togglePassword('password')" 
                                style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); 
                                       background: none; border: none; cursor: pointer;">
                            <i data-lucide="eye" style="width: 1.2rem; height: 1.2rem;"></i>
                        </button>
                    </div>
                    <div class="forgot-password">
                        <a href="forgot_password.php">Forgot Password?</a>
                    </div>
                </div>
                
                <button type="submit" class="btn" style="width: 100%;">
                    <i data-lucide="log-in" style="width: 1.2rem; height: 1.2rem; margin-right: 0.5rem;"></i>
                    Login
                </button>
            </form>
            
            <div style="text-align: center; margin-top: 2rem; padding-top: 1rem; border-top: 1px solid #e9ecef;">
                <p>Don't have an account? <a href="register.php">Register here</a></p>
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
    </script>
</body>
</html>