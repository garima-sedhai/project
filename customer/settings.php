<?php
// Filename: customer/settings.php
// For customer files:
$base_path = dirname(__DIR__);
require_once $base_path . '/includes/config.php';
require_once $base_path . '/includes/db_connection.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Check if user is admin trying to access customer dashboard
if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1) {
    header("Location: ../admin/dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Check if user exists
if (!$user) {
    session_destroy();
    $_SESSION['logout_message'] = "User account not found. Please register again.";
    header("Location: ../auth/login.php");
    exit();
}

// Get first letters of both first and last names
$first_name = $user['full_name'];
$names = explode(' ', $first_name);
$initials = '';
foreach ($names as $n) {
    $initials .= strtoupper(substr($n, 0, 1));
}
$first_letter = strtoupper(substr($initials, 0, 2));

// Get notification count
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = FALSE");
    $stmt->execute([$user_id]);
    $notification_result = $stmt->fetch();
    $notification_count = $notification_result['count'] ?? 0;
} catch (Exception $e) {
    $notification_count = 0;
}

// Initialize variables
$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle notification settings update
    if (isset($_POST['update_notifications'])) {
        $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
        $sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
        $invoice_reminders = isset($_POST['invoice_reminders']) ? 1 : 0;
        $payment_receipts = isset($_POST['payment_receipts']) ? 1 : 0;
        $newsletter = isset($_POST['newsletter']) ? 1 : 0;
        
        try {
            // First, check if settings exist for this user
            $stmt = $pdo->prepare("SELECT id FROM user_settings WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $settings_exist = $stmt->fetch();
            
            if ($settings_exist) {
                // Update existing settings
                $stmt = $pdo->prepare("UPDATE user_settings SET 
                    email_notifications = ?,
                    sms_notifications = ?,
                    invoice_reminders = ?,
                    payment_receipts = ?,
                    newsletter = ?,
                    updated_at = NOW()
                    WHERE user_id = ?");
                $stmt->execute([
                    $email_notifications,
                    $sms_notifications,
                    $invoice_reminders,
                    $payment_receipts,
                    $newsletter,
                    $user_id
                ]);
            } else {
                // Insert new settings
                $stmt = $pdo->prepare("INSERT INTO user_settings 
                    (user_id, email_notifications, sms_notifications, invoice_reminders, payment_receipts, newsletter)
                    VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $user_id,
                    $email_notifications,
                    $sms_notifications,
                    $invoice_reminders,
                    $payment_receipts,
                    $newsletter
                ]);
            }
            
            $success_message = "Notification settings updated successfully!";
        } catch (Exception $e) {
            $error_message = "Error updating settings: " . $e->getMessage();
        }
    }
    
    // Handle privacy settings update
    if (isset($_POST['update_privacy'])) {
        $profile_visibility = $_POST['profile_visibility'] ?? 'private';
        $show_payment_history = isset($_POST['show_payment_history']) ? 1 : 0;
        $allow_data_collection = isset($_POST['allow_data_collection']) ? 1 : 0;
        
        try {
            // Update or insert privacy settings
            $stmt = $pdo->prepare("INSERT INTO user_privacy_settings 
                (user_id, profile_visibility, show_payment_history, allow_data_collection, updated_at)
                VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                profile_visibility = VALUES(profile_visibility),
                show_payment_history = VALUES(show_payment_history),
                allow_data_collection = VALUES(allow_data_collection),
                updated_at = VALUES(updated_at)");
            $stmt->execute([
                $user_id,
                $profile_visibility,
                $show_payment_history,
                $allow_data_collection
            ]);
            
            $success_message = "Privacy settings updated successfully!";
        } catch (Exception $e) {
            $error_message = "Error updating privacy settings: " . $e->getMessage();
        }
    }
    
    // Handle interface preferences update
    if (isset($_POST['update_interface'])) {
        $theme = $_POST['theme'] ?? 'light';
        $language = $_POST['language'] ?? 'en';
        $timezone = $_POST['timezone'] ?? 'UTC';
        $date_format = $_POST['date_format'] ?? 'Y-m-d';
        
        try {
            // Update or insert interface preferences
            $stmt = $pdo->prepare("INSERT INTO user_preferences 
                (user_id, theme, language, timezone, date_format, updated_at)
                VALUES (?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                theme = VALUES(theme),
                language = VALUES(language),
                timezone = VALUES(timezone),
                date_format = VALUES(date_format),
                updated_at = VALUES(updated_at)");
            $stmt->execute([
                $user_id,
                $theme,
                $language,
                $timezone,
                $date_format
            ]);
            
            // Store theme in session for immediate effect
            $_SESSION['theme'] = $theme;
            
            $success_message = "Interface preferences updated successfully!";
        } catch (Exception $e) {
            $error_message = "Error updating interface preferences: " . $e->getMessage();
        }
    }
    
    // Handle data export request
    if (isset($_POST['export_data'])) {
        try {
            // Gather user data
            $user_data = [
                'profile' => $user,
                'invoices' => [],
                'payments' => [],
                'notifications' => []
            ];
            
            // Get invoices
            $stmt = $pdo->prepare("SELECT * FROM invoices WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->execute([$user_id]);
            $user_data['invoices'] = $stmt->fetchAll();
            
            // Get payments
            $stmt = $pdo->prepare("SELECT p.*, i.invoice_number FROM payments p 
                                  LEFT JOIN invoices i ON p.invoice_id = i.id 
                                  WHERE p.user_id = ? ORDER BY payment_date DESC");
            $stmt->execute([$user_id]);
            $user_data['payments'] = $stmt->fetchAll();
            
            // Get notifications
            $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->execute([$user_id]);
            $user_data['notifications'] = $stmt->fetchAll();
            
            // Create JSON file
            $json_data = json_encode($user_data, JSON_PRETTY_PRINT);
            $filename = "user_data_" . $user_id . "_" . date('Y-m-d') . ".json";
            
            // Force download
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($json_data));
            echo $json_data;
            exit();
            
        } catch (Exception $e) {
            $error_message = "Error exporting data: " . $e->getMessage();
        }
    }
}

// Get current settings
try {
    $stmt = $pdo->prepare("SELECT * FROM user_settings WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $settings = $stmt->fetch() ?: [];
    
    $stmt = $pdo->prepare("SELECT * FROM user_privacy_settings WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $privacy_settings = $stmt->fetch() ?: [];
    
    $stmt = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $preferences = $stmt->fetch() ?: [];
} catch (Exception $e) {
    // Tables might not exist, use default values
    $settings = [];
    $privacy_settings = [];
    $preferences = [];
}

// Set default values if not set
$settings = array_merge([
    'email_notifications' => 1,
    'sms_notifications' => 0,
    'invoice_reminders' => 1,
    'payment_receipts' => 1,
    'newsletter' => 0
], $settings);

$privacy_settings = array_merge([
    'profile_visibility' => 'private',
    'show_payment_history' => 0,
    'allow_data_collection' => 1
], $privacy_settings);

$preferences = array_merge([
    'theme' => 'light',
    'language' => 'en',
    'timezone' => 'UTC',
    'date_format' => 'Y-m-d'
], $preferences);

// Timezone options
$timezones = [
    'UTC' => 'UTC',
    'America/New_York' => 'Eastern Time',
    'America/Chicago' => 'Central Time',
    'America/Denver' => 'Mountain Time',
    'America/Los_Angeles' => 'Pacific Time',
    'Europe/London' => 'London',
    'Europe/Paris' => 'Paris',
    'Asia/Kolkata' => 'India',
    'Asia/Tokyo' => 'Tokyo',
    'Australia/Sydney' => 'Sydney'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - BillPay Pro</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <style>
        body {
            background-color: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
        }
        
        .navbar {
            background: #075B5E !important;
            padding: 0.8rem 0;
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: white !important;
        }
        
        .navbar-nav .nav-link {
            color: rgba(255,255,255,0.9) !important;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            margin: 0 0.2rem;
        }
        
        .navbar-nav .nav-link:hover {
            background: rgba(255,255,255,0.1);
            color: white !important;
        }
        
        .navbar-nav .nav-link.active {
            background: rgba(255,255,255,0.2);
            color: white !important;
        }
        
        .settings-container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .settings-card {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border: none;
        }
        
        .settings-header {
            display: flex;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f7f7;
        }
        
        .settings-header i {
            color: #075B5E;
            font-size: 1.5rem;
            margin-right: 1rem;
        }
        
        .settings-header h1 {
            color: #075B5E;
            font-weight: 600;
            margin: 0;
        }
        
        .settings-section {
            margin-bottom: 2.5rem;
        }
        
        .settings-section h3 {
            color: #075B5E;
            font-weight: 600;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .form-check {
            margin-bottom: 1rem;
            padding-left: 2.5rem;
        }
        
        .form-check-input:checked {
            background-color: #075B5E;
            border-color: #075B5E;
        }
        
        .form-check-label {
            color: #333;
            font-weight: 500;
        }
        
        .form-check .form-text {
            color: #666;
            margin-left: 0;
            margin-top: 0.25rem;
        }
        
        .form-select {
            border-radius: 6px;
            border: 1px solid #dee2e6;
            padding: 0.5rem 1rem;
        }
        
        .btn-primary {
            background: #075B5E;
            border: none;
            padding: 0.5rem 2rem;
            border-radius: 6px;
            font-weight: 500;
        }
        
        .btn-primary:hover {
            background: #05484a;
        }
        
        .btn-outline-primary {
            color: #075B5E;
            border-color: #075B5E;
            padding: 0.5rem 2rem;
            border-radius: 6px;
            font-weight: 500;
        }
        
        .btn-outline-primary:hover {
            background: #075B5E;
            border-color: #075B5E;
        }
        
        .danger-zone {
            background: #fff5f5;
            border: 2px solid #feb2b2;
            border-radius: 10px;
            padding: 2rem;
            margin-top: 3rem;
        }
        
        .danger-zone h3 {
            color: #c53030;
            border-bottom: none;
        }
        
        .danger-zone p {
            color: #666;
        }
        
        .btn-danger {
            background: #c53030;
            border: none;
            padding: 0.5rem 2rem;
            border-radius: 6px;
            font-weight: 500;
        }
        
        .user-menu {
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
            cursor: pointer;
        }
        
        .user-avatar {
            width: 35px;
            height: 35px;
            background: white;
            color: #075B5E;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.9rem;
        }
        
        .footer {
            background: #f8f9fa;
            padding: 1.5rem 0;
            margin-top: 3rem;
            border-top: 1px solid #e9ecef;
        }
        
        @media (max-width: 768px) {
            .settings-container {
                margin: 1rem auto;
                padding: 0 0.5rem;
            }
            
            .settings-card {
                padding: 1.5rem;
            }
            
            .settings-header {
                flex-direction: column;
                text-align: center;
            }
            
            .settings-header i {
                margin-right: 0;
                margin-bottom: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-file-invoice-dollar"></i> BillPay Pro
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="bills.php">
                            <i class="fas fa-file-invoice-dollar"></i> My Bills
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="payment_history.php">
                            <i class="fas fa-history"></i> Payment History
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <div class="user-menu">
                                <div class="user-avatar">
                                    <?php echo $first_letter; ?>
                                </div>
                                <span><?php echo htmlspecialchars($user['full_name']); ?></span>
                                <?php if ($notification_count > 0): ?>
                                    <span class="badge bg-danger ms-1"><?php echo $notification_count; ?></span>
                                <?php endif; ?>
                            </div>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i> My Profile</a></li>
                            <li><a class="dropdown-item" href="change_password.php"><i class="fas fa-key me-2"></i> Change Password</a></li>
                            <li><a class="dropdown-item text-danger" href="delete_account.php"><i class="fas fa-user-times me-2"></i> Delete Account</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item active" href="settings.php"><i class="fas fa-cog me-2"></i> Settings</a></li>
                            <li><a class="dropdown-item" href="notification.php">
                                <i class="fas fa-bell me-2"></i> Notifications
                                <?php if ($notification_count > 0): ?>
                                    <span class="badge bg-danger float-end"><?php echo $notification_count; ?></span>
                                <?php endif; ?>
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="settings-container">
        <div class="settings-card">
            <div class="settings-header">
                <i class="fas fa-cog"></i>
                <h1>Account Settings</h1>
            </div>

            <!-- Success/Error Messages -->
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Notification Settings -->
            <div class="settings-section">
                <h3><i class="fas fa-bell me-2"></i>Notification Settings</h3>
                <form method="POST">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="email_notifications" id="email_notifications" 
                               <?php echo $settings['email_notifications'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="email_notifications">
                            Email Notifications
                        </label>
                        <div class="form-text">Receive important updates via email</div>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="sms_notifications" id="sms_notifications"
                               <?php echo $settings['sms_notifications'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="sms_notifications">
                            SMS Notifications
                        </label>
                        <div class="form-text">Receive text message alerts (standard rates may apply)</div>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="invoice_reminders" id="invoice_reminders"
                               <?php echo $settings['invoice_reminders'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="invoice_reminders">
                            Invoice Reminders
                        </label>
                        <div class="form-text">Get reminders for upcoming and overdue invoices</div>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="payment_receipts" id="payment_receipts"
                               <?php echo $settings['payment_receipts'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="payment_receipts">
                            Payment Receipts
                        </label>
                        <div class="form-text">Receive receipts for successful payments</div>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="newsletter" id="newsletter"
                               <?php echo $settings['newsletter'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="newsletter">
                            Newsletter
                        </label>
                        <div class="form-text">Receive monthly newsletters and updates</div>
                    </div>
                    
                    <button type="submit" name="update_notifications" class="btn btn-primary mt-3">
                        <i class="fas fa-save me-2"></i>Save Notification Settings
                    </button>
                </form>
            </div>

            <!-- Privacy Settings -->
            <div class="settings-section">
                <h3><i class="fas fa-shield-alt me-2"></i>Privacy Settings</h3>
                <form method="POST">
                    <div class="mb-3">
                        <label for="profile_visibility" class="form-label">Profile Visibility</label>
                        <select class="form-select" name="profile_visibility" id="profile_visibility">
                            <option value="private" <?php echo $privacy_settings['profile_visibility'] == 'private' ? 'selected' : ''; ?>>Private (Only you)</option>
                            <option value="admin_only" <?php echo $privacy_settings['profile_visibility'] == 'admin_only' ? 'selected' : ''; ?>>Administrators only</option>
                            <option value="public" <?php echo $privacy_settings['profile_visibility'] == 'public' ? 'selected' : ''; ?>>Public (Visible to all users)</option>
                        </select>
                        <div class="form-text">Control who can see your profile information</div>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="show_payment_history" id="show_payment_history"
                               <?php echo $privacy_settings['show_payment_history'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="show_payment_history">
                            Show Payment History
                        </label>
                        <div class="form-text">Allow administrators to view your payment history</div>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="allow_data_collection" id="allow_data_collection"
                               <?php echo $privacy_settings['allow_data_collection'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="allow_data_collection">
                            Allow Data Collection
                        </label>
                        <div class="form-text">Allow us to collect anonymous usage data to improve our services</div>
                    </div>
                    
                    <button type="submit" name="update_privacy" class="btn btn-primary mt-3">
                        <i class="fas fa-save me-2"></i>Save Privacy Settings
                    </button>
                </form>
            </div>

            <!-- Interface Preferences -->
            <div class="settings-section">
                <h3><i class="fas fa-paint-brush me-2"></i>Interface Preferences</h3>
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="theme" class="form-label">Theme</label>
                            <select class="form-select" name="theme" id="theme">
                                <option value="light" <?php echo $preferences['theme'] == 'light' ? 'selected' : ''; ?>>Light Mode</option>
                                <option value="dark" <?php echo $preferences['theme'] == 'dark' ? 'selected' : ''; ?>>Dark Mode</option>
                                <option value="auto" <?php echo $preferences['theme'] == 'auto' ? 'selected' : ''; ?>>Auto (System Preference)</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="language" class="form-label">Language</label>
                            <select class="form-select" name="language" id="language">
                                <option value="en" <?php echo $preferences['language'] == 'en' ? 'selected' : ''; ?>>English</option>
                                <option value="es" <?php echo $preferences['language'] == 'es' ? 'selected' : ''; ?>>Spanish</option>
                                <option value="fr" <?php echo $preferences['language'] == 'fr' ? 'selected' : ''; ?>>French</option>
                                <option value="de" <?php echo $preferences['language'] == 'de' ? 'selected' : ''; ?>>German</option>
                                <option value="hi" <?php echo $preferences['language'] == 'hi' ? 'selected' : ''; ?>>Hindi</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="timezone" class="form-label">Timezone</label>
                            <select class="form-select" name="timezone" id="timezone">
                                <?php foreach ($timezones as $value => $label): ?>
                                    <option value="<?php echo $value; ?>" <?php echo $preferences['timezone'] == $value ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="date_format" class="form-label">Date Format</label>
                            <select class="form-select" name="date_format" id="date_format">
                                <option value="Y-m-d" <?php echo $preferences['date_format'] == 'Y-m-d' ? 'selected' : ''; ?>>YYYY-MM-DD (2024-01-09)</option>
                                <option value="m/d/Y" <?php echo $preferences['date_format'] == 'm/d/Y' ? 'selected' : ''; ?>>MM/DD/YYYY (01/09/2024)</option>
                                <option value="d/m/Y" <?php echo $preferences['date_format'] == 'd/m/Y' ? 'selected' : ''; ?>>DD/MM/YYYY (09/01/2024)</option>
                                <option value="F j, Y" <?php echo $preferences['date_format'] == 'F j, Y' ? 'selected' : ''; ?>>Month Day, Year (January 9, 2024)</option>
                            </select>
                        </div>
                    </div>
                    
                    <button type="submit" name="update_interface" class="btn btn-primary mt-3">
                        <i class="fas fa-save me-2"></i>Save Interface Preferences
                    </button>
                </form>
            </div>

            <!-- Data Management -->
            <div class="settings-section">
                <h3><i class="fas fa-database me-2"></i>Data Management</h3>
                <div class="row">
                    <div class="col-md-6">
                        <div class="card border-light mb-3">
                            <div class="card-body">
                                <h5 class="card-title">Export Your Data</h5>
                                <p class="card-text">Download all your personal data, including profile information, invoices, and payment history in JSON format.</p>
                                <form method="POST">
                                    <button type="submit" name="export_data" class="btn btn-outline-primary">
                                        <i class="fas fa-download me-2"></i>Export Data
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card border-light mb-3">
                            <div class="card-body">
                                <h5 class="card-title">Clear Cache</h5>
                                <p class="card-text">Clear your browser cache and temporary data stored on our servers.</p>
                                <a href="#" class="btn btn-outline-secondary">
                                    <i class="fas fa-trash-alt me-2"></i>Clear Cache
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Danger Zone -->
            <div class="danger-zone">
                <h3><i class="fas fa-exclamation-triangle me-2"></i>Danger Zone</h3>
                <p class="mb-3">These actions are irreversible. Please proceed with caution.</p>
                
                <div class="d-flex flex-column flex-md-row gap-3">
                    <a href="change_password.php" class="btn btn-warning">
                        <i class="fas fa-key me-2"></i>Change Password
                    </a>
                    
                    <a href="delete_account.php" class="btn btn-danger">
                        <i class="fas fa-user-times me-2"></i>Delete Account
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="text-center">
                <p class="mb-0 text-muted">&copy; <?php echo date('Y'); ?> BillPay Pro - Online Billing System</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script>
        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // Apply theme preference immediately
        const theme = '<?php echo $preferences['theme']; ?>';
        if (theme === 'dark' || (theme === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.body.style.backgroundColor = '#1a1a1a';
            document.body.style.color = '#ffffff';
            // You can add more dark mode styles here
        }
    </script>
    <script src="js/logout.js"></script>
</body>
</html>