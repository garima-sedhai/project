<?php
// install.php - Database Installation Script
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install Database - Online Billing System</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: white;
        }
        .container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        h1 {
            text-align: center;
            margin-bottom: 30px;
            color: white;
            font-size: 2.5em;
        }
        .btn {
            display: block;
            width: 100%;
            padding: 15px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 18px;
            cursor: pointer;
            margin: 20px 0;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #45a049;
        }
        .btn-danger {
            background: #f44336;
        }
        .btn-danger:hover {
            background: #d32f2f;
        }
        .status {
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            background: rgba(255, 255, 255, 0.2);
        }
        .success {
            color: #4CAF50;
            background: rgba(76, 175, 80, 0.1);
            border-left: 4px solid #4CAF50;
        }
        .error {
            color: #f44336;
            background: rgba(244, 67, 54, 0.1);
            border-left: 4px solid #f44336;
        }
        .warning {
            color: #ff9800;
            background: rgba(255, 152, 0, 0.1);
            border-left: 4px solid #ff9800;
        }
        .info {
            background: rgba(33, 150, 243, 0.1);
            border-left: 4px solid #2196F3;
        }
        .credentials {
            background: rgba(255, 255, 255, 0.15);
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📊 Database Installation</h1>
        
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';
            
            try {
                // Connect to MySQL
                $pdo = new PDO("mysql:host=localhost", "root", "");
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                if ($action === 'install') {
                    echo '<div class="status info">Starting installation...</div>';
                    
                    // Read SQL file
                    $sql = file_get_contents('database.sql');
                    
                    // Split and execute SQL statements
                    $statements = explode(';', $sql);
                    
                    foreach ($statements as $statement) {
                        $statement = trim($statement);
                        if (!empty($statement)) {
                            $pdo->exec($statement);
                            echo '<div class="status success">✓ Executed SQL statement</div>';
                        }
                    }
                    
                    echo '<div class="credentials">';
                    echo '<h3>✅ Installation Complete!</h3>';
                    echo '<p><strong>Admin Login:</strong></p>';
                    echo '<ul>';
                    echo '<li>Email: <code>admin@billpay.com</code></li>';
                    echo '<li>Password: <code>password</code></li>';
                    echo '</ul>';
                    echo '<p><strong>Customer Registration:</strong></p>';
                    echo '<ul>';
                    echo '<li>Register with email OTP verification</li>';
                    echo '<li>First login requires admin approval</li>';
                    echo '<li>Subsequent logins work automatically</li>';
                    echo '</ul>';
                    echo '</div>';
                    
                    echo '<div class="status info">';
                    echo '<p><a href="index.php" style="color: white; text-decoration: underline;">Go to Website →</a></p>';
                    echo '</div>';
                    
                } elseif ($action === 'delete') {
                    $pdo->exec("DROP DATABASE IF EXISTS online_billing_system");
                    echo '<div class="status success">✅ Old database deleted successfully</div>';
                    echo '<p>You can now install a fresh database.</p>';
                }
                
            } catch (PDOException $e) {
                echo '<div class="status error">❌ Error: ' . $e->getMessage() . '</div>';
                echo '<p>Make sure MySQL is running in XAMPP.</p>';
            }
        } else {
        ?>
        
        <div class="status warning">
            <h3>⚠️ Important Instructions</h3>
            <p>This will:</p>
            <ol>
                <li>Create a fresh database with all required tables</li>
                <li>Insert default admin user (admin@billpay.com / password)</li>
                <li>Add sample categories and products</li>
                <li>Set up OTP verification system</li>
                <li>Configure admin approval workflow</li>
            </ol>
        </div>
        
        <form method="POST">
            <button type="submit" name="action" value="install" class="btn" 
                    onclick="return confirm('This will create a fresh database. Continue?')">
                🚀 Install Fresh Database
            </button>
        </form>
        
        <form method="POST">
            <button type="submit" name="action" value="delete" class="btn btn-danger"
                    onclick="return confirm('WARNING: This will delete ALL data. Are you sure?')">
                🗑️ Delete Existing Database
            </button>
        </form>
        
        <div class="status info">
            <h4>Before Installing:</h4>
            <ol>
                <li>Make sure XAMPP Apache and MySQL are running (green in XAMPP Control Panel)</li>
                <li>If you have old data, export it from phpMyAdmin first</li>
                <li>Your existing config.php should work with this database</li>
            </ol>
        </div>
        
        <?php } ?>
    </div>
</body>
</html>