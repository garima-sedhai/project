<?php
session_start();
include '../includes/config.php';
include '../includes/db_helper.php';

// Redirect if not logged in as admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: login.php");
    exit();
}

$message = '';
$message_type = '';

// Get service ID from URL if provided
$preselected_service_id = isset($_GET['service_id']) ? $_GET['service_id'] : null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['create_bill'])) {
        $user_id = $_POST['user_id'];
        $service_id = $_POST['service_id'];
        $amount = $_POST['amount'];
        $description = trim($_POST['description']);
        $tax_rate = $_POST['tax_rate'] ?? 0;
        $late_fee = $_POST['late_fee'] ?? 0;
        
        // Get service details
        $stmt = $pdo->prepare("SELECT * FROM services WHERE id = ?");
        $stmt->execute([$service_id]);
        $service = $stmt->fetch();
        
        if (!$service) {
            $message = "Selected service not found";
            $message_type = "error";
        } elseif (empty($user_id) || empty($amount) || empty($description)) {
            $message = "Please fill all required fields";
            $message_type = "error";
        } else {
            // Calculate totals
            $tax_amount = ($amount * $tax_rate) / 100;
            $total_amount = $amount + $tax_amount + $late_fee;
            
            // Generate professional bill number
            $bill_number = 'BL' . date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            try {
                $stmt = $pdo->prepare("INSERT INTO bills (user_id, bill_type, amount, due_date, description, bill_number, tax_rate, tax_amount, late_fee, total_amount, status) VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, 'pending')");
                $result = $stmt->execute([$user_id, $service['service_name'], $amount, $description, $bill_number, $tax_rate, $tax_amount, $late_fee, $total_amount]);
                
                if ($result) {
                    $message = "Bill created successfully! Bill Number: <strong>$bill_number</strong>";
                    $message_type = "success";
                    
                    // Create notification for user
                    $notification_success = DBHelper::createNotification(
                        $pdo, 
                        $user_id, 
                        'New Bill Generated', 
                        "A new bill of ₹" . number_format($total_amount, 2) . " has been generated for " . $service['service_name'],
                        'bill'
                    );
                    
                    if (!$notification_success) {
                        error_log("Notification creation failed for bill: $bill_number");
                    }
                    
                } else {
                    $message = "Error creating bill.";
                    $message_type = "error";
                }
            } catch (Exception $e) {
                $message = "Database error: " . $e->getMessage();
                $message_type = "error";
                error_log("Bill creation error: " . $e->getMessage());
            }
        }
    }
}

// Get all bills with user information
try {
    $stmt = $pdo->query("SELECT b.*, u.full_name, u.phone, u.email, u.address, u.customer_code FROM bills b JOIN users u ON b.user_id = u.id ORDER BY b.created_at DESC");
    $bills = $stmt->fetchAll();
} catch (Exception $e) {
    $bills = [];
    $message = "Error loading bills: " . $e->getMessage();
    $message_type = "error";
    error_log("Bills loading error: " . $e->getMessage());
}

// Get all users for dropdown
try {
    $stmt = $pdo->query("SELECT id, full_name, phone, customer_code, address, email FROM users WHERE is_admin = FALSE AND is_active = TRUE");
    $users = $stmt->fetchAll();
} catch (Exception $e) {
    $users = [];
    error_log("Users loading error: " . $e->getMessage());
}

// Get all services for dropdown
try {
    $stmt = $pdo->query("SELECT id, service_name, service_type, base_amount, description FROM services WHERE is_active = TRUE ORDER BY service_name");
    $services = $stmt->fetchAll();
} catch (Exception $e) {
    $services = [];
    error_log("Services loading error: " . $e->getMessage());
}

// Get current date and time for display - Using server time with proper timezone
date_default_timezone_set('Asia/Kathmandu'); // Set to your timezone
$current_date = date('M d, Y');
$current_time = date('h:i A');
$current_datetime = date('M d, Y h:i A');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Bills - BillPay Pro</title>
    <link rel="stylesheet" href="../css/style.css">
    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        .bill-creation {
            background: #f8f9fa;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            border-left: 5px solid #3498db;
        }
        
        .customer-details {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            margin: 1rem 0;
            border: 2px solid #e9ecef;
            display: none;
        }
        
        .service-details {
            background: #e8f4fd;
            padding: 1.5rem;
            border-radius: 8px;
            margin: 1rem 0;
            border-left: 4px solid #3498db;
            display: none;
        }
        
        .bill-preview {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            margin: 1rem 0;
            border: 2px dashed #dee2e6;
            display: none;
            font-family: Arial, sans-serif;
        }
        
        .bill-header {
            text-align: center;
            border-bottom: 3px double #3498db;
            padding-bottom: 1rem;
            margin-bottom: 2rem;
        }
        
        .bill-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin: 2rem 0;
        }
        
        .bill-items {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
            border: 1px solid #dee2e6;
        }
        
        .bill-items th {
            background: #3498db;
            color: white;
            padding: 1rem;
            text-align: left;
            border: 1px solid #dee2e6;
        }
        
        .bill-items td {
            padding: 1rem;
            border-bottom: 1px solid #dee2e6;
            border: 1px solid #dee2e6;
        }
        
        .bill-total {
            background: #f8f9fa;
            font-weight: bold;
            font-size: 1.1em;
        }
        
        .bill-footer {
            text-align: center;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid #dee2e6;
            color: #6c757d;
            font-style: italic;
        }
        
        .bill-grid {
            display: grid;
            gap: 1.5rem;
            margin-top: 2rem;
        }
        
        .bill-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #3498db;
        }
        
        .bill-card.paid {
            border-left-color: #27ae60;
        }
        
        .status-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: bold;
        }
        
        .status-pending {
            background: #ffeaa7;
            color: #e17055;
        }
        
        .status-paid {
            background: #55efc4;
            color: #00b894;
        }
        
        .datetime-display {
            background: #f8f9fa;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            margin: 0.5rem 0;
            font-family: monospace;
            border-left: 3px solid #3498db;
            display: flex;
            align-items: center;
            gap: 0.5rem;
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
        
        .empty-state {
            text-align: center;
            padding: 3rem;
        }
        
        .empty-state-icon {
            width: 4rem;
            height: 4rem;
            color: #6c757d;
            margin: 0 auto 1rem auto;
            display: block;
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
            margin-right: 0.5rem;
            vertical-align: middle;
        }
        
        .quick-actions {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
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
                    <li><a href="manage_bills.php" style="color: #3498db;">Manage Bills</a></li>
                    <li><a href="manage_users.php">Manage Users</a></li>
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
            <i data-lucide="file-text" class="section-icon"></i>
            <h1>Manage Bills</h1>
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

        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="manage_services.php" class="btn" style="display: flex; align-items: center; gap: 0.5rem;">
                <i data-lucide="settings" class="btn-icon"></i>
                Manage Services
            </a>
            <a href="manage_users.php" class="btn" style="background: #2ecc71; display: flex; align-items: center; gap: 0.5rem;">
                <i data-lucide="users" class="btn-icon"></i>
                Add New Customer
            </a>
        </div>

        <!-- Current Date & Time Display -->
        <div class="datetime-display">
            <i data-lucide="clock" style="width: 1.2rem; height: 1.2rem;"></i>
            <strong>Current Time:</strong> <span id="currentTime"><?php echo $current_datetime; ?></span>
        </div>

        <!-- Create Bill Form -->
        <div class="card">
            <div class="section-header">
                <i data-lucide="plus-circle" class="section-icon"></i>
                <h2>Create New Bill</h2>
            </div>
            <form method="POST" action="" class="bill-creation" id="billForm">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="user_id">
                            <i data-lucide="users" class="form-icon"></i>
                            Select Customer *
                        </label>
                        <select id="user_id" name="user_id" class="form-control" required onchange="loadCustomerDetails(this.value)">
                            <option value="">Choose Customer</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" data-email="<?php echo $user['email']; ?>">
                                    <?php echo $user['full_name'] . ' (' . $user['customer_code'] . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="service_id">
                            <i data-lucide="settings" class="form-icon"></i>
                            Select Service *
                        </label>
                        <select id="service_id" name="service_id" class="form-control" required onchange="loadServiceDetails(this.value)">
                            <option value="">Choose Service</option>
                            <?php foreach ($services as $service): 
                                $is_selected = $preselected_service_id == $service['id'];
                            ?>
                                <option value="<?php echo $service['id']; ?>" 
                                        data-base-amount="<?php echo $service['base_amount']; ?>"
                                        data-description="<?php echo htmlspecialchars($service['description']); ?>"
                                        <?php echo $is_selected ? 'selected' : ''; ?>>
                                    <?php echo $service['service_name'] . ' - ₹' . number_format($service['base_amount'], 2); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color: #666;">
                            <a href="manage_services.php" style="color: #3498db;">Add new service</a> if not listed
                        </small>
                    </div>
                </div>
                
                <!-- Customer Details -->
                <div id="customerDetails" class="customer-details">
                    <div class="section-header">
                        <i data-lucide="user" style="width: 1.5rem; height: 1.5rem;"></i>
                        <h4>Customer Information</h4>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div>
                            <strong>Customer Code:</strong> <span id="cust_code">-</span>
                        </div>
                        <div>
                            <strong>Phone:</strong> <span id="cust_phone">-</span>
                        </div>
                        <div>
                            <strong>Email:</strong> <span id="cust_email">-</span>
                        </div>
                        <div>
                            <strong>Address:</strong> <span id="cust_address">-</span>
                        </div>
                    </div>
                </div>
                
                <!-- Service Details -->
                <div id="serviceDetails" class="service-details">
                    <div class="section-header">
                        <i data-lucide="settings" style="width: 1.5rem; height: 1.5rem;"></i>
                        <h4>Service Information</h4>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div>
                            <strong>Service Name:</strong> <span id="service_name">-</span>
                        </div>
                        <div>
                            <strong>Base Price:</strong> ₹<span id="service_base_amount">0.00</span>
                        </div>
                        <div style="grid-column: 1 / -1;">
                            <strong>Description:</strong> <span id="service_description">-</span>
                        </div>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="amount">
                            <i data-lucide="dollar-sign" class="form-icon"></i>
                            Amount (₹) *
                        </label>
                        <input type="number" id="amount" name="amount" class="form-control" step="0.01" min="0" required onchange="updateBillPreview()">
                    </div>
                    
                    <div class="form-group">
                        <label for="tax_rate">
                            <i data-lucide="percent" class="form-icon"></i>
                            Tax Rate (%)
                        </label>
                        <input type="number" id="tax_rate" name="tax_rate" class="form-control" step="0.01" min="0" max="30" value="13" onchange="updateBillPreview()">
                    </div>
                    
                    <div class="form-group">
                        <label for="late_fee">
                            <i data-lucide="credit-card" class="form-icon"></i>
                            Service Charge (₹)
                        </label>
                        <input type="number" id="late_fee" name="late_fee" class="form-control" step="0.01" min="0" value="0" onchange="updateBillPreview()">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description">
                        <i data-lucide="file-text" class="form-icon"></i>
                        Service Description *
                    </label>
                    <textarea id="description" name="description" class="form-control" rows="3" 
                              placeholder="Detailed description of services provided..." required oninput="updateBillPreview()"></textarea>
                </div>
                
                <!-- Bill Preview -->
                <div id="billPreview" class="bill-preview">
                    <div class="bill-header">
                        <i data-lucide="building" style="width: 2.5rem; height: 2.5rem; color: #3498db; margin-bottom: 0.5rem;"></i>
                        <h2>BillPay Pro Hotels</h2>
                        <p>123 Hotel Street, Tourism District, Kathmandu<br>
                           Phone: +977-1-4000000 | Email: info@billpayhotel.com</p>
                    </div>
                    
                    <div class="bill-details">
                        <div>
                            <strong>Bill To:</strong><br>
                            <span id="preview_customer">-</span><br>
                            <span id="preview_address">-</span><br>
                            <span id="preview_phone">-</span><br>
                            <span id="preview_email">-</span>
                        </div>
                        <div style="text-align: right;">
                            <strong>Bill Number:</strong> <span id="preview_bill_no">BL<?php echo date('Ymd'); ?>XXXX</span><br>
                            <strong>Bill Date:</strong> <span id="preview_date"><?php echo $current_date; ?></span><br>
                            <strong>Bill Time:</strong> <span id="preview_time"><?php echo $current_time; ?></span><br>
                            <strong>Status:</strong> <span style="color: #e74c3c;">Pending Payment</span>
                        </div>
                    </div>
                    
                    <table class="bill-items">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th style="text-align: right;">Amount (₹)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td id="preview_description">-</td>
                                <td style="text-align: right;" id="preview_amount">0.00</td>
                            </tr>
                            <tr>
                                <td>Tax (<span id="preview_tax_rate">0</span>%)</td>
                                <td style="text-align: right;" id="preview_tax">0.00</td>
                            </tr>
                            <tr>
                                <td>Service Charge</td>
                                <td style="text-align: right;" id="preview_late_fee">0.00</td>
                            </tr>
                            <tr class="bill-total">
                                <td><strong>Total Amount Due</strong></td>
                                <td style="text-align: right;"><strong id="preview_total">0.00</strong></td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <div class="bill-footer">
                        <p>Thank you for choosing BillPay Pro Hotels!<br>
                        Please pay at checkout. For queries, contact accounts@billpayhotel.com</p>
                        <p><small>Bill generated on: <span id="preview_generated_time"><?php echo $current_datetime; ?></span></small></p>
                    </div>
                </div>
                
                <button type="submit" name="create_bill" class="btn" style="width: 100%; margin-top: 1rem; padding: 1rem; font-size: 1.1rem; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                    <i data-lucide="file-plus" class="btn-icon"></i>
                    Generate Bill
                </button>
            </form>
        </div>

        <!-- Bills List -->
        <div class="card">
            <div class="section-header">
                <i data-lucide="list" class="section-icon"></i>
                <h2>All Bills</h2>
            </div>
            
            <?php if (count($bills) > 0): ?>
                <div class="bill-grid">
                    <?php foreach ($bills as $bill): 
                        $status = $bill['status'];
                        $bill_datetime = date('M d, Y h:i A', strtotime($bill['created_at']));
                    ?>
                        <div class="bill-card <?php echo $status; ?>" data-status="<?php echo $status; ?>">
                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                <div style="flex: 1;">
                                    <h3>Bill #<?php echo $bill['bill_number']; ?></h3>
                                    <p><strong>Customer:</strong> <?php echo $bill['full_name']; ?> (<?php echo $bill['customer_code']; ?>)</p>
                                    <p><strong>Service:</strong> <?php echo ucfirst($bill['bill_type']); ?></p>
                                    <p><strong>Total Amount:</strong> ₹<?php echo number_format($bill['total_amount'], 2); ?></p>
                                    <p><strong>Bill Date:</strong> <?php echo $bill_datetime; ?></p>
                                    <?php if ($bill['description']): ?>
                                        <p><strong>Description:</strong> <?php echo $bill['description']; ?></p>
                                    <?php endif; ?>
                                </div>
                                <div style="text-align: right;">
                                    <span class="status-badge status-<?php echo $status; ?>">
                                        <?php if ($status == 'pending'): ?>
                                            <i data-lucide="clock" style="width: 1rem; height: 1rem; margin-right: 0.3rem; vertical-align: middle;"></i>
                                        <?php else: ?>
                                            <i data-lucide="check-circle" style="width: 1rem; height: 1rem; margin-right: 0.3rem; vertical-align: middle;"></i>
                                        <?php endif; ?>
                                        <?php echo ucfirst($status); ?>
                                    </span>
                                    <?php if ($status == 'pending'): ?>
                                        <p style="color: #e74c3c; margin-top: 0.5rem; display: flex; align-items: center; justify-content: flex-end; gap: 0.3rem;">
                                            <i data-lucide="alert-circle" style="width: 1rem; height: 1rem;"></i>
                                            Awaiting Payment
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #eee;">
                            <strong>Breakdown:</strong><br>
                            Base: ₹<?php echo number_format($bill['amount'], 2); ?> | 
                            Tax: ₹<?php echo number_format($bill['tax_amount'], 2); ?> | 
                            Service Charge: ₹<?php echo number_format($bill['late_fee'] ?? 0, 2); ?>
                        </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i data-lucide="file-text" class="empty-state-icon"></i>
                    <h3>No Bills Found</h3>
                    <p>No bills have been created yet.</p>
                    <p>Create your first bill using the form above.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Online Billing System - BCA Project | Tribhuvan University</p>
        </div>
    </footer>

    <script>
        // Customer data for preview
        const customers = {
            <?php foreach ($users as $user): ?>
                '<?php echo $user['id']; ?>': {
                    code: '<?php echo $user['customer_code']; ?>',
                    name: '<?php echo $user['full_name']; ?>',
                    phone: '<?php echo $user['phone']; ?>',
                    email: '<?php echo $user['email']; ?>',
                    address: '<?php echo addslashes($user['address']); ?>'
                },
            <?php endforeach; ?>
        };

        // Service data for preview
        const services = {
            <?php foreach ($services as $service): ?>
                '<?php echo $service['id']; ?>': {
                    name: '<?php echo $service['service_name']; ?>',
                    base_amount: <?php echo $service['base_amount']; ?>,
                    description: '<?php echo addslashes($service['description']); ?>'
                },
            <?php endforeach; ?>
        };

        function loadCustomerDetails(userId) {
            const detailsDiv = document.getElementById('customerDetails');
            const customer = customers[userId];
            
            if (customer) {
                document.getElementById('cust_code').textContent = customer.code;
                document.getElementById('cust_phone').textContent = customer.phone;
                document.getElementById('cust_email').textContent = customer.email;
                document.getElementById('cust_address').textContent = customer.address || 'Not provided';
                detailsDiv.style.display = 'block';
                updateBillPreview();
            } else {
                detailsDiv.style.display = 'none';
            }
        }

        function loadServiceDetails(serviceId) {
            const detailsDiv = document.getElementById('serviceDetails');
            const service = services[serviceId];
            
            if (service) {
                document.getElementById('service_name').textContent = service.name;
                document.getElementById('service_base_amount').textContent = service.base_amount.toFixed(2);
                document.getElementById('service_description').textContent = service.description || 'No description';
                
                // Auto-fill amount and description
                document.getElementById('amount').value = service.base_amount;
                if (service.description) {
                    document.getElementById('description').value = service.description;
                }
                
                detailsDiv.style.display = 'block';
                updateBillPreview();
            } else {
                detailsDiv.style.display = 'none';
            }
        }

        function updateBillPreview() {
            const previewDiv = document.getElementById('billPreview');
            const userId = document.getElementById('user_id').value;
            const serviceId = document.getElementById('service_id').value;
            const customer = customers[userId];
            const service = services[serviceId];
            
            if (customer && service && document.getElementById('amount').value) {
                // Calculate amounts
                const amount = parseFloat(document.getElementById('amount').value) || 0;
                const taxRate = parseFloat(document.getElementById('tax_rate').value) || 0;
                const serviceCharge = parseFloat(document.getElementById('late_fee').value) || 0;
                const taxAmount = (amount * taxRate) / 100;
                const totalAmount = amount + taxAmount + serviceCharge;
                
                // Get current date and time for preview
                const now = new Date();
                const previewDate = now.toLocaleDateString('en-US', { 
                    year: 'numeric', 
                    month: 'short', 
                    day: 'numeric' 
                });
                const previewTime = now.toLocaleTimeString('en-US', { 
                    hour: '2-digit', 
                    minute: '2-digit',
                    hour12: true 
                });
                const previewDateTime = now.toLocaleDateString('en-US', { 
                    year: 'numeric', 
                    month: 'short', 
                    day: 'numeric',
                    hour: '2-digit', 
                    minute: '2-digit',
                    hour12: true 
                });
                
                // Update preview
                document.getElementById('preview_customer').textContent = customer.name;
                document.getElementById('preview_address').textContent = customer.address || 'Address not provided';
                document.getElementById('preview_phone').textContent = customer.phone;
                document.getElementById('preview_email').textContent = customer.email;
                document.getElementById('preview_description').textContent = document.getElementById('description').value || service.description || 'Service description';
                document.getElementById('preview_amount').textContent = '₹' + amount.toFixed(2);
                document.getElementById('preview_tax_rate').textContent = taxRate;
                document.getElementById('preview_tax').textContent = '₹' + taxAmount.toFixed(2);
                document.getElementById('preview_late_fee').textContent = '₹' + serviceCharge.toFixed(2);
                document.getElementById('preview_total').textContent = '₹' + totalAmount.toFixed(2);
                
                // Update date and time in preview with current client time
                document.getElementById('preview_date').textContent = previewDate;
                document.getElementById('preview_time').textContent = previewTime;
                document.getElementById('preview_generated_time').textContent = previewDateTime;
                
                previewDiv.style.display = 'block';
            } else {
                previewDiv.style.display = 'none';
            }
        }

        // Update current time display every minute
        function updateCurrentTime() {
            const now = new Date();
            const options = { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                hour12: true 
            };
            const currentTime = now.toLocaleDateString('en-US', options);
            document.getElementById('currentTime').textContent = currentTime;
        }

        // Auto-select service if provided in URL
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($preselected_service_id): ?>
                const serviceSelect = document.getElementById('service_id');
                if (serviceSelect) {
                    serviceSelect.value = '<?php echo $preselected_service_id; ?>';
                    loadServiceDetails('<?php echo $preselected_service_id; ?>');
                }
            <?php endif; ?>
            
            // Initialize time display
            updateCurrentTime();
            // Update time every minute
            setInterval(updateCurrentTime, 60000);
        });

        lucide.createIcons();
    </script>
</body>
</html>