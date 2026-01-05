<?php
session_start();
include '../includes/config.php';

// Only redirect if already logged in as admin
if (isset($_SESSION['user_id']) && isset($_SESSION['is_admin']) && $_SESSION['is_admin']) {
    header("Location: index.php");
    exit();
}

// If logged in as customer, show message but don't redirect
if (isset($_SESSION['user_id']) && (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin'])) {
    $warning = "You are logged in as a customer. <a href='../customer/logout.php'>Logout</a> to access admin panel.";
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_admin = TRUE");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['is_admin'] = true;
            
            header("Location: index.php");
            exit();
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
    <title>Admin Login - BillPay Pro</title>
    <link rel="stylesheet" href="../css/style.css">
    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        .login-icon {
            width: 3rem;
            height: 3rem;
            margin: 0 auto 1rem auto;
            color: #e74c3c;
            display: block;
        }
        
        .password-container {
            position: relative;
        }
        
        .password-container .form-control {
            padding-right: 45px;
            height: 48px;
            padding: 0.75rem;
        }
        
        .toggle-password {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
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
        
        .toggle-password .lucide-icon {
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
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <div class="logo">BillPay Pro</div>
                <ul class="nav-links">
                    <li><a href="../index.php">Home</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="container">
        <div class="card" style="max-width: 400px; margin: 2rem auto;">
            <i data-lucide="shield" class="login-icon"></i>
            <h2 style="text-align: center; margin-bottom: 1.5rem;">Admin Login</h2>
            
            <?php if (isset($warning)): ?>
                <div class="alert alert-warning">
                    <i data-lucide="alert-triangle" style="width: 1.2rem; height: 1.2rem; margin-right: 0.5rem; vertical-align: middle;"></i>
                    <?php echo $warning; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i data-lucide="alert-circle" style="width: 1.2rem; height: 1.2rem; margin-right: 0.5rem; vertical-align: middle;"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">
                        <i data-lucide="mail" style="width: 1rem; height: 1rem; margin-right: 0.5rem; vertical-align: middle;"></i>
                        Admin Email
                    </label>
                    <input type="email" id="email" name="email" class="form-control" 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="password">
                        <i data-lucide="lock" style="width: 1rem; height: 1rem; margin-right: 0.5rem; vertical-align: middle;"></i>
                        Password
                    </label>
                    <div class="password-container">
                        <input type="password" id="password" name="password" class="form-control" required>
                        <button type="button" class="toggle-password" onclick="togglePassword()" aria-label="Toggle password visibility">
                            <i data-lucide="eye" class="lucide-icon" id="password-icon"></i>
                        </button>
                    </div>
                </div>
                        
                <button type="submit" class="btn" style="background: #e74c3c; width: 100%; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                    <i data-lucide="log-in" style="width: 1.2rem; height: 1.2rem;"></i>
                    Admin Login
                </button>
            </form>
            
            <div style="text-align: center; margin-top: 1rem; color: #666; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                <small>Admin access is restricted. Contact system administrator for credentials.</small>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Online Billing System - BCA Project | Tribhuvan University</p>
        </div>
    </footer>

    <script>
        // Initialize Lucide Icons
        lucide.createIcons();
        
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
    </script>
</body>
</html>