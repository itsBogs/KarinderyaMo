<?php
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json');

try {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $data = $_POST;
    }
    $id = isset($data['id']) ? (int)$data['id'] : 0;
    if ($id <= 0) {
        throw new Exception('Invalid menu item id');
    }

    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT image FROM menu_items WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new Exception('Menu item not found');
    }
    $image = $row['image'] ?? '';

    $del = $pdo->prepare('DELETE FROM menu_items WHERE id = ?');
    $del->execute([$id]);

    // Remove local image file if present and not a remote URL
    if ($image && stripos($image, 'http') !== 0) {
        $localPath = __DIR__ . '/..' . (strpos($image, '/') === 0 ? $image : '/' . $image);
        if (file_exists($localPath)) {
            @unlink($localPath);
        }
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
