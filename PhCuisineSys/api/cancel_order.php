<?php

session_start();
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

if ($_SESSION['user_role'] !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Only customers can cancel their orders']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$order_id = $_POST['order_id'] ?? null;
$user_id = $_SESSION['user_id'];

if (!$order_id) {
    echo json_encode(['success' => false, 'message' => 'Order is required']);
    exit;
}

try {
    $pdo = getPDO();
    $check = $pdo->prepare('SELECT status FROM orders WHERE id = ? AND customer_id = ?');
    $check->execute([$order_id, $user_id]);
    $order = $check->fetch();

    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }

    if ($order['status'] !== 'pending') {
        echo json_encode(['success' => false, 'message' => 'Only pending orders can be cancelled']);
        exit;
    }

    $upd = $pdo->prepare('UPDATE orders SET status = "cancelled" WHERE id = ?');
    $upd->execute([$order_id]);

    echo json_encode(['success' => true, 'message' => 'Order cancelled']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
