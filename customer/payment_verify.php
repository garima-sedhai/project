<?php
session_start();
include '../includes/config.php';
include '../includes/payment_config.php';

// This file handles payment verification from gateways
if (!isset($_GET['transaction_id']) || !isset($_GET['gateway'])) {
    header("Location: payment_failed.php");
    exit();
}

$transaction_id = $_GET['transaction_id'];
$gateway = $_GET['gateway'];

// Verify payment based on gateway
if ($gateway == 'esewa') {
    verifyEsewaPayment($transaction_id);
} elseif ($gateway == 'khalti') {
    verifyKhaltiPayment($transaction_id);
} else {
    header("Location: payment_failed.php?error=invalid_gateway");
    exit();
}

function verifyEsewaPayment($transaction_id) {
    global $pdo;
    
    // In sandbox mode, we'll simulate successful payment
    // In production, you would verify with eSewa API
    
    $stmt = $pdo->prepare("SELECT p.*, b.*, u.full_name FROM payments p 
                          JOIN bills b ON p.bill_id = b.id 
                          JOIN users u ON p.user_id = u.id 
                          WHERE p.transaction_id = ?");
    $stmt->execute([$transaction_id]);
    $payment = $stmt->fetch();
    
    if ($payment) {
        // Simulate successful payment in sandbox
        if ($payment['status'] == 'pending') {
            // Update payment status
            $stmt = $pdo->prepare("UPDATE payments SET status = 'completed', payment_date = NOW() WHERE transaction_id = ?");
            $stmt->execute([$transaction_id]);
            
            // Update bill status
            $stmt = $pdo->prepare("UPDATE bills SET status = 'paid', paid_at = NOW() WHERE id = ?");
            $stmt->execute([$payment['bill_id']]);
            
            // Create notifications
            createPaymentNotifications($payment['user_id'], $payment['full_name'], $payment['payment_amount'], $payment['bill_type']);
        }
        
        header("Location: payment_success.php?transaction_id=" . $transaction_id);
    } else {
        header("Location: payment_failed.php?error=payment_not_found");
    }
    exit();
}

function verifyKhaltiPayment($transaction_id) {
    global $pdo;
    
    // Similar verification for Khalti
    // For sandbox, we simulate success
    
    $stmt = $pdo->prepare("SELECT p.*, b.*, u.full_name FROM payments p 
                          JOIN bills b ON p.bill_id = b.id 
                          JOIN users u ON p.user_id = u.id 
                          WHERE p.transaction_id = ?");
    $stmt->execute([$transaction_id]);
    $payment = $stmt->fetch();
    
    if ($payment) {
        if ($payment['status'] == 'pending') {
            // Update payment status
            $stmt = $pdo->prepare("UPDATE payments SET status = 'completed', payment_date = NOW() WHERE transaction_id = ?");
            $stmt->execute([$transaction_id]);
            
            // Update bill status
            $stmt = $pdo->prepare("UPDATE bills SET status = 'paid', paid_at = NOW() WHERE id = ?");
            $stmt->execute([$payment['bill_id']]);
            
            // Create notifications
            createPaymentNotifications($payment['user_id'], $payment['full_name'], $payment['payment_amount'], $payment['bill_type']);
        }
        
        header("Location: payment_success.php?transaction_id=" . $transaction_id);
    } else {
        header("Location: payment_failed.php?error=payment_not_found");
    }
    exit();
}

function createPaymentNotifications($user_id, $user_name, $amount, $service) {
    global $pdo;
    
    // Notification for admin
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, created_at) 
                          SELECT id, 'Payment Received', ?, 'payment', NOW() FROM users WHERE is_admin = TRUE");
    $admin_message = "Payment of ₹" . number_format($amount, 2) . " received from " . $user_name . " for " . $service;
    $stmt->execute([$admin_message]);
    
    // Notification for user
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, created_at) VALUES (?, 'Payment Successful', ?, 'payment', NOW())");
    $user_message = "Your payment of ₹" . number_format($amount, 2) . " for " . $service . " has been processed successfully.";
    $stmt->execute([$user_id, $user_message]);
}
?>