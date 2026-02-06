<?php
session_start();
require_once '../includes/config.php';

// Check if payment session exists
if (!isset($_SESSION['payment_session'])) {
    // If not, create a new one based on GET parameters
    if (isset($_GET['bill_id']) && isset($_GET['transaction_id'])) {
        $_SESSION['payment_session'] = [
            'bill_id' => $_GET['bill_id'],
            'transaction_id' => $_GET['transaction_id'],
            'created_at' => time()
        ];
    } else {
        // Try to get from payment_process session
        if (isset($_SESSION['payment_process_data'])) {
            $_SESSION['payment_session'] = $_SESSION['payment_process_data'];
        } else {
            header("Location: payment.php?error=no_session");
            exit();
        }
    }
}

// Now redirect to the actual payment page
header("Location: payment_esewa.php");
exit();
?>