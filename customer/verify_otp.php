<?php
// For customer files:
$base_path = dirname(__DIR__);
require_once $base_path . '/includes/config.php';
// session_start(); // REMOVED
require_once $base_path . '/includes/db_connection.php';
require_once $base_path . '/includes/email_functions.php';
// Display debug OTPs if in debug mode
if (defined('EMAIL_DEBUG') && EMAIL_DEBUG) {
    // You can implement displayDebugOTPs() function if needed
}

$user_id = $_SESSION['temp_user_id'] ?? null;
$email = $_SESSION['temp_email'] ?? '';
$error = '';

// Redirect if no user in session
if (!$user_id) {
    header("Location: register.php");
    exit;
}

// Handle OTP verification
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $entered_otp = trim($_POST['otp'] ?? '');
    
    if (empty($entered_otp)) {
        $error = "Please enter the OTP";
    } else {
        try {
            // Check OTP in verification_otps table first
            $stmt = $pdo->prepare("
                SELECT * FROM verification_otps 
                WHERE email = ? 
                AND otp = ? 
                AND type = 'registration' 
                AND is_used = 0 
                AND expires_at > NOW()
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            
            $stmt->execute([$email, $entered_otp]);
            $otp_record = $stmt->fetch();
            
            if ($otp_record) {
                // Mark OTP as used in verification_otps table
                $stmt = $pdo->prepare("UPDATE verification_otps SET is_used = 1 WHERE id = ?");
                $stmt->execute([$otp_record['id']]);
                
                // Update user as verified
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET email_verified = 1, 
                        registration_status = 'verified', 
                        updated_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$user_id]);
                
                // Clear temp session
                unset($_SESSION['temp_user_id']);
                unset($_SESSION['temp_email']);
                unset($_SESSION['temp_full_name']);
                
                // Show success message
                $_SESSION['registration_success'] = "Email verified successfully! Your account is pending admin approval. You will be notified once approved.";
                
                header("Location: login.php");
                exit();
                
            } else {
                // Fallback: Check OTP in users table (if columns exist)
                try {
                    $stmt = $pdo->prepare("SELECT otp, otp_expiry FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $user_data = $stmt->fetch();
                    
                    if ($user_data && isset($user_data['otp']) && isset($user_data['otp_expiry'])) {
                        $stored_otp = $user_data['otp'];
                        $otp_expiry = $user_data['otp_expiry'];
                        
                        // Check if OTP is expired
                        if (strtotime($otp_expiry) < time()) {
                            $error = "OTP has expired. Please request a new one.";
                        } elseif ($entered_otp == $stored_otp) {
                            // OTP is correct - update user status
                            $stmt = $pdo->prepare("
                                UPDATE users 
                                SET email_verified = 1, 
                                    otp = NULL, 
                                    otp_expiry = NULL, 
                                    registration_status = 'verified', 
                                    updated_at = NOW() 
                                WHERE id = ?
                            ");
                            $stmt->execute([$user_id]);
                            
                            // Clear temp session
                            unset($_SESSION['temp_user_id']);
                            unset($_SESSION['temp_email']);
                            unset($_SESSION['temp_full_name']);
                            
                            // Show success message
                            $_SESSION['registration_success'] = "Email verified successfully! Your account is pending admin approval. You will be notified once approved.";
                            
                            header("Location: login.php");
                            exit();
                        } else {
                            $error = "Invalid OTP. Please try again.";
                        }
                    } else {
                        $error = "Invalid OTP. Please try again or request a new OTP.";
                    }
                } catch (Exception $e) {
                    $error = "OTP verification error. Please try again.";
                }
            }
            
        } catch (Exception $e) {
            $error = "Error updating verification status: " . $e->getMessage();
        }
    }
}

// Check if OTP has expired on page load
try {
    // Check in verification_otps table
    $stmt = $pdo->prepare("
        SELECT expires_at FROM verification_otps 
        WHERE email = ? 
        AND type = 'registration' 
        AND is_used = 0 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$email]);
    $otp_data = $stmt->fetch();
    
    if ($otp_data && $otp_data['expires_at'] && strtotime($otp_data['expires_at']) < time()) {
        $error = "OTP has expired. Please register again.";
        // Clear invalid session
        unset($_SESSION['temp_user_id']);
        unset($_SESSION['temp_email']);
        unset($_SESSION['temp_full_name']);
    }
} catch (Exception $e) {
    // If verification_otps table doesn't exist or has error, check users table
    try {
        $stmt = $pdo->prepare("SELECT otp_expiry FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if ($user && $user['otp_expiry'] && strtotime($user['otp_expiry']) < time()) {
            $error = "OTP has expired. Please register again.";
            // Clear invalid session
            unset($_SESSION['temp_user_id']);
            unset($_SESSION['temp_email']);
            unset($_SESSION['temp_full_name']);
        }
    } catch (Exception $e2) {
        // Ignore error if columns don't exist
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP - BillPay Pro</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        .otp-container {
            max-width: 400px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .otp-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .otp-icon {
            width: 80px;
            height: 80px;
            background: #075B5E;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 1rem;
        }
        
        .otp-inputs {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin: 2rem 0;
        }
        
        .otp-input {
            width: 50px;
            height: 60px;
            text-align: center;
            font-size: 1.5rem;
            border: 2px solid #ddd;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .otp-input:focus {
            border-color: #075B5E;
            box-shadow: 0 0 0 3px rgba(7, 91, 94, 0.1);
            outline: none;
        }
        
        .otp-timer {
            text-align: center;
            margin: 1rem 0;
            color: #666;
        }
        
        .otp-note {
            background: #f0f7f7;
            padding: 1rem;
            border-radius: 5px;
            margin: 1rem 0;
            font-size: 0.9rem;
            text-align: center;
        }
        
        .resend-otp {
            text-align: center;
            margin-top: 1rem;
        }
        
        .resend-btn {
            background: none;
            border: none;
            color: #075B5E;
            text-decoration: underline;
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .resend-btn:hover {
            color: #0a7c80;
        }
        
        .resend-btn:disabled {
            color: #999;
            cursor: not-allowed;
            text-decoration: none;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <div class="logo">BillPay Pro</div>
                <ul class="nav-links">
                    <li><a href="../index.php">Home</a></li>
                    <li><a href="register.php">Register</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="container">
        <div class="otp-container">
            <!-- OTP Header -->
            <div class="otp-header">
                <div class="otp-icon">
                    <i data-lucide="shield-check"></i>
                </div>
                <h1>Verify Email</h1>
                <p>Enter the 6-digit OTP sent to your email</p>
                <p><strong><?php echo htmlspecialchars($email); ?></strong></p>
            </div>
            
            <!-- Messages -->
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i data-lucide="alert-circle" style="width: 1.2rem; height: 1.2rem; margin-right: 0.5rem; vertical-align: middle;"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <!-- OTP Note -->
            <div class="otp-note">
                <i data-lucide="mail" style="width: 1rem; height: 1rem; margin-right: 0.5rem;"></i>
                <p>Check your email inbox (and spam folder) for the OTP code.</p>
                <p>The OTP is valid for 10 minutes.</p>
            </div>
            
            <!-- OTP Form -->
            <form method="POST" action="" id="otpForm">
                <div class="otp-inputs">
                    <?php for ($i = 1; $i <= 6; $i++): ?>
                        <input type="text" name="otp[]" class="otp-input" maxlength="1" 
                               onkeyup="moveToNext(this, <?php echo $i; ?>)" 
                               onkeydown="handleBackspace(this, <?php echo $i; ?>, event)">
                    <?php endfor; ?>
                    <input type="hidden" name="otp" id="fullOtp">
                </div>
                
                <div class="otp-timer" id="timer">
                    <i data-lucide="clock" style="width: 1rem; height: 1rem; margin-right: 0.5rem;"></i>
                    OTP valid for: <span id="countdown">10:00</span>
                </div>
                
                <div class="resend-otp">
                    <button type="button" id="resendBtn" class="resend-btn" onclick="resendOTP()" disabled>
                        Resend OTP (<span id="resendTimer">60</span>s)
                    </button>
                </div>
                
                <button type="submit" class="btn" style="width: 100%;">
                    <i data-lucide="check" style="width: 1.2rem; height: 1.2rem; margin-right: 0.5rem;"></i>
                    Verify & Continue
                </button>
            </form>
            
            <div style="text-align: center; margin-top: 2rem; padding-top: 1rem; border-top: 1px solid #e9ecef;">
                <p>Wrong email? <a href="register.php">Register again</a></p>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> BillPay Pro - Online Billing System</p>
        </div>
    </footer>

    <script>
        lucide.createIcons();
        
        // OTP input handling
        function moveToNext(input, currentIndex) {
            if (input.value.length === 1) {
                if (currentIndex < 6) {
                    const nextInput = document.querySelector(`input[name="otp[]"]:nth-child(${currentIndex + 1})`);
                    if (nextInput) nextInput.focus();
                }
                updateFullOTP();
            }
        }
        
        function handleBackspace(input, currentIndex, event) {
            if (event.key === 'Backspace' && input.value.length === 0) {
                if (currentIndex > 1) {
                    const prevInput = document.querySelector(`input[name="otp[]"]:nth-child(${currentIndex - 1})`);
                    if (prevInput) {
                        prevInput.focus();
                        prevInput.value = '';
                    }
                }
                updateFullOTP();
            }
        }
        
        function updateFullOTP() {
            const inputs = document.querySelectorAll('input[name="otp[]"]');
            let fullOtp = '';
            inputs.forEach(input => {
                fullOtp += input.value;
            });
            document.getElementById('fullOtp').value = fullOtp;
        }
        
        // Timer countdown for OTP
        let timeLeft = 600; // 10 minutes in seconds
        
        function updateTimer() {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            document.getElementById('countdown').textContent = 
                `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            if (timeLeft > 0) {
                timeLeft--;
                setTimeout(updateTimer, 1000);
            } else {
                document.getElementById('timer').innerHTML = 
                    '<i data-lucide="alert-triangle" style="width: 1rem; height: 1rem; margin-right: 0.5rem; color: #e74c3c;"></i>' +
                    '<span style="color: #e74c3c;">OTP expired</span>';
            }
        }
        
        // Resend OTP timer
        let resendTimeLeft = 60;
        
        function updateResendTimer() {
            document.getElementById('resendTimer').textContent = resendTimeLeft;
            
            if (resendTimeLeft > 0) {
                resendTimeLeft--;
                setTimeout(updateResendTimer, 1000);
            } else {
                document.getElementById('resendBtn').disabled = false;
                document.getElementById('resendBtn').innerHTML = 'Resend OTP';
            }
        }
        
        // Resend OTP function
        function resendOTP() {
            if (resendTimeLeft > 0) return;
            
            // Disable button and reset timer
            document.getElementById('resendBtn').disabled = true;
            resendTimeLeft = 60;
            updateResendTimer();
            
            // Send AJAX request to resend OTP
            fetch('resend_otp.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=resend_otp'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('New OTP has been sent to your email.');
                    // Reset main timer
                    timeLeft = 600;
                    updateTimer();
                } else {
                    alert('Failed to resend OTP: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to resend OTP. Please try again.');
            });
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            updateTimer();
            updateResendTimer();
            
            // Focus first OTP input
            const firstInput = document.querySelector('input[name="otp[]"]:first-child');
            if (firstInput) firstInput.focus();
            
            // Form submission
            document.getElementById('otpForm').addEventListener('submit', function(e) {
                updateFullOTP();
                const fullOtp = document.getElementById('fullOtp').value;
                
                if (fullOtp.length !== 6) {
                    e.preventDefault();
                    alert('Please enter all 6 digits of the OTP');
                    return false;
                }
                
                return true;
            });
        });
    </script>
</body>
</html>