<?php

session_start();
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');


if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$user_id = $_SESSION['user_id'];
$name = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$home_address = trim($_POST['home_address'] ?? '');


if (empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Name is required']);
    exit;
}

if (empty($phone)) {
    echo json_encode(['success' => false, 'message' => 'Phone number is required']);
    exit;
}

if (empty($home_address)) {
    echo json_encode(['success' => false, 'message' => 'Home address is required']);
    exit;
}

try {
    $pdo = getPDO();
    

    $updateStmt = $pdo->prepare('UPDATE users SET name = ?, phone = ?, delivery_address = ? WHERE id = ?');
    $updateStmt->execute([$name, $phone, $home_address, $user_id]);
    

    $_SESSION['user_name'] = $name;
    $_SESSION['user_phone'] = $phone;
    $_SESSION['user_address'] = $home_address;
    
    echo json_encode([
        'success' => true, 
        'message' => 'Profile updated successfully!',
        'data' => [
            'name' => $name,
            'phone' => $phone,
            'address' => $home_address
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
