<?php

session_start();
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}
if ($_SESSION['user_role'] !== 'customer') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only customers can view orders']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $pdo = getPDO();
    $ordersStmt = $pdo->prepare('
        SELECT o.id, o.order_number, o.status, o.total_amount, o.delivery_address, o.created_at,
               o.rider_id, r.name AS rider_name, r.phone AS rider_phone
        FROM orders o
        LEFT JOIN users r ON o.rider_id = r.id
        WHERE o.customer_id = ?
        ORDER BY o.created_at DESC
    ');
    $ordersStmt->execute([$user_id]);
    $orders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);


    $itemsStmt = $pdo->prepare('
        SELECT oi.order_id, oi.quantity, oi.price, mi.name, mi.image
        FROM order_items oi
        JOIN menu_items mi ON oi.menu_item_id = mi.id
        WHERE oi.order_id = ?
    ');

    $result = [];
    foreach ($orders as $order) {
        $itemsStmt->execute([$order['id']]);
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        $order['items'] = $items;
        $result[] = $order;
    }

    echo json_encode(['success' => true, 'orders' => $result]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
