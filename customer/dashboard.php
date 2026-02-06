<?php
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

// Get user details including approval status directly from database
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Check if user exists
if (!$user) {
    // User not found in database
    session_destroy();
    $_SESSION['logout_message'] = "User account not found. Please register again.";
    header("Location: ../auth/login.php");
    exit();
}

// Check if account has been deactivated
if (!empty($user['deleted_at'])) {
    // Account has been deactivated
    session_destroy();
    $_SESSION['logout_message'] = "Account isn't available. Please register first.";
    header("Location: login.php");
    exit();
}

// Check if email is verified
if (!$user['email_verified']) {
    // Email not verified - redirect to verification page or show message
    $_SESSION['verification_email'] = $user['email'];
    header("Location: verify_email.php");
    exit();
}

// Check if admin has approved
if (!$user['admin_approved'] || $user['registration_status'] != 'approved') {
    // Not approved by admin - redirect to pending approval page
    header("Location: pending_approval.php");
    exit();
}

// Check if account is active
if (isset($user['is_active']) && !$user['is_active']) {
    // Account deactivated
    $_SESSION['logout_message'] = "Your account has been deactivated. Please contact support.";
    session_destroy();
    header("Location: ../auth/login.php");
    exit();
}

// Update login count and last login
$stmt = $pdo->prepare("UPDATE users SET login_count = COALESCE(login_count, 0) + 1, last_login = NOW() WHERE id = ?");
$stmt->execute([$user_id]);

// Get updated user details including login count
$stmt = $pdo->prepare("SELECT *, COALESCE(login_count, 0) as login_count FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Determine welcome message based on login count
if ($user['login_count'] <= 1) {
    $welcome_message = "Welcome to BillPay Pro, " . htmlspecialchars($user['full_name']) . "!";
    $welcome_subtitle = "We're excited to have you onboard. Let's get started with your billing dashboard.";
} else {
    $welcome_message = "Welcome back, " . htmlspecialchars($user['full_name']) . "!";
    $welcome_subtitle = "Here's your billing overview and quick actions";
}

// Get user statistics - Check if invoices table exists
try {
    // Total pending invoices
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_pending, COALESCE(SUM(total_amount), 0) as total_amount 
                           FROM invoices WHERE user_id = ? AND payment_status IN ('pending', 'due')");
    $stmt->execute([$user_id]);
    $pending_stats = $stmt->fetch();
} catch (Exception $e) {
    // If invoices table doesn't exist, try bills table
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total_pending, COALESCE(SUM(total_amount), 0) as total_amount 
                               FROM bills WHERE customer_id = ? AND status IN ('pending', 'overdue')");
        $stmt->execute([$user_id]);
        $pending_stats = $stmt->fetch();
    } catch (Exception $e2) {
        $pending_stats = ['total_pending' => 0, 'total_amount' => 0];
        error_log("Bills query error: " . $e2->getMessage());
    }
}

try {
    // Total paid invoices
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_paid, COALESCE(SUM(total_amount), 0) as paid_amount 
                           FROM invoices WHERE user_id = ? AND payment_status = 'paid'");
    $stmt->execute([$user_id]);
    $paid_stats = $stmt->fetch();
} catch (Exception $e) {
    // Try bills table
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total_paid, COALESCE(SUM(total_amount), 0) as paid_amount 
                               FROM bills WHERE customer_id = ? AND status = 'paid'");
        $stmt->execute([$user_id]);
        $paid_stats = $stmt->fetch();
    } catch (Exception $e2) {
        $paid_stats = ['total_paid' => 0, 'paid_amount' => 0];
    }
}

try {
    // Recent invoices (last 5) - try invoices table first
    $stmt = $pdo->prepare("SELECT * FROM invoices WHERE user_id = ? ORDER BY due_date ASC LIMIT 5");
    $stmt->execute([$user_id]);
    $recent_invoices = $stmt->fetchAll();
    
    if (empty($recent_invoices)) {
        // Try bills table
        $stmt = $pdo->prepare("SELECT * FROM bills WHERE customer_id = ? ORDER BY due_date ASC LIMIT 5");
        $stmt->execute([$user_id]);
        $recent_invoices = $stmt->fetchAll();
    }
} catch (Exception $e) {
    $recent_invoices = [];
}

try {
    // Recent payments (last 3)
    $stmt = $pdo->prepare("SELECT p.*, i.invoice_number, b.bill_number 
                          FROM payments p 
                          LEFT JOIN invoices i ON p.invoice_id = i.id 
                          LEFT JOIN bills b ON p.bill_id = b.id 
                          WHERE p.user_id = ? 
                          ORDER BY p.payment_date DESC 
                          LIMIT 3");
    $stmt->execute([$user_id]);
    $recent_payments = $stmt->fetchAll();
} catch (Exception $e) {
    $recent_payments = [];
}

// Check if this is first login (for special first-time message)
$is_first_login = ($user['login_count'] == 1);

// Get first letters of both first and last names
$first_name = $user['full_name'];
$names = explode(' ', $first_name);
$initials = '';
foreach ($names as $n) {
    $initials .= strtoupper(substr($n, 0, 1));
}
$first_letter = strtoupper(substr($initials, 0, 2)); // Get first two initials

// Get notification count
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = FALSE");
    $stmt->execute([$user_id]);
    $notification_result = $stmt->fetch();
    $notification_count = $notification_result['count'] ?? 0;
} catch (Exception $e) {
    $notification_count = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - BillPay Pro</title>
    
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
        
        .welcome-card {
            background: linear-gradient(135deg, #075B5E 0%, #0a7a7e 100%);
            color: white;
            border-radius: 10px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(7, 91, 94, 0.2);
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border: none;
            transition: transform 0.3s, box-shadow 0.3s;
            height: 100%;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .stat-card h3 {
            font-size: 2rem;
            font-weight: 700;
            color: #075B5E;
            margin-bottom: 0.5rem;
        }
        
        .stat-card p {
            color: #666;
            margin: 0;
            font-size: 0.9rem;
        }
        
        .alert {
            border-radius: 8px;
            border: none;
            padding: 1rem 1.5rem;
        }
        
        .table-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .table th {
            border-top: none;
            color: #075B5E;
            font-weight: 600;
            padding: 1rem;
        }
        
        .table td {
            padding: 1rem;
            vertical-align: middle;
        }
        
        .badge-pending {
            background: #fff3cd;
            color: #856404;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-weight: 500;
        }
        
        .badge-paid {
            background: #d4edda;
            color: #155724;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-weight: 500;
        }
        
        .sidebar {
            background: white;
            min-height: calc(100vh - 70px);
            box-shadow: 1px 0 5px rgba(0,0,0,0.05);
            padding-top: 2rem;
        }
        
        .nav-sidebar .nav-link {
            color: #333;
            padding: 0.8rem 1.5rem;
            margin: 0.2rem 0;
            border-radius: 0;
            border-left: 4px solid transparent;
            display: flex;
            align-items: center;
        }
        
        .nav-sidebar .nav-link i {
            width: 20px;
            margin-right: 10px;
            text-align: center;
        }
        
        .nav-sidebar .nav-link:hover {
            background: #f8f9fa;
            color: #075B5E;
            border-left-color: #075B5E;
        }
        
        .nav-sidebar .nav-link.active {
            background: #f0f7f7;
            color: #075B5E;
            border-left-color: #075B5E;
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
        
        .main-content {
            padding-top: 2rem;
            min-height: calc(100vh - 70px);
        }
        
        .footer {
            background: #f8f9fa;
            padding: 1.5rem 0;
            margin-top: 3rem;
            border-top: 1px solid #e9ecef;
        }
        
        .first-time-alert {
            background: rgba(255,255,255,0.2);
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
            backdrop-filter: blur(10px);
        }
        
        @media (max-width: 768px) {
            .sidebar {
                min-height: auto;
                padding-top: 1rem;
            }
            
            .main-content {
                padding-top: 1rem;
            }
            
            .welcome-card {
                padding: 1.5rem;
            }
        }
        
        /* Quick action buttons */
        .quick-action {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
            display: block;
            text-decoration: none;
            color: inherit;
            transition: all 0.3s;
        }
        
        .quick-action:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-decoration: none;
            color: inherit;
            border-color: #075B5E;
        }
        
        .quick-action i {
            font-size: 2rem;
            color: #075B5E;
            margin-bottom: 1rem;
        }
        
        .quick-action h4 {
            color: #075B5E;
            font-weight: 600;
            margin-bottom: 0.5rem;
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
                        <a class="nav-link active" href="dashboard.php">
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
        <!-- ADDED: Change Password option -->
        <li><a class="dropdown-item" href="change_password.php"><i class="fas fa-key me-2"></i> Change Password</a></li>
        <!-- ADDED: Delete Account option -->
        <li><a class="dropdown-item text-danger" href="delete_account.php"><i class="fas fa-user-times me-2"></i> Delete Account</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i> Settings</a></li>
        <!-- UPDATED: Changed from notifications.php to notification.php -->
        <li><a class="dropdown-item" href="notification.php">
            <i class="fas fa-bell me-2"></i> Notifications
            <?php if ($notification_count > 0): ?>
                <span class="badge bg-danger float-end"><?php echo $notification_count; ?></span>
            <?php endif; ?>
        </a></li>
        <li><hr class="dropdown-divider"></li>
        <!-- UPDATED LOGOUT LINK -->
        <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
    </ul>
</li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-none d-md-block sidebar">
                <div class="position-sticky">
                    <ul class="nav flex-column nav-sidebar">
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="bills.php">
                                <i class="fas fa-file-invoice-dollar"></i> My Bills
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="payment.php">
                                <i class="fas fa-credit-card"></i> Make Payment
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="payment_history.php">
                                <i class="fas fa-history"></i> Payment History
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="profile.php">
                                <i class="fas fa-user"></i> Profile
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="notification.php">
                                <i class="fas fa-bell"></i> Notifications
                                <?php if ($notification_count > 0): ?>
                                    <span class="badge bg-danger float-end"><?php echo $notification_count; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <!-- Optional: Add logout to sidebar too -->
                        <li class="nav-item d-md-none">
                            <a class="nav-link text-danger" href="logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 main-content">
                <!-- Welcome Section -->
                <div class="welcome-card">
                    <h1><?php echo $welcome_message; ?></h1>
                    <p class="lead"><?php echo $welcome_subtitle; ?></p>
                    
                    <?php if ($is_first_login): ?>
                        <div class="first-time-alert">
                            <strong>First Time Here!</strong>
                            <p class="mb-0">Welcome to our platform! Get started by viewing your invoices and making payments.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Payment Alert -->
                <?php if ($pending_stats['total_pending'] > 0): ?>
                <div class="alert alert-warning alert-dismissible fade show">
                    <strong>Action Required!</strong> You have <?php echo $pending_stats['total_pending']; ?> pending invoice(s) totaling ₹<?php echo number_format($pending_stats['total_amount'], 2); ?>
                    <a href="payment.php" class="btn btn-primary btn-sm ms-3">Pay Now</a>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-card text-center">
                            <h3><?php echo $pending_stats['total_pending']; ?></h3>
                            <p>Pending Invoices</p>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-card text-center">
                            <h3>₹<?php echo number_format($pending_stats['total_amount'], 2); ?></h3>
                            <p>Total Due</p>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-card text-center">
                            <h3><?php echo $paid_stats['total_paid']; ?></h3>
                            <p>Paid Invoices</p>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-card text-center">
                            <h3>₹<?php echo number_format($paid_stats['paid_amount'], 2); ?></h3>
                            <p>Total Paid</p>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <a href="bills.php" class="quick-action">
                            <i class="fas fa-file-invoice-dollar"></i>
                            <h4>View Invoices</h4>
                            <p class="text-muted mb-0">Check all your invoices</p>
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a href="payment.php" class="quick-action">
                            <i class="fas fa-credit-card"></i>
                            <h4>Make Payment</h4>
                            <p class="text-muted mb-0">Pay pending invoices</p>
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a href="payment_history.php" class="quick-action">
                            <i class="fas fa-history"></i>
                            <h4>Payment History</h4>
                            <p class="text-muted mb-0">View past transactions</p>
                        </a>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="row">
                    <!-- Recent Invoices -->
                    <div class="col-lg-6 mb-4">
                        <div class="table-card">
                            <h4 class="mb-3">Recent Invoices</h4>
                            <?php if (count($recent_invoices) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Invoice #</th>
                                                <th>Amount</th>
                                                <th>Due Date</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_invoices as $invoice): 
                                                $invoice_number = $invoice['invoice_number'] ?? $invoice['bill_number'] ?? 'N/A';
                                                $amount = $invoice['total_amount'] ?? $invoice['amount'] ?? 0;
                                                $due_date = isset($invoice['due_date']) ? date('M d, Y', strtotime($invoice['due_date'])) : 'N/A';
                                                $status = $invoice['payment_status'] ?? $invoice['status'] ?? 'pending';
                                            ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($invoice_number); ?></td>
                                                    <td>₹<?php echo number_format($amount, 2); ?></td>
                                                    <td><?php echo $due_date; ?></td>
                                                    <td>
                                                        <?php if ($status == 'pending' || $status == 'due' || $status == 'overdue'): ?>
                                                            <span class="badge-pending">Pending</span>
                                                        <?php else: ?>
                                                            <span class="badge-paid">Paid</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="bills.php" class="btn btn-outline-primary">View All Invoices</a>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No invoices found.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Recent Payments -->
                    <div class="col-lg-6 mb-4">
                        <div class="table-card">
                            <h4 class="mb-3">Recent Payments</h4>
                            <?php if (count($recent_payments) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Invoice #</th>
                                                <th>Amount</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_payments as $payment): 
                                                $invoice_number = $payment['invoice_number'] ?? $payment['bill_number'] ?? 'N/A';
                                                $amount = $payment['payment_amount'] ?? $payment['amount'] ?? 0;
                                                $payment_date = isset($payment['payment_date']) ? date('M d, Y', strtotime($payment['payment_date'])) : 'N/A';
                                                $status = $payment['status'] ?? 'completed';
                                            ?>
                                                <tr>
                                                    <td><?php echo $payment_date; ?></td>
                                                    <td><?php echo htmlspecialchars($invoice_number); ?></td>
                                                    <td>₹<?php echo number_format($amount, 2); ?></td>
                                                    <td>
                                                        <span class="badge-paid">Paid</span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="payment_history.php" class="btn btn-outline-primary">View Full History</a>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No payments yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
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
            var alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
            alerts.forEach(function(alert) {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // Mobile sidebar toggle
        document.addEventListener('DOMContentLoaded', function() {
            // Highlight active sidebar item
            var currentPage = window.location.pathname.split('/').pop();
            var sidebarLinks = document.querySelectorAll('.nav-sidebar .nav-link');
            
            sidebarLinks.forEach(function(link) {
                var linkPage = link.getAttribute('href');
                if (linkPage === currentPage) {
                    link.classList.add('active');
                }
            });
        });
    </script>
    <script src="js/logout.js"></script>
</body>
</html>