<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/db_connection.php';

// Return JSON
header('Content-Type: application/json');

// Check if admin
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    echo json_encode(['error' => 'Unauthorized', 'success' => false]);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$notification_id = $data['id'] ?? 0;

if ($notification_id > 0) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
    $stmt->execute([$notification_id]);
    
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid notification ID']);
}
?>