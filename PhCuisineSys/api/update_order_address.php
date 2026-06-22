<?php
// api/update_order_address.php - AJAX handler for updating delivery address
session_start();
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

if ($_SESSION['user_role'] !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Only customers can update their order']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$order_id = $_POST['order_id'] ?? null;
$new_address = trim($_POST['delivery_address'] ?? '');
$user_id = $_SESSION['user_id'];

if (!$order_id || !$new_address) {
    echo json_encode(['success' => false, 'message' => 'Order and address are required']);
    exit;
}

try {
    $pdo = getPDO();
    // Ensure order belongs to customer and still pending
    $check = $pdo->prepare('SELECT status FROM orders WHERE id = ? AND customer_id = ?');
    $check->execute([$order_id, $user_id]);
    $order = $check->fetch();
    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }
    if ($order['status'] !== 'pending') {
        echo json_encode(['success' => false, 'message' => 'Only pending orders can be updated']);
        exit;
    }

    $upd = $pdo->prepare('UPDATE orders SET delivery_address = ? WHERE id = ?');
    $upd->execute([$new_address, $order_id]);

    echo json_encode(['success' => true, 'message' => 'Address updated']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
