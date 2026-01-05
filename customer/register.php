<?php
session_start();
include '../includes/config.php';

// Redirect if already logged in as customer
if (isset($_SESSION['user_id']) && !$_SESSION['is_admin']) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['verify_code'])) {
        $customer_code = trim($_POST['customer_code']);
        
        if (empty($customer_code)) {
            $error = 'Please enter customer code';
        } else {
            // SIMPLE CHECK: Find customer by code
            $stmt = $pdo->prepare("SELECT * FROM users WHERE customer_code = ? AND is_admin = FALSE");
            $stmt->execute([$customer_code]);
            $customer = $stmt->fetch();
            
            if ($customer) {
                if ($customer['phone_verified']) {
                    $error = 'This customer is already registered. Please login.';
                } else {
                    // Store in session and move to password setup
                    $_SESSION['register_customer_id'] = $customer['id'];
                    $_SESSION['register_customer_code'] = $customer_code;
                    $_SESSION['register_customer_name'] = $customer['full_name'];
                    $success = "Code verified! Welcome " . $customer['full_name'] . ". Please set your password.";
                }
            } else {
                $error = 'Invalid customer code. Please check and try again.';
            }
        }
    }
    elseif (isset($_POST['set_password'])) {
        if (!isset($_SESSION['register_customer_id'])) {
            $error = 'Session expired. Please start over.';
        } else {
            $password = $_POST['password'];
            $confirm_password = $_POST['confirm_password'];
            
            if (empty($password) || $password !== $confirm_password) {
                $error = 'Passwords do not match';
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ?, phone_verified = 1 WHERE id = ?");
                
                if ($stmt->execute([$hashed_password, $_SESSION['register_customer_id']])) {

                    // Success message for login page
                    $_SESSION['registration_success'] = "Registration successful! You can now login.";

                    // Clear registration session
                    unset($_SESSION['register_customer_id']);
                    unset($_SESSION['register_customer_code']);
                    unset($_SESSION['register_customer_name']);

                    // 🔥 Fixed Redirect (PHP header + JS fallback)
                    header("Location: login.php"); 
                    echo '<script>window.location.href = "login.php";</script>';
                    exit();
                } else {
                    $error = 'Registration failed. Please try again.';
                }
            }
        }
    }
}

// Handle restart
if (isset($_GET['restart'])) {
    unset($_SESSION['register_customer_id']);
    unset($_SESSION['register_customer_code']);
    unset($_SESSION['register_customer_name']);
    header("Location: register.php");
    exit();
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
        html, body {
            height: 100%;
        }
        
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        .container {
            flex: 1;
        }
        
        .footer {
            margin-top: auto;
        }

        .password-container {
            position: relative;
        }
        
        .password-container .form-control {
            padding-right: 45px;
            height: 48px;
            padding: 0.75rem;
            line-height: normal;
            box-sizing: border-box;
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
        
        .toggle-password .lucide-icon {
            width: 1.2rem;
            height: 1.2rem;
            margin: 0;
        }
        
        .form-group {
            margin-bottom: 1rem;
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
                    <li><a href="login.php">Login</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="container">
        <div class="card" style="max-width: 500px; margin: 2rem auto;">
            <h2 style="text-align: center;">Customer Registration</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <?php if (!isset($_SESSION['register_customer_id'])): ?>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="customer_code">Customer Code *</label>
                        <input type="text" id="customer_code" name="customer_code" class="form-control" 
                               value="<?php echo isset($_POST['customer_code']) ? htmlspecialchars($_POST['customer_code']) : ''; ?>" 
                               placeholder="Enter customer code from admin" required
                               style="font-family: monospace; font-size: 1.1rem;">
                        <small style="color: #666;">Get your customer code from the administrator</small>
                    </div>
                    
                    <button type="submit" name="verify_code" class="btn" style="width: 100%;">Verify Code</button>
                </form>
            <?php else: ?>
                <div style="background: #f8f9fa; padding: 1rem; border-radius: 5px; margin-bottom: 1rem;">
                    <strong>Welcome:</strong> <?php echo $_SESSION['register_customer_name']; ?><br>
                    <strong>Code:</strong> <?php echo $_SESSION['register_customer_code']; ?>
                </div>
                
                <form method="POST" action="" id="registrationForm">
                    <div class="form-group">
                        <label for="password">Password *</label>
                        <div class="password-container">
                            <input type="password" id="password" name="password" class="form-control" required>
                            <button type="button" class="toggle-password" onclick="togglePassword('password')" aria-label="Toggle password visibility">
                                <i data-lucide="eye" class="lucide-icon" id="password-icon"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password *</label>
                        <div class="password-container">
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                            <button type="button" class="toggle-password" onclick="togglePassword('confirm_password')" aria-label="Toggle password visibility">
                                <i data-lucide="eye" class="lucide-icon" id="confirm-password-icon"></i>
                            </button>
                        </div>
                    </div>
                    
                    <button type="submit" name="set_password" class="btn" style="width: 100%; background: #27ae60;">
                        Complete Registration
                    </button>
                </form>
                
                <div style="text-align: center; margin-top: 1rem;">
                    <a href="?restart=1" style="color: #e74c3c;">
                        <i data-lucide="refresh-cw" style="width: 1rem; height: 1rem; margin-right: 0.5rem;"></i>
                        Start Over
                    </a>
                </div>
            <?php endif; ?>
            
            <p style="text-align: center; margin-top: 1rem;">
                Already registered? <a href="login.php">Login here</a>
            </p>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Online Billing System - BCA Project | Tribhuvan University</p>
        </div>
    </footer>

    <script>
        lucide.createIcons();
        
        function togglePassword(fieldId) {
            const passwordField = document.getElementById(fieldId);
            const passwordIcon = document.getElementById(fieldId + '-icon');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                passwordIcon.setAttribute('data-lucide', 'eye-off');
            } else {
                passwordField.type = 'password';
                passwordIcon.setAttribute('data-lucide', 'eye');
            }
            lucide.createIcons();
        }
    </script>
</body>
</html>
