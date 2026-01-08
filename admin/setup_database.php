<?php
session_start();
include '../includes/config.php';

// Only allow admin access
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: login.php");
    exit();
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['setup_database'])) {
    try {
        // Run setup queries
        $queries = [
            // Create bill_items table
            "CREATE TABLE IF NOT EXISTS bill_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                bill_id INT NOT NULL,
                service_id INT NOT NULL,
                price DECIMAL(10,2) NOT NULL,
                quantity INT DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (bill_id) REFERENCES bills(id) ON DELETE CASCADE,
                FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
                INDEX idx_bill_id (bill_id),
                INDEX idx_service_id (service_id)
            )",
            
            // Update services table
            "ALTER TABLE services 
             ADD COLUMN IF NOT EXISTS service_name VARCHAR(255) AFTER id",
            
            // Generate customer codes
            "ALTER TABLE users 
             ADD COLUMN IF NOT EXISTS customer_code VARCHAR(50) UNIQUE",
             
            // Update bills table
            "ALTER TABLE bills
             ADD COLUMN IF NOT EXISTS bill_number VARCHAR(50) UNIQUE,
             ADD COLUMN IF NOT EXISTS tax_rate DECIMAL(5,2) DEFAULT 0,
             ADD COLUMN IF NOT EXISTS tax_amount DECIMAL(10,2) DEFAULT 0,
             ADD COLUMN IF NOT EXISTS late_fee DECIMAL(10,2) DEFAULT 0,
             ADD COLUMN IF NOT EXISTS total_amount DECIMAL(10,2) DEFAULT 0,
             ADD COLUMN IF NOT EXISTS service_details TEXT,
             ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
             ADD COLUMN IF NOT EXISTS status ENUM('pending', 'paid', 'cancelled') DEFAULT 'pending'"
        ];
        
        foreach ($queries as $query) {
            $pdo->exec($query);
        }
        
        $message = "Database setup completed successfully!";
        $message_type = "success";
        
    } catch (Exception $e) {
        $message = "Database setup error: " . $e->getMessage();
        $message_type = "error";
        error_log("Database setup error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - BillPay Pro</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="container">
        <h1>Database Setup</h1>
        
        <?php if ($message): ?>
            <div class="<?php echo $message_type == 'success' ? 'alert alert-success' : 'alert alert-danger'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <h2>Setup Instructions</h2>
            <p>This will create missing database tables and fix column names.</p>
            <p>Make sure you have backed up your database before proceeding.</p>
            
            <form method="POST" action="">
                <button type="submit" name="setup_database" class="btn btn-primary">
                    Run Database Setup
                </button>
                <a href="manage_bills.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
</body>
</html>