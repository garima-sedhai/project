<?php
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
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

// Check for account deactivated message
if (isset($_GET['account_deactivated'])) {
    $error = "Account isn't available. Please register first.";
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
                // Check if account has been deactivated (has deletion timestamp)
                elseif (!empty($user['deleted_at'])) {
                    $error = "Account isn't available. Please register first.";
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
    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        body {
            background: white;
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
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
            padding: 2rem;
            border: 1px solid #e0e0e0;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        
        .logo h1 {
            color: #075B5E;
            margin: 0;
            font-size: 1.8rem;
        }
        
        .logo p {
            color: #666;
            margin: 5px 0 0;
            font-size: 0.95rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #075B5E;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .form-control {
            width: 100%;
            padding: 0.6rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.95rem;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #075B5E;
        }
        
        .btn {
            width: 100%;
            padding: 0.6rem 1.2rem;
            background: #075B5E;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        
        .btn:hover {
            background: #0a7c80;
        }
        
        .login-links {
            margin-top: 1.5rem;
            text-align: center;
            font-size: 0.85rem;
        }
        
        .login-links a {
            color: #075B5E;
            text-decoration: none;
            margin: 0 0.5rem;
            transition: color 0.3s;
        }
        
        .login-links a:hover {
            text-decoration: underline;
        }
        
        .alert {
            padding: 0.75rem 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            font-size: 0.85rem;
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
            margin-top: 1rem;
            font-size: 0.85rem;
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
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 4px;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
        }
        
        .toggle-password:hover {
            background: #f5f5f5;
        }
        
        .toggle-password .lucide-icon {
            width: 1.2rem;
            height: 1.2rem;
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
                    <i data-lucide="check-circle" style="width: 1.2rem; height: 1.2rem; margin-right: 0.5rem; vertical-align: middle; color: #155724;"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i data-lucide="alert-circle" style="width: 1.2rem; height: 1.2rem; margin-right: 0.5rem; vertical-align: middle; color: #721c24;"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
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
                    <div class="password-container">
                        <input type="password" id="password" name="password" class="form-control" required>
                        <button type="button" class="toggle-password" id="togglePassword" aria-label="Toggle password visibility">
                            <i data-lucide="eye" class="lucide-icon" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="btn">
                    <i data-lucide="log-in" style="width: 1.2rem; height: 1.2rem; margin-right: 0.5rem; vertical-align: middle;"></i>
                    Login
                </button>
            </form>
            
          <div class="login-links">
            <a href="forgot_password.php">Forgot Password?</a>
            <a href="register.php">Create Account</a>
            <a href="../index.php">Back to Home</a> <!-- Add this line -->
           </div>
            
            <div class="admin-login-link">
                <a href="../admin/login.php">Admin Login</a>
            </div>
        </div>
    </div>
    
    <script>
        // Initialize Lucide Icons
        lucide.createIcons();
        
        // Auto-focus on email field
        document.getElementById('email').focus();
        
        // Show/hide password functionality with lucide icons
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        const toggleIcon = document.getElementById('toggleIcon');
        
        togglePassword.addEventListener('click', function() {
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.setAttribute('data-lucide', 'eye-off');
            } else {
                passwordInput.type = 'password';
                toggleIcon.setAttribute('data-lucide', 'eye');
            }
            // Re-render the icon
            lucide.createIcons();
        });
        
        // Prevent form submission on enter key in password toggle button
        togglePassword.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
            }
        });
    </script>
<script>
    // Back button handling for project defense - Allows going back to index.php
    (function() {
        // Check if we came from logout
        const fromLogout = sessionStorage.getItem('logout_redirect') === 'allow_back';
        
        if (fromLogout) {
            // Clear the flag
            sessionStorage.removeItem('logout_redirect');
            
            // Set up history state to allow back navigation to index.php
            history.replaceState({page: 'login_after_logout'}, '', window.location.href);
            
            // When back button is pressed, go to index.php
            window.addEventListener('popstate', function(event) {
                if (event.state && event.state.page === 'login_after_logout') {
                    // Go to main index.php
                    window.location.href = '../index.php';
                }
            });
            
            // Add a small delay and push another state to ensure back button works
            setTimeout(function() {
                history.pushState({page: 'login_after_logout'}, '');
            }, 100);
        } else {
            // Normal login - prevent back to dashboard but allow to index.php
            history.replaceState({page: 'normal_login'}, '', window.location.href);
            
            window.addEventListener('popstate', function(event) {
                if (event.state && event.state.page === 'normal_login') {
                    // Go to main index.php
                    window.location.href = '../index.php';
                }
            });
            
            // Push state for back button
            setTimeout(function() {
                history.pushState({page: 'normal_login'}, '');
            }, 100);
        }
        
        // Add "Back to Home" button functionality
        const backToHomeBtn = document.createElement('div');
        backToHomeBtn.innerHTML = `
            <div style="text-align: center; margin-top: 15px;">
                <a href="../index.php" style="color: #075B5E; text-decoration: none; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 5px;">
                    <i data-lucide="arrow-left" style="width: 1rem; height: 1rem;"></i>
                    Back to Home
                </a>
            </div>
        `;
        
        // Insert after admin login link
        const adminLink = document.querySelector('.admin-login-link');
        if (adminLink) {
            adminLink.parentNode.insertBefore(backToHomeBtn, adminLink.nextSibling);
        }
        
        // Initialize icons
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    })();
</script>
</body>
</html>