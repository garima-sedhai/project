<?php
// project/index.php - Updated Version
session_start();

// Define base URL
$base_url = 'http://' . $_SERVER['HTTP_HOST'] . '/project/';

// If user is logged in, redirect to appropriate dashboard
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1) {
        header('Location: ' . $base_url . 'admin/dashboard.php');
    } else {
        header('Location: ' . $base_url . 'customer/dashboard.php');
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Service Billing & Payment System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        .portal-container {
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            margin: 2rem 0;
            flex-wrap: wrap;
        }
        
        .portal-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
            width: 300px;
        }
        
        .portal-card.admin {
            border-top: 4px solid #075B5E;
        }
        
        .portal-card.customer {
            border-top: 4px solid #075B5E;
        }
        
        .portal-icon {
            font-size: 2.5rem;
            margin-bottom: 0.8rem;
        }
        
        .features-list {
            text-align: left;
            margin: 0.8rem 0;
        }
        
        .features-list ul {
            padding-left: 1rem;
            margin: 0.4rem 0;
        }
        
        .features-list li {
            margin: 0.4rem 0;
        }
        
        .portal-actions {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .feature-icon {
            width: 40px;
            height: 40px;
            margin: 0 auto 0.8rem;
        }

        .icon-lg {
            width: 56px;
            height: 56px;
        }

        .user-status {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255,255,255,0.1);
            padding: 8px 12px;
            border-radius: 4px;
        }
        
        .user-status a {
            color: white;
            text-decoration: none;
            margin-left: 8px;
            padding: 4px 8px;
            background: rgba(255,255,255,0.2);
            border-radius: 3px;
        }
        
        .user-status a:hover {
            background: rgba(255,255,255,0.3);
        }

        .hero {
            background: linear-gradient(135deg, #075B5E 0%, #0a7c80 100%);
            color: white;
            padding: 3rem 0;
            text-align: center;
            margin-bottom: 2rem;
            position: relative;
        }
        
        .hero h1 {
            font-size: 2.2rem;
            margin-bottom: 0.8rem;
        }
        
        .hero p {
            font-size: 1.1rem;
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
                font-size: 1.8rem;
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
<body style="background: #ffffff; min-height: 100vh; margin: 0;">
    <header class="header" style="background: #075B5E; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1); position: sticky; top: 0; z-index: 1000;">
        <div class="container">
            <nav class="navbar" style="display: flex; justify-content: space-between; align-items: center; padding: 0.8rem 0;">
                <div class="logo" style="font-size: 1.6rem; font-weight: bold; color: white;">BillPay Pro</div>
                <ul class="nav-links" style="display: flex; list-style: none; gap: 1.5rem; margin: 0; padding: 0;">
                    <li><a href="#features" style="text-decoration: none; color: white; font-weight: 500;">Features</a></li>
                    <li><a href="#about" style="text-decoration: none; color: white; font-weight: 500;">About</a></li>
                    <li><a href="#contact" style="text-decoration: none; color: white; font-weight: 500;">Contact</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main>
        <section class="hero">
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
        </section>

        <div class="container" style="max-width: 1200px; margin: 0 auto; padding: 0 20px;">
            <section class="portal-selection">
                <h2 style="text-align: center; margin-bottom: 0.5rem; color: #075B5E; font-size: 1.8rem;">Select Your Portal</h2>
                <p style="text-align: center; margin-bottom: 1.5rem; font-size: 1rem;">Choose your role to continue</p>
                
                <div class="portal-container">
                    <!-- Admin Portal -->
                    <article class="portal-card admin">
                        <div class="portal-icon" style="color: #075B5E;">
                            <i data-lucide="shield" class="icon-lg"></i>
                        </div>
                        <h3 style="color: #075B5E; margin-bottom: 0.5rem; font-size: 1.3rem;">Admin Portal</h3>
                        <p style="margin-bottom: 0.8rem; font-size: 0.95rem;">Manage services, bills, and view reports</p>
                        
                        <div class="features-list">
                            <h4 style="color: #075B5E; margin-bottom: 0.5rem; font-size: 1.1rem;">Admin Features:</h4>
                            <ul style="font-size: 0.9rem;">
                                <li>Manage Services & Bills</li>
                                <li>View Payment Reports</li>
                                <li>Customer Management</li>
                                <li>Download Transaction History</li>
                                <li>Real-time Analytics</li>
                            </ul>
                        </div>
                        
                        <div class="portal-actions">
                            <a href="admin/login.php" class="btn" style="background: #075B5E; display: inline-block; padding: 0.6rem 1.2rem; color: white; text-decoration: none; border-radius: 4px; font-weight: 500; border: none; cursor: pointer; text-align: center; transition: background-color 0.3s ease; font-size: 0.9rem;">Admin Login</a>
                        </div>
                    </article>
                    
                    <!-- Customer Portal -->
                    <article class="portal-card customer">
                        <div class="portal-icon" style="color: #075B5E;">
                            <i data-lucide="users" class="icon-lg"></i>
                        </div>
                        <h3 style="color: #075B5E; margin-bottom: 0.5rem; font-size: 1.3rem;">Customer Portal</h3>
                        <p style="margin-bottom: 0.8rem; font-size: 0.95rem;">View bills, make payments, and track history</p>
                        
                        <div class="features-list">
                            <h4 style="color: #075B5E; margin-bottom: 0.5rem; font-size: 1.1rem;">Customer Features:</h4>
                            <ul style="font-size: 0.9rem;">
                                <li>View & Pay Bills Online</li>
                                <li>Multiple Payment Options</li>
                                <li>Payment History</li>
                                <li>QR Code Payments</li>
                                <li>Bill Download</li>
                            </ul>
                        </div>
                        
                        <div class="portal-actions">
                            <a href="customer/register.php" class="btn" style="background: white; display: inline-block; padding: 0.6rem 1.2rem; color: #075B5E; text-decoration: none; border-radius: 4px; font-weight: 500; border: 1px solid #075B5E; cursor: pointer; text-align: center; transition: background-color 0.3s ease, color 0.3s ease; font-size: 0.9rem;">Register New Account</a>
                            <a href="customer/login.php" class="btn" style="background: #075B5E; display: inline-block; padding: 0.6rem 1.2rem; color: white; text-decoration: none; border-radius: 4px; font-weight: 500; border: none; cursor: pointer; text-align: center; transition: background-color 0.3s ease; font-size: 0.9rem;">Customer Login</a>
                        </div>
                    </article>
                </div>
            </section>

            <section id="features" class="features" style="padding: 2rem 0; margin-top: 1rem;">
                <h2 style="text-align: center; margin-bottom: 1rem; color: #075B5E; font-size: 1.8rem;">Why Choose Our System?</h2>
                <div class="features-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-top: 1.5rem;">
                    <div class="feature-item" style="background: white; padding: 1.5rem; border-radius: 8px; text-align: center; box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);">
                        <div class="feature-icon" style="color: #075B5E;">
                            <i data-lucide="shield-check" class="icon-lg"></i>
                        </div>
                        <h3 style="margin-bottom: 0.5rem; color: #075B5E; font-size: 1.2rem;">Secure Payments</h3>
                        <p style="line-height: 1.5; font-size: 0.9rem;">Bank-level security with multiple payment gateway integration</p>
                    </div>
                    <div class="feature-item" style="background: white; padding: 1.5rem; border-radius: 8px; text-align: center; box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);">
                        <div class="feature-icon" style="color: #075B5E;">
                            <i data-lucide="zap" class="icon-lg"></i>
                        </div>
                        <h3 style="margin-bottom: 0.5rem; color: #075B5E; font-size: 1.2rem;">Instant Processing</h3>
                        <p style="line-height: 1.5; font-size: 0.9rem;">Real-time payment processing and instant confirmation</p>
                    </div>
                    <div class="feature-item" style="background: white; padding: 1.5rem; border-radius: 8px; text-align: center; box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);">
                        <div class="feature-icon" style="color: #075B5E;">
                            <i data-lucide="smartphone" class="icon-lg"></i>
                        </div>
                        <h3 style="margin-bottom: 0.5rem; color: #075B5E; font-size: 1.2rem;">Mobile Friendly</h3>
                        <p style="line-height: 1.5; font-size: 0.9rem;">QR code payments and mobile-optimized interface</p>
                    </div>
                    <div class="feature-item" style="background: white; padding: 1.5rem; border-radius: 8px; text-align: center; box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);">
                        <div class="feature-icon" style="color: #075B5E;">
                            <i data-lucide="bar-chart-3" class="icon-lg"></i>
                        </div>
                        <h3 style="margin-bottom: 0.5rem; color: #075B5E; font-size: 1.2rem;">Detailed Reports</h3>
                        <p style="line-height: 1.5; font-size: 0.9rem;">Comprehensive reporting and analytics for businesses</p>
                    </div>
                </div>
            </section>

            <section id="about" class="about" style="background: white; border-radius: 8px; padding: 1.5rem; margin: 1.5rem 0; box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);">
                <h2 style="text-align: center; margin-bottom: 1rem; color: #075B5E; font-size: 1.8rem;">About BillPay Pro</h2>
                <p style="line-height: 1.6; font-size: 0.95rem;">
                    BillPay Pro is a comprehensive online billing and payment system designed to simplify 
                    the billing process for businesses and provide convenient payment options for customers. 
                    Our platform offers secure transactions, real-time tracking, and detailed reporting 
                    to help businesses manage their finances efficiently.
                </p>
            </section>

            <section id="contact" class="contact" style="background: white; border-radius: 8px; padding: 1.5rem; margin: 1.5rem 0 2.5rem 0; box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);">
                <h2 style="text-align: center; margin-bottom: 1rem; color: #075B5E; font-size: 1.8rem;">Contact Us</h2>
                <p style="line-height: 1.6; font-size: 0.95rem;">
                    Email: support@billpaypro.com<br>
                    Phone: +977-1-4000000<br>
                    Address: Kathmandu, Nepal
                </p>
            </section>
        </div>
    </main>

    <footer class="footer" style="background: #075B5E; color: white; padding: 1.5rem 0; margin-top: 2rem;">
        <div class="container" style="max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center;">
            <p style="margin: 0; font-size: 0.9rem;">&copy; <?php echo date('Y'); ?> BillPay Pro - Online Billing System</p>
            <nav class="footer-links" style="display: flex; gap: 1.5rem;">
                <a href="privacy.php" style="color: white; text-decoration: none; font-size: 0.9rem;">Privacy Policy</a>
                <a href="terms.php" style="color: white; text-decoration: none; font-size: 0.9rem;">Terms of Service</a>
            </nav>
        </div>
    </footer>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();
        
        // Add button hover effects
        document.querySelectorAll('.btn').forEach(button => {
            button.addEventListener('mouseenter', function() {
                if (this.style.backgroundColor === 'rgb(7, 91, 94)') {
                    this.style.backgroundColor = '#0a7c80';
                } else if (this.style.backgroundColor === 'white') {
                    this.style.backgroundColor = '#075B5E';
                    this.style.color = 'white';
                }
            });
            
            button.addEventListener('mouseleave', function() {
                if (this.style.backgroundColor === 'rgb(10, 124, 128)' || this.getAttribute('href') === 'customer/login.php' || this.getAttribute('href') === 'admin/login.php') {
                    this.style.backgroundColor = '#075B5E';
                } else if (this.getAttribute('href') === 'customer/register.php') {
                    this.style.backgroundColor = 'white';
                    this.style.color = '#075B5E';
                }
            });
        });
        
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