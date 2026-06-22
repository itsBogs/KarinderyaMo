<?php

require '../db.php';
session_start();

header('Content-Type: application/json');


if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'balance' => 0, 'error' => 'Not logged in']);
    exit;
}

$wallet_user_id = $_SESSION['bank_unlocked_user_id'] ?? $_SESSION['user_id'];
$pdo = getPDO();

try {
    $stmt = $pdo->prepare('SELECT balance FROM wallet WHERE user_id = ?');
    $stmt->execute([$wallet_user_id]);
    $wallet = $stmt->fetch();
    
    if (!$wallet) {

        $createStmt = $pdo->prepare('INSERT INTO wallet (user_id, balance) VALUES (?, 0.00)');
        $createStmt->execute([$wallet_user_id]);
        $balance = 0.00;
    } else {
        $balance = floatval($wallet['balance']);
    }
    
    echo json_encode([
        'success' => true,
        'balance' => $balance
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'balance' => 0,
        'error' => $e->getMessage()
    ]);
}
?>
