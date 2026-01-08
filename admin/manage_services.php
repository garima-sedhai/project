<?php
session_start();
include '../includes/config.php';

// Redirect if not logged in as admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: login.php");
    exit();
}

// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

$message = '';
$message_type = '';

// Check and fix database structure on page load
try {
    // Check if we need to copy data from 'name' to 'service_name'
    $stmt = $pdo->query("SELECT id, name, service_name FROM services WHERE service_name IS NULL AND name IS NOT NULL LIMIT 1");
    $needsUpdate = $stmt->fetch();
    
    if ($needsUpdate) {
        // Copy data from name to service_name
        $pdo->exec("UPDATE services SET service_name = name WHERE service_name IS NULL AND name IS NOT NULL");
        error_log("Copied service names from 'name' to 'service_name' column");
    }
    
    // Check if service_name column exists at all
    $stmt = $pdo->query("SHOW COLUMNS FROM services LIKE 'service_name'");
    $hasServiceName = $stmt->fetch();
    
    if (!$hasServiceName) {
        // Add service_name column if it doesn't exist
        $pdo->exec("ALTER TABLE services ADD COLUMN service_name VARCHAR(255)");
        error_log("Added service_name column to services table");
    }
} catch (Exception $e) {
    error_log("Database check error: " . $e->getMessage());
}

// Prevent duplicate form submissions
$formSubmitted = false;
if (isset($_SESSION['last_form_submission'])) {
    $timeSinceLast = time() - $_SESSION['last_form_submission'];
    if ($timeSinceLast < 2 && $_SERVER['REQUEST_METHOD'] == 'POST') {
        $formSubmitted = true;
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$formSubmitted) {
    $_SESSION['last_form_submission'] = time();
    
    if (isset($_POST['add_service'])) {
        // Add new service
        $service_name = trim($_POST['service_name']);
        $service_type = trim($_POST['service_type']);
        $description = trim($_POST['description']);
        $base_amount = !empty($_POST['base_amount']) ? $_POST['base_amount'] : 0.00;
        
        // Validate inputs
        if (empty($service_name) || empty($service_type)) {
            $message = "Please fill service name and type";
            $message_type = "error";
        } else {
            try {
                // Check if service with same name and type already exists
                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM services WHERE service_name = ? AND service_type = ?");
                $checkStmt->execute([$service_name, $service_type]);
                $count = $checkStmt->fetchColumn();
                
                if ($count > 0) {
                    $message = "Service with this name and category already exists!";
                    $message_type = "error";
                } else {
                    // Insert new service
                    $stmt = $pdo->prepare("INSERT INTO services (service_name, service_type, description, base_amount, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())");
                    
                    if ($stmt->execute([$service_name, $service_type, $description, $base_amount])) {
                        $message = "Service added successfully!";
                        $message_type = "success";
                        
                        // Clear form by redirecting
                        header("Location: manage_services.php?success=added");
                        exit();
                    } else {
                        $message = "Error adding service.";
                        $message_type = "error";
                    }
                }
            } catch (Exception $e) {
                // If service_name fails, try with name column
                try {
                    $stmt = $pdo->prepare("INSERT INTO services (name, service_type, description, base_amount, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())");
                    
                    if ($stmt->execute([$service_name, $service_type, $description, $base_amount])) {
                        // Also update service_name if column exists
                        try {
                            $service_id = $pdo->lastInsertId();
                            $updateStmt = $pdo->prepare("UPDATE services SET service_name = ? WHERE id = ?");
                            $updateStmt->execute([$service_name, $service_id]);
                        } catch (Exception $updateE) {
                            // Ignore update error
                        }
                        
                        $message = "Service added successfully!";
                        $message_type = "success";
                        
                        header("Location: manage_services.php?success=added");
                        exit();
                    }
                } catch (Exception $e2) {
                    $message = "Database error: " . $e->getMessage();
                    $message_type = "error";
                    error_log("Add service error: " . $e->getMessage());
                }
            }
        }
    } elseif (isset($_POST['update_service'])) {
        // Update service
        $service_id = $_POST['service_id'];
        $service_name = trim($_POST['service_name']);
        $service_type = trim($_POST['service_type']);
        $description = trim($_POST['description']);
        $base_amount = !empty($_POST['base_amount']) ? $_POST['base_amount'] : 0.00;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        try {
            // Check if service exists (except current one)
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM services WHERE service_name = ? AND service_type = ? AND id != ?");
            $checkStmt->execute([$service_name, $service_type, $service_id]);
            $count = $checkStmt->fetchColumn();
            
            if ($count > 0) {
                $message = "Service with this name and category already exists!";
                $message_type = "error";
            } else {
                // Try updating with service_name
                try {
                    $stmt = $pdo->prepare("UPDATE services SET service_name = ?, service_type = ?, description = ?, base_amount = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
                    
                    if ($stmt->execute([$service_name, $service_type, $description, $base_amount, $is_active, $service_id])) {
                        $message = "Service updated successfully!";
                        $message_type = "success";
                        
                        header("Location: manage_services.php?success=updated&id=" . $service_id);
                        exit();
                    }
                } catch (Exception $updateE) {
                    // If service_name fails, try with name column
                    $stmt = $pdo->prepare("UPDATE services SET name = ?, service_type = ?, description = ?, base_amount = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
                    
                    if ($stmt->execute([$service_name, $service_type, $description, $base_amount, $is_active, $service_id])) {
                        $message = "Service updated successfully!";
                        $message_type = "success";
                        
                        header("Location: manage_services.php?success=updated&id=" . $service_id);
                        exit();
                    }
                }
            }
        } catch (Exception $e) {
            $message = "Database error: " . $e->getMessage();
            $message_type = "error";
            error_log("Update service error: " . $e->getMessage());
        }
    } elseif (isset($_POST['delete_service'])) {
        // Delete service
        $service_id = $_POST['service_id'];
        
        try {
            $can_delete = true;
            $error_message = '';
            
            // Check if service is referenced in bill_items table
            try {
                // First check if bill_items table exists
                $tableCheck = $pdo->query("SHOW TABLES LIKE 'bill_items'");
                if ($tableCheck->rowCount() > 0) {
                    // Check both product_id and service_id columns
                    $total_count = 0;
                    
                    // Check product_id column
                    $columnCheck = $pdo->query("SHOW COLUMNS FROM bill_items LIKE 'product_id'");
                    if ($columnCheck->rowCount() > 0) {
                        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM bill_items WHERE product_id = ?");
                        $checkStmt->execute([$service_id]);
                        $product_count = $checkStmt->fetchColumn();
                        $total_count += $product_count;
                    }
                    
                    // Check service_id column
                    $columnCheck = $pdo->query("SHOW COLUMNS FROM bill_items LIKE 'service_id'");
                    if ($columnCheck->rowCount() > 0) {
                        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM bill_items WHERE service_id = ?");
                        $checkStmt->execute([$service_id]);
                        $service_count = $checkStmt->fetchColumn();
                        $total_count += $service_count;
                    }
                    
                    if ($total_count > 0) {
                        $can_delete = false;
                        $error_message = "Cannot delete service because it is used in $total_count existing bill(s)!";
                    }
                }
            } catch (Exception $checkE) {
                // If check fails, assume no references
                error_log("Reference check error: " . $checkE->getMessage());
            }
            
            // If we can delete, proceed
            if ($can_delete) {
                $stmt = $pdo->prepare("DELETE FROM services WHERE id = ?");
                if ($stmt->execute([$service_id])) {
                    $message = "Service deleted successfully!";
                    $message_type = "success";
                    
                    header("Location: manage_services.php?success=deleted");
                    exit();
                } else {
                    $message = "Error deleting service.";
                    $message_type = "error";
                }
            } else {
                $message = $error_message;
                $message_type = "error";
            }
            
        } catch (Exception $e) {
            $message = "Database error: " . $e->getMessage();
            $message_type = "error";
            error_log("Delete service error: " . $e->getMessage());
        }
    }
}

// Check for success messages from redirect
if (isset($_GET['success'])) {
    if ($_GET['success'] == 'added') {
        $message = "Service added successfully!";
        $message_type = "success";
    } elseif ($_GET['success'] == 'updated') {
        $message = "Service updated successfully!";
        $message_type = "success";
    } elseif ($_GET['success'] == 'deleted') {
        $message = "Service deleted successfully!";
        $message_type = "success";
    }
}

// Get all services - FIXED VERSION
try {
    // First check what columns exist
    $columnCheck = $pdo->query("SHOW COLUMNS FROM services");
    $columns = $columnCheck->fetchAll(PDO::FETCH_COLUMN);
    
    $hasServiceName = in_array('service_name', $columns);
    $hasName = in_array('name', $columns);
    
    // Build query based on available columns
    $query = "";
    if ($hasServiceName && $hasName) {
        // Both columns exist, prefer service_name
        $query = "SELECT 
                    id,
                    COALESCE(service_name, name) as service_name,
                    service_type,
                    description,
                    base_amount,
                    is_active,
                    created_at
                  FROM services 
                  ORDER BY COALESCE(service_name, name) ASC";
    } elseif ($hasServiceName) {
        // Only service_name exists
        $query = "SELECT 
                    id,
                    service_name,
                    service_type,
                    description,
                    base_amount,
                    is_active,
                    created_at
                  FROM services 
                  ORDER BY service_name ASC";
    } elseif ($hasName) {
        // Only name exists
        $query = "SELECT 
                    id,
                    name as service_name,
                    service_type,
                    description,
                    base_amount,
                    is_active,
                    created_at
                  FROM services 
                  ORDER BY name ASC";
    } else {
        // No name columns found, create fallback
        $query = "SELECT 
                    id,
                    'Unnamed Service' as service_name,
                    COALESCE(service_type, 'General') as service_type,
                    description,
                    COALESCE(base_amount, 0.00) as base_amount,
                    COALESCE(is_active, 1) as is_active,
                    created_at
                  FROM services 
                  ORDER BY id ASC";
    }
    
    // Execute query
    $stmt = $pdo->query($query);
    $allServices = $stmt->fetchAll();
    
    // Process services and remove any duplicates by ID
    $services = [];
    $processedIds = [];
    
    foreach ($allServices as $service) {
        $serviceId = $service['id'];
        
        // Skip if we've already processed this ID (prevent duplicates)
        if (in_array($serviceId, $processedIds)) {
            continue;
        }
        
        $processedIds[] = $serviceId;
        
        // Ensure all required fields are set
        $service['service_name'] = $service['service_name'] ?? 'Unnamed Service';
        $service['service_type'] = $service['service_type'] ?? 'General';
        $service['description'] = $service['description'] ?? '';
        $service['base_amount'] = $service['base_amount'] ?? 0.00;
        $service['is_active'] = $service['is_active'] ?? 1;
        
        $services[] = $service;
    }
    
    // Log for debugging
    error_log("Processed " . count($services) . " unique services from database");
    
} catch (Exception $e) {
    $services = [];
    $message = "Error loading services: " . $e->getMessage();
    $message_type = "error";
    error_log("Load services error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Services - BillPay Pro</title>
    <link rel="stylesheet" href="../css/style.css">
    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        body {
            background: #f5f7fa;
            min-height: 100vh;
            margin: 0;
        }
        
        .service-form {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            border: 1px solid #e0e0e0;
        }
        
        .service-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }
        
        .service-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 5px solid #075B5E;
            position: relative;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .service-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
        }
        
        .service-card.inactive {
            opacity: 0.7;
            border-left-color: #95a5a6;
        }
        
        .service-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }
        
        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            font-weight: 500;
        }
        
        .btn-primary {
            background: #075B5E;
            color: white;
        }
        
        .btn-primary:hover {
            background: #064c4f;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .service-price {
            font-size: 1.5rem;
            font-weight: bold;
            color: #075B5E;
            margin: 0.5rem 0;
        }
        
        .service-type {
            background: #f0f7f7;
            color: #075B5E;
            padding: 0.3rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            display: inline-block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .section-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .section-icon {
            width: 2rem;
            height: 2rem;
            color: #075B5E;
        }
        
        .form-icon {
            width: 1rem;
            height: 1rem;
            margin-right: 0.5rem;
            vertical-align: middle;
        }
        
        .btn-icon {
            width: 1rem;
            height: 1rem;
        }
        
        .service-status {
            display: flex;
            align-items: center;
            gap: 0.3rem;
            margin-top: 0.5rem;
            font-size: 0.9rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .empty-state-icon {
            width: 4rem;
            height: 4rem;
            color: #6c757d;
            margin: 0 auto 1rem auto;
            display: block;
        }
        
        .quick-actions {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        
        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(2px);
        }
        
        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            animation: modalSlideIn 0.3s ease;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .modal-close {
            background: none;
            border: none;
            cursor: pointer;
            color: #666;
            padding: 0;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background-color 0.2s;
        }
        
        .modal-close:hover {
            background: #f8f9fa;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: all 0.3s;
            box-sizing: border-box;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #075B5E;
            box-shadow: 0 0 0 3px rgba(7, 91, 94, 0.1);
        }
        
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
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
        
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 5px;
            font-size: 1rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            font-weight: 500;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .form-group {
            margin-bottom: 1.25rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #075B5E;
            font-weight: 500;
            font-size: 0.95rem;
        }
        
        .form-group small {
            color: #666;
            font-size: 0.85rem;
            display: block;
            margin-top: 0.25rem;
        }
        
        /* Table styles */
        .services-table-container {
            overflow-x: auto;
            margin-top: 1rem;
        }
        
        .services-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .services-table th {
            background: #075B5E;
            color: white;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
        }
        
        .services-table td {
            padding: 1rem;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .services-table tr:last-child td {
            border-bottom: none;
        }
        
        .services-table tr:hover {
            background: #f8f9fa;
        }
        
        /* Status badges */
        .status-active {
            color: #28a745;
            font-weight: 500;
        }
        
        .status-inactive {
            color: #6c757d;
            font-weight: 500;
        }
        
        /* Serial number column */
        .serial-col {
            width: 50px;
            text-align: center;
            color: #666;
            font-weight: bold;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .service-grid {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
                flex-direction: column;
            }
            
            .modal-content {
                padding: 1.5rem;
                width: 95%;
            }
            
            .service-actions {
                flex-direction: column;
            }
            
            .btn-small {
                width: 100%;
                justify-content: center;
            }
            
            .services-table {
                font-size: 0.9rem;
            }
            
            .services-table th,
            .services-table td {
                padding: 0.75rem 0.5rem;
            }
        }
    </style>
</head>
<body>
    <header class="header" style="background: #075B5E; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);">
        <div class="container" style="max-width: 1200px; margin: 0 auto; padding: 0 20px;">
            <nav class="navbar" style="display: flex; justify-content: space-between; align-items: center; padding: 0.8rem 0;">
                <div class="logo" style="font-size: 1.6rem; font-weight: bold; color: white;">BillPay Pro</div>
                <ul class="nav-links" style="display: flex; list-style: none; gap: 1.5rem; margin: 0; padding: 0;">
                    <li><a href="index.php" style="text-decoration: none; color: white; font-weight: 500;">Dashboard</a></li>
                    <li><a href="manage_services.php" style="text-decoration: none; color: white; font-weight: 500; border-bottom: 2px solid white;">Manage Services</a></li>
                    <li><a href="manage_bills.php" style="text-decoration: none; color: white; font-weight: 500;">Manage Bills</a></li>
                    <li><a href="manage_users.php" style="text-decoration: none; color: white; font-weight: 500;">Manage Users</a></li>
                    <li><a href="reports.php" style="text-decoration: none; color: white; font-weight: 500;">Reports</a></li>
                    <li style="display: flex; align-items: center; gap: 0.5rem; color: white;">
                        <div class="user-avatar" style="background: #0a7c80; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 0.9rem;">
                            <?php 
                            $names = explode(' ', $_SESSION['full_name']);
                            $initials = '';
                            foreach ($names as $n) {
                                $initials .= strtoupper(substr($n, 0, 1));
                            }
                            echo substr($initials, 0, 2);
                            ?>
                        </div>
                        <span><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                        <a href="logout.php" style="margin-left: 15px; color: white; text-decoration: none;">Logout</a>
                    </li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="container" style="max-width: 1200px; margin: 0 auto; padding: 0 20px; padding-top: 2rem;">
        <div class="section-header">
            <i data-lucide="settings" class="section-icon"></i>
            <h1 style="color: #075B5E; margin: 0;">Manage Services</h1>
        </div>
        
        <?php if ($message): ?>
            <div class="<?php echo $message_type == 'success' ? 'alert alert-success' : 'alert alert-error'; ?>">
                <?php if ($message_type == 'success'): ?>
                    <i data-lucide="check-circle" style="width: 1.2rem; height: 1.2rem;"></i>
                <?php else: ?>
                    <i data-lucide="alert-circle" style="width: 1.2rem; height: 1.2rem;"></i>
                <?php endif; ?>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="manage_bills.php" class="btn btn-primary">
                <i data-lucide="file-text" class="btn-icon"></i>
                Create New Bill
            </a>
            <a href="manage_users.php" class="btn btn-primary">
                <i data-lucide="users" class="btn-icon"></i>
                Manage Customers
            </a>
        </div>

        <!-- Add Service Form -->
        <div class="service-form">
            <div class="section-header">
                <i data-lucide="plus-circle" style="width: 1.5rem; height: 1.5rem; color: #075B5E;"></i>
                <h2 style="color: #075B5E; margin: 0;">Add New Service</h2>
            </div>
            <form method="POST" action="">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div class="form-group">
                        <label for="service_name">
                            <i data-lucide="tag" class="form-icon"></i>
                            Service Name *
                        </label>
                        <input type="text" id="service_name" name="service_name" class="form-control" 
                               placeholder="e.g., Room Service, Laundry, Internet" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="service_type">
                            <i data-lucide="layers" class="form-icon"></i>
                            Service Category *
                        </label>
                        <input type="text" id="service_type" name="service_type" class="form-control" 
                               placeholder="e.g., Hotel Services, Restaurant, Spa" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description">
                        <i data-lucide="file-text" class="form-icon"></i>
                        Service Description
                    </label>
                    <textarea id="description" name="description" class="form-control" rows="3" 
                              placeholder="Detailed description of the service..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="base_amount">
                        <i data-lucide="dollar-sign" class="form-icon"></i>
                        Base Price (₹)
                    </label>
                    <input type="number" id="base_amount" name="base_amount" class="form-control" 
                           step="0.01" min="0" value="0.00" required>
                    <small>This will be used as default price when creating bills</small>
                </div>
                
                <button type="submit" name="add_service" class="btn btn-primary">
                    <i data-lucide="plus" style="width: 1.2rem; height: 1.2rem;"></i>
                    Add Service
                </button>
            </form>
        </div>

        <!-- Services List -->
        <div style="background: white; border-radius: 10px; padding: 2rem; box-shadow: 0 2px 15px rgba(0,0,0,0.08); border: 1px solid #e0e0e0;">
            <div class="section-header">
                <i data-lucide="list" style="width: 1.5rem; height: 1.5rem; color: #075B5E;"></i>
                <h2 style="color: #075B5E; margin: 0;">Available Services (<?php echo count($services); ?>)</h2>
            </div>
            
            <?php if (count($services) > 0): ?>
                <!-- Table View -->
                <div class="services-table-container">
                    <table class="services-table">
                        <thead>
                            <tr>
                                <th class="serial-col">#</th>
                                <th>Service Name</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $counter = 1;
                            foreach ($services as $service): 
                                $serviceId = $service['id'] ?? 0;
                                $serviceName = $service['service_name'] ?? 'Unnamed Service';
                                $serviceType = $service['service_type'] ?? 'General';
                                $description = $service['description'] ?? '';
                                $baseAmount = $service['base_amount'] ?? 0;
                                $isActive = $service['is_active'] ?? 1;
                            ?>
                                <tr>
                                    <td class="serial-col"><?php echo $counter++; ?></td>
                                    <td>
                                        <strong style="color: #075B5E;"><?php echo htmlspecialchars($serviceName); ?></strong>
                                        <?php if (!empty($description)): ?>
                                            <br><small style="color: #666; font-size: 0.85rem;"><?php echo htmlspecialchars(substr($description, 0, 60)); ?><?php echo strlen($description) > 60 ? '...' : ''; ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="service-type"><?php echo htmlspecialchars($serviceType); ?></span>
                                    </td>
                                    <td>
                                        <span class="service-price">₹<?php echo number_format($baseAmount, 2); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($isActive): ?>
                                            <span class="status-active" style="display: flex; align-items: center; gap: 0.3rem;">
                                                <i data-lucide="check-circle" style="width: 1rem; height: 1rem;"></i>
                                                Active
                                            </span>
                                        <?php else: ?>
                                            <span class="status-inactive" style="display: flex; align-items: center; gap: 0.3rem;">
                                                <i data-lucide="x-circle" style="width: 1rem; height: 1rem;"></i>
                                                Inactive
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="service-actions">
                                            <button type="button" class="btn-small btn-primary" onclick="editService(<?php echo $serviceId; ?>)">
                                                <i data-lucide="edit" class="btn-icon"></i>
                                                Edit
                                            </button>
                                            <form method="POST" action="" style="display: inline;">
                                                <input type="hidden" name="service_id" value="<?php echo $serviceId; ?>">
                                                <button type="submit" name="delete_service" class="btn-small btn-danger" 
                                                        onclick="return confirm('Are you sure you want to delete this service?')">
                                                    <i data-lucide="trash-2" class="btn-icon"></i>
                                                    Delete
                                                </button>
                                            </form>
                                            <a href="manage_bills.php?service_id=<?php echo $serviceId; ?>" class="btn-small btn-success">
                                                <i data-lucide="file-text" class="btn-icon"></i>
                                                Create Bill
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i data-lucide="settings" class="empty-state-icon"></i>
                    <h3 style="color: #075B5E;">No Services Found</h3>
                    <p style="color: #666;">Add your first service using the form above.</p>
                    <p style="color: #666; font-size: 0.9rem;">Services you add will appear here and can be used when creating bills.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit Service Modal -->
    <div id="editModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <div class="section-header">
                    <i data-lucide="edit" style="width: 1.5rem; height: 1.5rem; color: #075B5E;"></i>
                    <h2 style="margin: 0; color: #075B5E;">Edit Service</h2>
                </div>
                <button type="button" class="modal-close" onclick="closeEditModal()">
                    <i data-lucide="x" style="width: 1.2rem; height: 1.2rem; color: #666;"></i>
                </button>
            </div>
            <form method="POST" action="" id="editForm">
                <input type="hidden" name="service_id" id="edit_service_id">
                
                <div class="form-group">
                    <label for="edit_service_name">
                        <i data-lucide="tag" class="form-icon"></i>
                        Service Name *
                    </label>
                    <input type="text" id="edit_service_name" name="service_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_service_type">
                        <i data-lucide="layers" class="form-icon"></i>
                        Service Category *
                    </label>
                    <input type="text" id="edit_service_type" name="service_type" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_description">
                        <i data-lucide="file-text" class="form-icon"></i>
                        Description
                    </label>
                    <textarea id="edit_description" name="description" class="form-control" rows="3" placeholder="Service description..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="edit_base_amount">
                        <i data-lucide="dollar-sign" class="form-icon"></i>
                        Base Price (₹)
                    </label>
                    <input type="number" id="edit_base_amount" name="base_amount" class="form-control" step="0.01" min="0" required>
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; color: #075B5E; font-weight: 500;">
                        <input type="checkbox" id="edit_is_active" name="is_active" value="1" style="width: 1.2rem; height: 1.2rem;">
                        <i data-lucide="power" style="width: 1rem; height: 1rem;"></i>
                        Active Service
                    </label>
                </div>
                
                <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                    <button type="submit" name="update_service" class="btn btn-primary" style="flex: 1;">
                        <i data-lucide="save" style="width: 1.2rem; height: 1.2rem;"></i>
                        Update Service
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">
                        <i data-lucide="x" style="width: 1.2rem; height: 1.2rem;"></i>
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <footer class="footer" style="background: #075B5E; color: white; padding: 1.5rem 0; margin-top: 3rem;">
        <div class="container" style="max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: center; align-items: center;">
            <p style="margin: 0; font-size: 0.9rem;">&copy; <?php echo date('Y'); ?> BillPay Pro - Online Billing System</p>
        </div>
    </footer>

    <script>
        // Service data from PHP
        const servicesData = {
            <?php foreach ($services as $service): 
                $serviceId = $service['id'] ?? 0;
                $serviceName = $service['service_name'];
            ?>
                '<?php echo $serviceId; ?>': {
                    id: <?php echo $serviceId; ?>,
                    service_name: `<?php echo addslashes($serviceName); ?>`,
                    service_type: `<?php echo addslashes($service['service_type'] ?? ''); ?>`,
                    description: `<?php echo addslashes($service['description'] ?? ''); ?>`,
                    base_amount: <?php echo $service['base_amount'] ?? 0; ?>,
                    is_active: <?php echo $service['is_active'] ?? 0; ?>
                },
            <?php endforeach; ?>
        };

        // Track if modal is already open for a service
        let currentEditingServiceId = null;
        
        function editService(serviceId) {
            const service = servicesData[serviceId];
            
            if (!service) {
                alert('Service data not found');
                return;
            }
            
            // Don't reopen if already editing this service
            if (currentEditingServiceId === serviceId && document.getElementById('editModal').style.display === 'flex') {
                return;
            }
            
            currentEditingServiceId = serviceId;
            
            // Fill the form with service data
            document.getElementById('edit_service_id').value = service.id;
            document.getElementById('edit_service_name').value = service.service_name;
            document.getElementById('edit_service_type').value = service.service_type;
            document.getElementById('edit_description').value = service.description;
            document.getElementById('edit_base_amount').value = service.base_amount;
            document.getElementById('edit_is_active').checked = service.is_active == 1;
            
            // Show the modal
            document.getElementById('editModal').style.display = 'flex';
            
            // Re-initialize Lucide icons in the modal
            lucide.createIcons();
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
            currentEditingServiceId = null;
            // Clear form when closing
            document.getElementById('editForm').reset();
        }
        
        // Close modal when clicking outside
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeEditModal();
            }
        });
        
        // Initialize Lucide Icons
        lucide.createIcons();
    </script>
</body>
</html>