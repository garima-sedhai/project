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

// Get user statistics for sidebar
$stmt = $pdo->prepare("SELECT COUNT(*) as total_pending, COALESCE(SUM(amount), 0) as total_amount FROM bills WHERE user_id = ? AND status = 'pending'");
$stmt->execute([$user_id]);
$pending_stats = $stmt->fetch();

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
        /* Remove any duplicate header styling */
        body > .header {
            display: none !important;
        }
        
        .edit-profile-wrapper {
            background: #f8f9fa;
            min-height: 100vh;
        }
        
        .edit-profile-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }
        
        /* Back Navigation - Better styling */
        .back-navigation {
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .back-link {
            color: #075B5E;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            transition: all 0.3s;
            background: white;
            border: 1px solid #e9ecef;
        }
        
        .back-link:hover {
            background: #f0f7f7;
            border-color: #075B5E;
            transform: translateX(-5px);
        }
        
        /* Main Content Card */
        .edit-profile-card {
            background: white;
            border-radius: 12px;
            padding: 2.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
        }
        
        /* Header Section */
        .edit-profile-header {
            text-align: center;
            margin-bottom: 2.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 2px solid #f0f7f7;
        }
        
        .edit-profile-title {
            color: #075B5E;
            font-size: 2rem;
            margin: 0 0 0.5rem 0;
            font-weight: 600;
        }
        
        .edit-profile-subtitle {
            color: #666;
            font-size: 1.1rem;
            margin: 0;
        }
        
        /* Form Styling */
        .edit-profile-form {
            margin-top: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1.8rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.75rem;
            font-weight: 500;
            color: #333;
            font-size: 0.95rem;
        }
        
        .form-control {
            width: 100%;
            padding: 0.9rem 1rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
            transition: all 0.3s;
            background: #fcfdfd;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #075B5E;
            box-shadow: 0 0 0 3px rgba(7, 91, 94, 0.1);
            background: white;
        }
        
        .form-control:disabled {
            background: #f5f5f5;
            color: #888;
            cursor: not-allowed;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        
        .form-help {
            display: block;
            margin-top: 0.5rem;
            color: #666;
            font-size: 0.85rem;
            font-style: italic;
        }
        
        /* Form Actions */
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 1px solid #eee;
        }
        
        .btn-primary {
            background: #075B5E;
            color: white;
            padding: 0.9rem 2rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            flex: 1;
            justify-content: center;
        }
        
        .btn-primary:hover {
            background: #054749;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(7, 91, 94, 0.2);
        }
        
        .btn-secondary {
            background: white;
            color: #6c757d;
            padding: 0.9rem 2rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s;
            flex: 1;
            justify-content: center;
        }
        
        .btn-secondary:hover {
            background: #f8f9fa;
            color: #495057;
            border-color: #6c757d;
            transform: translateY(-2px);
        }
        
        /* Alert Messages */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            border-left: 4px solid transparent;
        }
        
        .alert-success {
            background: #f0f9f1;
            color: #155724;
            border-left-color: #27ae60;
        }
        
        .alert-error {
            background: #fdf2f2;
            color: #721c24;
            border-left-color: #e74c3c;
        }
        
        /* Additional Options */
        .additional-options {
            margin-top: 3rem;
            padding: 2rem;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        
        .additional-options h3 {
            color: #075B5E;
            margin: 0 0 1.5rem 0;
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .option-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .option-btn {
            color: #075B5E;
            text-decoration: none;
            padding: 0.9rem 1.5rem;
            background: white;
            border-radius: 6px;
            border: 1px solid #ddd;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .option-btn:hover {
            background: #f0f7f7;
            border-color: #075B5E;
            transform: translateY(-2px);
        }
        
        .option-btn-danger {
            color: #e74c3c;
            border-color: #f5c6cb;
        }
        
        .option-btn-danger:hover {
            background: #f8d7da;
            border-color: #e74c3c;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .edit-profile-container {
                padding: 1rem;
            }
            
            .edit-profile-card {
                padding: 1.5rem;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn-primary,
            .btn-secondary {
                width: 100%;
            }
            
            .option-buttons {
                flex-direction: column;
            }
            
            .option-btn {
                width: 100%;
                justify-content: center;
            }
            
            .edit-profile-title {
                font-size: 1.5rem;
            }
            
            .edit-profile-subtitle {
                font-size: 1rem;
            }
        }
        
        /* Textarea specific styling */
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
            font-family: inherit;
            line-height: 1.5;
        }
        
        /* Input focus states */
        .form-control:focus {
            background: white;
            border-color: #075B5E;
        }
        
        /* Section spacing */
        .form-section {
            margin-bottom: 2.5rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid #eee;
        }
        
        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        /* Field description */
        .field-description {
            display: block;
            margin-top: 0.5rem;
            color: #666;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <div class="edit-profile-wrapper">
        <div class="edit-profile-container">
            <!-- Back Navigation -->
            <div class="back-navigation">
                <a href="dashboard.php" class="back-link">
                    <i data-lucide="arrow-left"></i>
                    Back to Dashboard
                </a>
            </div>
            
            <!-- Main Content Card -->
            <div class="edit-profile-card">
                <!-- Header Section -->
                <div class="edit-profile-header">
                    <h1 class="edit-profile-title">Edit Profile</h1>
                    <p class="edit-profile-subtitle">Update your personal information</p>
                </div>
                
                <!-- Messages -->
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Edit Profile Form -->
                <form method="POST" action="" class="edit-profile-form">
                    <!-- Basic Information Section -->
                    <div class="form-section">
                        <h3 style="color: #075B5E; margin-bottom: 1.5rem; font-size: 1.2rem;">
                            <i data-lucide="user" style="width: 18px; height: 18px; margin-right: 0.5rem;"></i>
                            Basic Information
                        </h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="full_name">Full Name *</label>
                                <input type="text" id="full_name" name="full_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                                <span class="field-description">Your complete name as it should appear on bills</span>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email Address *</label>
                                <input type="email" id="email" name="email" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                <span class="field-description">Used for account notifications and communication</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Contact Information Section -->
                    <div class="form-section">
                        <h3 style="color: #075B5E; margin-bottom: 1.5rem; font-size: 1.2rem;">
                            <i data-lucide="phone" style="width: 18px; height: 18px; margin-right: 0.5rem;"></i>
                            Contact Information
                        </h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="tel" id="phone" name="phone" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" 
                                       pattern="[0-9]{10}" maxlength="10">
                                <span class="field-description">10 digits without spaces or dashes</span>
                            </div>
                            
                            <div class="form-group">
                                <label for="customer_code">Customer Code</label>
                                <input type="text" id="customer_code" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['customer_code'] ?? 'N/A'); ?>" disabled>
                                <span class="field-description">Unique identifier for your account</span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="address">Address</label>
                            <textarea id="address" name="address" class="form-control" rows="4"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                            <span class="field-description">Your complete postal address</span>
                        </div>
                    </div>
                    
                    <!-- Account Information Section -->
                    <div class="form-section">
                        <h3 style="color: #075B5E; margin-bottom: 1.5rem; font-size: 1.2rem;">
                            <i data-lucide="info" style="width: 18px; height: 18px; margin-right: 0.5rem;"></i>
                            Account Information
                        </h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Account Created</label>
                                <input type="text" class="form-control" 
                                       value="<?php echo date('F d, Y', strtotime($user['created_at'])); ?>" disabled>
                                <span class="field-description">Date you joined BillPay Pro</span>
                            </div>
                            
                            <div class="form-group">
                                <label>Last Updated</label>
                                <input type="text" class="form-control" 
                                       value="<?php echo !empty($user['updated_at']) ? date('F d, Y', strtotime($user['updated_at'])) : 'Never'; ?>" disabled>
                                <span class="field-description">When your profile was last modified</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="form-actions">
                        <button type="submit" class="btn-primary">
                            <i data-lucide="save"></i> Save Changes
                        </button>
                        <a href="dashboard.php" class="btn-secondary">
                            <i data-lucide="x"></i> Cancel
                        </a>
                    </div>
                </form>
                
                <!-- Additional Options -->
                <div class="additional-options">
                    <h3>
                        <i data-lucide="settings"></i>
                        Account Management
                    </h3>
                    <div class="option-buttons">
                        <a href="change_password.php" class="option-btn">
                            <i data-lucide="key"></i> Change Password
                        </a>
                        <a href="delete_account.php" class="option-btn option-btn-danger">
                            <i data-lucide="trash-2"></i> Delete Account
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const phone = document.getElementById('phone').value;
            const email = document.getElementById('email').value;
            let isValid = true;
            
            // Validate phone if provided
            if (phone && !/^[0-9]{10}$/.test(phone)) {
                alert('Please enter a valid 10-digit phone number (numbers only, no spaces or dashes).');
                isValid = false;
            }
            
            // Validate email format
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                alert('Please enter a valid email address.');
                isValid = false;
            }
            
            // Validate name
            const name = document.getElementById('full_name').value;
            if (name.trim().length < 2) {
                alert('Please enter a valid full name.');
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
                return false;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i data-lucide="loader-2" style="animation: spin 1s linear infinite;"></i> Saving...';
            submitBtn.disabled = true;
            
            // Create spin animation
            const style = document.createElement('style');
            style.textContent = `
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
            `;
            document.head.appendChild(style);
            
            return true;
        });
        
        // Auto-format phone number
        document.getElementById('phone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 10) {
                value = value.substring(0, 10);
            }
            e.target.value = value;
        });
        
        // Real-time email validation
        document.getElementById('email').addEventListener('blur', function(e) {
            const email = e.target.value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (email && !emailRegex.test(email)) {
                e.target.style.borderColor = '#e74c3c';
                e.target.style.boxShadow = '0 0 0 3px rgba(231, 76, 60, 0.1)';
            } else {
                e.target.style.borderColor = '#ddd';
                e.target.style.boxShadow = 'none';
            }
        });
        
        // Add character counter for address
        const addressField = document.getElementById('address');
        const charCounter = document.createElement('div');
        charCounter.className = 'field-description';
        charCounter.style.marginTop = '0.5rem';
        charCounter.style.color = '#666';
        charCounter.style.fontSize = '0.85rem';
        charCounter.style.textAlign = 'right';
        
        function updateCharCount() {
            const count = addressField.value.length;
            charCounter.textContent = `${count} characters`;
            if (count > 500) {
                charCounter.style.color = '#e74c3c';
            } else if (count > 250) {
                charCounter.style.color = '#f39c12';
            } else {
                charCounter.style.color = '#666';
            }
        }
        
        addressField.addEventListener('input', updateCharCount);
        addressField.parentNode.appendChild(charCounter);
        updateCharCount(); // Initialize count
    </script>
</body>
</html>