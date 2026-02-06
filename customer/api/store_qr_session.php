<?php
session_start();
require_once '../../includes/config.php';

header('Content-Type: application/json');

// Allow only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$transaction_id = $data['transaction_id'] ?? null;
$bill_id = $data['bill_id'] ?? null;
$amount = $data['amount'] ?? null;

if (!$transaction_id || !$bill_id) {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit();
}

// Store QR session in database or session
try {
    // Store in session for demo purposes
    $_SESSION['qr_session'] = [
        'transaction_id' => $transaction_id,
        'bill_id' => $bill_id,
        'amount' => $amount,
        'created_at' => time(),
        'status' => 'pending'
    ];
    
    echo json_encode([
        'success' => true,
        'message' => 'QR session stored',
        'transaction_id' => $transaction_id
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error storing session: ' . $e->getMessage()
    ]);
}