<?php
session_start();
include '../includes/config.php';

// If already logged in as customer, redirect to dashboard
if (isset($_SESSION['user_id']) && (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin'])) {
    header("Location: dashboard.php");
    exit();
}

// If admin is logged in, show message
if (isset($_SESSION['user_id']) && isset($_SESSION['is_admin']) && $_SESSION['is_admin']) {
    $error = 'You are logged in as Admin. Please <a href="../admin/logout.php">logout</a> first to access customer login.';
}

$error = '';

// Check for registration success message
if (isset($_SESSION['registration_success'])) {
    $success = $_SESSION['registration_success'];
    unset($_SESSION['registration_success']);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']); // Changed from phone to email
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_admin = FALSE");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Check if email is verified
            if (!$user['email_verified']) {
                $error = 'Please verify your email address first';
            }
            // Check if admin has approved
            elseif (!$user['admin_approved']) {
                $error = 'Your account is pending admin approval. You will be notified once approved.';
            }
            // Check if account is active
            elseif (!$user['is_active']) {
                $error = 'Your account has been deactivated. Please contact support.';
            }
            else {
                // Successful login
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['is_admin'] = false;
                
                // Update last login
                $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $stmt->execute([$user['id']]);
                
                header("Location: dashboard.php");
                exit();
            }
        } else {
            $error = 'Invalid email or password';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Login - BillPay Pro</title>
    <link rel="stylesheet" href="../css/style.css">
    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        .password-container {
            position: relative;
        }
        
        .password-container .form-control {
            padding-right: 45px;
            height: 48px;
            padding: 0.75rem;
            line-height: normal;
        }
        
        .toggle-password {
            position: absolute;
            right: 10px;
            top: 22px;
            background: transparent !important;
            border: none;
            cursor: pointer;
            color: #666;
            padding: 4px;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 2px;
            margin: 0;
            outline: none;
        }
        
        .toggle-password:hover {
            background: #f8f9fa !important;
        }
        
        .toggle-password:active,
        .toggle-password:focus {
            background: transparent !important;
            outline: none;
            box-shadow: none;
        }
        
        .toggle-password .icon {
            width: 1.2rem;
            height: 1.2rem;
            margin: 0;
        }
        
        /* Ensure proper form group spacing */
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .demo-accounts {
            text-align: center;
            margin-top: 1rem;
            color: #666;
            font-size: 0.9rem;
        }
        
        .force-logout {
            text-align: center;
            margin-top: 1rem;
        }
        
        .force-logout a {
            color: #e74c3c;
            font-size: 0.9rem;
            text-decoration: none;
        }
        
        .registration-success {
            background: #d4edda;
            color: #155724;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            border-left: 4px solid #28a745;
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
        <div class="card" style="max-width: 400px; margin: 2rem auto;">
            <h2 style="text-align: center; margin-bottom: 1.5rem;">Customer Login</h2>
            
            <?php if (isset($success)): ?>
                <div class="registration-success">
                    <i data-lucide="check-circle" style="width: 1.2rem; height: 1.2rem; margin-right: 0.5rem; vertical-align: middle;"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Email Address</label> <!-- Changed from Phone to Email -->
                    <input type="email" id="email" name="email" class="form-control" 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                           placeholder="your@email.com" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-container">
                        <input type="password" id="password" name="password" class="form-control" required>
                        <button type="button" class="toggle-password" onclick="togglePassword()" aria-label="Toggle password visibility">
                            <i data-lucide="eye" class="icon" id="password-icon"></i>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="btn" style="width: 100%;">Login</button>
            </form>
            
            <p style="text-align: center; margin-top: 1rem;">
                Don't have an account? <a href="register.php">Register here</a>
            </p>

            <!-- Add force logout option -->
            <div class="force-logout">
                <a href="clear_sessions.php">
                    <i data-lucide="refresh-cw" style="width: 1rem; height: 1rem; margin-right: 0.5rem;"></i>
                    Clear Sessions
                </a>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Online Billing System - BCA Project | Tribhuvan University</p>
        </div>
    </footer>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const passwordIcon = document.getElementById('password-icon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordIcon.setAttribute('data-lucide', 'eye-off');
            } else {
                passwordInput.type = 'password';
                passwordIcon.setAttribute('data-lucide', 'eye');
            }
            // Re-render the icon
            lucide.createIcons();
        }
        
        // Initialize Lucide Icons
        lucide.createIcons();
    </script>
</body>
</html>