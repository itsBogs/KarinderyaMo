<?php
// api/clear_cart.php - no-op endpoint for clearing server-side cart (if any)
// This endpoint allows the client to call via AJAX to avoid blocking confirm dialogs.

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// If you later store cart server-side, clear it here.
// Example:
// require_once __DIR__ . '/../db.php';
// $pdo = getPDO();
// $stmt = $pdo->prepare('DELETE FROM carts WHERE user_id = ?');
// $stmt->execute([$_SESSION['user_id']]);

// For now just return success so client can clear local cart
echo json_encode(['success' => true, 'message' => 'Cart cleared']);
