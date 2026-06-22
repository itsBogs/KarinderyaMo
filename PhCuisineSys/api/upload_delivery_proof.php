<?php

require_once '../db.php';
session_start();

header('Content-Type: application/json');


if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$is_rider = ($user_role === 'rider');
$is_admin = ($user_role === 'admin' || $user_role === 'owner');

$order_id = $_POST['order_id'] ?? null;
if (!$order_id || !is_numeric($order_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit;
}

if (!isset($_FILES['proof'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    exit;
}

try {
    $pdo = getPDO();


    $orderStmt = $pdo->prepare('SELECT rider_id, status FROM orders WHERE id = ?');
    $orderStmt->execute([$order_id]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception('Order not found');
    }

    if ($is_rider && intval($order['rider_id']) !== intval($user_id)) {
        throw new Exception('Order is not assigned to you');
    }




    $file = $_FILES['proof'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Upload failed with error code ' . $file['error']);
    }


    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    $allowedMimes = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
    if (!isset($allowedMimes[$mime])) {
        throw new Exception('Only JPG and PNG files are allowed');
    }

    $ext = $allowedMimes[$mime];


    $destDir = realpath(__DIR__ . '/../uploads/delivery_proofs');
    if (!$destDir) {
        $destDir = __DIR__ . '/../uploads/delivery_proofs';
        if (!is_dir($destDir)) {
            mkdir($destDir, 0775, true);
        }
    }


    $filename = 'order_' . intval($order_id) . '_' . time() . '.' . $ext;
    $destPath = rtrim($destDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        throw new Exception('Failed to save uploaded file');
    }


    $publicUrl = 'uploads/delivery_proofs/' . $filename;

    echo json_encode(['success' => true, 'message' => 'Proof uploaded', 'proof_url' => $publicUrl]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
