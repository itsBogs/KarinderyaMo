<?php


require 'db.php'; 

session_start();
require_once __DIR__ . '/includes/settings.php';


if (!isset($_SESSION['user_id'])) {
    header('Location: main/login.html');
    exit;
}


$user_role = $_SESSION['user_role'] ?? 'customer'; 
if (in_array($user_role, ['admin', 'rider', 'owner'])) {
    if ($user_role === 'rider') {
        header('Location: rider_panel.php');
    } else {
        header('Location: admin_dashboard.php');
    }
    exit;
}

$pdo = getPDO();
$user_id = $_SESSION['user_id'];
$message = '';
$messageType = 'success';


$userStmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$userStmt->execute([$user_id]);
$customer = $userStmt->fetch();


if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_profile') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $home_address = trim($_POST['home_address'] ?? '');
    
    if ($name && $phone && $home_address) {
        $updateStmt = $pdo->prepare('UPDATE users SET name = ?, phone = ?, delivery_address = ? WHERE id = ?');
        $updateStmt->execute([$name, $phone, $home_address, $user_id]);
        $message = '✅ Profile updated successfully!';

        $userStmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $userStmt->execute([$user_id]);
        $customer = $userStmt->fetch();
    } else {
        $message = '❌ Name, phone, and home address are required';
        $messageType = 'error';
    }
}


if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_address') {
    $order_id = $_POST['order_id'];
    $new_address = trim($_POST['delivery_address']);
    
    if ($new_address) {
        $updateStmt = $pdo->prepare('UPDATE orders SET delivery_address = ? WHERE id = ? AND customer_id = ?');
        $updateStmt->execute([$new_address, $order_id, $user_id]);
        $message = '✅ Address updated successfully!';
    } else {
        $message = '❌ Address cannot be empty';
        $messageType = 'error';
    }
}


if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'cancel_order') {
    $order_id = $_POST['order_id'];
    

    $checkStmt = $pdo->prepare('SELECT status FROM orders WHERE id = ? AND customer_id = ?');
    $checkStmt->execute([$order_id, $user_id]);
    $order = $checkStmt->fetch();
    
    if ($order && $order['status'] === 'pending') {
        $cancelStmt = $pdo->prepare('UPDATE orders SET status = "cancelled" WHERE id = ?');
        $cancelStmt->execute([$order_id]);
        $message = '✅ Order cancelled successfully!';
    } else if ($order) {

        $statusReasons = [
            'approved' => 'The order has been approved by the restaurant and cannot be cancelled.',
            'rejected' => 'The order has been rejected by the restaurant.',
            'preparing' => 'The order is being prepared and cannot be cancelled.',
            'out_for_delivery' => 'The order is out for delivery and cannot be cancelled.',
            'delivered' => 'The order has already been delivered.',
            'cancelled' => 'The order is already cancelled.'
        ];
        $reason = $statusReasons[$order['status']] ?? 'This order cannot be cancelled.';
        $message = '❌ Cannot cancel this order. ' . $reason;
        $messageType = 'error';
    } else {
        $message = '❌ Order not found';
        $messageType = 'error';
    }
}


$ordersStmt = $pdo->prepare('
    SELECT o.*, u.name as rider_name, u.phone as rider_phone
    FROM orders o
    LEFT JOIN users u ON o.rider_id = u.id
    WHERE o.customer_id = ? 
    ORDER BY o.created_at DESC
');
$ordersStmt->execute([$user_id]);
$orders = $ordersStmt->fetchAll();


$orderDetailsMap = [];
foreach ($orders as $order) {
    $itemsStmt = $pdo->prepare('
        SELECT oi.*, mi.name, mi.image 
        FROM order_items oi 
        JOIN menu_items mi ON oi.menu_item_id = mi.id 
        WHERE oi.order_id = ?
    ');
    $itemsStmt->execute([$order['id']]);
    $orderDetailsMap[$order['id']] = $itemsStmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>My Orders — <?php echo htmlspecialchars(get_setting('site_name', 'Karinderya Mo')); ?></title>
    <link rel="stylesheet" href="css/design.css">
    <style>
        
        
        .profile-container {
            width: 100%;
            padding: 0;
        }

        
        .profile-header {
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: var(--text);
            padding: 15px 25px;
            border-radius: var(--radius);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            flex-wrap: wrap;
            gap: 15px;
        }

        .profile-info {
            display: flex;
            align-items: center;
            gap: 25px;
            flex-wrap: wrap;
        }

        .profile-info h2 {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
            background: var(--text);
            color: var(--accent);
            padding: 8px 16px;
            border-radius: 8px;
        }

        .profile-details {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            font-size: 13px;
        }

        .profile-details span {
            display: flex;
            align-items: center;
            gap: 5px;
            opacity: 0.9;
        }

        .btn-profile {
            background: var(--strong-accent);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 700;
            font-size: 13px;
            transition: all 0.2s;
            box-shadow: 0 2px 8px rgba(233, 162, 9, 0.3);
            white-space: nowrap;
        }

        .btn-profile:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(233, 162, 9, 0.4);
        }

        .orders-section h3 {
            font-size: 22px;
            margin: 0 0 20px 0;
            color: var(--text);
            font-weight: 700;
            padding-bottom: 10px;
            border-bottom: 3px solid var(--strong-accent);
            display: inline-block;
        }

        
        .orders-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 25px;
            width: 100%;
        }

        
        .order-card {
            background: var(--card);
            border: 2px solid var(--muted);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: all 0.2s ease;
            display: flex;
            flex-direction: column;
        }

        .order-card:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
            border-color: var(--strong-accent);
            transform: translateY(-4px);
        }

        
        .order-card-header {
            background: linear-gradient(135deg, var(--accent), var(--strong-accent));
            color: var(--text);
            padding: 18px 20px;
        }

        .order-card-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
        }

        .order-number-section h4 {
            margin: 0;
            font-size: 18px;
            font-weight: 800;
        }

        .order-date-time {
            font-size: 13px;
            opacity: 0.8;
            margin-top: 4px;
        }

        .order-status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 700;
            background: rgba(255,255,255,0.9);
            color: var(--text);
            white-space: nowrap;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }

        
        .order-card-body {
            padding: 20px;
            flex: 1;
        }

        .order-items-title {
            font-size: 15px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        
        .order-items {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 15px;
            max-height: 200px;
            overflow-y: auto;
        }

        .order-items::-webkit-scrollbar {
            width: 5px;
        }

        .order-items::-webkit-scrollbar-thumb {
            background: var(--muted);
            border-radius: 4px;
        }

        .order-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            background: var(--bg);
            border-radius: 10px;
            border: 1px solid var(--muted);
            transition: all 0.2s;
        }

        .order-item:hover {
            background: var(--muted);
        }

        .order-item-image {
            width: 55px;
            height: 55px;
            border-radius: 10px;
            overflow: hidden;
            flex-shrink: 0;
            background: white;
            border: 2px solid var(--muted);
        }

        .order-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .order-item-info {
            flex: 1;
            min-width: 0;
        }

        .order-item-name {
            font-size: 15px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 3px;
        }

        .order-item-qty-price {
            font-size: 13px;
            color: #666;
            font-weight: 500;
        }

        
        .order-total-price {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 18px;
            background: linear-gradient(135deg, var(--bg), var(--muted));
            border-radius: 10px;
            margin-bottom: 15px;
            border: 2px solid var(--accent);
        }

        .order-total-label {
            font-size: 15px;
            font-weight: 700;
            color: var(--text);
        }

        .order-total-amount {
            font-size: 24px;
            font-weight: 800;
            color: var(--strong-accent);
        }

        
        .delivery-info-badge {
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
            border: 2px solid #81c784;
            border-radius: 10px;
            padding: 12px 15px;
            font-size: 14px;
        }

        .delivery-info-badge strong {
            color: #2e7d32;
            display: block;
            font-size: 14px;
            margin-bottom: 3px;
        }

        .delivery-info-badge .rider-phone {
            color: #388e3c;
            font-size: 13px;
        }

        .delivery-info-badge.pending {
            background: linear-gradient(135deg, var(--bg), var(--muted));
            border-color: var(--accent);
        }

        .delivery-info-badge.pending strong {
            color: var(--strong-accent);
        }

        
        .order-card-footer {
            padding: 15px 20px;
            display: flex;
            gap: 10px;
            border-top: 2px solid var(--muted);
            background: var(--bg);
        }

        .btn-action {
            flex: 1;
            padding: 14px 16px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 700;
            text-align: center;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .btn-track {
            background: linear-gradient(135deg, var(--accent), var(--strong-accent));
            color: var(--text);
            box-shadow: 0 2px 8px rgba(233, 162, 9, 0.3);
        }

        .btn-track:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(233, 162, 9, 0.4);
        }

        .btn-edit {
            background: var(--bg);
            color: var(--text);
            border: 2px solid var(--accent);
        }

        .btn-edit:hover {
            background: var(--muted);
        }

        .btn-cancel {
            background: #ffebee;
            color: #c62828;
            border: 2px solid #ef9a9a;
        }

        .btn-cancel:hover {
            background: #ffcdd2;
        }

        .status-cancelled-msg,
        .status-rejected-msg {
            padding: 12px;
            background: #ffebee;
            border: 2px solid #ef9a9a;
            border-radius: 10px;
            color: #c62828;
            font-size: 14px;
            text-align: center;
            font-weight: 700;
            width: 100%;
        }

        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(2px);
        }

        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: var(--card);
            border-radius: var(--radius);
            max-width: 480px;
            width: 95%;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }

        .modal-header {
            background: linear-gradient(135deg, var(--accent), var(--strong-accent));
            color: var(--text);
            padding: 18px 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 18px;
            font-weight: 700;
        }

        .modal-close {
            background: none;
            border: none;
            color: var(--text);
            font-size: 26px;
            cursor: pointer;
            line-height: 1;
            opacity: 0.7;
        }

        .modal-close:hover {
            opacity: 1;
        }

        .modal-body {
            padding: 22px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            color: var(--text);
            font-weight: 700;
            font-size: 14px;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid var(--muted);
            border-radius: 8px;
            font-size: 14px;
            box-sizing: border-box;
            background: var(--bg);
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--strong-accent);
            box-shadow: 0 0 0 3px rgba(233, 162, 9, 0.15);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .modal-footer {
            padding: 18px 22px;
            background: var(--bg);
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            border-top: 2px solid var(--muted);
        }

        .btn-cancel-modal {
            background: var(--muted);
            color: var(--text);
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 700;
        }

        .btn-cancel-modal:hover {
            background: #ddd;
        }

        .btn-save {
            background: linear-gradient(135deg, var(--accent), var(--strong-accent));
            color: var(--text);
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 700;
            box-shadow: 0 2px 8px rgba(233, 162, 9, 0.3);
        }

        .btn-save:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(233, 162, 9, 0.4);
        }

        
        .status-tracker {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            position: relative;
            padding: 26px 6px;
        }

        .status-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
            position: relative;
        }

        .status-step::after {
            content: '';
            position: absolute;
            top: 28px;
            left: 50%;
            width: 100%;
            height: 6px;
            background: var(--muted);
            z-index: 0;
        }

        .status-step:last-child::after {
            display: none;
        }

        .status-step.active::after {
            background: var(--strong-accent);
        }

        .step-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--card);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            z-index: 1;
            border: 4px solid var(--muted);
            margin-bottom: 10px;
        }

        .step-icon.active {
            background: var(--strong-accent);
            border-color: var(--strong-accent);
            color: white;
        }

        .step-label {
            font-size: 14px;
            text-align: center;
            color: #888;
            max-width: 120px;
            font-weight: 600;
            line-height: 1.3;
        }

        .step-label.active {
            color: var(--strong-accent);
            font-weight: 700;
        }

        
        .message {
            padding: 15px 18px;
            margin-bottom: 20px;
            border-radius: 10px;
            font-size: 15px;
            background: linear-gradient(135deg, var(--bg), var(--muted));
            color: var(--text);
            border-left: 5px solid var(--strong-accent);
        }

        .message.error {
            background: #ffebee;
            color: #c62828;
            border-left-color: #ef5350;
        }

        .empty-state {
            text-align: center;
            padding: 60px 25px;
            color: #888;
            background: var(--card);
            border-radius: var(--radius);
            border: 2px dashed var(--muted);
        }

        .empty-state-icon {
            font-size: 60px;
            margin-bottom: 15px;
        }

        .empty-state a {
            color: var(--strong-accent);
            font-weight: 700;
        }

        
        @media (max-width: 900px) {
            .orders-grid {
                grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            }
            
            .profile-details {
                gap: 12px;
            }
        }

        @media (max-width: 768px) {
            .profile-header {
                flex-direction: column;
                text-align: center;
                gap: 12px;
                padding: 15px;
            }
            
            .profile-info {
                flex-direction: column;
                gap: 10px;
            }
            
            .profile-details {
                flex-direction: column;
                gap: 6px;
            }

            .orders-grid {
                grid-template-columns: 1fr;
            }

            .order-items {
                flex-wrap: nowrap;
            }
        }

        
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
    </style>
</head>
<body>
    <div class="app">
        <header>
            <div class="brand">
                <div class="logo">KM</div>
                <div>
                    <h1 class="site-name"><?php echo htmlspecialchars(get_setting('site_name', 'Karinderya Mo')); ?></h1>
                    <p class="lead">My Orders</p>
                </div>
            </div>
            <div class="actions">
                <a href="index.php" class="icon-btn" title="Back to Menu">🏠 Menu</a>
                <a href="api/logout.php" class="icon-btn" title="Logout">🚪 Logout</a>
            </div>
        </header>

        <main>
            <div class="profile-container">
                
                <div class="profile-header">
                    <div class="profile-info" 
                             data-name="<?php echo htmlspecialchars($customer['name']); ?>"
                             data-phone="<?php echo htmlspecialchars($customer['phone'] ?? ''); ?>"
                             data-address="<?php echo htmlspecialchars($customer['delivery_address'] ?? ''); ?>">
                        <h2><?php echo htmlspecialchars($customer['name']); ?></h2>
                        <div class="profile-details">
                            <span>✉️ <?php echo htmlspecialchars($customer['email']); ?></span>
                            <span>📱 <?php echo htmlspecialchars($customer['phone'] ?? 'N/A'); ?></span>
                            <span>🏠 <?php echo htmlspecialchars(substr($customer['delivery_address'] ?? 'N/A', 0, 40)); ?><?php echo strlen($customer['delivery_address'] ?? '') > 40 ? '...' : ''; ?></span>
                        </div>
                    </div>
                    <button type="button" class="btn-profile" onclick="showEditProfileModal()">✏️ Edit Profile</button>
                </div>

                <?php if (!empty($message)): ?>
                    <div class="message <?php echo htmlspecialchars($messageType); ?>"><?php echo $message; ?></div>
                <?php endif; ?>

                <div class="orders-section">
                    <h3>📦 Your Orders</h3>
                    
                    <?php if (empty($orders)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">🛒</div>
                            <p>No orders yet. <a href="index.php">Start ordering now!</a></p>
                        </div>
                    <?php else: ?>
                        
                        <div class="orders-grid" id="ordersGrid">
                            <?php foreach ($orders as $order): 
                                $status = $order['status'];
                                $statusText = [
                                    'pending' => 'Pending',
                                    'approved' => 'Approved',
                                    'rejected' => 'Rejected',
                                    'preparing' => 'Preparing',
                                    'out_for_delivery' => 'On the Way',
                                    'delivered' => 'Delivered',
                                    'cancelled' => 'Cancelled'
                                ][$status] ?? $status;

                                $statusIcon = [
                                    'pending' => '⏳',
                                    'approved' => '✅',
                                    'rejected' => '❌',
                                    'preparing' => '👨‍🍳',
                                    'out_for_delivery' => '🚚',
                                    'delivered' => '✅',
                                    'cancelled' => '❌'
                                ][$status] ?? '❓';
                                
                                $items = $orderDetailsMap[$order['id']];
                            ?>
                            <div class="order-card" data-order-id="<?php echo $order['id']; ?>">
                                <div class="order-card-header">
                                    <div class="order-card-top">
                                        <div class="order-number-section">
                                            <h4>#<?php echo htmlspecialchars($order['order_number']); ?></h4>
                                            <div class="order-date-time"><?php echo date('M d, Y • h:i A', strtotime($order['created_at'])); ?></div>
                                        </div>
                                        <div class="order-status-badge">
                                            <?php echo $statusIcon . ' ' . $statusText; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="order-card-body">
                                    <div class="order-items-title">📦 Items (<?php echo count($items); ?>)</div>
                                    <div class="order-items">
                                        <?php foreach ($items as $item): ?>
                                        <div class="order-item">
                                            <div class="order-item-image">
                                                <img src="<?php echo htmlspecialchars($item['image'] ?? ''); ?>" alt="" onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><rect fill=%22%23f0f0f0%22 width=%22100%22 height=%22100%22/><text x=%2250%25%22 y=%2250%25%22 font-size=%2240%22 fill=%22%23999%22 text-anchor=%22middle%22 dominant-baseline=%22middle%22>🍽️</text></svg>'">
                                            </div>
                                            <div class="order-item-info">
                                                <div class="order-item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                                <div class="order-item-qty-price">×<?php echo $item['quantity']; ?> • ₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <div class="order-total-price">
                                        <span class="order-total-label">Total:</span>
                                        <span class="order-total-amount">₱<?php echo number_format($order['total_amount'], 2); ?></span>
                                    </div>

                                    <?php if ($order['rider_id']): ?>
                                        <div class="delivery-info-badge">
                                            <strong>🚚 <?php echo htmlspecialchars($order['rider_name']); ?></strong>
                                            📞 <?php echo htmlspecialchars($order['rider_phone'] ?? 'N/A'); ?>
                                        </div>
                                    <?php elseif ($status === 'approved'): ?>
                                        <div class="delivery-info-badge pending">
                                            <strong>⏳ Finding rider...</strong>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="order-card-footer">
                                    <?php if ($status !== 'cancelled' && $status !== 'rejected'): ?>
                                        <button type="button" class="btn-action btn-track" onclick="showTrackModal('<?php echo $order['id']; ?>')">
                                            📍 Track
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($status === 'pending'): ?>
                                        <button type="button" class="btn-action btn-edit" onclick="showEditModal('<?php echo $order['id']; ?>', '<?php echo htmlspecialchars(addslashes($order['delivery_address'])); ?>')">
                                            ✏️ Edit
                                        </button>
                                        <button type="button" class="btn-action btn-cancel" onclick="showCancelModal('<?php echo $order['id']; ?>', '<?php echo htmlspecialchars($order['order_number']); ?>')">
                                            ❌ Cancel
                                        </button>
                                    <?php elseif ($status === 'cancelled'): ?>
                                        <div class="status-cancelled-msg">❌ Order Cancelled</div>
                                    <?php elseif ($status === 'rejected'): ?>
                                        <div class="status-rejected-msg">❌ Order Rejected</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>

        <footer>
            <span class="site-name"><?php echo htmlspecialchars(get_setting('site_name', 'Karinderya Mo')); ?></span> - <?php echo htmlspecialchars(get_setting('site_description', 'Lasapin ang sarap Pinoy!')); ?>
        </footer>
    </div>

    <div id="trackModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>📍 Track Your Order</h2>
                <button type="button" class="modal-close" onclick="closeTrackModal()">×</button>
            </div>
            <div class="modal-body" id="trackModalBody">
                <p style="text-align: center;">Fetching latest status...</p>
            </div>
        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>✏️ Edit Address</h2>
                <button type="button" class="modal-close" onclick="closeEditModal()">×</button>
            </div>
            <form id="editForm" method="POST">
                <input type="hidden" name="action" value="update_address">
                <input type="hidden" name="order_id" id="editOrderId" value="">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="delivery_address">New Delivery Address:</label>
                        <textarea id="delivery_address" name="delivery_address" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel-modal" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn-save">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <div id="profileModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>👤 Edit Profile</h2>
                <button type="button" class="modal-close" onclick="closeProfileModal()">×</button>
            </div>
            <form id="profileForm" method="POST">
                <input type="hidden" name="action" value="update_profile">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="profile_name">Full Name:</label>
                        <input type="text" id="profile_name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="profile_phone">Phone Number:</label>
                        <input type="tel" id="profile_phone" name="phone" required>
                    </div>
                    <div class="form-group">
                        <label for="profile_home_address">Home Address:</label>
                        <textarea id="profile_home_address" name="home_address" rows="3" placeholder="Enter your complete home address" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel-modal" onclick="closeProfileModal()">Cancel</button>
                    <button type="submit" class="btn-save">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <div id="cancelModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>⚠️ Cancel Order</h2>
                <button type="button" class="modal-close" onclick="closeCancelModal()">×</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to cancel <strong id="cancelOrderNum">Order #12345</strong>?</p>
                <p style="color: #999; font-size: 11px;">This action cannot be undone once the order is being prepared.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel-modal" onclick="closeCancelModal()">No, Keep It</button>
                <form id="cancelForm" method="POST" style="display: inline;">
                    <input type="hidden" name="order_id" id="cancelOrderId" value="">
                    <button type="submit" class="btn-cancel" style="background: #FF6B6B; color: white; padding: 6px 12px; border: none; border-radius: 5px; cursor: pointer; font-size: 11px;">Yes, Cancel Order</button>
                </form>
            </div>
        </div>
    </div>

    <script>

        let trackRefreshInterval;


        const ordersGrid = document.getElementById('ordersGrid');
        const statusTextMap = {
            'pending': 'Pending',
            'approved': 'Approved',
            'rejected': 'Rejected',
            'preparing': 'Preparing',
            'out_for_delivery': 'On the Way',
            'delivered': 'Delivered',
            'cancelled': 'Cancelled'
        };
        const statusIconMap = {
            'pending': '⏳',
            'approved': '✅',
            'rejected': '❌',
            'preparing': '👨‍🍳',
            'out_for_delivery': '🚚',
            'delivered': '✅',
            'cancelled': '❌'
        };

        function escapeHtml(str) {
            return str.replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c]));
        }

        function renderOrders(orders) {
            if (!ordersGrid) return;
            ordersGrid.innerHTML = orders.map(order => {
                const status = order.status;
                const statusText = statusTextMap[status] || status;
                const statusIcon = statusIconMap[status] || '❓';
                const items = order.items || [];
                const itemsHtml = items.map(item => `
                    <div class="order-item">
                        <div class="order-item-image">
                            <img src="${escapeHtml(item.image || '')}" alt="" onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><rect fill=%22%23f0f0f0%22 width=%22100%22 height=%22100%22/><text x=%2250%25%22 y=%2250%25%22 font-size=%2240%22 fill=%22%23999%22 text-anchor=%22middle%22 dominant-baseline=%22middle%22>🍽️</text></svg>'">
                        </div>
                        <div class="order-item-info">
                            <div class="order-item-name">${escapeHtml(item.name || '')}</div>
                            <div class="order-item-qty-price">×${item.quantity} • ₱${Number(item.price * item.quantity).toFixed(2)}</div>
                        </div>
                    </div>
                `).join('');

                const riderHtml = order.rider_id
                    ? `<div class="delivery-info-badge"><strong>🚚 ${escapeHtml(order.rider_name || '')}</strong> 📞 ${escapeHtml(order.rider_phone || 'N/A')}</div>`
                    : (status === 'approved' ? `<div class="delivery-info-badge pending"><strong>⏳ Finding rider...</strong></div>` : '');

                const actionButtons = (() => {
                    if (status === 'cancelled') return '<div class="status-cancelled-msg">❌ Order Cancelled</div>';
                    if (status === 'rejected') return '<div class="status-rejected-msg">❌ Order Rejected</div>';
                    let btns = `<button type="button" class="btn-action btn-track" onclick="showTrackModal('${order.id}')">📍 Track</button>`;
                    if (status === 'pending') {
                        btns += `<button type="button" class="btn-action btn-edit" onclick="showEditModal('${order.id}', '${escapeHtml(order.delivery_address || '')}')">✏️ Edit</button>`;
                        btns += `<button type="button" class="btn-action btn-cancel" onclick="showCancelModal('${order.id}', '${escapeHtml(order.order_number)}')">❌ Cancel</button>`;
                    }
                    return btns;
                })();

                return `
                <div class="order-card" data-order-id="${order.id}">
                    <div class="order-card-header">
                        <div class="order-card-top">
                            <div class="order-number-section">
                                <h4>#${escapeHtml(order.order_number)}</h4>
                                <div class="order-date-time">${new Date(order.created_at).toLocaleString()}</div>
                            </div>
                            <div class="order-status-badge">${statusIcon} ${statusText}</div>
                        </div>
                    </div>
                    <div class="order-card-body">
                        <div class="order-items-title">📦 Items (${items.length})</div>
                        <div class="order-items">${itemsHtml}</div>
                        <div class="order-total-price">
                            <span class="order-total-label">Total:</span>
                            <span class="order-total-amount">₱${Number(order.total_amount).toFixed(2)}</span>
                        </div>
                        ${riderHtml}
                    </div>
                    <div class="order-card-footer">${actionButtons}</div>
                </div>`;
            }).join('');
        }

        function pollOrders() {
            fetch('api/get_customer_orders.php', { credentials: 'same-origin' })
                .then(res => res.ok ? res.json() : null)
                .then(data => {
                    if (data && data.success) {
                        renderOrders(data.orders || []);
                    }
                })
                .catch(() => {});
        }


        pollOrders();
        setInterval(pollOrders, 1000);

        function getStatusIndex(status) {

            const statusMap = {
                'pending': 0, 
                'approved': 1, 
                'preparing': 1, 
                'in_progress': 1, 
                'accepted': 1, 
                'out_for_delivery': 2, 
                'on_the_way': 2, 
                'delivered': 3, 
                'cancelled': 3 
            };
            return statusMap[status] !== undefined ? statusMap[status] : -1;
        }

        function generateTracker(currentStatus, proofUrl = '') {
            const currentStepIndex = getStatusIndex(currentStatus);
            

            const isCancelled = currentStatus === 'cancelled' || currentStatus === 'rejected';
            const finalLabel = isCancelled ? 'Cancelled' : 'Delivered';
            const finalIcon = isCancelled ? '❌' : '✅';

            const statusSteps = [
                { label: 'Order Placed', icon: '📝' },
                { label: 'Preparation', icon: '👨‍🍳' },
                { label: 'Out for Delivery', icon: '🚚' },
                { label: finalLabel, icon: finalIcon }
            ];
            
            let trackerHTML = '<div class="status-tracker">';
            statusSteps.forEach((step, index) => {
                const isActive = index <= currentStepIndex ? ' active' : '';
                const isFinalCancelled = isCancelled && index === statusSteps.length - 1;
                const bgColor = isActive ? (isFinalCancelled ? '#ff6b6b' : '#FF8B54') : 'white';
                const borderColor = isActive ? (isFinalCancelled ? '#ff6b6b' : '#FF8B54') : '#ddd';
                const textColor = isActive ? 'white' : '#FF8B54';
                trackerHTML += `
                    <div class="status-step${isActive}">
                        <div class="step-icon${isActive}" style="background: ${bgColor}; color: ${textColor}; border-color: ${borderColor};">${step.icon}</div>
                        <div class="step-label" style="color: ${isActive ? (isFinalCancelled ? '#c0392b' : '#333') : '#999'}; font-weight: ${isActive ? '800' : '600'}; font-size: 14px;">${step.label}</div>
                    </div>
                `;
            });
            trackerHTML += '</div>';

            let message = 'Your order is being processed. Sit back and relax!';
            if (currentStatus === 'out_for_delivery' || currentStatus === 'on_the_way') {
                message = 'Your order is currently out for delivery and will arrive soon!';
            } else if (currentStatus === 'delivered') {
                message = 'Your order has been successfully delivered! Thank you for ordering.';
            } else if (currentStatus === 'cancelled' || currentStatus === 'rejected') {
                message = 'This order was cancelled. If this is unexpected, please contact support.';
            } else if (currentStatus === 'approved' || currentStatus === 'preparing' || currentStatus === 'accepted' || currentStatus === 'in_progress') {
                message = 'Your order has been approved and is now being prepared!';
            } else if (currentStatus === 'pending') {
                message = 'Waiting for the restaurant to approve your order.';
            }

            trackerHTML += `<p style="text-align: center; color: #222; margin-top: 16px; font-size: 15px; font-weight: 700; letter-spacing: 0.2px;">${message}</p>`;
            

            if (proofUrl) {
                trackerHTML += `
                    <div style="margin-top: 14px; text-align:center;">
                        <div style="font-weight:700; margin-bottom:6px; color:#2e7d32; font-size:14px;">Delivery Proof</div>
                        <img src="${proofUrl}" alt="Delivery Proof" style="max-width:100%; max-height:320px; width:auto; height:auto; object-fit:contain; border: 3px solid #dcedc8; border-radius: 10px; box-shadow: 0 4px 16px rgba(0,0,0,0.12); display:block; margin:0 auto;">
                    </div>
                `;
            }
            

            trackerHTML += `<p style="text-align: center; color: #555; margin-top: 10px; font-size: 12px; font-weight: 700;">Last updated: ${new Date().toLocaleTimeString()}</p>`;
            
            return trackerHTML;
        }





        function updateTrackModal(orderId) {
            const modalBody = document.getElementById('trackModalBody');
            


            if (modalBody.innerHTML.indexOf('status-tracker') === -1) {
                modalBody.innerHTML = '<p style="text-align: center; color: #666;">Fetching latest status... <span style="font-size: 18px;">🔄</span></p>';
            }



            fetch(`api/get_order_status.php?order_id=${orderId}`) 
                .then(response => {
                    if (!response.ok) {

                        return response.json().then(data => { throw new Error(data.error || 'Failed to fetch status'); });
                    }
                    return response.json();
                })
                .then(data => {
                    const latestStatus = data.status;
                    const proofUrl = data.proof_url || '';

                    modalBody.innerHTML = generateTracker(latestStatus, proofUrl);

                    document.querySelector('#trackModal h2').textContent = `📍 Track Order (Status: ${latestStatus.replace(/_/g, ' ').toUpperCase()})`;
                })
                .catch(error => {
                    console.error('Error fetching status:', error);

                    modalBody.innerHTML = `<p style="text-align: center; color: red;">❌ Failed to load status. Please ensure **api/get_order_status.php** is correctly set up.</p>`;

                    closeTrackModal(); 
                });
        }





        function showTrackModal(orderId) {
            const modal = document.getElementById('trackModal');
            

            if (trackRefreshInterval) {
                clearInterval(trackRefreshInterval);
            }
            

            updateTrackModal(orderId);
            

            trackRefreshInterval = setInterval(() => {
                updateTrackModal(orderId);
            }, 1000); 
            

            modal.classList.add('active');
        }




        function closeTrackModal() {

            if (trackRefreshInterval) {
                clearInterval(trackRefreshInterval);
                trackRefreshInterval = null;
            }
            document.getElementById('trackModal').classList.remove('active');

            document.querySelector('#trackModal h2').textContent = `📍 Track Your Order`;
        }



        document.getElementById('editForm').addEventListener('submit', function(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            const orderId = formData.get('order_id');
            const submitBtn = form.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Saving...';
            
            fetch('api/update_order_address.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast('✅ ' + data.message, 'success');

                } else {
                    showToast('❌ ' + (data.message || 'Failed to update address'), 'error');
                }
            })
            .catch(err => {
                console.error(err);
                showToast('❌ Failed to update address.', 'error');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Save';
                closeEditModal();
            });
        });

        function showEditModal(orderId, currentAddress) {
            document.getElementById('editOrderId').value = orderId;
            document.getElementById('delivery_address').value = currentAddress;
            document.getElementById('editModal').classList.add('active');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }
        


        document.getElementById('profileForm').addEventListener('submit', function(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            const submitBtn = form.querySelector('button[type="submit"]');
            

            submitBtn.disabled = true;
            submitBtn.textContent = 'Saving...';
            

            fetch('api/update_profile.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {

                    const newName = data.data.name;
                    const newPhone = data.data.phone;
                    const newAddress = data.data.address;
                    
                    const profileInfo = document.querySelector('.profile-info');
                    

                    profileInfo.dataset.name = newName;
                    profileInfo.dataset.phone = newPhone;
                    profileInfo.dataset.address = newAddress;
                    

                    profileInfo.querySelector('h2').textContent = newName;
                    const spans = profileInfo.querySelectorAll('.profile-details span');
                    if (spans[1]) spans[1].innerHTML = '📱 ' + (newPhone || 'N/A');
                    if (spans[2]) {
                        const shortAddress = newAddress.length > 40 ? newAddress.substring(0, 40) + '...' : newAddress;
                        spans[2].innerHTML = '🏠 ' + (shortAddress || 'N/A');
                    }
                    
                    closeProfileModal();
                    

                    showToast('✅ ' + data.message, 'success');
                } else {
                    showToast('❌ ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('❌ Failed to update profile. Please try again.', 'error');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Save Changes';
            });
        });

        function showEditProfileModal() {
            const profileInfo = document.querySelector('.profile-info');
            

            const nameText = profileInfo.dataset.name || '';
            const phoneText = profileInfo.dataset.phone || '';
            const addressText = profileInfo.dataset.address || '';
            
            document.getElementById('profile_name').value = nameText;
            document.getElementById('profile_phone').value = phoneText;
            document.getElementById('profile_home_address').value = addressText;
            document.getElementById('profileModal').classList.add('active');
        }

        function closeProfileModal() {
            document.getElementById('profileModal').classList.remove('active');
        }
        

        function showToast(message, type = 'info') {

            const existingToast = document.querySelector('.toast-notification');
            if (existingToast) existingToast.remove();
            
            const toast = document.createElement('div');
            toast.className = 'toast-notification';
            toast.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 12px 20px;
                border-radius: 8px;
                font-size: 13px;
                font-weight: 600;
                z-index: 9999;
                animation: slideIn 0.3s ease;
                background: ${type === 'success' ? '#4CAF50' : type === 'error' ? '#f44336' : '#2196F3'};
                color: white;
                box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            `;
            toast.textContent = message;
            document.body.appendChild(toast);
            

            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }



        function showCancelModal(orderId, orderNum) {
            document.getElementById('cancelOrderId').value = orderId;
            document.getElementById('cancelOrderNum').textContent = 'Order #' + orderNum;
            document.getElementById('cancelModal').classList.add('active');
        }

        function closeCancelModal() {
            document.getElementById('cancelModal').classList.remove('active');
        }


        document.getElementById('cancelForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            const orderId = formData.get('order_id');
            const btn = form.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Cancelling...';

            fetch('api/cancel_order.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {

                    const card = document.querySelector(`.order-card[data-order-id="${orderId}"]`);
                    if (card) {
                        const badge = card.querySelector('.order-status-badge');
                        if (badge) badge.textContent = '❌ Cancelled';
                        const footer = card.querySelector('.order-card-footer');
                        if (footer) footer.innerHTML = '<div class="status-cancelled-msg">❌ Order Cancelled</div>';
                    }
                    showToast('✅ ' + data.message, 'success');
                } else {
                    showToast('❌ ' + (data.message || 'Failed to cancel order'), 'error');
                }
            })
            .catch(err => {
                console.error(err);
                showToast('❌ Failed to cancel order.', 'error');
            })
            .finally(() => {
                btn.disabled = false;
                btn.textContent = 'Yes, Cancel Order';
                closeCancelModal();
            });
        });


        window.addEventListener('click', function(event) {
            const trackModal = document.getElementById('trackModal');
            if (event.target === trackModal) {
                closeTrackModal(); 
            }
            
            const editModal = document.getElementById('editModal');
            if (event.target === editModal) {
                closeEditModal();
            }
            
            const profileModal = document.getElementById('profileModal');
            if (event.target === profileModal) {
                closeProfileModal(); 
            }
            
            const cancelModal = document.getElementById('cancelModal');
            if (event.target === cancelModal) {
                closeCancelModal();
            }
        });
    </script>
        </script>
        <script>
        (function(){
            function applySiteMeta(meta){
                if(!meta) return;
                try{
                    const m = typeof meta === 'string' ? JSON.parse(meta) : meta;
                    if(m.site_name) document.querySelectorAll('.site-name').forEach(el=>el.textContent = m.site_name);
                    if(m.site_description) document.querySelectorAll('.site-desc').forEach(el=>el.textContent = m.site_description);
                    if(m.site_name){
                        if(document.title && document.title.indexOf('—')!==-1){
                            const left = document.title.split('—')[0].trim();
                            document.title = left + ' — ' + m.site_name;
                        } else {
                            document.title = m.site_name + ' — ' + document.title;
                        }
                    }
                }catch(e){}
            }
            applySiteMeta(localStorage.getItem('site_meta'));
            window.addEventListener('storage', (e)=>{ if(e.key==='site_meta') applySiteMeta(e.newValue); });
        })();
        </script>
</body>
</html>