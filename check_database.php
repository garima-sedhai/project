<?php
include 'includes/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Structure Verification</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        .status-success { color: #27ae60; }
        .status-error { color: #e74c3c; }
        .status-warning { color: #f39c12; }
        details { margin: 10px 0; }
        summary { cursor: pointer; padding: 5px; background: #f8f9fa; }
        .icon { width: 16px; height: 16px; margin-right: 8px; vertical-align: middle; }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <div class="logo">BillPay Pro</div>
                <ul class="nav-links">
                    <li><a href="index.php">Home</a></li>
                    <li><a href="admin/login.php">Admin</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="container">
        <div class="card">
            <h1>
                <i data-lucide="database" class="icon" style="width: 24px; height: 24px;"></i>
                Database Structure Verification
            </h1>

            <?php
            $tables = ['users', 'services', 'bills', 'payments', 'notifications'];

            foreach ($tables as $table) {
                try {
                    $result = $pdo->query("SELECT COUNT(*) as count FROM $table");
                    $count = $result->fetch()['count'];
                    echo "<p class='status-success'>";
                    echo "<i data-lucide='check-circle' class='icon'></i>";
                    echo "Table '$table': EXISTS ($count records)";
                    echo "</p>";
                    
                    // Show table structure
                    $structure = $pdo->query("DESCRIBE $table");
                    $columns = $structure->fetchAll();
                    echo "<details>";
                    echo "<summary><i data-lucide='list' class='icon'></i>Columns in $table</summary>";
                    echo "<ul style='margin-left: 20px;'>";
                    foreach ($columns as $col) {
                        echo "<li><code>{$col['Field']}</code> ({$col['Type']})";
                        if ($col['Key'] == 'PRI') echo " <strong>PRIMARY KEY</strong>";
                        if ($col['Key'] == 'UNI') echo " <strong>UNIQUE</strong>";
                        echo "</li>";
                    }
                    echo "</ul></details>";
                    
                } catch (Exception $e) {
                    echo "<p class='status-error'>";
                    echo "<i data-lucide='x-circle' class='icon'></i>";
                    echo "Table '$table': MISSING - " . $e->getMessage();
                    echo "</p>";
                }
            }

            echo "<hr><h3><i data-lucide='settings' class='icon'></i>Critical Column Verification</h3>";

            // Check for specific columns that might be missing
            $critical_columns = [
                'notifications' => ['title', 'message', 'type', 'is_read'],
                'payments' => ['transaction_id', 'payment_method', 'status'],
                'bills' => ['bill_number', 'total_amount', 'tax_amount', 'late_fee']
            ];

            $missing_elements = [];

            foreach ($critical_columns as $table => $columns) {
                foreach ($columns as $column) {
                    try {
                        $pdo->query("SELECT $column FROM $table LIMIT 1");
                        echo "<p class='status-success'>";
                        echo "<i data-lucide='check-circle' class='icon'></i>";
                        echo "Column '$table.$column': EXISTS";
                        echo "</p>";
                    } catch (Exception $e) {
                        echo "<p class='status-error'>";
                        echo "<i data-lucide='x-circle' class='icon'></i>";
                        echo "Column '$table.$column': MISSING";
                        echo "</p>";
                        $missing_elements[] = "Column '$table.$column'";
                    }
                }
            }

            // Summary
            echo "<hr><h3><i data-lucide='clipboard-list' class='icon'></i>Verification Summary</h3>";
            
            if (empty($missing_elements)) {
                echo "<div class='alert alert-success'>";
                echo "<i data-lucide='check-circle' class='icon'></i>";
                echo "<strong>All database elements are properly configured.</strong>";
                echo "</div>";
            } else {
                echo "<div class='alert alert-danger'>";
                echo "<i data-lucide='alert-triangle' class='icon'></i>";
                echo "<strong>Missing elements found:</strong>";
                echo "<ul>";
                foreach ($missing_elements as $element) {
                    echo "<li>$element</li>";
                }
                echo "</ul>";
                echo "</div>";
                
                echo "<div class='alert alert-success'>";
                echo "<i data-lucide='wrench' class='icon'></i>";
                echo "<strong>Next Steps:</strong> Run the specific SQL commands to add the missing elements.";
                echo "</div>";
            }
            ?>

            <div style="margin-top: 2rem; text-align: center;">
                <a href="index.php" class="btn">
                    <i data-lucide="home" class="icon"></i>
                    Back to Home
                </a>
                <a href="admin/login.php" class="btn" style="background: #3498db;">
                    <i data-lucide="shield" class="icon"></i>
                    Admin Login
                </a>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Online Billing System - BCA Project | Tribhuvan University</p>
        </div>
    </footer>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();
    </script>
</body>
</html>