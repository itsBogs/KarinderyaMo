<?php
// admin_customers_section.php - Customer profiles section for admin dashboard
require_once __DIR__ . '/db.php';

session_start();

// Allow admin and owner (read-only) to view
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin','owner'], true)) {
    echo '<div class="alert alert-danger">Unauthorized access</div>';
    exit();
}

$selected_customer = null;

try {
    $pdo = getPDO();
    
    // Get all customers
    $stmt = $pdo->query("
        SELECT id, name, email, phone, delivery_address, created_at, status
        FROM users 
        WHERE role = 'customer'
        ORDER BY created_at DESC
    ");
    $customers = $stmt->fetchAll();

    // If a customer is selected, get their details and orders
    if (isset($_GET['customer_id'])) {
        $customer_id = intval($_GET['customer_id']);
        
        $stmt = $pdo->prepare("
            SELECT id, name, email, phone, delivery_address, created_at, status
            FROM users 
            WHERE id = ? AND role = 'customer'
        ");
        $stmt->execute([$customer_id]);
        $selected_customer = $stmt->fetch();

        if ($selected_customer) {
            // Get customer orders with items
            $stmt = $pdo->prepare("
                SELECT o.id, o.order_number, o.total_amount, o.status, o.payment_method, o.delivery_address, o.created_at
                FROM orders o
                WHERE o.customer_id = ?
                ORDER BY o.created_at DESC
            ");
            $stmt->execute([$customer_id]);
            $selected_customer['orders'] = $stmt->fetchAll();

            // Get order items for each order
            foreach ($selected_customer['orders'] as &$order) {
                $stmt = $pdo->prepare("
                    SELECT oi.id, oi.quantity, oi.price, m.name, m.image
                    FROM order_items oi
                    JOIN menu_items m ON oi.menu_item_id = m.id
                    WHERE oi.order_id = ?
                ");
                $stmt->execute([$order['id']]);
                $order['items'] = $stmt->fetchAll();
            }
        }
    }
} catch (Exception $e) {
    $customers = [];
}
?>

<style>
    .customers-container { display: grid; grid-template-columns: 380px 1fr; gap: 26px; }
    .customer-list { background: white; border-radius: 14px; box-shadow: 0 4px 14px rgba(0,0,0,0.1); max-height: 700px; overflow-y: auto; }
    .customer-item { padding: 18px 20px; border-bottom: 1px solid #e5e5e5; cursor: pointer; transition: background 0.2s, transform 0.1s; font-size: 16px; }
    .customer-item:hover { background: #f8efd9; transform: translateX(3px); }
    .customer-item.active { background: #ffe7c7; border-left: 6px solid #FF8B54; padding-left: 14px; }
    .customer-item .name { font-weight: 800; color: #1f1f1f; font-size: 18px; }
    .customer-item .email { font-size: 14px; color: #4a4a4a; margin-top: 6px; }
    .customer-details { background: white; border-radius: 14px; box-shadow: 0 4px 14px rgba(0,0,0,0.1); padding: 30px; font-size: 16px; }
    .customer-details h2 { margin-bottom: 24px; color: #1f1f1f; border-bottom: 3px solid #FF8B54; padding-bottom: 14px; font-size: 26px; }
    .detail-row { margin-bottom: 18px; }
    .detail-label { font-weight: 800; color: #4a4a4a; font-size: 15px; letter-spacing: 0.2px; }
    .detail-value { color: #1f1f1f; margin-top: 6px; font-size: 18px; }
    .orders-section { margin-top: 36px; }
    .orders-section h3 { margin-bottom: 18px; color: #1f1f1f; border-bottom: 3px solid #FF8B54; padding-bottom: 12px; font-size: 22px; }
    .order-card { background: #fdf2e3; padding: 20px; border-radius: 12px; margin-bottom: 16px; border-left: 6px solid #FF8B54; font-size: 16px; }
    .order-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
    .order-number { font-weight: 900; color: #1f1f1f; font-size: 19px; }
    .order-status { display: inline-block; padding: 7px 14px; border-radius: 16px; font-size: 13px; font-weight: 800; }
    .order-status.pending { background: #fff3cd; color: #856404; }
    .order-status.approved { background: #d1ecf1; color: #0c5460; }
    .order-status.preparing { background: #e7d4f5; color: #5a3f78; }
    .order-status.out_for_delivery { background: #d4edff; color: #0a4a7a; }
    .order-status.delivered { background: #d4edda; color: #155724; }
    .order-status.rejected { background: #f8d7da; color: #721c24; }
    .order-items { margin-top: 12px; font-size: 14px; }
    .order-item-row { display: flex; gap: 14px; align-items: center; margin-bottom: 10px; }
    .order-item-img { width: 80px; height: 80px; border-radius: 10px; overflow: hidden; background: white; border: 1px solid #cfcfcf; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .order-item-img img { width: 100%; height: 100%; object-fit: cover; }
    .order-item-details { flex: 1; }
    .order-item-name { font-weight: 800; color: #1f1f1f; font-size: 17px; }
    .order-item-qty { color: #4a4a4a; font-size: 14px; margin-top: 3px; }
    .order-meta { display: flex; justify-content: space-between; align-items: center; margin-top: 12px; font-size: 13px; color: #4a4a4a; }
    .order-total { font-weight: 900; color: #FF8B54; font-size: 17px; }
    .no-details { color: #666; text-align: center; padding: 48px 24px; font-size: 16px; }
    .badge { display: inline-block; padding: 6px 12px; border-radius: 8px; font-size: 13px; font-weight: 800; }
    .badge.active { background: #d4edda; color: #155724; }
    .badge.inactive { background: #f8d7da; color: #721c24; }
    @media (max-width: 1024px) {
        .customers-container { grid-template-columns: 1fr; }
        .customer-list { max-height: 360px; }
        .customer-item { font-size: 17px; }
        .customer-details { font-size: 17px; }
    }
</style>

<div class="card p-4">
    <h2 style="margin-bottom: 20px;">👥 Customer Profiles & Orders</h2>
    
    <?php if (empty($customers)): ?>
        <div class="no-details">
            <p style="font-size: 48px; margin-bottom: 10px;">📭</p>
            <p>No customers found</p>
        </div>
    <?php else: ?>
        <div class="customers-container">
            <div class="customer-list">
                <?php foreach ($customers as $customer): ?>
                    <div class="customer-item <?= (isset($_GET['customer_id']) && $_GET['customer_id'] == $customer['id']) ? 'active' : '' ?>" 
                         onclick="reloadCustomerSection(<?= $customer['id'] ?>)">
                        <div class="name"><?= htmlspecialchars($customer['name']) ?></div>
                        <div class="email"><?= htmlspecialchars($customer['email']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="customer-details">
                <?php if ($selected_customer): ?>
                    <h2><?= htmlspecialchars($selected_customer['name']) ?></h2>
                    
                    <div class="detail-row">
                        <div class="detail-label">📧 Email</div>
                        <div class="detail-value"><?= htmlspecialchars($selected_customer['email']) ?></div>
                    </div>

                    <div class="detail-row">
                        <div class="detail-label">📱 Phone</div>
                        <div class="detail-value"><?= htmlspecialchars($selected_customer['phone'] ?? 'N/A') ?></div>
                    </div>

                    <div class="detail-row">
                        <div class="detail-label">🏠 Delivery Address</div>
                        <div class="detail-value"><?= htmlspecialchars($selected_customer['delivery_address'] ?? 'N/A') ?></div>
                    </div>

                    <div class="detail-row">
                        <div class="detail-label">📅 Joined</div>
                        <div class="detail-value"><?= date('M d, Y H:i', strtotime($selected_customer['created_at'])) ?></div>
                    </div>

                    <div class="detail-row">
                        <div class="detail-label">🔄 Status</div>
                        <div class="detail-value">
                            <span class="badge <?= ($selected_customer['status'] === 'active') ? 'active' : 'inactive' ?>">
                                <?= ucfirst($selected_customer['status']) ?>
                            </span>
                        </div>
                    </div>

                    <div class="orders-section">
                        <h3>📦 Order History (<?= count($selected_customer['orders']) ?> orders)</h3>
                        
                        <?php if (empty($selected_customer['orders'])): ?>
                            <p style="color: #999; font-size: 14px;">No orders yet</p>
                        <?php else: ?>
                            <?php foreach ($selected_customer['orders'] as $order): ?>
                                <div class="order-card">
                                    <div class="order-header">
                                        <div class="order-number">#<?= htmlspecialchars($order['order_number']) ?></div>
                                        <span class="order-status <?= strtolower(str_replace('_', ' ', $order['status'])) ?>">
                                            <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $order['status']))) ?>
                                        </span>
                                    </div>

                                    <div class="order-items">
                                        <?php foreach ($order['items'] as $item): ?>
                                            <div class="order-item-row">
                                                <div class="order-item-img">
                                                    <?php if ($item['image']): ?>
                                                        <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                                                    <?php else: ?>
                                                        <span style="font-size: 20px;">🍽️</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="order-item-details">
                                                    <div class="order-item-name"><?= htmlspecialchars($item['name']) ?></div>
                                                    <div class="order-item-qty">Qty: <?= htmlspecialchars($item['quantity']) ?> × ₱<?= number_format($item['price'], 2) ?></div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <div class="order-meta">
                                        <span><?= date('M d, Y H:i', strtotime($order['created_at'])) ?></span>
                                        <span class="order-total">Total: ₱<?= number_format($order['total_amount'], 2) ?></span>
                                    </div>

                                    <div style="font-size: 11px; color: #666; margin-top: 8px;">
                                        💳 <?= ucfirst($order['payment_method'] === 'cod' ? 'Cash on Delivery' : 'Online Payment') ?>
                                    </div>

                                    <div style="font-size: 11px; color: #666; margin-top: 4px;">
                                        📍 <?= htmlspecialchars($order['delivery_address']) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                <?php else: ?>
                    <div class="no-details">
                        <p style="font-size: 48px; margin-bottom: 10px;">👈</p>
                        <p>Select a customer to view details and order history</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

