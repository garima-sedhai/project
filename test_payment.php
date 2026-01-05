<?php
session_start();
include 'includes/config.php';
include 'includes/payment_config.php';

// This is a test script to verify payment integration
echo "<h1>Payment Integration Test</h1>";

// Test database connection
try {
    $pdo->query("SELECT 1");
    echo "Database connection: OK<br>";
} catch (Exception $e) {
    echo "Database connection: FAILED - " . $e->getMessage() . "<br>";
}

// Test if required tables exist
$tables = ['users', 'bills', 'payments', 'notifications'];
foreach ($tables as $table) {
    try {
        $pdo->query("SELECT 1 FROM $table LIMIT 1");
        echo "Table '$table': EXISTS<br>";
    } catch (Exception $e) {
        echo "Table '$table': MISSING - " . $e->getMessage() . "<br>";
    }
}

// Test payment configuration
echo "<h2>Payment Configuration</h2>";
echo "eSewa Merchant ID: " . PaymentConfig::$esewa['merchant_id'] . "<br>";
echo "Khalti Public Key: " . substr(PaymentConfig::$khalti['public_key'], 0, 10) . "..." . "<br>";

// Test transaction ID generation
$transaction_id = PaymentConfig::generateTransactionId();
echo "Generated Transaction ID: $transaction_id<br>";

// Test amount validation
$test_amounts = [100, 0, -50, 'abc'];
foreach ($test_amounts as $amount) {
    $is_valid = PaymentConfig::validateAmount($amount);
    echo "Amount $amount: " . ($is_valid ? "VALID" : "INVALID") . "<br>";
}

echo "<h2>Next Steps:</h2>";
echo "1. Go to <a href='customer/login.php'>Customer Login</a><br>";
echo "2. Login with phone: 9800000001, password: password<br>";
echo "3. Go to Make Payment section<br>";
echo "4. Test eSewa or Khalti payment<br>";

echo "<h2>Demo Credentials:</h2>";
echo "<strong>eSewa:</strong><br>";
echo "- Mobile: 9800000001<br>";
echo "- MPIN: 1234<br>";
echo "- OTP: 987654<br><br>";

echo "<strong>Khalti:</strong><br>";
echo "- Mobile: 9800000001<br>";
echo "- MPIN: 1111<br>";
echo "- OTP: 123456<br>";
?>