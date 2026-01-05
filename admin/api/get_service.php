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
    echo json_encode(['error' => 'Service ID required']);
    exit();
}

$service_id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM services WHERE id = ?");
$stmt->execute([$service_id]);
$service = $stmt->fetch();

if (!$service) {
    http_response_code(404);
    echo json_encode(['error' => 'Service not found']);
    exit();
}

header('Content-Type: application/json');
echo json_encode($service);
?>