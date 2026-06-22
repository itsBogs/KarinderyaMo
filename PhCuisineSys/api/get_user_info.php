<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

// Check if user is a customer
if ($_SESSION['user_role'] !== 'customer') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only customers can access cart']);
    exit();
}

// Return user information
echo json_encode([
    'success' => true,
    'user_id' => $_SESSION['user_id'],
    'user_name' => $_SESSION['user_name'] ?? '',
    'user_phone' => $_SESSION['user_phone'] ?? '',
    'user_email' => $_SESSION['user_email'] ?? '',
    'user_address' => $_SESSION['user_address'] ?? ''
]);
?>
