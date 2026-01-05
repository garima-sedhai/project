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
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    
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
    
    if (!empty($phone) && !preg_match('/^[0-9]{10}$/', $phone)) {
        $errors[] = "Please enter a valid 10-digit phone number.";
    }
    
    // Check if email already exists (excluding current user)
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$email, $user_id]);
    if ($stmt->fetch()) {
        $errors[] = "This email is already registered.";
    }
    
    if (empty($errors)) {
        try {
            // Update user profile
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, address = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$full_name, $email, $phone, $address, $user_id]);
            
            // Update session variables
            $_SESSION['full_name'] = $full_name;
            $_SESSION['email'] = $email;
            
            $message = "Profile updated successfully!";
            $message_type = "success";
            
            // Refresh user data
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
        } catch (PDOException $e) {
            $message = "Error updating profile: " . $e->getMessage();
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
    <title>Edit Profile - BillPay Pro</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        .profile-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .profile-icon {
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
        
        .profile-form {
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
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
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
            display: inline-block;
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
        
        .message {
            margin-bottom: 1rem;
            padding: 1rem;
            border-radius: 4px;
        }
        
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .profile-container {
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
        <div class="profile-container">
            <!-- Back Navigation -->
            <div style="margin-bottom: 1.5rem;">
                <a href="profile.php" style="color: #075B5E; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem;">
                    <i data-lucide="arrow-left"></i>
                    Back to Profile
                </a>
            </div>
            
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="profile-icon">
                    <?php echo $first_letter; ?>
                </div>
                <div>
                    <h1>Edit Profile</h1>
                    <p>Update your personal information</p>
                </div>
            </div>
            
            <!-- Messages -->
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <!-- Edit Profile Form -->
            <form method="POST" action="" class="profile-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="full_name">Full Name *</label>
                        <input type="text" id="full_name" name="full_name" class="form-control" 
                               value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" class="form-control" 
                               value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" class="form-control" 
                               value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" 
                               pattern="[0-9]{10}" maxlength="10">
                        <small style="color: #666; font-size: 0.85rem;">10 digits without spaces or dashes</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="customer_code">Customer Code</label>
                        <input type="text" id="customer_code" class="form-control" 
                               value="<?php echo htmlspecialchars($user['customer_code'] ?? ''); ?>" disabled>
                        <small style="color: #666; font-size: 0.85rem;">This cannot be changed</small>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea id="address" name="address" class="form-control" rows="4"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label>Account Created</label>
                    <input type="text" class="form-control" 
                           value="<?php echo date('F d, Y', strtotime($user['created_at'])); ?>" disabled>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        <i data-lucide="save"></i> Save Changes
                    </button>
                    <a href="profile.php" class="btn-secondary">
                        <i data-lucide="x"></i> Cancel
                    </a>
                </div>
            </form>
            
            <!-- Additional Options -->
            <div style="margin-top: 2rem; padding: 1.5rem; background: #f8f9fa; border-radius: 8px;">
                <h3 style="color: #075B5E; margin-bottom: 1rem;">Account Management</h3>
                <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                    <a href="change_password.php" style="color: #075B5E; text-decoration: none; padding: 0.75rem 1rem; background: white; border-radius: 4px; border: 1px solid #ddd;">
                        <i data-lucide="key"></i> Change Password
                    </a>
                    <a href="delete_account.php" style="color: #e74c3c; text-decoration: none; padding: 0.75rem 1rem; background: white; border-radius: 4px; border: 1px solid #f5c6cb;">
                        <i data-lucide="trash-2"></i> Delete Account
                    </a>
                </div>
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
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const phone = document.getElementById('phone').value;
            const email = document.getElementById('email').value;
            
            // Validate phone if provided
            if (phone && !/^[0-9]{10}$/.test(phone)) {
                e.preventDefault();
                alert('Please enter a valid 10-digit phone number.');
                return false;
            }
            
            // Validate email format
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('Please enter a valid email address.');
                return false;
            }
            
            return true;
        });
    </script>
</body>
</html>