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
            $final_amount = $total_base_amount + $tax_amount + $late_fee;
            
            // Generate professional bill number
            $bill_number = 'BL' . date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            try {
                // Start transaction
                $pdo->beginTransaction();
                
                // Insert main bill
                $stmt = $pdo->prepare("INSERT INTO bills (
                    user_id, 
                    bill_type, 
                    amount, 
                    total_amount, 
                    tax_rate, 
                    tax_amount, 
                    late_fee, 
                    final_amount, 
                    due_date, 
                    description, 
                    bill_number, 
                    status,
                    payment_status,
                    payment_method,
                    service_details
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, 'pending', 'pending', 'cash', ?)");
                
                // Create service names list for bill_type
                $service_names = array_map(function($s) { return $s['name']; }, $service_details);
                $bill_type = implode(', ', $service_names);
                
                // Create service_details JSON
                $service_details_json = json_encode($service_details);
                
                $result = $stmt->execute([
                    $user_id, 
                    $bill_type, 
                    $total_base_amount,
                    $total_base_amount,
                    $tax_rate, 
                    $tax_amount, 
                    $late_fee, 
                    $final_amount,
                    $description, 
                    $bill_number,
                    $service_details_json
                ]);
                
                if ($result) {
                    $bill_id = $pdo->lastInsertId();
                    
                    // Insert bill items for each service
                    try {
                        $stmt = $pdo->prepare("INSERT INTO bill_items (bill_id, service_id, price) VALUES (?, ?, ?)");
                        foreach ($service_details as $service) {
                            $stmt->execute([
                                $bill_id,
                                $service['id'],
                                $service['amount']
                            ]);
                        }
                    } catch (Exception $e) {
                        // Try with 'amount' column if 'price' doesn't exist
                        try {
                            $stmt = $pdo->prepare("INSERT INTO bill_items (bill_id, service_id, amount) VALUES (?, ?, ?)");
                            foreach ($service_details as $service) {
                                $stmt->execute([
                                    $bill_id,
                                    $service['id'],
                                    $service['amount']
                                ]);
                            }
                        } catch (Exception $e2) {
                            // Continue without bill items
                            error_log("Could not insert bill items: " . $e2->getMessage());
                        }
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
                        "A new bill of ₹" . number_format($final_amount, 2) . " has been generated for " . $bill_type,
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

// Get all bills with user information
try {
    $stmt = $pdo->query("SELECT b.*, u.full_name, u.phone, u.email, u.address, u.customer_code 
                         FROM bills b 
                         JOIN users u ON b.user_id = u.id 
                         ORDER BY b.created_at DESC");
    $bills = $stmt->fetchAll();
    
    // For each bill, get the service details from bill_items
    foreach ($bills as &$bill) {
        $bill_id = $bill['id'];
        try {
            $stmt = $pdo->prepare("SELECT s.service_name, bi.price 
                                   FROM bill_items bi 
                                   JOIN services s ON bi.service_id = s.id 
                                   WHERE bi.bill_id = ?");
            $stmt->execute([$bill_id]);
            $bill_services = $stmt->fetchAll();
        } catch (Exception $e) {
            try {
                $stmt = $pdo->prepare("SELECT s.service_name, bi.amount as price 
                                       FROM bill_items bi 
                                       JOIN services s ON bi.service_id = s.id 
                                       WHERE bi.bill_id = ?");
                $stmt->execute([$bill_id]);
                $bill_services = $stmt->fetchAll();
            } catch (Exception $e2) {
                $bill_services = [];
            }
        }
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
    <style>
        /* Main Layout */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Header - Green Theme - UPDATED */
        .header {
            background: linear-gradient(135deg, #075B5E 0%, #0A6F73 100%); /* Changed from #2E7D32, #4CAF50 */
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 2rem;
        }
        
        .logo {
            font-size: 1.8rem;
            font-weight: bold;
            color: white;
        }
        
        .nav-links {
            display: flex;
            list-style: none;
            gap: 2rem;
            align-items: center;
        }
        
        .nav-links a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }
        
        .nav-links a:hover {
            color: #e8f5e9;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #FF9800;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 10px;
        }
        
        .admin-badge {
            background: #FF5722;
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            margin-left: 10px;
        }
        
        /* Card Styles */
        .card {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            border: 1px solid #eaeaea;
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #075B5E; /* Changed from #4CAF50 */
            box-shadow: 0 0 0 3px rgba(7, 91, 94, 0.1); /* Changed from rgba(76, 175, 80, 0.1) */
        }
        
        /* Button Styles - Green Theme - UPDATED */
        .btn {
            background: linear-gradient(135deg, #075B5E 0%, #0A6F73 100%); /* Changed from #4CAF50, #2E7D32 */
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(7, 91, 94, 0.4); /* Changed from rgba(76, 175, 80, 0.4) */
        }
        
        /* Alert Messages */
        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Bill Creation Section */
        .bill-creation {
            background: #f8f9fa;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            border-left: 5px solid #075B5E; /* Changed from #4CAF50 */
        }
        
        .section-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }
        
        .section-header h1, .section-header h2 {
            margin: 0;
            color: #333;
        }
        
        .section-icon {
            color: #075B5E; /* Changed from #4CAF50 */
        }
        
        /* Customer Details */
        .customer-details {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            margin: 1rem 0;
            border: 2px solid #e9ecef;
            display: none;
        }
        
        .customer-details h4 {
            margin-top: 0;
            color: #333;
        }
        
        /* Services Selection */
        .services-checkbox-container {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 1rem;
            max-height: 300px;
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
            border-color: #075B5E; /* Changed from #4CAF50 */
        }
        
        .service-checkbox-item.selected {
            background: #e8f5e9;
            border-color: #075B5E; /* Changed from #4CAF50 */
            border-left: 4px solid #075B5E; /* Changed from #4CAF50 */
        }
        
        .service-checkbox {
            margin-right: 1rem;
        }
        
        .service-info {
            flex: 1;
        }
        
        .service-name {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .service-price {
            color: #075B5E; /* Changed from #27ae60 */
            font-weight: 500;
        }
        
        .service-description {
            font-size: 0.85rem;
            color: #7f8c8d;
            margin-top: 0.25rem;
        }
        
        /* Selected Services */
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
            border-left: 4px solid #075B5E; /* Changed from #4CAF50 */
        }
        
        .service-amount-input {
            width: 120px;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-align: right;
        }
        
        .remove-service-btn {
            background: #f44336;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 0.5rem 1rem;
            cursor: pointer;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .remove-service-btn:hover {
            background: #d32f2f;
        }
        
        /* Service Summary */
        .service-summary {
            background: #e8f5e9;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
            border-left: 4px solid #075B5E; /* Changed from #4CAF50 */
            display: none;
        }
        
        .service-summary-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin: 1rem 0;
        }
        
        .summary-item {
            text-align: center;
            padding: 1rem;
            background: white;
            border-radius: 6px;
            border: 1px solid #dee2e6;
        }
        
        .summary-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #075B5E; /* Changed from #4CAF50 */
            margin-top: 0.5rem;
        }
        
        /* Bill Preview */
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
            border-bottom: 3px double #075B5E; /* Changed from #4CAF50 */
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
        }
        
        .bill-items th {
            background: #075B5E; /* Changed from #4CAF50 */
            color: white;
            padding: 1rem;
            text-align: left;
        }
        
        .bill-items td {
            padding: 1rem;
            border-bottom: 1px solid #dee2e6;
        }
        
        .bill-total {
            background: #f8f9fa;
            font-weight: bold;
            font-size: 1.1em;
        }
        
        /* Footer - Green Theme - UPDATED */
        .footer {
            background: linear-gradient(135deg, #075B5E 0%, #0A6F73 100%); /* Changed from #2E7D32, #4CAF50 */
            color: white;
            text-align: center;
            padding: 2rem;
            margin-top: 3rem;
            border-top: 1px solid #eaeaea;
        }
        
        .footer p {
            margin: 0;
            color: white;
            font-size: 1rem;
        }
        
        /* Bills Grid */
        .bill-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }
        
        .bill-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #075B5E; /* Changed from #4CAF50 */
            transition: transform 0.3s;
        }
        
        .bill-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .bill-card.paid {
            border-left-color: #075B5E; /* Changed from #27ae60 */
        }
        
        /* Status Badges */
        .status-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        .status-pending {
            background: #ffeaa7;
            color: #e17055;
        }
        
        .status-paid {
            background: #55efc4;
            color: #00b894;
        }
        
        /* Quick Actions */
        .quick-actions {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        /* Datetime Display */
        .datetime-display {
            background: #e8f5e9;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
            font-family: monospace;
            border-left: 4px solid #075B5E; /* Changed from #4CAF50 */
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }
        
        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #dee2e6;
        }
        
        /* Utility Classes */
        .select-all-container {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
            padding: 0.5rem;
            background: #f8f9fa;
            border-radius: 6px;
        }
        
        .service-count-badge {
            background: #075B5E; /* Changed from #4CAF50 */
            color: white;
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            font-size: 0.8rem;
            margin-left: 0.5rem;
        }
        
        .no-services-message {
            text-align: center;
            padding: 2rem;
            color: #7f8c8d;
            font-style: italic;
        }
        
        /* Grid Layouts */
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .grid-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1rem;
        }
        
        /* Green Theme Accents - UPDATED */
        .green-accent {
            color: #075B5E; /* Changed from #4CAF50 */
        }
        
        .green-bg {
            background: #075B5E; /* Changed from #4CAF50 */
            color: white;
        }
        
        .light-green-bg {
            background: #e8f5e9;
        }
        
        /* Active Navigation Item */
        .nav-links a.active {
            color: #fff;
            font-weight: bold;
            border-bottom: 2px solid white;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <nav class="navbar">
                <div class="logo">BillPay Pro</div>
                <ul class="nav-links">
                    <li><a href="index.php">Dashboard</a></li>
                    <li><a href="manage_services.php">Manage Services</a></li>
                    <li><a href="manage_bills.php" class="active">Manage Bills</a></li>
                    <li><a href="manage_users.php">Manage Users</a></li>
                    <li><a href="reports.php">Reports</a></li>
                    <li style="display: flex; align-items: center;">
                        <div class="user-avatar">
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
                        <a href="logout.php" style="margin-left: 15px; color: white; text-decoration: underline;">Logout</a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>

    <div class="container">
        <!-- Page Header -->
        <div class="section-header">
            <svg class="section-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
                <line x1="16" y1="13" x2="8" y2="13"></line>
                <line x1="16" y1="17" x2="8" y2="17"></line>
                <polyline points="10 9 9 9 8 9"></polyline>
            </svg>
            <h1>Manage Bills</h1>
        </div>
        
        <!-- Message Display -->
        <?php if ($message): ?>
            <div class="alert <?php echo $message_type == 'success' ? 'alert-success' : 'alert-danger'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="manage_services.php" class="btn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                </svg>
                Manage Services
            </a>
            <a href="manage_users.php" class="btn" style="background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                Add New Customer
            </a>
        </div>

        <!-- Current Date & Time -->
        <div class="datetime-display">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#075B5E" stroke-width="2"> <!-- Changed from #4CAF50 -->
                <circle cx="12" cy="12" r="10"></circle>
                <polyline points="12 6 12 12 16 14"></polyline>
            </svg>
            <strong>Current Time:</strong> <span id="currentTime"><?php echo $current_datetime; ?></span>
        </div>

        <!-- Create Bill Form -->
        <div class="card">
            <div class="section-header">
                <svg class="section-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <h2>Create New Bill</h2>
            </div>
            
            <form method="POST" action="" class="bill-creation" id="billForm">
                <!-- Customer and Services Selection -->
                <div class="grid-2">
                    <div class="form-group">
                        <label for="user_id">Select Customer *</label>
                        <select id="user_id" name="user_id" class="form-control" required onchange="loadCustomerDetails(this.value)">
                            <option value="">Choose Customer</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo $user['full_name'] . ' (' . $user['customer_code'] . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            Select Services *
                            <span id="serviceCountBadge" class="service-count-badge">0 selected</span>
                        </label>
                        <div class="services-checkbox-container" id="servicesCheckboxContainer">
                            <div class="select-all-container">
                                <input type="checkbox" id="selectAllServices" class="service-checkbox" onchange="toggleSelectAll()">
                                <label for="selectAllServices" style="cursor: pointer; font-weight: 600;">Select All Services</label>
                            </div>
                            
                            <?php if (count($services) > 0): ?>
                                <?php foreach ($services as $service): ?>
                                    <div class="service-checkbox-item" data-service-id="<?php echo $service['id']; ?>" onclick="toggleServiceSelection(this, event)">
                                        <input type="checkbox" name="services[]" value="<?php echo $service['id']; ?>" 
                                               class="service-checkbox" id="service_<?php echo $service['id']; ?>"
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
                                    No services available. <a href="manage_services.php" style="color: #075B5E;">Add services first</a>. <!-- Changed from #4CAF50 -->
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Hidden fields for service amounts -->
                <div id="serviceAmountsContainer" style="display: none;"></div>
                
                <!-- Customer Details -->
                <div id="customerDetails" class="customer-details">
                    <h4>Customer Information</h4>
                    <div class="grid-2">
                        <div><strong>Customer Code:</strong> <span id="cust_code">-</span></div>
                        <div><strong>Phone:</strong> <span id="cust_phone">-</span></div>
                        <div><strong>Email:</strong> <span id="cust_email">-</span></div>
                        <div><strong>Address:</strong> <span id="cust_address">-</span></div>
                    </div>
                </div>
                
                <!-- Selected Services -->
                <div id="selectedServicesContainer" class="selected-services-container">
                    <h4>Selected Services <span id="selectedServicesCount">(0)</span></h4>
                    <div id="selectedServicesList"></div>
                    
                    <div id="serviceSummary" class="service-summary">
                        <h4>Service Summary</h4>
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
                
                <!-- Tax and Charges -->
                <div class="grid-3" style="margin-top: 1rem;">
                    <div class="form-group">
                        <label for="tax_rate">Tax Rate (%)</label>
                        <input type="number" id="tax_rate" name="tax_rate" class="form-control" 
                               step="0.01" min="0" max="30" value="13" onchange="updateBillPreview()">
                    </div>
                    
                    <div class="form-group">
                        <label for="late_fee">Service Charge (₹)</label>
                        <input type="number" id="late_fee" name="late_fee" class="form-control" 
                               step="0.01" min="0" value="0" onchange="updateBillPreview()">
                    </div>
                    
                    <div class="form-group">
                        <label for="total_base_amount">Total Base Amount (₹)</label>
                        <input type="text" id="total_base_amount" class="form-control" readonly style="background: #f8f9fa; font-weight: bold;">
                        <input type="hidden" id="amount" name="amount" value="0">
                    </div>
                </div>
                
                <!-- Description -->
                <div class="form-group">
                    <label for="description">Bill Description *</label>
                    <textarea id="description" name="description" class="form-control" rows="3" 
                              placeholder="Detailed description of services provided..." required 
                              oninput="updateBillPreview()"></textarea>
                </div>
                
                <!-- Bill Preview -->
                <div id="billPreview" class="bill-preview">
                    <div class="bill-header">
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
                            <strong>Status:</strong> <span style="color: #f44336;">Pending Payment</span>
                        </div>
                    </div>
                    
                    <table class="bill-items">
                        <thead>
                            <tr>
                                <th>Service Description</th>
                                <th style="text-align: right;">Amount (₹)</th>
                            </tr>
                        </thead>
                        <tbody id="previewServicesBody"></tbody>
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
                
                <!-- Submit Button -->
                <button type="submit" name="create_bill" class="btn" style="width: 100%; padding: 1rem; font-size: 1.1rem;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10 9 9 9 8 9"></polyline>
                    </svg>
                    Generate Bill
                </button>
            </form>
        </div>

        <!-- Bills List -->
        <div class="card">
            <div class="section-header">
                <svg class="section-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="8" y1="6" x2="21" y2="6"></line>
                    <line x1="8" y1="12" x2="21" y2="12"></line>
                    <line x1="8" y1="18" x2="21" y2="18"></line>
                    <line x1="3" y1="6" x2="3.01" y2="6"></line>
                    <line x1="3" y1="12" x2="3.01" y2="12"></line>
                    <line x1="3" y1="18" x2="3.01" y2="18"></line>
                </svg>
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
                        <div class="bill-card <?php echo $status; ?>">
                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                <div style="flex: 1;">
                                    <h3 style="margin-top: 0; color: #333;">Bill #<?php echo $bill['bill_number']; ?></h3>
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
                                    <p><strong>Total Amount:</strong> ₹<?php echo number_format($bill['final_amount'], 2); ?></p>
                                    <p><strong>Bill Date:</strong> <?php echo $bill_datetime; ?></p>
                                    <?php if ($bill['description']): ?>
                                        <p><strong>Description:</strong> <?php echo $bill['description']; ?></p>
                                    <?php endif; ?>
                                </div>
                                <div style="text-align: right;">
                                    <span class="status-badge status-<?php echo $status; ?>">
                                        <?php echo ucfirst($status); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #eee;">
                                <strong>Breakdown:</strong><br>
                                Base: ₹<?php echo number_format($bill['total_amount'], 2); ?> | 
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
                    <div class="empty-state-icon">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                            <polyline points="10 9 9 9 8 9"></polyline>
                        </svg>
                    </div>
                    <h3>No Bills Found</h3>
                    <p>No bills have been created yet.</p>
                    <p>Create your first bill using the form above.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Green and White Footer -->
    <div class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Online Billing System - BCA Project | Tribhuvan University</p>
        </div>
    </div>

    <script>
        // Service data
        const services = {
            <?php foreach ($services as $service): ?>
                '<?php echo $service['id']; ?>': {
                    name: '<?php echo addslashes($service['service_name']); ?>',
                    base_amount: <?php echo $service['base_amount']; ?>,
                    description: '<?php echo addslashes($service['description']); ?>'
                },
            <?php endforeach; ?>
        };

        // Customer data
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
            if (event.target.type === 'checkbox') return;
            const checkbox = serviceItem.querySelector('.service-checkbox');
            checkbox.checked = !checkbox.checked;
            updateSelectedServices();
        }
        
        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAllServices').checked;
            document.querySelectorAll('.service-checkbox').forEach(cb => cb.checked = selectAll);
            document.querySelectorAll('.service-checkbox-item').forEach(item => {
                item.classList.toggle('selected', selectAll);
            });
            updateSelectedServices();
        }
        
        function updateSelectedServices() {
            const checkboxes = document.querySelectorAll('.service-checkbox');
            const selected = Array.from(checkboxes).filter(cb => cb.checked);
            const selectedCount = selected.length;
            
            // Update badges
            document.getElementById('serviceCountBadge').textContent = selectedCount + ' selected';
            document.getElementById('selectedServicesCount').textContent = '(' + selectedCount + ')';
            
            // Update UI
            document.querySelectorAll('.service-checkbox-item').forEach(item => {
                const checkbox = item.querySelector('.service-checkbox');
                item.classList.toggle('selected', checkbox.checked);
            });
            
            // Update select all checkbox
            const allChecked = selectedCount === checkboxes.length;
            const someChecked = selectedCount > 0 && selectedCount < checkboxes.length;
            const selectAll = document.getElementById('selectAllServices');
            selectAll.checked = allChecked;
            selectAll.indeterminate = someChecked;
            
            // Update selected services list
            const container = document.getElementById('selectedServicesContainer');
            const list = document.getElementById('selectedServicesList');
            const summary = document.getElementById('serviceSummary');
            const amountsContainer = document.getElementById('serviceAmountsContainer');
            
            if (selectedCount === 0) {
                container.style.display = 'none';
                summary.style.display = 'none';
                selectedServices = [];
                updateTotals();
                return;
            }
            
            container.style.display = 'block';
            summary.style.display = 'block';
            
            // Clear and rebuild
            list.innerHTML = '';
            amountsContainer.innerHTML = '';
            selectedServices = [];
            
            selected.forEach((checkbox, index) => {
                const serviceId = checkbox.value;
                const service = services[serviceId];
                if (!service) return;
                
                const serviceItem = {
                    id: serviceId,
                    name: service.name,
                    baseAmount: service.base_amount,
                    customAmount: service.base_amount,
                    index: index
                };
                selectedServices.push(serviceItem);
                
                // Create list item
                const div = document.createElement('div');
                div.className = 'selected-service-item';
                div.innerHTML = `
                    <div style="flex: 1;">
                        <strong>${service.name}</strong><br>
                        <small>Base Price: ₹${service.base_amount.toFixed(2)}</small>
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <span>Amount:</span>
                        <input type="number" class="service-amount-input" 
                               value="${service.base_amount.toFixed(2)}" step="0.01" min="0"
                               data-service-id="${serviceId}" data-index="${index}"
                               onchange="updateServiceAmount(this)">
                    </div>
                    <button type="button" class="remove-service-btn" onclick="removeService('${serviceId}')">
                        Remove
                    </button>
                `;
                list.appendChild(div);
                
                // Add hidden input
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'service_amounts[]';
                input.value = service.base_amount;
                input.id = `service_amount_${serviceId}`;
                amountsContainer.appendChild(input);
            });
            
            updateTotals();
            updateBillPreview();
        }
        
        function updateServiceAmount(input) {
            const serviceId = input.dataset.serviceId;
            const index = parseInt(input.dataset.index);
            const newAmount = parseFloat(input.value) || 0;
            
            // Update hidden input
            const hiddenInput = document.getElementById(`service_amount_${serviceId}`);
            if (hiddenInput) hiddenInput.value = newAmount;
            
            // Update selectedServices array
            const serviceIndex = selectedServices.findIndex(s => s.id === serviceId);
            if (serviceIndex !== -1) {
                selectedServices[serviceIndex].customAmount = newAmount;
                updateTotals();
                updateBillPreview();
            }
        }
        
        function removeService(serviceId) {
            const checkbox = document.querySelector(`input.service-checkbox[value="${serviceId}"]`);
            if (checkbox) checkbox.checked = false;
            updateSelectedServices();
        }
        
        function updateTotals() {
            let totalBase = 0;
            let totalCustom = 0;
            
            selectedServices.forEach(service => {
                totalBase += service.baseAmount;
                totalCustom += service.customAmount;
            });
            
            const adjustments = totalCustom - totalBase;
            
            document.getElementById('summaryServiceCount').textContent = selectedServices.length;
            document.getElementById('summaryBaseAmount').textContent = '₹' + totalBase.toFixed(2);
            document.getElementById('summaryAdjustments').textContent = (adjustments >= 0 ? '+₹' : '-₹') + Math.abs(adjustments).toFixed(2);
            document.getElementById('total_base_amount').value = '₹' + totalCustom.toFixed(2);
            document.getElementById('amount').value = totalCustom.toFixed(2);
        }
        
        function updateBillPreview() {
            const preview = document.getElementById('billPreview');
            const userId = document.getElementById('user_id').value;
            const customer = customers[userId];
            const taxRate = parseFloat(document.getElementById('tax_rate').value) || 0;
            const serviceCharge = parseFloat(document.getElementById('late_fee').value) || 0;
            const totalBase = parseFloat(document.getElementById('amount').value) || 0;
            
            if (customer && selectedServices.length > 0 && totalBase > 0) {
                const taxAmount = (totalBase * taxRate) / 100;
                const totalAmount = totalBase + taxAmount + serviceCharge;
                
                // Update customer info
                document.getElementById('preview_customer').textContent = customer.name;
                document.getElementById('preview_address').textContent = customer.address || 'Address not provided';
                document.getElementById('preview_phone').textContent = customer.phone;
                document.getElementById('preview_email').textContent = customer.email;
                
                // Update services
                const body = document.getElementById('previewServicesBody');
                body.innerHTML = '';
                selectedServices.forEach(service => {
                    const isAdjusted = service.customAmount !== service.baseAmount;
                    const adjustment = isAdjusted ? 
                        ` <small style="color: #666;">(Base: ₹${service.baseAmount.toFixed(2)})</small>` : '';
                    body.innerHTML += `
                        <tr>
                            <td>${service.name}${adjustment}</td>
                            <td style="text-align: right;">₹${service.customAmount.toFixed(2)}</td>
                        </tr>
                    `;
                });
                
                // Update totals
                document.getElementById('preview_subtotal').textContent = '₹' + totalBase.toFixed(2);
                document.getElementById('preview_tax_rate').textContent = taxRate;
                document.getElementById('preview_tax').textContent = '₹' + taxAmount.toFixed(2);
                document.getElementById('preview_late_fee').textContent = '₹' + serviceCharge.toFixed(2);
                document.getElementById('preview_total').textContent = '₹' + totalAmount.toFixed(2);
                
                // Update date/time
                const now = new Date();
                document.getElementById('preview_date').textContent = now.toLocaleDateString('en-US', { 
                    year: 'numeric', month: 'short', day: 'numeric' 
                });
                document.getElementById('preview_time').textContent = now.toLocaleTimeString('en-US', { 
                    hour: '2-digit', minute: '2-digit', hour12: true 
                });
                document.getElementById('preview_generated_time').textContent = now.toLocaleDateString('en-US', { 
                    year: 'numeric', month: 'short', day: 'numeric',
                    hour: '2-digit', minute: '2-digit', hour12: true 
                });
                
                preview.style.display = 'block';
            } else {
                preview.style.display = 'none';
            }
        }
        
        function updateCurrentTime() {
            const now = new Date();
            const options = { 
                year: 'numeric', month: 'short', day: 'numeric',
                hour: '2-digit', minute: '2-digit', hour12: true 
            };
            document.getElementById('currentTime').textContent = now.toLocaleDateString('en-US', options);
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($preselected_service_id): ?>
                const checkbox = document.querySelector(`input.service-checkbox[value="<?php echo $preselected_service_id; ?>"]`);
                if (checkbox) {
                    checkbox.checked = true;
                    const item = checkbox.closest('.service-checkbox-item');
                    if (item) item.classList.add('selected');
                    updateSelectedServices();
                }
            <?php endif; ?>
            
            updateCurrentTime();
            setInterval(updateCurrentTime, 60000);
        });
    </script>
</body>
</html>