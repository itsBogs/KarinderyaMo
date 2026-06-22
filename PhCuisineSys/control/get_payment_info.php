<?php



header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

try {
    $pdo = getPDO();
    
    $keys = ['gcash_name', 'gcash_number', 'bank_name', 'bank_account_name', 'bank_account_number'];
    $in_clause = implode(',', array_fill(0, count($keys), '?'));
    
    $stmt = $pdo->prepare("SELECT key_name, value FROM settings WHERE key_name IN ($in_clause)");
    $stmt->execute($keys);
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);


    $response = [];
    foreach ($keys as $key) {
        $response[$key] = $settings[$key] ?? '';
    }

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to retrieve payment information.']);
}
