<?php
// api/get_order_status.php - Minimal API endpoint for real-time status
// Tiyakin na TAMA ang path ng db.php
require_once '../db.php'; 
session_start();

header('Content-Type: application/json');

// Security Check: Dapat naka-login
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
// Kunin ang order_id mula sa URL query string
$order_id = $_GET['order_id'] ?? null;

if (!$order_id || !is_numeric($order_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid Order ID']);
    exit;
}

try {
    $pdo = getPDO();
    // Kumuha lang ng status para sa order ID at customer ID
    $stmt = $pdo->prepare('SELECT status FROM orders WHERE id = ? AND customer_id = ?');
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($order) {
        // Check if a delivery proof image exists
        $proofDir = realpath(__DIR__ . '/../uploads/delivery_proofs');
        $proofUrl = '';
        if ($proofDir && is_dir($proofDir)) {
            // Our uploads are saved as order_{id}_{timestamp}.ext — match any timestamped proof
            $patterns = [
                $proofDir . '/order_' . $order_id . '_*',
                $proofDir . '/order_' . $order_id . '.*'
            ];
            foreach ($patterns as $pattern) {
                $matches = glob($pattern);
                if (!empty($matches)) {
                    // Choose the most recently modified file (newest proof)
                    $newest = null;
                    $newestMtime = 0;
                    foreach ($matches as $m) {
                        $mtime = filemtime($m);
                        if ($mtime > $newestMtime) {
                            $newestMtime = $mtime;
                            $newest = $m;
                        }
                    }
                    if ($newest) {
                        $basename = basename($newest);
                        $proofUrl = 'uploads/delivery_proofs/' . $basename;
                        break;
                    }
                }
            }
        }

        // Ibalik ang status at proof URL sa JSON format
        echo json_encode(['status' => $order['status'], 'proof_url' => $proofUrl]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Order not found or does not belong to user']);
    }

} catch (Exception $e) {
    http_response_code(500);
    error_log("Status API Error: " . $e->getMessage());
    echo json_encode(['error' => 'Server Error']);
}
?>