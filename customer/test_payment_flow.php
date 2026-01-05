<?php
session_start();
echo "<h1>Payment Flow Debug</h1>";
echo "<pre>";
echo "SESSION data:\n";
print_r($_SESSION);
echo "\nPOST data:\n";
print_r($_POST);
echo "\nGET data:\n";
print_r($_GET);
echo "</pre>";

// Test if payment session is created
if (isset($_SESSION['payment_session'])) {
    echo "<h2>Payment Session Exists:</h2>";
    echo "<pre>";
    print_r($_SESSION['payment_session']);
    echo "</pre>";
    
    echo "<h3>Redirect Test:</h3>";
    echo '<a href="payment_esewa.php">Go to eSewa Payment</a>';
} else {
    echo "<h2>No Payment Session Found</h2>";
    echo "<p>Try submitting the payment form first.</p>";
}
?>