<?php
// Main index.php - Redirect to appropriate page based on user status
session_start();

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']) {
        // User is admin - redirect to admin dashboard
        header("Location: admin/index.php");
        exit();
    } else {
        // User is customer - redirect to customer dashboard
        header("Location: customer/dashboard.php");
        exit();
    }
}
// If not logged in, continue to show the landing page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Service Billing & Payment System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        .portal-container {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin: 3rem 0;
            flex-wrap: wrap;
        }
        
        .portal-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            width: 300px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .portal-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
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
        
        .features-list ul {
            padding-left: 1.2rem;
            margin: 0.5rem 0;
        }
        
        .features-list li {
            margin: 0.5rem 0;
            color: #666;
        }
        
        .portal-actions {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-top: 1.5rem;
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

        /* User status display */
        .user-status {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255,255,255,0.1);
            padding: 10px 15px;
            border-radius: 5px;
            color: white;
        }
        
        .user-status a {
            color: white;
            text-decoration: none;
            margin-left: 10px;
            padding: 5px 10px;
            background: rgba(255,255,255,0.2);
            border-radius: 3px;
        }
        
        .user-status a:hover {
            background: rgba(255,255,255,0.3);
        }

        .hero {
            background: linear-gradient(135deg, #075B5E 0%, #0a7c80 100%);
            color: white;
            padding: 4rem 0;
            text-align: center;
            margin-bottom: 3rem;
            position: relative;
        }
        
        .hero h1 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .hero p {
            font-size: 1.2rem;
            opacity: 0.9;
            max-width: 700px;
            margin: 0 auto;
        }
        
        @media (max-width: 768px) {
            .portal-container {
                flex-direction: column;
                align-items: center;
            }
            
            .portal-card {
                width: 90%;
                max-width: 350px;
            }
            
            .hero h1 {
                font-size: 2rem;
            }
            
            .hero p {
                font-size: 1rem;
                padding: 0 1rem;
            }
            
            .user-status {
                position: static;
                margin-top: 1rem;
                display: inline-block;
            }
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
            <h1>Welcome to BillPay Pro</h1>
            <p>Streamline your billing process with our secure and efficient payment solutions</p>
            
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="user-status">
                    Welcome, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?>!
                    <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                        <a href="admin/index.php">Go to Admin Panel</a>
                    <?php else: ?>
                        <a href="customer/dashboard.php">Go to Dashboard</a>
                    <?php endif; ?>
                    <a href="logout_all.php">Logout</a>
                </div>
            <?php endif; ?>
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
                    <p>Bank-level security with multiple payment gateway integration</p>
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
        
        <div id="about" class="card" style="margin: 4rem 0; background: #f8f9fa;">
            <h2 style="text-align: center;">About BillPay Pro</h2>
            <p style="text-align: center; max-width: 800px; margin: 1rem auto; line-height: 1.6;">
                BillPay Pro is a comprehensive online billing and payment system designed to simplify 
                the billing process for businesses and provide convenient payment options for customers. 
                Our platform offers secure transactions, real-time tracking, and detailed reporting 
                to help businesses manage their finances efficiently.
            </p>
        </div>
        
        <div id="contact" class="card" style="margin: 4rem 0;">
            <h2 style="text-align: center;">Contact Us</h2>
            <p style="text-align: center;">
                Email: support@billpaypro.com<br>
                Phone: +977-1-4000000<br>
                Address: Kathmandu, Nepal
            </p>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> BillPay Pro - Online Billing System</p>
            <p style="font-size: 0.9rem; margin-top: 0.5rem; opacity: 0.8;">
                <a href="privacy.php" style="color: white; margin: 0 10px;">Privacy Policy</a> | 
                <a href="terms.php" style="color: white; margin: 0 10px;">Terms of Service</a>
            </p>
        </div>
    </footer>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();
        
        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const targetId = this.getAttribute('href');
                if (targetId === '#') return;
                
                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    targetElement.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>