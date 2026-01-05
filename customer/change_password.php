<?php
session_start();
include '../includes/config.php';

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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate inputs
    $errors = [];
    
    // Verify current password
    if (!password_verify($current_password, $user['password'])) {
        $errors[] = "Current password is incorrect.";
    }
    
    // Validate new password
    if (empty($new_password)) {
        $errors[] = "New password is required.";
    } elseif (strlen($new_password) < 8) {
        $errors[] = "New password must be at least 8 characters long.";
    } elseif ($new_password === $current_password) {
        $errors[] = "New password must be different from current password.";
    }
    
    if ($new_password !== $confirm_password) {
        $errors[] = "New password and confirmation do not match.";
    }
    
    if (empty($errors)) {
        try {
            // Hash the new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update password
            $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$hashed_password, $user_id]);
            
            $message = "Password changed successfully!";
            $message_type = "success";
            
            // Clear form fields
            $_POST = [];
            
        } catch (PDOException $e) {
            $message = "Error changing password: " . $e->getMessage();
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
    <title>Change Password - BillPay Pro</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        .password-container {
            max-width: 600px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .password-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .password-icon {
            width: 60px;
            height: 60px;
            background: #075B5E;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        .password-form {
            background: white;
            border-radius: 8px;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
            border-radius: 4px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #075B5E;
            box-shadow: 0 0 0 3px rgba(7, 91, 94, 0.1);
        }
        
        .password-strength {
            margin-top: 0.5rem;
            height: 5px;
            border-radius: 2px;
            background: #eee;
            overflow: hidden;
        }
        
        .strength-meter {
            height: 100%;
            width: 0%;
            transition: width 0.3s, background-color 0.3s;
        }
        
        .strength-weak { width: 33%; background-color: #e74c3c; }
        .strength-medium { width: 66%; background-color: #f39c12; }
        .strength-strong { width: 100%; background-color: #27ae60; }
        
        .password-requirements {
            margin-top: 0.5rem;
            font-size: 0.85rem;
            color: #666;
        }
        
        .requirement {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.25rem;
        }
        
        .requirement.met {
            color: #27ae60;
        }
        
        .requirement.unmet {
            color: #e74c3c;
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #eee;
        }
        
        .btn-primary {
            background: #075B5E;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            transition: background 0.3s;
        }
        
        .btn-primary:hover {
            background: #054749;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: background 0.3s;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
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
        
        .password-toggle {
            position: relative;
        }
        
        .password-toggle-btn {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #666;
        }
        
        .security-tips {
            margin-top: 2rem;
            padding: 1.5rem;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        @media (max-width: 768px) {
            .form-actions {
                flex-direction: column;
            }
            
            .password-container {
                padding: 0 0.5rem;
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
        <div class="password-container">
            <!-- Back Navigation - UPDATED: Now links to dashboard.php -->
            <div style="margin-bottom: 1.5rem;">
                <a href="dashboard.php" style="color: #075B5E; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem;">
                    <i data-lucide="arrow-left"></i>
                    Back to Dashboard
                </a>
            </div>
            
            <!-- Password Header -->
            <div class="password-header">
                <div class="password-icon">
                    <i data-lucide="key"></i>
                </div>
                <div>
                    <h1>Change Password</h1>
                    <p>Update your account password</p>
                </div>
            </div>
            
            <!-- Messages -->
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <!-- Change Password Form -->
            <form method="POST" action="" class="password-form" id="passwordForm">
                <div class="form-group">
                    <label for="current_password">Current Password *</label>
                    <div class="password-toggle">
                        <input type="password" id="current_password" name="current_password" class="form-control" required>
                        <button type="button" class="password-toggle-btn" onclick="togglePassword('current_password')">
                            <i data-lucide="eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="new_password">New Password *</label>
                    <div class="password-toggle">
                        <input type="password" id="new_password" name="new_password" class="form-control" required 
                               oninput="checkPasswordStrength()">
                        <button type="button" class="password-toggle-btn" onclick="togglePassword('new_password')">
                            <i data-lucide="eye"></i>
                        </button>
                    </div>
                    
                    <div class="password-strength">
                        <div class="strength-meter" id="strengthMeter"></div>
                    </div>
                    
                    <div class="password-requirements" id="passwordRequirements">
                        <div class="requirement" id="reqLength">
                            <i data-lucide="x" style="width: 16px; height: 16px;"></i>
                            At least 8 characters
                        </div>
                        <div class="requirement" id="reqLower">
                            <i data-lucide="x" style="width: 16px; height: 16px;"></i>
                            Contains lowercase letter
                        </div>
                        <div class="requirement" id="reqUpper">
                            <i data-lucide="x" style="width: 16px; height: 16px;"></i>
                            Contains uppercase letter
                        </div>
                        <div class="requirement" id="reqNumber">
                            <i data-lucide="x" style="width: 16px; height: 16px;"></i>
                            Contains number
                        </div>
                        <div class="requirement" id="reqSpecial">
                            <i data-lucide="x" style="width: 16px; height: 16px;"></i>
                            Contains special character
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password *</label>
                    <div class="password-toggle">
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                        <button type="button" class="password-toggle-btn" onclick="togglePassword('confirm_password')">
                            <i data-lucide="eye"></i>
                        </button>
                    </div>
                    <div id="passwordMatch" style="margin-top: 0.5rem; font-size: 0.85rem;"></div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        <i data-lucide="save"></i> Change Password
                    </button>
                    <a href="dashboard.php" class="btn-secondary">
                        <i data-lucide="x"></i> Cancel
                    </a>
                </div>
            </form>
            
            <!-- Security Tips -->
            <div class="security-tips">
                <h3 style="color: #075B5E; margin-bottom: 1rem;">
                    <i data-lucide="shield"></i> Security Tips
                </h3>
                <ul style="color: #666; line-height: 1.6;">
                    <li>Use a unique password that you don't use elsewhere</li>
                    <li>Combine uppercase, lowercase, numbers, and symbols</li>
                    <li>Avoid using personal information like birthdays or names</li>
                    <li>Consider using a password manager for strong, unique passwords</li>
                    <li>Never share your password with anyone</li>
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
        
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = field.parentNode.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.setAttribute('data-lucide', 'eye-off');
            } else {
                field.type = 'password';
                icon.setAttribute('data-lucide', 'eye');
            }
            lucide.createIcons();
        }
        
        function checkPasswordStrength() {
            const password = document.getElementById('new_password').value;
            const strengthMeter = document.getElementById('strengthMeter');
            const requirements = {
                length: password.length >= 8,
                lower: /[a-z]/.test(password),
                upper: /[A-Z]/.test(password),
                number: /[0-9]/.test(password),
                special: /[^A-Za-z0-9]/.test(password)
            };
            
            // Update requirement indicators
            document.getElementById('reqLength').className = 
                `requirement ${requirements.length ? 'met' : 'unmet'}`;
            document.getElementById('reqLower').className = 
                `requirement ${requirements.lower ? 'met' : 'unmet'}`;
            document.getElementById('reqUpper').className = 
                `requirement ${requirements.upper ? 'met' : 'unmet'}`;
            document.getElementById('reqNumber').className = 
                `requirement ${requirements.number ? 'met' : 'unmet'}`;
            document.getElementById('reqSpecial').className = 
                `requirement ${requirements.special ? 'met' : 'unmet'}`;
            
            // Update icons
            document.querySelectorAll('.requirement i').forEach((icon, index) => {
                const isMet = Object.values(requirements)[index];
                icon.setAttribute('data-lucide', isMet ? 'check' : 'x');
            });
            lucide.createIcons();
            
            // Calculate strength
            let strength = 0;
            Object.values(requirements).forEach(req => {
                if (req) strength++;
            });
            
            // Update strength meter
            strengthMeter.className = 'strength-meter';
            if (password.length === 0) {
                strengthMeter.style.width = '0%';
            } else if (strength <= 2) {
                strengthMeter.classList.add('strength-weak');
            } else if (strength <= 4) {
                strengthMeter.classList.add('strength-medium');
            } else {
                strengthMeter.classList.add('strength-strong');
            }
            
            // Check password match
            checkPasswordMatch();
        }
        
        function checkPasswordMatch() {
            const password = document.getElementById('new_password').value;
            const confirm = document.getElementById('confirm_password').value;
            const matchDiv = document.getElementById('passwordMatch');
            
            if (confirm.length === 0) {
                matchDiv.innerHTML = '';
                matchDiv.style.color = '';
            } else if (password === confirm) {
                matchDiv.innerHTML = '<i data-lucide="check" style="width: 16px; height: 16px; color: #27ae60;"></i> Passwords match';
                matchDiv.style.color = '#27ae60';
                lucide.createIcons();
            } else {
                matchDiv.innerHTML = '<i data-lucide="x" style="width: 16px; height: 16px; color: #e74c3c;"></i> Passwords do not match';
                matchDiv.style.color = '#e74c3c';
                lucide.createIcons();
            }
        }
        
        // Add event listeners
        document.getElementById('confirm_password').addEventListener('input', checkPasswordMatch);
        document.getElementById('new_password').addEventListener('input', checkPasswordStrength);
        
        // Form validation
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const password = document.getElementById('new_password').value;
            const confirm = document.getElementById('confirm_password').value;
            
            if (password !== confirm) {
                e.preventDefault();
                alert('Passwords do not match. Please check and try again.');
                return false;
            }
            
            if (password.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long.');
                return false;
            }
            
            return true;
        });
    </script>
</body>
</html>