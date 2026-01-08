<?php
// direct_payment_test.php - Bypasses all checks
session_start();
require_once '../includes/config.php';

echo "<h1>Direct Payment Test</h1>";

// Force set everything
$_SESSION['user_id'] = 2; // Your user ID
$_SESSION['full_name'] = 'Test Customer';
$_SESSION['is_admin'] = false;

// Get a bill ID
$stmt = $pdo->query("SELECT id FROM bills WHERE payment_status = 'pending' LIMIT 1");
$bill = $stmt->fetch();

if ($bill) {
    $bill_id = $bill['id'];
    
    // Direct form to payment_esewa.php
    echo "<form method='POST' action='payment_esewa.php'>";
    echo "<input type='hidden' name='simulate_payment' value='1'>";
    echo "<input type='hidden' name='mobile' value='9800000001'>";
    echo "<input type='hidden' name='mpin' value='1234'>";
    
    // Create session directly
    $_SESSION['payment_session'] = [
        'bill_id' => $bill_id,
        'transaction_id' => 'DIRECT-TEST-' . time(),
        'created_at' => time()
    ];
    
    echo "<p>Bill ID: $bill_id</p>";
    echo "<p>Transaction ID: " . $_SESSION['payment_session']['transaction_id'] . "</p>";
    echo "<button type='submit'>Test Direct Payment</button>";
    echo "</form>";
} else {
    echo "<p>No pending bills found. Create one first.</p>";
}
?>