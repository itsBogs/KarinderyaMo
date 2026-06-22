<?php
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

try {
    $pdo = getPDO();
    $stmt = $pdo->query('SELECT id, name, category, price, availability, image FROM menu_items ORDER BY category, name');
    $items = $stmt->fetchAll();

    // Normalize image paths for client display
    foreach ($items as &$item) {
        $img = $item['image'] ?? '';
        if ($img) {
            if (stripos($img, 'http') === 0) {
                // leave absolute URLs
                $item['image'] = $img;
            } else {
                // ensure leading slash for relative paths
                $item['image'] = ($img[0] === '/') ? $img : '/' . $img;
            }
        }
    }

    echo json_encode(['items' => $items]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['items' => [], 'error' => 'Could not load menu items']);
}
