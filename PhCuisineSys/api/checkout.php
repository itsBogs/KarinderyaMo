<?php
// api/checkout.php - Process order checkout
require '../db.php';

session_start();

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in first']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $pdo = getPDO();
    $user_id = $_SESSION['user_id'];

    // Get form data
    $customer_name = trim($_POST['customer_name'] ?? '');
    $contact_phone = trim($_POST['contact_phone'] ?? '');
    $delivery_address = trim($_POST['delivery_address'] ?? '');
    $cart_items = json_decode($_POST['cart_items'] ?? '[]', true);

    // Validate
    if (!$customer_name || !$contact_phone || !$delivery_address) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit;
    }

    if (empty($cart_items)) {
        echo json_encode(['success' => false, 'message' => 'Cart is empty']);
        exit;
    }

    // Calculate total
    $total_amount = 0;
    foreach ($cart_items as $item) {
        if (!isset($item['id'], $item['qty'], $item['price'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid cart item']);
            exit;
        }
        $total_amount += $item['price'] * $item['qty'];
    }

    // Update user phone if provided
    $updateStmt = $pdo->prepare('UPDATE users SET phone = ? WHERE id = ?');
    $updateStmt->execute([$contact_phone, $user_id]);

    // Generate unique order number
    $order_number = 'ORD-' . time() . '-' . substr(md5(rand()), 0, 5);

    // Create order
    $orderStmt = $pdo->prepare('
        INSERT INTO orders (order_number, customer_id, total_amount, delivery_address, status)
        VALUES (?, ?, ?, ?, ?)
    ');
    $orderStmt->execute([$order_number, $user_id, $total_amount, $delivery_address, 'pending']);
    $order_id = $pdo->lastInsertId();

    // Insert order items
    $itemStmt = $pdo->prepare('
        INSERT INTO order_items (order_id, menu_item_id, quantity, price)
        VALUES (?, ?, ?, ?)
    ');

    foreach ($cart_items as $item) {
        // Verify menu item exists
        $checkStmt = $pdo->prepare('SELECT price FROM menu_items WHERE id = ?');
        $checkStmt->execute([$item['id']]);
        $menuItem = $checkStmt->fetch();

        if (!$menuItem) {
            // Rollback: delete the order
            $pdo->prepare('DELETE FROM orders WHERE id = ?')->execute([$order_id]);
            echo json_encode(['success' => false, 'message' => 'Menu item not found']);
            exit;
        }

        $itemStmt->execute([
            $order_id,
            $item['id'],
            $item['qty'],
            $item['price']
        ]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Order placed successfully!',
        'order_id' => $order_id,
        'order_number' => $order_number
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
