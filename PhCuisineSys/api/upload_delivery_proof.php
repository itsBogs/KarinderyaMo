<?php
// api/upload_delivery_proof.php - Rider uploads proof of delivery photo
require_once '../db.php';
session_start();

header('Content-Type: application/json');

// Require login and rider/admin role
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

    // Verify order belongs to rider when rider role
    $orderStmt = $pdo->prepare('SELECT rider_id, status FROM orders WHERE id = ?');
    $orderStmt->execute([$order_id]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception('Order not found');
    }

    if ($is_rider && intval($order['rider_id']) !== intval($user_id)) {
        throw new Exception('Order is not assigned to you');
    }

    // Allow proof upload regardless of order status (rider may need to send photo anytime)
    // (Order ownership/assignment already checked above)

    $file = $_FILES['proof'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Upload failed with error code ' . $file['error']);
    }

    // Validate mime type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    $allowedMimes = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
    if (!isset($allowedMimes[$mime])) {
        throw new Exception('Only JPG and PNG files are allowed');
    }

    $ext = $allowedMimes[$mime];

    // Ensure destination directory exists
    $destDir = realpath(__DIR__ . '/../uploads/delivery_proofs');
    if (!$destDir) {
        $destDir = __DIR__ . '/../uploads/delivery_proofs';
        if (!is_dir($destDir)) {
            mkdir($destDir, 0775, true);
        }
    }

    // Use timestamp to avoid overwriting previous proofs and keep history
    $filename = 'order_' . intval($order_id) . '_' . time() . '.' . $ext;
    $destPath = rtrim($destDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        throw new Exception('Failed to save uploaded file');
    }

    // Return public URL
    $publicUrl = 'uploads/delivery_proofs/' . $filename;

    echo json_encode(['success' => true, 'message' => 'Proof uploaded', 'proof_url' => $publicUrl]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
