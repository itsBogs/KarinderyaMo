<?php
// api/get_riders.php - Get all available riders
require_once __DIR__ . '/../db.php';

try {
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT id, name, phone FROM users WHERE role = "rider" ORDER BY name ASC');
    $stmt->execute();
    $riders = $stmt->fetchAll();
    
    header('Content-Type: application/json');
    echo json_encode($riders);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([]);
}
?>
