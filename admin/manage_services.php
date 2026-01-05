<?php
session_start();
include '../includes/config.php';

// Redirect if not logged in as admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: login.php");
    exit();
}

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_service'])) {
        // Add new service
        $service_name = trim($_POST['service_name']);
        $service_type = trim($_POST['service_type']);
        $description = trim($_POST['description']);
        $base_amount = $_POST['base_amount'];
        
        // Validate inputs
        if (empty($service_name) || empty($service_type)) {
            $message = "Please fill service name and type";
            $message_type = "error";
        } else {
            $stmt = $pdo->prepare("INSERT INTO services (service_name, service_type, description, base_amount) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$service_name, $service_type, $description, $base_amount])) {
                $message = "Service added successfully!";
                $message_type = "success";
            } else {
                $message = "Error adding service.";
                $message_type = "error";
            }
        }
    } elseif (isset($_POST['update_service'])) {
        // Update service
        $service_id = $_POST['service_id'];
        $service_name = trim($_POST['service_name']);
        $service_type = trim($_POST['service_type']);
        $description = trim($_POST['description']);
        $base_amount = $_POST['base_amount'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $stmt = $pdo->prepare("UPDATE services SET service_name = ?, service_type = ?, description = ?, base_amount = ?, is_active = ? WHERE id = ?");
        if ($stmt->execute([$service_name, $service_type, $description, $base_amount, $is_active, $service_id])) {
            $message = "Service updated successfully!";
            $message_type = "success";
            
            // Refresh the page to show updated data
            header("Location: manage_services.php?success=updated");
            exit();
        } else {
            $message = "Error updating service.";
            $message_type = "error";
        }
    } elseif (isset($_POST['delete_service'])) {
        // Delete service
        $service_id = $_POST['service_id'];
        
        $stmt = $pdo->prepare("DELETE FROM services WHERE id = ?");
        if ($stmt->execute([$service_id])) {
            $message = "Service deleted successfully!";
            $message_type = "success";
            
            // Refresh the page
            header("Location: manage_services.php?success=deleted");
            exit();
        } else {
            $message = "Error deleting service.";
            $message_type = "error";
        }
    }
}

// Check for success messages from redirect
if (isset($_GET['success'])) {
    if ($_GET['success'] == 'updated') {
        $message = "Service updated successfully!";
        $message_type = "success";
    } elseif ($_GET['success'] == 'deleted') {
        $message = "Service deleted successfully!";
        $message_type = "success";
    }
}

// Get all services
$stmt = $pdo->query("SELECT * FROM services ORDER BY service_name");
$services = $stmt->fetchAll();
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
        .service-form {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        
        .service-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }
        
        .service-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #3498db;
            position: relative;
        }
        
        .service-card.inactive {
            opacity: 0.6;
            border-left-color: #95a5a6;
        }
        
        .service-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }
        
        .btn-small {
            padding: 0.3rem 0.8rem;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        .service-price {
            font-size: 1.2rem;
            font-weight: bold;
            color: #27ae60;
            margin: 0.5rem 0;
        }
        
        .service-type {
            background: #3498db;
            color: white;
            padding: 0.2rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            display: inline-block;
            margin-bottom: 0.5rem;
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
        
        .service-status {
            display: flex;
            align-items: center;
            gap: 0.3rem;
            margin-top: 0.5rem;
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
        }
        
        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
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
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        
        .modal-close:hover {
            background: #f8f9fa;
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
                    <li><a href="manage_services.php" style="color: #3498db;">Manage Services</a></li>
                    <li><a href="manage_bills.php">Manage Bills</a></li>
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
            <i data-lucide="settings" class="section-icon"></i>
            <h1>Manage Services</h1>
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
            <a href="manage_bills.php" class="btn" style="display: flex; align-items: center; gap: 0.5rem;">
                <i data-lucide="file-text" class="btn-icon"></i>
                Create New Bill
            </a>
            <a href="manage_users.php" class="btn" style="background: #2ecc71; display: flex; align-items: center; gap: 0.5rem;">
                <i data-lucide="users" class="btn-icon"></i>
                Manage Customers
            </a>
        </div>

        <!-- Add Service Form -->
        <div class="card">
            <div class="section-header">
                <i data-lucide="plus-circle" style="width: 1.5rem; height: 1.5rem;"></i>
                <h2>Add New Service</h2>
            </div>
            <form method="POST" action="" class="service-form">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
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
                           step="0.01" min="0" placeholder="0.00">
                    <small style="color: #666;">This will be used as default price when creating bills</small>
                </div>
                
                <button type="submit" name="add_service" class="btn" style="display: flex; align-items: center; gap: 0.5rem;">
                    <i data-lucide="plus" class="btn-icon"></i>
                    Add Service
                </button>
            </form>
        </div>

        <!-- Services List -->
        <div class="card">
            <div class="section-header">
                <i data-lucide="list" style="width: 1.5rem; height: 1.5rem;"></i>
                <h2>Available Services (<?php echo count($services); ?>)</h2>
            </div>
            <?php if (count($services) > 0): ?>
                <div class="service-grid">
                    <?php foreach ($services as $service): ?>
                        <div class="service-card <?php echo !$service['is_active'] ? 'inactive' : ''; ?>">
                            <span class="service-type"><?php echo htmlspecialchars($service['service_type']); ?></span>
                            <h3><?php echo htmlspecialchars($service['service_name']); ?></h3>
                            
                            <div class="service-price">
                                ₹<?php echo number_format($service['base_amount'], 2); ?>
                            </div>
                            
                            <?php if ($service['description']): ?>
                                <p style="margin: 0.5rem 0; color: #666;"><?php echo htmlspecialchars($service['description']); ?></p>
                            <?php endif; ?>
                            
                            <p class="service-status">
                                <strong>Status:</strong> 
                                <?php if ($service['is_active']): ?>
                                    <i data-lucide="check-circle" style="width: 1rem; height: 1rem; color: #27ae60; margin-left: 0.3rem;"></i>
                                    <span style="color: #27ae60;">Active</span>
                                <?php else: ?>
                                    <i data-lucide="x-circle" style="width: 1rem; height: 1rem; color: #e74c3c; margin-left: 0.3rem;"></i>
                                    <span style="color: #e74c3c;">Inactive</span>
                                <?php endif; ?>
                            </p>
                            
                            <div class="service-actions">
                                <button type="button" class="btn btn-small" onclick="editService(<?php echo $service['id']; ?>)" style="display: flex; align-items: center;">
                                    <i data-lucide="edit" style="width: 1rem; height: 1rem;"></i>
                                    Edit
                                </button>
                                <form method="POST" action="" style="display: inline;">
                                    <input type="hidden" name="service_id" value="<?php echo $service['id']; ?>">
                                    <button type="submit" name="delete_service" class="btn btn-small" style="background: #e74c3c; display: flex; align-items: center;" 
                                            onclick="return confirm('Are you sure you want to delete this service?')">
                                        <i data-lucide="trash-2" style="width: 1rem; height: 1rem;"></i>
                                        Delete
                                    </button>
                                </form>
                                <a href="manage_bills.php?service_id=<?php echo $service['id']; ?>" class="btn btn-small" style="background: #2ecc71; display: flex; align-items: center; text-decoration: none;">
                                    <i data-lucide="file-text" style="width: 1rem; height: 1rem;"></i>
                                    Create Bill
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i data-lucide="settings" class="empty-state-icon"></i>
                    <h3>No Services Found</h3>
                    <p>Add your first service using the form above.</p>
                    <p>Services you add will appear here and can be used when creating bills.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit Service Modal -->
    <div id="editModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <div class="section-header">
                    <i data-lucide="edit" style="width: 1.5rem; height: 1.5rem;"></i>
                    <h2 style="margin: 0;">Edit Service</h2>
                </div>
                <button type="button" class="modal-close" onclick="closeEditModal()">
                    <i data-lucide="x" style="width: 1.2rem; height: 1.2rem;"></i>
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
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="checkbox" id="edit_is_active" name="is_active" value="1">
                        <i data-lucide="power" style="width: 1rem; height: 1rem;"></i>
                        Active Service
                    </label>
                </div>
                
                <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                    <button type="submit" name="update_service" class="btn" style="display: flex; align-items: center; gap: 0.5rem; flex: 1;">
                        <i data-lucide="save" style="width: 1.2rem; height: 1.2rem;"></i>
                        Update Service
                    </button>
                    <button type="button" class="btn" style="background: #95a5a6; display: flex; align-items: center; gap: 0.5rem;" onclick="closeEditModal()">
                        <i data-lucide="x" style="width: 1.2rem; height: 1.2rem;"></i>
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Online Billing System - BCA Project | Tribhuvan University</p>
        </div>
    </footer>

    <script>
        // Service data from PHP
        const servicesData = {
            <?php foreach ($services as $service): ?>
                '<?php echo $service['id']; ?>': {
                    id: <?php echo $service['id']; ?>,
                    service_name: '<?php echo addslashes($service['service_name']); ?>',
                    service_type: '<?php echo addslashes($service['service_type']); ?>',
                    description: `<?php echo addslashes($service['description'] ?? ''); ?>`,
                    base_amount: <?php echo $service['base_amount']; ?>,
                    is_active: <?php echo $service['is_active']; ?>
                },
            <?php endforeach; ?>
        };

        function editService(serviceId) {
            console.log('Editing service ID:', serviceId);
            
            const service = servicesData[serviceId];
            
            if (!service) {
                alert('Service data not found');
                return;
            }
            
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