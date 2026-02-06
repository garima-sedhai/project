<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/db_connection.php';

// Return JSON
header('Content-Type: application/json');

// Check if admin
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    echo json_encode(['error' => 'Unauthorized', 'count' => 0]);
    exit;
}

// Get unread count
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE is_read = FALSE");
$stmt->execute();
$result = $stmt->fetch();

echo json_encode(['count' => $result['count'] ?? 0]);
?>