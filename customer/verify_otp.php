<?php
session_start();
include '../includes/config.php';

// Check if user is in OTP verification process
if (!isset($_SESSION['temp_user_id']) || !isset($_SESSION['temp_otp'])) {
    header("Location: register.php");
    exit();
}

$user_id = $_SESSION['temp_user_id'];
$stored_otp = $_SESSION['temp_otp'];
$email = $_SESSION['temp_email'];
$error = '';

// Handle OTP verification
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $entered_otp = trim($_POST['otp']);
    
    if (empty($entered_otp)) {
        $error = "Please enter the OTP";
    } elseif ($entered_otp == $stored_otp) {
        // OTP is correct - update user status
        try {
            $stmt = $pdo->prepare("UPDATE users SET email_verified = 1, otp = NULL, otp_expiry = NULL, 
                                  registration_status = 'verified' WHERE id = ?");
            $stmt->execute([$user_id]);
            
            // Clear temp session
            unset($_SESSION['temp_user_id']);
            unset($_SESSION['temp_otp']);
            unset($_SESSION['temp_email']);
            
            // Show success message
            $_SESSION['registration_success'] = "Email verified successfully! Your account is pending admin approval. You will be notified once approved.";
            
            header("Location: login.php");
            exit();
            
        } catch (Exception $e) {
            $error = "Error updating verification status: " . $e->getMessage();
        }
    } else {
        $error = "Invalid OTP. Please try again.";
    }
}

// Check if OTP has expired
$stmt = $pdo->prepare("SELECT otp_expiry FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($user && $user['otp_expiry'] && strtotime($user['otp_expiry']) < time()) {
    $error = "OTP has expired. Please register again.";
    // Clear invalid session
    unset($_SESSION['temp_user_id']);
    unset($_SESSION['temp_otp']);
    unset($_SESSION['temp_email']);
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
        
        .otp-demo {
            background: #fff3cd;
            padding: 1rem;
            border-radius: 5px;
            margin: 1rem 0;
            border-left: 4px solid #ffc107;
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
            
            <!-- OTP Demo Note -->
            <div class="otp-demo">
                <h4><i data-lucide="info" style="width: 1rem; height: 1rem; margin-right: 0.5rem;"></i>Demo OTP</h4>
                <p>For demonstration purposes, use OTP: <strong><?php echo $stored_otp; ?></strong></p>
                <p><small>In production, this would be sent to your email.</small></p>
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
                
                <div class="otp-note">
                    <i data-lucide="mail" style="width: 1rem; height: 1rem; margin-right: 0.5rem;"></i>
                    Didn't receive OTP? <a href="resend_otp.php">Resend OTP</a>
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
        
        // Timer countdown
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
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            updateTimer();
            
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