<?php
// Only start session if we need it
if (isset($_GET['session_needed'])) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Service Billing & Payment System</title>
    <link rel="stylesheet" href="css/style.css">
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        .portal-container {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin: 3rem 0;
        }
        
        .portal-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            width: 300px;
        }
        
        .portal-card.admin {
            border-top: 5px solid #e74c3c;
        }
        
        .portal-card.customer {
            border-top: 5px solid #3498db;
        }
        
        .portal-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #666;
        }
        
        .features-list {
            text-align: left;
            margin: 1rem 0;
        }
        
        .features-list li {
            margin: 0.5rem 0;
            color: #666;
        }
        
        .portal-actions {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .feature-icon {
            width: 48px;
            height: 48px;
            margin: 0 auto 1rem;
            color: #3498db;
        }

        .icon-lg {
            width: 64px;
            height: 64px;
        }

        /* Button transitions only */
        .portal-actions .btn {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .portal-actions .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <div class="logo">BillPay Pro</div>
                <ul class="nav-links">
                    <li><a href="#features">Features</a></li>
                    <li><a href="#about">About</a></li>
                    <li><a href="#contact">Contact</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="hero">
        <div class="container">
            <h1 align="center">Welcome to Online Service Billing & Payment System</h1>
            <p align="center">Streamline your billing process with our secure and efficient payment solutions</p>
        </div>
    </div>

    <div class="container">
        <div style="text-align: center; margin: 3rem 0;">
            <h2>Select Your Portal</h2>
            <p>Choose your role to continue</p>
        </div>
        
        <div class="portal-container">
            <!-- Admin Portal -->
            <div class="portal-card admin">
                <div class="portal-icon">
                    <i data-lucide="shield" class="icon-lg"></i>
                </div>
                <h3>Admin Portal</h3>
                <p>Manage services, bills, and view reports</p>
                
                <div class="features-list">
                    <strong>Admin Features:</strong>
                    <ul>
                        <li>Manage Services & Bills</li>
                        <li>View Payment Reports</li>
                        <li>Customer Management</li>
                        <li>Download Transaction History</li>
                        <li>Real-time Analytics</li>
                    </ul>
                </div>
                
                <div class="portal-actions">
                    <a href="admin/login.php" class="btn" style="background: #e74c3c;">Admin Login</a>
                </div>
            </div>
            
            <!-- Customer Portal -->
            <div class="portal-card customer">
                <div class="portal-icon">
                    <i data-lucide="users" class="icon-lg"></i>
                </div>
                <h3>Customer Portal</h3>
                <p>View bills, make payments, and track history</p>
                
                <div class="features-list">
                    <strong>Customer Features:</strong>
                    <ul>
                        <li>View & Pay Bills Online</li>
                        <li>Multiple Payment Options</li>
                        <li>Payment History</li>
                        <li>QR Code Payments</li>
                        <li>Bill Download</li>
                    </ul>
                </div>
                
                <div class="portal-actions">
                    <a href="customer/register.php" class="btn">Register New Account</a>
                    <a href="customer/login.php" class="btn" style="background: #2ecc71;">Customer Login</a>
                </div>
            </div>
        </div>
        
        <div id="features" class="card" style="margin: 4rem 0;">
            <h2 style="text-align: center;">Why Choose Our System?</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem; margin-top: 2rem;">
                <div style="text-align: center;">
                    <div class="feature-icon">
                        <i data-lucide="shield-check" class="icon-lg"></i>
                    </div>
                    <h3>Secure Payments</h3>
                    <p>Bank-level security with eSewa and Khalti integration</p>
                </div>
                <div style="text-align: center;">
                    <div class="feature-icon">
                        <i data-lucide="zap" class="icon-lg"></i>
                    </div>
                    <h3>Instant Processing</h3>
                    <p>Real-time payment processing and instant confirmation</p>
                </div>
                <div style="text-align: center;">
                    <div class="feature-icon">
                        <i data-lucide="smartphone" class="icon-lg"></i>
                    </div>
                    <h3>Mobile Friendly</h3>
                    <p>QR code payments and mobile-optimized interface</p>
                </div>
                <div style="text-align: center;">
                    <div class="feature-icon">
                        <i data-lucide="bar-chart-3" class="icon-lg"></i>
                    </div>
                    <h3>Detailed Reports</h3>
                    <p>Comprehensive reporting and analytics for businesses</p>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Online Billing System</p>
        </div>
    </footer>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();
    </script>
</body>
</html>