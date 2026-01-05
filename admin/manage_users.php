<?php
session_start();
include '../includes/config.php';

// Redirect if not logged in as admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: login.php");
    exit();
}

// Function to highlight search terms
function highlightText($text, $search_term) {
    if (empty($search_term) || empty(trim($search_term))) return $text;
    $pattern = '/(' . preg_quote(trim($search_term), '/') . ')/i';
    return preg_replace($pattern, '<span class="highlight">$1</span>', $text);
}

$message = '';
$message_type = '';

// Handle search
$search_term = '';
$customers = [];

if (isset($_GET['search'])) {
    $search_term = trim($_GET['search']);
    if (!empty($search_term)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE is_admin = FALSE AND 
                              (full_name LIKE ? OR email LIKE ? OR phone LIKE ? OR customer_code LIKE ?) 
                              ORDER BY created_at DESC");
        $search_like = "%$search_term%";
        $stmt->execute([$search_like, $search_like, $search_like, $search_like]);
        $customers = $stmt->fetchAll();
    } else {
        // If search is empty, show all customers
        $stmt = $pdo->query("SELECT * FROM users WHERE is_admin = FALSE ORDER BY created_at DESC");
        $customers = $stmt->fetchAll();
    }
} else {
    // Default: show all customers
    $stmt = $pdo->query("SELECT * FROM users WHERE is_admin = FALSE ORDER BY created_at DESC");
    $customers = $stmt->fetchAll();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_customer'])) {
        // Add new customer
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        
        // Validate inputs
        if (empty($full_name) || empty($email) || empty($phone)) {
            $message = "Please fill all required fields";
            $message_type = "error";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Please enter a valid email address";
            $message_type = "error";
        } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {
            $message = "Please enter a valid 10-digit phone number";
            $message_type = "error";
        } else {
            // Check if phone or email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ? OR email = ?");
            $stmt->execute([$phone, $email]);
            
            if ($stmt->rowCount() > 0) {
                $message = "Phone number or email already exists";
                $message_type = "error";
            } else {
                // Generate unique customer code - FIXED FORMAT
                $customer_code = 'CUST' . date('YmdHis') . rand(100, 999);
                
                // Generate temporary password
                $temp_password = 'password123'; // Simple password for testing
                $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
                
                // Insert customer with ALL required fields
                try {
                    $sql = "INSERT INTO users (email, password, full_name, phone, address, customer_code, is_admin, admin_approved, phone_verified, is_active, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, 0, 1, 0, 1, NOW())";
                    
                    $stmt = $pdo->prepare($sql);
                    $result = $stmt->execute([$email, $hashed_password, $full_name, $phone, $address, $customer_code]);
                    
                    if ($result) {
                        $new_customer_id = $pdo->lastInsertId();
                        $message = "Customer added successfully!<br>
                                   <strong>Customer Code:</strong> <code style='background: #f8f9fa; padding: 5px; border-radius: 3px;'>$customer_code</code><br>
                                   <strong>Temporary Password:</strong> $temp_password<br>
                                   <small>Provide this code to the customer for registration</small>";
                        $message_type = "success";
                        
                        // Store the new customer ID to highlight it
                        $_SESSION['new_customer_id'] = $new_customer_id;
                        
                        // Force refresh to show new customer
                        header("Location: manage_users.php?success=1&code=" . urlencode($customer_code));
                        exit();
                    } else {
                        $message = "Error adding customer to database";
                        $message_type = "error";
                    }
                } catch (Exception $e) {
                    $message = "Database error: " . $e->getMessage();
                    $message_type = "error";
                }
            }
        }
    }
}

// Check for success message from redirect
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $message = "Customer added successfully!";
    if (isset($_GET['code'])) {
        $message .= "<br><strong>Customer Code:</strong> <code style='background: #f8f9fa; padding: 5px; border-radius: 3px;'>" . htmlspecialchars($_GET['code']) . "</code>";
    }
    $message_type = "success";
}

// Get new customer ID from session
$new_customer_id = isset($_SESSION['new_customer_id']) ? $_SESSION['new_customer_id'] : null;
unset($_SESSION['new_customer_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - BillPay Pro</title>
    <link rel="stylesheet" href="../css/style.css">
    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        .user-management {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
            margin-top: 2rem;
        }
        
        .user-card.new-customer {
            border-left-color: #2ecc71;
            animation: pulse 2s infinite;
            background: #f8fff9;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(46, 204, 113, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(46, 204, 113, 0); }
            100% { box-shadow: 0 0 0 0 rgba(46, 204, 113, 0); }
        }
        
        .new-badge {
            background: #2ecc71;
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
            margin-left: 10px;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        .customer-code-display {
            background: #3498db;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-family: monospace;
            font-size: 0.9rem;
            display: inline-block;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .search-container {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            align-items: center;
        }
        
        .search-form {
            display: flex;
            flex: 1;
            gap: 0.5rem;
        }
        
        .search-input {
            flex: 1;
        }
        
        .search-results {
            background: #f8f9fa;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .no-results {
            text-align: center;
            padding: 2rem;
            color: #666;
        }
        
        .highlight {
            background-color: #fff3cd;
            padding: 2px 4px;
            border-radius: 3px;
            font-weight: bold;
        }
        
        .user-grid {
            display: grid;
            gap: 1.5rem;
        }
        
        .user-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #3498db;
        }
        
        .section-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .section-icon {
            width: 2rem;
            height: 2rem;
            color: #3498db;
        }
        
        .form-icon {
            width: 1rem;
            height: 1rem;
            margin-right: 0.5rem;
            vertical-align: middle;
        }
        
        .btn-icon {
            width: 1.2rem;
            height: 1.2rem;
        }
        
        .empty-state-icon {
            width: 4rem;
            height: 4rem;
            color: #6c757d;
            margin: 0 auto 1rem auto;
            display: block;
        }
        
        .status-icon {
            width: 1rem;
            height: 1rem;
            margin-right: 0.3rem;
            vertical-align: middle;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <div class="logo">BillPay Pro</div>
                <ul class="nav-links">
                    <li><a href="index.php">Dashboard</a></li>
                    <li><a href="manage_services.php">Manage Services</a></li>
                    <li><a href="manage_bills.php">Manage Bills</a></li>
                    <li><a href="manage_users.php" style="color: #3498db;">Manage Users</a></li>
                    <li><a href="reports.php">Reports</a></li>
                    <li style="display: flex; align-items: center;">
                        <div class="user-avatar" style="background: #e74c3c;">
                            <?php 
                            $names = explode(' ', $_SESSION['full_name']);
                            $initials = '';
                            foreach ($names as $n) {
                                $initials .= strtoupper(substr($n, 0, 1));
                            }
                            echo substr($initials, 0, 2);
                            ?>
                        </div>
                        <span><?php echo $_SESSION['full_name']; ?></span>
                        <span class="admin-badge">ADMIN</span>
                        <a href="logout.php" style="margin-left: 15px; color: white;">Logout</a>
                    </li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="container">
        <div class="section-header">
            <i data-lucide="users" class="section-icon"></i>
            <h1>Manage Users</h1>
        </div>
        
        <?php if ($message): ?>
            <div class="<?php echo $message_type == 'success' ? 'alert alert-success' : 'alert alert-danger'; ?>">
                <?php if ($message_type == 'success'): ?>
                    <i data-lucide="check-circle" style="width: 1.2rem; height: 1.2rem; margin-right: 0.5rem; vertical-align: middle;"></i>
                <?php else: ?>
                    <i data-lucide="alert-circle" style="width: 1.2rem; height: 1.2rem; margin-right: 0.5rem; vertical-align: middle;"></i>
                <?php endif; ?>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="user-management">
            <!-- Add Customer Form -->
            <div class="card">
                <div class="section-header">
                    <i data-lucide="user-plus" style="width: 1.5rem; height: 1.5rem;"></i>
                    <h2>Add New Customer</h2>
                </div>
                <form method="POST" action="" class="add-customer-form" id="addCustomerForm">
                    <div class="form-group">
                        <label for="full_name">
                            <i data-lucide="user" class="form-icon"></i>
                            Full Name *
                        </label>
                        <input type="text" id="full_name" name="full_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">
                            <i data-lucide="mail" class="form-icon"></i>
                            Email *
                        </label>
                        <input type="email" id="email" name="email" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">
                            <i data-lucide="phone" class="form-icon"></i>
                            Phone Number *
                        </label>
                        <input type="tel" id="phone" name="phone" class="form-control" 
                               pattern="[0-9]{10}" placeholder="98XXXXXXXX" required>
                        <small style="color: #666;">10-digit phone number</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="address">
                            <i data-lucide="map-pin" class="form-icon"></i>
                            Address
                        </label>
                        <textarea id="address" name="address" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <button type="submit" name="add_customer" class="btn" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                        <i data-lucide="user-plus" class="btn-icon"></i>
                        Add Customer
                    </button>
                    
                    <div style="margin-top: 1rem; padding: 1rem; background: #e8f4fd; border-radius: 5px; display: flex; align-items: center; gap: 0.5rem;">
                        <i data-lucide="info" style="width: 1rem; height: 1rem;"></i>
                        <small><strong>Note:</strong> Customer will receive a unique code and temporary password for registration.</small>
                    </div>
                </form>
            </div>

            <!-- Customers List -->
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <div class="section-header">
                        <i data-lucide="list" style="width: 1.5rem; height: 1.5rem;"></i>
                        <h2>All Customers (<?php echo count($customers); ?>)</h2>
                    </div>
                </div>
                
                <!-- Search Form -->
                <div class="search-container">
                    <form method="GET" action="" class="search-form">
                        <div style="position: relative; flex: 1;">
                            <i data-lucide="search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 1.2rem; height: 1.2rem; color: #666;"></i>
                            <input type="text" name="search" class="form-control search-input" style="padding-left: 40px;"
                                   placeholder="Search by name, email, phone, or customer code..." 
                                   value="<?php echo htmlspecialchars($search_term); ?>">
                        </div>
                        <button type="submit" class="btn" style="display: flex; align-items: center; gap: 0.5rem;">
                            <i data-lucide="search" class="btn-icon"></i>
                            Search
                        </button>
                    </form>
                    <?php if (!empty($search_term)): ?>
                        <a href="manage_users.php" class="btn" style="background: #95a5a6; display: flex; align-items: center; gap: 0.5rem;">
                            <i data-lucide="x" class="btn-icon"></i>
                            Clear
                        </a>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($search_term)): ?>
                    <div class="search-results">
                        <i data-lucide="filter" style="width: 1.2rem; height: 1.2rem;"></i>
                        <strong>Search Results for:</strong> "<?php echo htmlspecialchars($search_term); ?>"
                        <span style="color: #666; margin-left: 1rem;">
                            Found <?php echo count($customers); ?> customer(s)
                        </span>
                    </div>
                <?php endif; ?>
                
                <?php if (count($customers) > 0): ?>
                    <div class="user-grid" id="customersList">
                        <?php foreach ($customers as $customer): 
                            $is_new = ($customer['id'] == $new_customer_id);
                        ?>
                            <div class="user-card <?php echo $is_new ? 'new-customer' : ''; ?>" id="customer-<?php echo $customer['id']; ?>">
                                
                                <div class="customer-code-display">
                                    <i data-lucide="id-card" style="width: 1.2rem; height: 1.2rem;"></i>
                                    <?php echo highlightText($customer['customer_code'], $search_term); ?>
                                    <?php if ($is_new): ?>
                                        <span class="new-badge">
                                            <i data-lucide="sparkles" style="width: 1rem; height: 1rem;"></i>
                                            NEW
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div style="display: flex; justify-content: space-between; align-items: start;">
                                    <div style="flex: 1;">
                                        <h3>
                                            <?php echo highlightText($customer['full_name'], $search_term); ?>
                                        </h3>
                                        <p>
                                            <i data-lucide="mail" class="status-icon"></i>
                                            <strong>Email:</strong> 
                                            <?php echo highlightText($customer['email'], $search_term); ?>
                                        </p>
                                        <p>
                                            <i data-lucide="phone" class="status-icon"></i>
                                            <strong>Phone:</strong> 
                                            <?php echo highlightText($customer['phone'], $search_term); ?>
                                        </p>
                                        <p>
                                            <i data-lucide="user-check" class="status-icon"></i>
                                            <strong>Status:</strong> 
                                            <?php if ($customer['phone_verified']): ?>
                                                <i data-lucide="check-circle" style="width: 1rem; height: 1rem; color: #27ae60; margin-left: 0.3rem; vertical-align: middle;"></i>
                                                <span style="color: #27ae60;">Registered</span>
                                            <?php else: ?>
                                                <i data-lucide="x-circle" style="width: 1rem; height: 1rem; color: #e74c3c; margin-left: 0.3rem; vertical-align: middle;"></i>
                                                <span style="color: #e74c3c;">Not Registered</span>
                                            <?php endif; ?>
                                        </p>
                                        <p>
                                            <i data-lucide="key" class="status-icon"></i>
                                            <strong>Customer Code:</strong> 
                                            <code><?php echo highlightText($customer['customer_code'], $search_term); ?></code>
                                        </p>
                                        <p>
                                            <i data-lucide="calendar" class="status-icon"></i>
                                            <strong>Registered:</strong> 
                                            <?php echo date('M d, Y', strtotime($customer['created_at'])); ?>
                                        </p>
                                    </div>
                                </div>
                                
                                <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #eee;">
                                    <strong>Registration Instructions:</strong><br>
                                    <small>Give this code to customer: <code style="background: #f8f9fa; padding: 2px 5px; border-radius: 3px;"><?php echo $customer['customer_code']; ?></code></small>
                                    <br>
                                    <small>Customer should go to: <strong>Customer Registration</strong> page</small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-results">
                        <i data-lucide="users" class="empty-state-icon"></i>
                        <h3>No Customers Found</h3>
                        <?php if (!empty($search_term)): ?>
                            <p>No customers found matching your search criteria.</p>
                            <p>Try different search terms or <a href="manage_users.php">view all customers</a>.</p>
                        <?php else: ?>
                            <p>No customers have been added yet.</p>
                            <p>Add your first customer using the form on the left.</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Online Billing System - BCA Project | Tribhuvan University</p>
        </div>
    </footer>

    <script>
        // Auto-scroll to new customer
        document.addEventListener('DOMContentLoaded', function() {
            const newCustomer = document.querySelector('.new-customer');
            if (newCustomer) {
                setTimeout(() => {
                    newCustomer.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 500);
            }
            
            // Focus on search input if there's a search term
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput && searchInput.value) {
                searchInput.focus();
                searchInput.select();
            }
            
            // Initialize Lucide Icons
            lucide.createIcons();
        });
        
        // Quick search functionality with Enter key
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput) {
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        this.form.submit();
                    }
                });
            }
        });
    </script>
</body>
</html>