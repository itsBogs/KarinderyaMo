<?php
// api/get_order_status.php - Minimal API endpoint for real-time status
require_once '../db.php'; // Adjust path as necessary
session_start();

header('Content-Type: application/json');

// Security Check: Must be logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$order_id = $_GET['order_id'] ?? null;

if (!$order_id || !is_numeric($order_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid Order ID']);
    exit;
}

try {
    $pdo = getPDO();
    // Fetch only the status for the given order ID and customer ID
    $stmt = $pdo->prepare('SELECT status FROM orders WHERE id = ? AND customer_id = ?');
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($order) {
        echo json_encode(['status' => $order['status']]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Order not found or does not belong to user']);
    }

} catch (Exception $e) {
    http_response_code(500);
    error_log("Status API Error: " . $e->getMessage());
    echo json_encode(['error' => 'Server Error']);
}
?>