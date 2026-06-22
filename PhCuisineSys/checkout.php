<?php
session_start();
// --- FIX 1: Connection File ---
// Tiyakin na ang 'db.php' ay nasa tamang folder
require_once 'db.php'; 
$pdo = getPDO();

// --- Role Restriction and ID Check ---

// Tiyakin na may nakalog-in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Kunin ang user ID at role mula sa session
$logged_in_user_id = $_SESSION['user_id'];
$logged_in_user_role = $_SESSION['user_role']; 
$wallet_user_id = $_SESSION['bank_unlocked_user_id'] ?? $logged_in_user_id;

// FIX 3: Role Restriction: Tanging 'customer' lang ang pwedeng mag-checkout
if ($logged_in_user_role !== 'customer') {
    // I-redirect o magpakita ng error
    echo "<script>alert('Only customers are allowed to place orders. Current role: " . htmlspecialchars($logged_in_user_role) . "'); window.location.href = 'index.php';</script>";
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    header('Content-Type: application/json');
    
    // Accept both JSON and multipart/form-data (for proof uploads)
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $isJson = stripos($contentType, 'application/json') !== false;

    $requestData = $isJson ? json_decode(file_get_contents('php://input'), true) : $_POST;

    if (!$requestData) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid request payload']);
        exit();
    }

    // Order details
    $delivery_address = trim($requestData['delivery_address'] ?? 'Address Not Specified');
    $raw_payment_method = $requestData['payment_method'] ?? 'cod';
    $payment_channel = $requestData['payment_channel'] ?? '';
    $payment_method = ($raw_payment_method === 'digital') ? 'online' : $raw_payment_method;

    // Decode items (JSON string when sent via FormData)
    $itemsPayload = $requestData['items'] ?? [];
    if (!$isJson && is_string($itemsPayload)) {
        $decoded = json_decode($itemsPayload, true);
        $itemsPayload = $decoded ?: [];
    }
    $cartItems = is_array($itemsPayload) ? $itemsPayload : [];

    // Validate payment method
    if (!in_array($payment_method, ['cod', 'wallet', 'online'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid payment method: ' . $payment_method]);
        exit();
    }


    // Kalkulahin ang Total Amount mula sa items
    $calculated_total = 0;
    if (!empty($cartItems)) {
        foreach ($cartItems as $item) {
            $price = is_numeric($item['price'] ?? 0) ? $item['price'] : 0;
            $quantity = is_numeric($item['quantity'] ?? 0) ? $item['quantity'] : 0;
            $calculated_total += $price * $quantity;
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Your cart is empty']);
        exit();
    }
    
    // Add shipping fee (₱58 per order)
    $shipping_fee = 58;
    $total_amount = $calculated_total + $shipping_fee; 
    
    if ($total_amount <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Order total must be greater than zero']);
        exit();
    }

    // Handle payment proof for digital/online payments
    $payment_ref_no = null;
    $payment_proof_path = null;
    if ($payment_method === 'online') {
        if (!isset($_FILES['payment_proof']) || $_FILES['payment_proof']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Payment proof is required for digital payments.']);
            exit();
        }

        $uploadDir = __DIR__ . '/uploads/payment_proofs';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $originalName = $_FILES['payment_proof']['name'];
        $ext = pathinfo($originalName, PATHINFO_EXTENSION);
        $safeExt = $ext ? '.' . preg_replace('/[^a-zA-Z0-9]/', '', $ext) : '';
        $proofFilename = 'proof_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . $safeExt;
        $targetPath = $uploadDir . '/' . $proofFilename;

        if (!move_uploaded_file($_FILES['payment_proof']['tmp_name'], $targetPath)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to upload payment proof.']);
            exit();
        }

        $payment_proof_path = 'uploads/payment_proofs/' . $proofFilename; // relative path for web display
        $payment_ref_no = ($payment_channel ?: 'digital') . '|' . $payment_proof_path;
    }

    try {
        // Simulan ang transaction para siguradong sabay-sabay mag-insert ng data
        $pdo->beginTransaction(); 

        // If wallet payment, validate and deduct balance first
        if ($payment_method === 'wallet') {
            // Lock the wallet row to prevent race conditions
            $sql_wallet = "SELECT id, balance FROM wallet WHERE user_id = ? FOR UPDATE";
            $stmt_wallet = $pdo->prepare($sql_wallet);
            $stmt_wallet->execute([$wallet_user_id]);
            $wallet = $stmt_wallet->fetch(PDO::FETCH_ASSOC);

            if (!$wallet) {
                $createWallet = $pdo->prepare('INSERT INTO wallet (user_id, balance) VALUES (?, 0.00)');
                $createWallet->execute([$wallet_user_id]);
                $wallet = [
                    'id' => $pdo->lastInsertId(),
                    'balance' => 0.00
                ];
            }

            $current_balance = $wallet['balance'];
            if ($current_balance < $total_amount) {
                throw new Exception('Insufficient wallet balance. Current balance: ₱' . number_format($current_balance, 2));
            }

            // Deduct from wallet
            $new_balance = $current_balance - $total_amount;
            $sql_update_wallet = "UPDATE wallet SET balance = ?, updated_at = NOW() WHERE id = ?";
            $stmt_update_wallet = $pdo->prepare($sql_update_wallet);
            $stmt_update_wallet->execute([$new_balance, $wallet['id']]);

            // Record transaction (we'll add order_id as reference after creating the order)
        }

        // 1. INSERT INTO orders TABLE
        // FIX 2: Gumagamit na ng $logged_in_user_id
        // Gumawa ng random order number (optional, mas maganda kung dynamic ito)
        $order_number = 'ORD-' . strtoupper(bin2hex(random_bytes(4))); 
        // Digital (online) payments start as pending_payment until verified
        $order_status = ($payment_method === 'online') ? 'pending_payment' : 'pending';
        
        $sql_order = "INSERT INTO orders (customer_id, order_number, status, total_amount, shipping_fee, delivery_address, payment_method, payment_ref_no, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt_order = $pdo->prepare($sql_order);
        $stmt_order->execute([$logged_in_user_id, $order_number, $order_status, $total_amount, $shipping_fee, $delivery_address, $payment_method, $payment_ref_no]);

        // Kunin ang ID ng bagong gawang order
        $order_id = $pdo->lastInsertId();

        // 2. INSERT INTO order_items TABLE
        $sql_item = "INSERT INTO order_items (order_id, menu_item_id, quantity, price) VALUES (?, ?, ?, ?)";
        $stmt_item = $pdo->prepare($sql_item);

        foreach ($cartItems as $item) {
            $menu_item_id = $item['menu_item_id'] ?? null;
            $quantity = $item['quantity'] ?? 0;
            $price = $item['price'] ?? 0;
            
            if ($menu_item_id && $quantity > 0) {
                $stmt_item->execute([$order_id, $menu_item_id, $quantity, $price]);
            }
        }

        // Create a delivery record for this order (rider will track it later)
        // Amount will be calculated after rider accepts - for now just create the record
        $sql_delivery = "INSERT INTO deliveries (order_id, rider_id, status, amount, delivered_at) VALUES (?, NULL, 'pending', 0, NULL)";
        $stmt_delivery = $pdo->prepare($sql_delivery);
        $stmt_delivery->execute([$order_id]);

        // If wallet payment, record the transaction
        if ($payment_method === 'wallet') {
            $sql_transaction = "INSERT INTO wallet_transactions (user_id, transaction_type, amount, balance_before, balance_after, reference_type, reference_id, description, created_at) VALUES (?, 'payment', ?, ?, ?, 'order', ?, ?, NOW())";
            $stmt_transaction = $pdo->prepare($sql_transaction);
            $description = "Payment for Order #$order_number";
            $stmt_transaction->execute([
                $wallet_user_id,
                $total_amount,
                $current_balance,
                $new_balance,
                $order_id,
                $description
            ]);
        }

        // I-commit ang transaction
        $pdo->commit();

        // Return JSON success response
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Order placed successfully',
            'order_id' => $order_id,
            'order_number' => $order_number
        ]);
        exit();

    } catch (Exception $e) {
        // Kung may error, i-rollback ang transaction
        $pdo->rollBack();
        error_log("Order failed for user ID $logged_in_user_id: " . $e->getMessage()); 
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Order placement failed: ' . $e->getMessage()
        ]);
        exit();
    }

} else {
    // Kung hindi POST request, return error
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}
?>