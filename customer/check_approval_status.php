<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT admin_approved, registration_status FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($user && $user['admin_approved'] && $user['registration_status'] == 'approved') {
    echo json_encode(['approved' => true]);
} else {
    echo json_encode(['approved' => false]);
}
?>