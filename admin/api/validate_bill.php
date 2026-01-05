<?php
session_start();
include '../../includes/config.php';

// Simple bill validation
if (isset($_GET['bill_id'])) {
    $bill_id = $_GET['bill_id'];
    $user_id = $_SESSION['user_id'] ?? 0;
    
    header('Content-Type: application/json');
    
    // Basic validation
    if ($bill_id && $user_id) {
        echo json_encode([
            'valid' => true,
            'bill' => [
                'id' => $bill_id,
                'status' => 'pending'
            ]
        ]);
    } else {
        echo json_encode([
            'valid' => false,
            'error' => 'Invalid bill'
        ]);
    }
} else {
    echo json_encode([
        'valid' => false,
        'error' => 'Bill ID required'
    ]);
}
?>