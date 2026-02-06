<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/payment_config.php';

// Check login
if (!isset($_SESSION['user_id']) || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'])) {
    header("Location: login.php");
    exit();
}

// Check if payment session exists
if (!isset($_SESSION['payment_session'])) {
    // Try to get from redirect data
    if (isset($_SESSION['payment_redirect_data'])) {
        $_SESSION['payment_session'] = [
            'bill_id' => $_SESSION['payment_redirect_data']['bill_id'],
            'transaction_id' => $_SESSION['payment_redirect_data']['transaction_id'],
            'payment_method' => 'esewa_qr',
            'created_at' => time()
        ];
        unset($_SESSION['payment_redirect_data']);
    } else {
        header("Location: payment.php?error=no_payment_session");
        exit();
    }
}

$payment_session = $_SESSION['payment_session'];
$user_id = $_SESSION['user_id'];

// Get bill details
$stmt = $pdo->prepare("SELECT 
    b.*, 
    u.full_name, 
    u.phone, 
    u.email,
    u.customer_code,
    COALESCE(b.amount, b.final_amount, b.total_amount, 0) as amount,
    u.id as user_id
    FROM bills b 
    JOIN users u ON b.user_id = u.id 
    WHERE b.id = ? AND b.user_id = ? AND b.payment_status = 'pending'");
    
$stmt->execute([$payment_session['bill_id'], $user_id]);
$bill = $stmt->fetch();

if (!$bill) {
    unset($_SESSION['payment_session']);
    header("Location: payment.php?error=invalid_bill");
    exit();
}

// Generate custom QR code with embedded credentials: Number: 9800000001, MPIN: 1234
$qr_data = "Number: 9800000001\nMPIN: 1234\nBill: " . $bill['bill_number'] . 
           "\nAmount: ₹" . number_format($bill['amount'], 2) . 
           "\nTxn: " . $payment_session['transaction_id'];

// Use the custom QR code generator URL
$qr_url = "https://api.qrserver.com/v1/create-qr-code/?color=000000&bgcolor=FFFFFF&data=" . 
          urlencode($qr_data) . "&qzone=1&margin=0&size=400x400&ecc=L";

// Check if scanning is complete
$scan_complete = isset($_GET['scan_complete']) && $_GET['scan_complete'] == '1';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>eSewa QR Payment - BillPay Pro</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        .qr-payment-container {
            max-width: 800px;
            margin: 2rem auto;
        }
        
        .qr-header {
            background: linear-gradient(135deg, #53c41a 0%, #389e0d 100%);
            color: white;
            padding: 2rem;
            border-radius: 10px 10px 0 0;
            text-align: center;
        }
        
        .qr-body {
            padding: 2rem;
            background: white;
            border-radius: 0 0 10px 10px;
            text-align: center;
        }
        
        .qr-section {
            margin: 2rem 0;
            padding: 2rem;
            border: 2px dashed #53c41a;
            border-radius: 10px;
            background: #f8fff9;
            position: relative;
            overflow: hidden;
        }
        
        .qr-code {
            margin: 1rem auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            display: inline-block;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            position: relative;
            z-index: 2;
            border: 2px solid #53c41a;
        }
        
        .qr-code img {
            display: block;
            width: 300px;
            height: 300px;
        }
        
        /* Scanner Animation */
        .scanner-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
            pointer-events: none;
        }
        
        .scanner-line {
            position: absolute;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(to right, transparent, #53c41a, transparent);
            box-shadow: 0 0 10px #53c41a;
            animation: scan 3s linear infinite;
        }
        
        @keyframes scan {
            0% {
                top: 0;
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                top: 100%;
                opacity: 0;
            }
        }
        
        .scanner-grid {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                linear-gradient(90deg, transparent 49%, rgba(83, 196, 26, 0.1) 49%, rgba(83, 196, 26, 0.1) 51%, transparent 51%),
                linear-gradient(0deg, transparent 49%, rgba(83, 196, 26, 0.1) 49%, rgba(83, 196, 26, 0.1) 51%, transparent 51%);
        }
        
        .scan-button {
            background: #53c41a;
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 6px;
            font-size: 1.1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin: 2rem auto;
            transition: all 0.3s;
        }
        
        .scan-button:hover {
            background: #389e0d;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(83, 196, 26, 0.3);
        }
        
        .scan-button.scanning {
            background: #f39c12;
            cursor: not-allowed;
        }
        
        .scan-button.scanning:hover {
            transform: none;
            box-shadow: none;
        }
        
        .scan-progress {
            width: 0;
            height: 3px;
            background: #53c41a;
            border-radius: 2px;
            margin: 1rem auto;
            transition: width 3s linear;
            max-width: 300px;
        }
        
        .scan-progress.active {
            width: 100%;
        }
        
        .instructions {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin: 2rem 0;
            text-align: left;
        }
        
        .payment-details {
            background: #e8f4fd;
            padding: 1.5rem;
            border-radius: 8px;
            margin: 1rem 0;
            text-align: left;
        }
        
        .detail-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.8rem;
            padding-bottom: 0.8rem;
            border-bottom: 1px solid #ddd;
        }
        
        .qr-hidden-info {
            background: #fff3cd;
            padding: 1rem;
            border-radius: 5px;
            margin: 1rem 0;
            border-left: 4px solid #ffc107;
            display: none; /* Hidden but contains data */
        }
        
        .scan-status {
            margin: 1rem 0;
            padding: 1rem;
            border-radius: 8px;
            display: none;
        }
        
        .scan-status.scanning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            display: block;
        }
        
        .scan-status.success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            display: block;
        }
        
        .qr-content-preview {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem auto;
            max-width: 400px;
            text-align: left;
            font-family: monospace;
            font-size: 0.9rem;
            border-left: 4px solid #53c41a;
        }
        
        .qr-content-preview h4 {
            color: #53c41a;
            margin-top: 0;
        }
        
        @media (max-width: 768px) {
            .qr-code {
                max-width: 250px;
            }
            .qr-code img {
                width: 100%;
                height: auto;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="qr-payment-container">
            <div class="qr-header">
                <i data-lucide="qrcode" style="width: 3rem; height: 3rem; margin-bottom: 1rem;"></i>
                <h1>eSewa QR Code Payment</h1>
                <p>Scan the QR code to pay instantly</p>
            </div>
            
            <div class="qr-body">
                <?php if ($scan_complete): ?>
                    <!-- After scanning, show payment page -->
                    <div style="text-align: center; padding: 3rem;">
                        <i data-lucide="check-circle" style="width: 4rem; height: 4rem; color: #53c41a; margin-bottom: 1rem;"></i>
                        <h2>QR Code Scanned Successfully!</h2>
                        <p>Redirecting to payment page...</p>
                        <div style="margin: 2rem 0;">
                            <i data-lucide="loader" style="width: 2rem; height: 2rem; animation: spin 1s linear infinite; color: #3498db;"></i>
                        </div>
                        <p><small>Credentials detected: Mobile: 9800000001, MPIN: 1234</small></p>
                    </div>
                    
                    <script>
                        setTimeout(function() {
                            window.location.href = 'payment_esewa.php?mode=qr&auto_fill=1';
                        }, 2000);
                    </script>
                    
                <?php else: ?>
                    <!-- QR Code Scanner Interface -->
                    <div class="payment-details">
                        <h3><i data-lucide="receipt"></i> Payment Details</h3>
                        <div class="detail-item">
                            <span>Bill Number:</span>
                            <strong><?php echo $bill['bill_number']; ?></strong>
                        </div>
                        <div class="detail-item">
                            <span>Customer:</span>
                            <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong>
                        </div>
                        <div class="detail-item">
                            <span>Amount:</span>
                            <strong style="color: #e74c3c;">₹<?php echo number_format($bill['amount'], 2); ?></strong>
                        </div>
                        <div class="detail-item">
                            <span>Transaction ID:</span>
                            <strong><?php echo $payment_session['transaction_id']; ?></strong>
                        </div>
                    </div>
                    
                    <!-- QR Code Content Preview -->
                    <div class="qr-content-preview">
                        <h4><i data-lucide="eye"></i> QR Code Contains:</h4>
                        <pre style="margin: 0; white-space: pre-wrap;"><?php echo htmlspecialchars($qr_data); ?></pre>
                        <p style="margin-top: 0.5rem; color: #666; font-size: 0.85rem;">
                            <i data-lucide="info"></i> This data is embedded in the QR code below
                        </p>
                    </div>
                    
                    <!-- Hidden QR data (contains 9800000001 / 1234) -->
                    <div class="qr-hidden-info" id="qrHiddenData">
                        <p><strong>QR Code Contains:</strong></p>
                        <p>Mobile: <strong>9800000001</strong></p>
                        <p>MPIN: <strong>1234</strong></p>
                        <p><small>This data is embedded in the QR code</small></p>
                    </div>
                    
                    <div class="qr-section" id="qrSection">
                        <!-- Scanner overlay -->
                        <div class="scanner-overlay">
                            <div class="scanner-grid"></div>
                            <div class="scanner-line" id="scannerLine"></div>
                        </div>
                        
                        <!-- QR Code (USING CUSTOM QR GENERATOR) -->
                        <div class="qr-code">
                            <!-- Custom QR code from api.qrserver.com -->
                            <img src="<?php echo $qr_url; ?>" alt="QR Code" id="qrImage" 
                                 title="QR contains: Number: 9800000001, MPIN: 1234">
                            <div style="margin-top: 1rem;">
                                <i data-lucide="smartphone" style="width: 1rem; height: 1rem;"></i>
                                <small>Scan with eSewa app or click "Scan QR Code" below</small>
                            </div>
                        </div>
                        
                        <!-- Scan Status -->
                        <div class="scan-status" id="scanStatus">
                            <div id="statusContent">
                                <i data-lucide="loader" style="width: 1.5rem; height: 1.5rem; margin-right: 0.5rem; vertical-align: middle; animation: spin 1s linear infinite;"></i>
                                <span id="statusText">Scanning QR code...</span>
                            </div>
                        </div>
                        
                        <!-- Scan Progress Bar -->
                        <div class="scan-progress" id="scanProgress"></div>
                        
                        <!-- Scan Button -->
                        <button class="scan-button" id="scanButton" onclick="startScanning()">
                            <i data-lucide="camera" id="scanIcon"></i>
                            <span id="scanText">Scan QR Code</span>
                        </button>
                        
                        <p style="color: #666; margin-top: 0.5rem;">
                            <i data-lucide="info"></i> Click to simulate QR code scanning
                        </p>
                    </div>
                    
                    <div class="instructions">
                        <h3><i data-lucide="help-circle"></i> How to Use Simulated Scanner</h3>
                        <ol>
                            <li><strong>Click "Scan QR Code" button</strong> to start simulated scanning</li>
                            <li>Watch the <strong>green scanner line</strong> move across the QR code</li>
                            <li>The scanner will <strong>automatically detect</strong> credentials (9800000001 / 1234)</li>
                            <li>You will be <strong>redirected automatically</strong> to the payment page</li>
                            <li>On the payment page, credentials will be <strong>pre-filled automatically</strong></li>
                            <li>Click "Pay" to complete the transaction</li>
                        </ol>
                        
                        <div style="margin-top: 1rem; padding: 1rem; background: #e8f4fd; border-radius: 5px;">
                            <h4><i data-lucide="info"></i> Note:</h4>
                            <p>This QR code contains embedded payment credentials:</p>
                            <ul>
                                <li><strong>Mobile Number:</strong> 9800000001</li>
                                <li><strong>MPIN:</strong> 1234</li>
                                <li><strong>Bill Number:</strong> <?php echo $bill['bill_number']; ?></li>
                                <li><strong>Amount:</strong> ₹<?php echo number_format($bill['amount'], 2); ?></li>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- Alternative Option -->
                    <div style="margin-top: 2rem; padding: 1.5rem; background: #f0f7ff; border-radius: 8px;">
                        <h4><i data-lucide="smartphone"></i> Alternative Option</h4>
                        <p>If you prefer to enter credentials manually:</p>
                        <a href="payment_esewa.php?mode=gateway" class="btn" style="background: #3498db; margin-top: 1rem;">
                            <i data-lucide="arrow-right"></i> Go to Gateway Payment
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <script>
        let isScanning = false;
        
        function startScanning() {
            if (isScanning) return;
            
            isScanning = true;
            const scanButton = document.getElementById('scanButton');
            const scanIcon = document.getElementById('scanIcon');
            const scanText = document.getElementById('scanText');
            const scannerLine = document.getElementById('scannerLine');
            const scanStatus = document.getElementById('scanStatus');
            const scanProgress = document.getElementById('scanProgress');
            const statusText = document.getElementById('statusText');
            const qrImage = document.getElementById('qrImage');
            
            // Update UI
            scanButton.classList.add('scanning');
            scanIcon.setAttribute('data-lucide', 'loader');
            scanText.textContent = 'Scanning...';
            scannerLine.style.animation = 'scan 3s linear infinite';
            scanStatus.classList.add('scanning');
            scanProgress.classList.add('active');
            
            // Add a glow effect to QR code during scanning
            qrImage.style.boxShadow = '0 0 20px rgba(83, 196, 26, 0.5)';
            qrImage.style.transition = 'box-shadow 0.3s';
            
            // Update Lucide icons
            lucide.createIcons();
            
            // Show scanning status with step-by-step updates
            statusText.textContent = 'Initializing scanner...';
            
            setTimeout(() => {
                statusText.textContent = 'Detecting QR code...';
            }, 600);
            
            setTimeout(() => {
                statusText.textContent = 'Reading QR code data...';
            }, 1200);
            
            setTimeout(() => {
                statusText.textContent = 'Credentials detected: 9800000001 / 1234';
                qrImage.style.boxShadow = '0 0 30px rgba(39, 174, 96, 0.7)';
            }, 1800);
            
            // Complete scanning after 3 seconds
            setTimeout(() => {
                scanStatus.classList.remove('scanning');
                scanStatus.classList.add('success');
                
                scanIcon.setAttribute('data-lucide', 'check-circle');
                scanText.textContent = 'Scanned Successfully!';
                statusText.innerHTML = '<i data-lucide="check" style="width: 1.5rem; height: 1.5rem; margin-right: 0.5rem; vertical-align: middle;"></i>QR code scanned successfully! Redirecting...';
                
                // Success glow effect
                qrImage.style.boxShadow = '0 0 40px rgba(39, 174, 96, 0.9)';
                
                // Update Lucide icons
                lucide.createIcons();
                
                // Show success animation on scanner line
                scannerLine.style.animation = 'none';
                scannerLine.style.background = '#27ae60';
                scannerLine.style.boxShadow = '0 0 15px #27ae60';
                
                // Redirect to payment page with scan_complete parameter
                setTimeout(() => {
                    window.location.href = 'payment_esewa.php?mode=qr&scan_complete=1&auto_fill=1';
                }, 1500);
                
            }, 3000);
        }
        
        // Auto-start scanning after 5 seconds for better UX
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Lucide Icons
            lucide.createIcons();
            
            // Add CSS for spinner animation
            const style = document.createElement('style');
            style.textContent = `
                @keyframes spin {
                    from { transform: rotate(0deg); }
                    to { transform: rotate(360deg); }
                }
                
                @keyframes pulse {
                    0% { box-shadow: 0 0 10px rgba(83, 196, 26, 0.3); }
                    50% { box-shadow: 0 0 20px rgba(83, 196, 26, 0.6); }
                    100% { box-shadow: 0 0 10px rgba(83, 196, 26, 0.3); }
                }
            `;
            document.head.appendChild(style);
            
            // Add subtle pulse animation to QR code
            const qrImage = document.getElementById('qrImage');
            if (qrImage) {
                qrImage.style.animation = 'pulse 2s infinite';
            }
            
            // Auto-start scanning after 3 seconds for demo convenience
            setTimeout(startScanning, 3000);
        });
        
        // Show hidden QR data on click (for debugging)
        document.getElementById('qrImage').addEventListener('click', function() {
            const hiddenData = document.getElementById('qrHiddenData');
            if (hiddenData.style.display === 'block') {
                hiddenData.style.display = 'none';
            } else {
                hiddenData.style.display = 'block';
            }
        });
        
        // Add keyboard shortcut for scanning (Space key)
        document.addEventListener('keydown', function(e) {
            if (e.code === 'Space' && !isScanning) {
                e.preventDefault();
                startScanning();
            }
        });
    </script>
    <script src="js/logout.js"></script>
</body>
</html>