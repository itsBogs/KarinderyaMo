<?php
// api/get_menu.php - Get menu items organized by category
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

try {
    $pdo = getPDO();
    $stmt = $pdo->query('SELECT id, name, category, price, image, availability FROM menu_items WHERE availability = "available" ORDER BY category, name');
    $items = $stmt->fetchAll();
    
    // Organize by category
    $organized = [];
    foreach ($items as $item) {
        if (!isset($organized[$item['category']])) {
            $organized[$item['category']] = [];
        }
        $organized[$item['category']][] = [
            'id' => (int)$item['id'],
            'name' => $item['name'],
            'price' => (float)$item['price'],
            'image' => $item['image'],
            'category' => $item['category']
        ];
    }
    
    echo json_encode($organized);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>