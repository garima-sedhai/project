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

// FIRST: Check if bills table has service_details column, add it if not
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM bills LIKE 'service_details'");
    if ($stmt->rowCount() == 0) {
        // Add service_details column
        $pdo->exec("ALTER TABLE bills ADD COLUMN service_details TEXT NULL");
        error_log("Added service_details column to bills table");
    }
} catch (Exception $e) {
    error_log("Database setup error: " . $e->getMessage());
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['create_bill'])) {
        $user_id = $_POST['user_id'];
        $selected_service_ids = isset($_POST['services']) ? $_POST['services'] : array();
        $service_amounts = isset($_POST['service_amounts']) ? $_POST['service_amounts'] : array();
        $description = trim($_POST['description']);
        $tax_rate = $_POST['tax_rate'] ?? 0;
        $late_fee = $_POST['late_fee'] ?? 0;
        
        if (empty($user_id)) {
            $message = "Please select a customer";
            $message_type = "error";
        } elseif (empty($selected_service_ids)) {
            $message = "Please select at least one service";
            $message_type = "error";
        } elseif (empty($description)) {
            $message = "Please enter service description";
            $message_type = "error";
        } else {
            // Calculate total amount from selected services
            $total_base_amount = 0;
            $service_details = array();
            
            // Get service details and calculate total
            foreach ($selected_service_ids as $index => $service_id) {
                $stmt = $pdo->prepare("SELECT * FROM services WHERE id = ?");
                $stmt->execute([$service_id]);
                $service = $stmt->fetch();
                
                if ($service) {
                    // Use custom amount if provided, otherwise use base amount
                    $service_amount = isset($service_amounts[$index]) ? floatval($service_amounts[$index]) : $service['base_amount'];
                    $total_base_amount += $service_amount;
                    
                    $service_details[] = array(
                        'id' => $service['id'],
                        'name' => $service['service_name'],
                        'amount' => $service_amount,
                        'original_price' => $service['base_amount']
                    );
                }
            }
            
            // Calculate totals
            $tax_amount = ($total_base_amount * $tax_rate) / 100;
            $total_amount = $total_base_amount + $tax_amount + $late_fee;
            
            // Generate professional bill number
            $bill_number = 'BL' . date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            try {
                // Start transaction
                $pdo->beginTransaction();
                
                // Insert main bill
                $stmt = $pdo->prepare("INSERT INTO bills (user_id, bill_type, amount, due_date, description, bill_number, tax_rate, tax_amount, late_fee, total_amount, status) 
                                      VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, 'pending')");
                
                // Create service names list
                $service_names = array_map(function($s) { return $s['name']; }, $service_details);
                $bill_type = implode(', ', $service_names);
                
                $result = $stmt->execute([
                    $user_id, 
                    $bill_type, 
                    $total_base_amount, 
                    $description, 
                    $bill_number, 
                    $tax_rate, 
                    $tax_amount, 
                    $late_fee, 
                    $total_amount
                ]);
                
                if ($result) {
                    $bill_id = $pdo->lastInsertId();
                    
                    // Insert bill items for each service
                    foreach ($service_details as $service) {
                        $stmt = $pdo->prepare("INSERT INTO bill_items (bill_id, service_id, price) 
                                              VALUES (?, ?, ?)");
                        $stmt->execute([
                            $bill_id,
                            $service['id'],
                            $service['amount']
                        ]);
                    }
                    
                    // Update the bill with service_details as JSON
                    try {
                        $service_details_json = json_encode($service_details);
                        $stmt = $pdo->prepare("UPDATE bills SET service_details = ? WHERE id = ?");
                        $stmt->execute([$service_details_json, $bill_id]);
                    } catch (Exception $e) {
                        // Column might not exist, that's okay
                        error_log("Could not update service_details: " . $e->getMessage());
                    }
                    
                    // Commit transaction
                    $pdo->commit();
                    
                    $message = "Bill created successfully! Bill Number: <strong>$bill_number</strong>";
                    $message_type = "success";
                    
                    // Create notification for user
                    $notification_success = DBHelper::createNotification(
                        $pdo, 
                        $user_id, 
                        'New Bill Generated', 
                        "A new bill of ₹" . number_format($total_amount, 2) . " has been generated for " . $bill_type,
                        'bill'
                    );
                    
                    if (!$notification_success) {
                        error_log("Notification creation failed for bill: $bill_number");
                    }
                    
                } else {
                    $pdo->rollBack();
                    $message = "Error creating bill.";
                    $message_type = "error";
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = "Database error: " . $e->getMessage();
                $message_type = "error";
                error_log("Bill creation error: " . $e->getMessage());
            }
        }
    }
}

// Get all bills with user information - UPDATED to include bill_items data
try {
    $stmt = $pdo->query("SELECT b.*, u.full_name, u.phone, u.email, u.address, u.customer_code 
                         FROM bills b 
                         JOIN users u ON b.user_id = u.id 
                         ORDER BY b.created_at DESC");
    $bills = $stmt->fetchAll();
    
    // For each bill, get the service details from bill_items
    foreach ($bills as &$bill) {
        $bill_id = $bill['id'];
        $stmt = $pdo->prepare("SELECT s.service_name, bi.price 
                               FROM bill_items bi 
                               JOIN services s ON bi.service_id = s.id 
                               WHERE bi.bill_id = ?");
        $stmt->execute([$bill_id]);
        $bill_services = $stmt->fetchAll();
        $bill['services_list'] = $bill_services;
    }
} catch (Exception $e) {
    $bills = [];
    $message = "Error loading bills: " . $e->getMessage();
    $message_type = "error";
    error_log("Bills loading error: " . $e->getMessage());
}

// Get all users for dropdown
try {
    $stmt = $pdo->query("SELECT id, full_name, phone, customer_code, address, email 
                         FROM users 
                         WHERE is_admin = FALSE AND is_active = TRUE 
                         ORDER BY full_name");
    $users = $stmt->fetchAll();
} catch (Exception $e) {
    $users = [];
    error_log("Users loading error: " . $e->getMessage());
}

// Get all services for dropdown
try {
    $stmt = $pdo->query("SELECT id, service_name, service_type, base_amount, description 
                         FROM services 
                         WHERE is_active = TRUE 
                         ORDER BY service_name");
    $services = $stmt->fetchAll();
} catch (Exception $e) {
    $services = [];
    error_log("Services loading error: " . $e->getMessage());
}

// Get current date and time for display
date_default_timezone_set('Asia/Kathmandu');
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
        /* ... (ALL THE CSS STYLES FROM PREVIOUS CODE - KEEP THEM EXACTLY AS THEY WERE) ... */
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
        
        .selected-services-container {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            margin: 1rem 0;
            border: 2px solid #e9ecef;
            display: none;
        }
        
        .selected-service-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            background: #f8f9fa;
            border-radius: 6px;
            border-left: 4px solid #3498db;
        }
        
        .service-amount-input {
            width: 120px;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-align: right;
        }
        
        .remove-service-btn {
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 0.5rem 1rem;
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .remove-service-btn:hover {
            background: #c0392b;
        }
        
        .service-summary {
            background: #e8f4fd;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
            border-left: 4px solid #3498db;
        }
        
        .service-summary-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin: 1rem 0;
        }
        
        .summary-item {
            text-align: center;
            padding: 0.5rem;
            background: white;
            border-radius: 6px;
            border: 1px solid #dee2e6;
        }
        
        .summary-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #3498db;
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
        
        .service-count-badge {
            background: #3498db;
            color: white;
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            font-size: 0.8rem;
            margin-left: 0.5rem;
        }
        
        /* Checkbox-based service selection */
        .services-checkbox-container {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 1rem;
            max-height: 250px;
            overflow-y: auto;
            margin-top: 0.5rem;
        }
        
        .service-checkbox-item {
            display: flex;
            align-items: center;
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            border-radius: 6px;
            border: 1px solid #e9ecef;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .service-checkbox-item:hover {
            background: #f8f9fa;
            border-color: #3498db;
        }
        
        .service-checkbox-item.selected {
            background: #e8f4fd;
            border-color: #3498db;
            border-left: 4px solid #3498db;
        }
        
        .service-checkbox {
            margin-right: 1rem;
            width: 1.2rem;
            height: 1.2rem;
            cursor: pointer;
        }
        
        .service-info {
            flex: 1;
        }
        
        .service-name {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .service-price {
            color: #27ae60;
            font-weight: 500;
        }
        
        .service-description {
            font-size: 0.85rem;
            color: #7f8c8d;
            margin-top: 0.25rem;
        }
        
        .select-all-container {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
            padding: 0.5rem;
            background: #f8f9fa;
            border-radius: 6px;
        }
        
        .select-all-checkbox {
            margin-right: 0.5rem;
            width: 1.2rem;
            height: 1.2rem;
            cursor: pointer;
        }
        
        .select-all-label {
            font-weight: 600;
            cursor: pointer;
        }
        
        .no-services-message {
            text-align: center;
            padding: 2rem;
            color: #7f8c8d;
            font-style: italic;
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
                        <label>
                            <i data-lucide="settings" class="form-icon"></i>
                            Select Services (Click to Select) *
                            <span id="serviceCountBadge" class="service-count-badge">0 selected</span>
                        </label>
                        
                        <div class="services-checkbox-container" id="servicesCheckboxContainer">
                            <!-- Select All option -->
                            <div class="select-all-container">
                                <input type="checkbox" id="selectAllServices" class="select-all-checkbox" onchange="toggleSelectAll()">
                                <label for="selectAllServices" class="select-all-label">Select All Services</label>
                            </div>
                            
                            <!-- Services will be dynamically added here -->
                            <?php if (count($services) > 0): ?>
                                <?php foreach ($services as $service): ?>
                                    <div class="service-checkbox-item" data-service-id="<?php echo $service['id']; ?>"
                                         onclick="toggleServiceSelection(this, event)">
                                        <input type="checkbox" 
                                               name="services[]" 
                                               value="<?php echo $service['id']; ?>" 
                                               class="service-checkbox" 
                                               id="service_<?php echo $service['id']; ?>"
                                               data-base-amount="<?php echo $service['base_amount']; ?>"
                                               data-service-name="<?php echo htmlspecialchars($service['service_name']); ?>"
                                               <?php echo $preselected_service_id == $service['id'] ? 'checked' : ''; ?>
                                               onchange="updateSelectedServices()">
                                        <div class="service-info">
                                            <div class="service-name"><?php echo $service['service_name']; ?></div>
                                            <div class="service-price">₹<?php echo number_format($service['base_amount'], 2); ?></div>
                                            <?php if (!empty($service['description'])): ?>
                                                <div class="service-description"><?php echo $service['description']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="no-services-message">
                                    <i data-lucide="package" style="width: 3rem; height: 3rem; margin-bottom: 1rem; display: block; margin-left: auto; margin-right: auto;"></i>
                                    No services available. <a href="manage_services.php">Add services first</a>.
                                </div>
                            <?php endif; ?>
                        </div>
                        <small style="color: #666; display: block; margin-top: 0.5rem;">
                            <a href="manage_services.php" style="color: #3498db;">
                                <i data-lucide="plus" style="width: 1rem; height: 1rem; vertical-align: middle;"></i>
                                Add new service
                            </a> if not listed
                        </small>
                    </div>
                </div>
                
                <!-- Hidden fields for service amounts -->
                <div id="serviceAmountsContainer" style="display: none;">
                    <!-- Service amount inputs will be dynamically added here -->
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
                
                <!-- Selected Services -->
                <div id="selectedServicesContainer" class="selected-services-container">
                    <div class="section-header">
                        <i data-lucide="list" style="width: 1.5rem; height: 1.5rem;"></i>
                        <h4>Selected Services <span id="selectedServicesCount">(0)</span></h4>
                    </div>
                    <div id="selectedServicesList">
                        <!-- Selected services will be dynamically added here -->
                    </div>
                    
                    <!-- Service Summary -->
                    <div id="serviceSummary" class="service-summary" style="display: none;">
                        <div class="section-header">
                            <i data-lucide="calculator" style="width: 1.5rem; height: 1.5rem;"></i>
                            <h4>Service Summary</h4>
                        </div>
                        <div class="service-summary-grid">
                            <div class="summary-item">
                                <div>Number of Services</div>
                                <div class="summary-value" id="summaryServiceCount">0</div>
                            </div>
                            <div class="summary-item">
                                <div>Base Amount</div>
                                <div class="summary-value" id="summaryBaseAmount">₹0.00</div>
                            </div>
                            <div class="summary-item">
                                <div>Custom Adjustments</div>
                                <div class="summary-value" id="summaryAdjustments">₹0.00</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-top: 1rem;">
                    <div class="form-group">
                        <label for="tax_rate">
                            <i data-lucide="percent" class="form-icon"></i>
                            Tax Rate (%)
                        </label>
                        <input type="number" id="tax_rate" name="tax_rate" class="form-control" 
                               step="0.01" min="0" max="30" value="13" onchange="updateBillPreview()">
                    </div>
                    
                    <div class="form-group">
                        <label for="late_fee">
                            <i data-lucide="credit-card" class="form-icon"></i>
                            Service Charge (₹)
                        </label>
                        <input type="number" id="late_fee" name="late_fee" class="form-control" 
                               step="0.01" min="0" value="0" onchange="updateBillPreview()">
                    </div>
                    
                    <div class="form-group">
                        <label for="total_base_amount">
                            <i data-lucide="dollar-sign" class="form-icon"></i>
                            Total Base Amount (₹)
                        </label>
                        <input type="text" id="total_base_amount" class="form-control" 
                               readonly style="background: #f8f9fa; font-weight: bold;">
                        <input type="hidden" id="amount" name="amount" value="0">
                    </div>
                </div>
                
                <div class="form-group" style="margin-top: 1rem;">
                    <label for="description">
                        <i data-lucide="file-text" class="form-icon"></i>
                        Bill Description *
                    </label>
                    <textarea id="description" name="description" class="form-control" rows="3" 
                              placeholder="Detailed description of services provided..." required 
                              oninput="updateBillPreview()"></textarea>
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
                    
                    <table class="bill-items" id="previewBillItems">
                        <thead>
                            <tr>
                                <th>Service Description</th>
                                <th style="text-align: right;">Amount (₹)</th>
                            </tr>
                        </thead>
                        <tbody id="previewServicesBody">
                            <!-- Services will be added here dynamically -->
                        </tbody>
                        <tfoot>
                            <tr>
                                <td><strong>Subtotal</strong></td>
                                <td style="text-align: right;"><strong id="preview_subtotal">0.00</strong></td>
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
                        </tfoot>
                    </table>
                    
                    <div class="bill-footer">
                        <p>Thank you for choosing BillPay Pro Hotels!<br>
                        Please pay at checkout. For queries, contact accounts@billpayhotel.com</p>
                        <p><small>Bill generated on: <span id="preview_generated_time"><?php echo $current_datetime; ?></span></small></p>
                    </div>
                </div>
                
                <button type="submit" name="create_bill" class="btn" 
                        style="width: 100%; margin-top: 1rem; padding: 1rem; font-size: 1.1rem; 
                               display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
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
                        $service_details = isset($bill['service_details']) ? json_decode($bill['service_details'], true) : [];
                        $services_list = isset($bill['services_list']) ? $bill['services_list'] : [];
                    ?>
                        <div class="bill-card <?php echo $status; ?>" data-status="<?php echo $status; ?>">
                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                <div style="flex: 1;">
                                    <h3>Bill #<?php echo $bill['bill_number']; ?></h3>
                                    <p><strong>Customer:</strong> <?php echo $bill['full_name']; ?> (<?php echo $bill['customer_code']; ?>)</p>
                                    <p><strong>Services:</strong> 
                                        <?php 
                                        if (!empty($service_details) && is_array($service_details)) {
                                            $service_names = array_map(function($s) { return $s['name']; }, $service_details);
                                            echo implode(', ', $service_names);
                                        } elseif (!empty($services_list)) {
                                            $service_names = array_map(function($s) { return $s['service_name']; }, $services_list);
                                            echo implode(', ', $service_names);
                                        } else {
                                            echo ucfirst($bill['bill_type']);
                                        }
                                        ?>
                                    </p>
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
                                <?php if (!empty($service_details) && is_array($service_details)): ?>
                                    <br><small>Services: <?php echo count($service_details); ?> item(s)</small>
                                <?php elseif (!empty($services_list)): ?>
                                    <br><small>Services: <?php echo count($services_list); ?> item(s)</small>
                                <?php endif; ?>
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
        // Service data for preview
        const services = {
            <?php foreach ($services as $service): ?>
                '<?php echo $service['id']; ?>': {
                    name: '<?php echo addslashes($service['service_name']); ?>',
                    base_amount: <?php echo $service['base_amount']; ?>,
                    description: '<?php echo addslashes($service['description']); ?>'
                },
            <?php endforeach; ?>
        };

        // Customer data for preview
        const customers = {
            <?php foreach ($users as $user): ?>
                '<?php echo $user['id']; ?>': {
                    code: '<?php echo $user['customer_code']; ?>',
                    name: '<?php echo addslashes($user['full_name']); ?>',
                    phone: '<?php echo $user['phone']; ?>',
                    email: '<?php echo $user['email']; ?>',
                    address: '<?php echo addslashes($user['address']); ?>'
                },
            <?php endforeach; ?>
        };

        // Store selected services with custom amounts
        let selectedServices = [];
        
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
        
        function toggleServiceSelection(serviceItem, event) {
            // Don't toggle if the click was on the checkbox itself
            if (event.target.type === 'checkbox') {
                return;
            }
            
            const checkbox = serviceItem.querySelector('.service-checkbox');
            checkbox.checked = !checkbox.checked;
            updateSelectedServices();
        }
        
        function toggleSelectAll() {
            const selectAllCheckbox = document.getElementById('selectAllServices');
            const allCheckboxes = document.querySelectorAll('.service-checkbox');
            const serviceItems = document.querySelectorAll('.service-checkbox-item');
            
            allCheckboxes.forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
            });
            
            serviceItems.forEach(item => {
                if (selectAllCheckbox.checked) {
                    item.classList.add('selected');
                } else {
                    item.classList.remove('selected');
                }
            });
            
            updateSelectedServices();
        }
        
        function updateSelectedServices() {
            const serviceCheckboxes = document.querySelectorAll('.service-checkbox');
            const serviceItems = document.querySelectorAll('.service-checkbox-item');
            const selectedServicesContainer = document.getElementById('selectedServicesContainer');
            const selectedServicesList = document.getElementById('selectedServicesList');
            const serviceSummary = document.getElementById('serviceSummary');
            const serviceCountBadge = document.getElementById('serviceCountBadge');
            const selectedServicesCount = document.getElementById('selectedServicesCount');
            const serviceAmountsContainer = document.getElementById('serviceAmountsContainer');
            
            // Update visual selection state
            serviceItems.forEach(item => {
                const checkbox = item.querySelector('.service-checkbox');
                if (checkbox.checked) {
                    item.classList.add('selected');
                } else {
                    item.classList.remove('selected');
                }
            });
            
            // Update "Select All" checkbox
            const allChecked = Array.from(serviceCheckboxes).every(cb => cb.checked);
            const someChecked = Array.from(serviceCheckboxes).some(cb => cb.checked);
            const selectAllCheckbox = document.getElementById('selectAllServices');
            selectAllCheckbox.checked = allChecked;
            selectAllCheckbox.indeterminate = someChecked && !allChecked;
            
            // Get selected services
            const selectedCheckboxes = Array.from(serviceCheckboxes).filter(cb => cb.checked);
            
            // Clear current selection
            selectedServicesList.innerHTML = '';
            serviceAmountsContainer.innerHTML = '';
            selectedServices = [];
            
            if (selectedCheckboxes.length === 0) {
                selectedServicesContainer.style.display = 'none';
                serviceSummary.style.display = 'none';
                serviceCountBadge.textContent = '0 selected';
                selectedServicesCount.textContent = '(0)';
                updateTotals();
                return;
            }
            
            // Show container
            selectedServicesContainer.style.display = 'block';
            serviceSummary.style.display = 'block';
            serviceCountBadge.textContent = selectedCheckboxes.length + ' selected';
            selectedServicesCount.textContent = '(' + selectedCheckboxes.length + ')';
            
            // Create service items
            selectedCheckboxes.forEach((checkbox, index) => {
                const serviceId = checkbox.value;
                const service = services[serviceId];
                
                if (service) {
                    const serviceItem = {
                        id: serviceId,
                        name: service.name,
                        baseAmount: service.base_amount,
                        customAmount: service.base_amount,
                        index: index
                    };
                    selectedServices.push(serviceItem);
                    
                    // Create HTML for service item
                    const serviceDiv = document.createElement('div');
                    serviceDiv.className = 'selected-service-item';
                    serviceDiv.innerHTML = `
                        <div style="flex: 1;">
                            <strong>${service.name}</strong><br>
                            <small>Base Price: ₹${service.base_amount.toFixed(2)}</small>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <span>Amount:</span>
                            <input type="number" 
                                   class="service-amount-input" 
                                   value="${service.base_amount.toFixed(2)}"
                                   step="0.01"
                                   min="0"
                                   data-service-id="${serviceId}"
                                   data-index="${index}"
                                   onchange="updateServiceAmount(this)">
                        </div>
                        <button type="button" 
                                class="remove-service-btn"
                                onclick="removeService('${serviceId}')">
                            <i data-lucide="x" style="width: 1rem; height: 1rem;"></i>
                        </button>
                    `;
                    selectedServicesList.appendChild(serviceDiv);
                    
                    // Add hidden input for service amount
                    const amountInput = document.createElement('input');
                    amountInput.type = 'hidden';
                    amountInput.name = 'service_amounts[]';
                    amountInput.value = service.base_amount;
                    amountInput.id = `service_amount_${serviceId}`;
                    serviceAmountsContainer.appendChild(amountInput);
                }
            });
            
            // Update totals and preview
            updateTotals();
            updateBillPreview();
            
            // Initialize Lucide icons for new elements
            lucide.createIcons();
        }
        
        function updateServiceAmount(input) {
            const serviceId = input.dataset.serviceId;
            const index = parseInt(input.dataset.index);
            const newAmount = parseFloat(input.value) || 0;
            
            // Update hidden input
            const hiddenInput = document.getElementById(`service_amount_${serviceId}`);
            if (hiddenInput) {
                hiddenInput.value = newAmount;
            }
            
            // Update selectedServices array
            const serviceIndex = selectedServices.findIndex(s => s.id === serviceId);
            if (serviceIndex !== -1) {
                selectedServices[serviceIndex].customAmount = newAmount;
                updateTotals();
                updateBillPreview();
            }
        }
        
        function removeService(serviceId) {
            // Uncheck the checkbox
            const checkbox = document.querySelector(`input.service-checkbox[value="${serviceId}"]`);
            if (checkbox) {
                checkbox.checked = false;
            }
            
            // Remove visual selection
            const serviceItem = document.querySelector(`.service-checkbox-item[data-service-id="${serviceId}"]`);
            if (serviceItem) {
                serviceItem.classList.remove('selected');
            }
            
            // Update selection
            updateSelectedServices();
        }
        
        function updateTotals() {
            let totalBaseAmount = 0;
            let totalCustomAmount = 0;
            let totalAdjustments = 0;
            
            selectedServices.forEach(service => {
                totalBaseAmount += service.baseAmount;
                totalCustomAmount += service.customAmount;
            });
            
            totalAdjustments = totalCustomAmount - totalBaseAmount;
            
            // Update summary display
            document.getElementById('summaryServiceCount').textContent = selectedServices.length;
            document.getElementById('summaryBaseAmount').textContent = '₹' + totalBaseAmount.toFixed(2);
            document.getElementById('summaryAdjustments').textContent = (totalAdjustments >= 0 ? '+₹' : '-₹') + Math.abs(totalAdjustments).toFixed(2);
            
            // Update total base amount input
            document.getElementById('total_base_amount').value = '₹' + totalCustomAmount.toFixed(2);
            document.getElementById('amount').value = totalCustomAmount.toFixed(2);
        }
        
        function updateBillPreview() {
            const previewDiv = document.getElementById('billPreview');
            const userId = document.getElementById('user_id').value;
            const customer = customers[userId];
            const taxRate = parseFloat(document.getElementById('tax_rate').value) || 0;
            const serviceCharge = parseFloat(document.getElementById('late_fee').value) || 0;
            const totalBaseAmount = parseFloat(document.getElementById('amount').value) || 0;
            
            if (customer && selectedServices.length > 0 && totalBaseAmount > 0) {
                // Calculate totals
                const taxAmount = (totalBaseAmount * taxRate) / 100;
                const totalAmount = totalBaseAmount + taxAmount + serviceCharge;
                
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
                
                // Update customer info in preview
                document.getElementById('preview_customer').textContent = customer.name;
                document.getElementById('preview_address').textContent = customer.address || 'Address not provided';
                document.getElementById('preview_phone').textContent = customer.phone;
                document.getElementById('preview_email').textContent = customer.email;
                
                // Update services list in preview
                const previewServicesBody = document.getElementById('previewServicesBody');
                previewServicesBody.innerHTML = '';
                
                selectedServices.forEach(service => {
                    const row = document.createElement('tr');
                    const isAdjusted = service.customAmount !== service.baseAmount;
                    const adjustmentText = isAdjusted ? 
                        ` <small style="color: #666;">(Base: ₹${service.baseAmount.toFixed(2)})</small>` : '';
                    
                    row.innerHTML = `
                        <td>${service.name}${adjustmentText}</td>
                        <td style="text-align: right;">₹${service.customAmount.toFixed(2)}</td>
                    `;
                    previewServicesBody.appendChild(row);
                });
                
                // Update totals in preview
                document.getElementById('preview_subtotal').textContent = '₹' + totalBaseAmount.toFixed(2);
                document.getElementById('preview_tax_rate').textContent = taxRate;
                document.getElementById('preview_tax').textContent = '₹' + taxAmount.toFixed(2);
                document.getElementById('preview_late_fee').textContent = '₹' + serviceCharge.toFixed(2);
                document.getElementById('preview_total').textContent = '₹' + totalAmount.toFixed(2);
                
                // Update date and time in preview
                document.getElementById('preview_date').textContent = previewDate;
                document.getElementById('preview_time').textContent = previewTime;
                document.getElementById('preview_generated_time').textContent = previewDateTime;
                
                // Show preview
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
                const checkbox = document.querySelector(`input.service-checkbox[value="<?php echo $preselected_service_id; ?>"]`);
                if (checkbox) {
                    checkbox.checked = true;
                    const serviceItem = checkbox.closest('.service-checkbox-item');
                    if (serviceItem) {
                        serviceItem.classList.add('selected');
                    }
                    updateSelectedServices();
                }
            <?php endif; ?>
            
            // Initialize time display
            updateCurrentTime();
            setInterval(updateCurrentTime, 60000);
            
            // Initialize Lucide icons
            lucide.createIcons();
        });
    </script>
</body>
</html>