<?php
// REMOVE THIS LINE: session_start(); // config.php already starts it
require_once '../includes/config.php';
require_once '../includes/db_connection.php';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']) {
        header("Location: ../admin/index.php");
    } else {
        header("Location: dashboard.php");
    }
    exit();
}
// ... rest of your login.php code

$error = '';
$success = '';

// Check for logout message
if (isset($_SESSION['logout_message'])) {
    $success = $_SESSION['logout_message'];
    unset($_SESSION['logout_message']);
}

// Check for registration success message
if (isset($_GET['success'])) {
    if ($_GET['success'] == 'registered') {
        $success = "Registration successful! Please verify your email and wait for admin approval.";
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    
    // Validate inputs
    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password";
    } else {
        try {
            // Check if user exists
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Check if user is admin trying to login to customer portal
                if (!empty($user['is_admin']) && $user['is_admin']) {
                    $error = "Administrators should login through admin login page.";
                }
                // Check if email is verified
                elseif (empty($user['email_verified']) || !$user['email_verified']) {
                    $error = "Please verify your email address first. Check your inbox for verification code.";
                }
                // Check if admin has approved
                elseif (empty($user['admin_approved']) || !$user['admin_approved'] || $user['registration_status'] != 'approved') {
                    $error = "Your account is pending admin approval. You will receive an email once approved.";
                }
                // Verify password
                elseif (password_verify($password, $user['password'])) {
                    // Successful login - update last login
                    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    $stmt->execute([$user['id']]);
                    
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['is_admin'] = $user['is_admin'] ?? false;
                    $_SESSION['customer_code'] = $user['customer_code'] ?? '';
                    $_SESSION['last_activity'] = time();
                    
                    // Generate new session ID for security
                    session_regenerate_id(true);
                    
                    // Redirect to dashboard
                    header("Location: dashboard.php");
                    exit();
                } else {
                    $error = "Invalid email or password";
                }
            } else {
                $error = "Invalid email or password";
            }
        } catch (Exception $e) {
            $error = "Login error. Please try again later.";
            error_log("Login error: " . $e->getMessage());
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
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* Keep the same CSS styles as above */
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            width: 100%;
            max-width: 400px;
            padding: 20px;
        }
        
        .login-box {
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
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: 500;
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
        
        .login-links {
            margin-top: 20px;
            text-align: center;
            font-size: 14px;
        }
        
        .login-links a {
            color: #075B5E;
            text-decoration: none;
            margin: 0 10px;
            transition: color 0.3s;
        }
        
        .login-links a:hover {
            color: #0a7c80;
            text-decoration: underline;
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
        
        .admin-login-link {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
        }
        
        .admin-login-link a {
            color: #666;
            text-decoration: none;
        }
        
        .admin-login-link a:hover {
            color: #075B5E;
            text-decoration: underline;
        }
        
        .password-container {
            position: relative;
        }
        
        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="logo">
                <h1>BillPay Pro</h1>
                <p>Online Billing System</p>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                           required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-container">
                        <input type="password" id="password" name="password" class="form-control" required>
                        <button type="button" class="toggle-password" id="togglePassword">
                            <span id="toggleIcon">👁️</span>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="btn">Login</button>
            </form>
            
            <div class="login-links">
                <a href="forgot_password.php">Forgot Password?</a>
                <a href="register.php">Create Account</a>
            </div>
            
            <div class="admin-login-link">
                <a href="../admin/login.php">Admin Login</a>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-focus on email field
        document.getElementById('email').focus();
        
        // Show/hide password functionality
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        const toggleIcon = document.getElementById('toggleIcon');
        
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            toggleIcon.textContent = type === 'password' ? '👁️' : '🙈';
        });
        
        // Prevent form submission on enter key in password toggle button
        togglePassword.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>