<?php
session_start();
include '../../includes/config.php';

// Only allow admins
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit();
}

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Bill ID required']);
    exit();
}

$bill_id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM bills WHERE id = ?");
$stmt->execute([$bill_id]);
$bill = $stmt->fetch();

if (!$bill) {
    http_response_code(404);
    echo json_encode(['error' => 'Bill not found']);
    exit();
}

header('Content-Type: application/json');
echo json_encode($bill);
?>