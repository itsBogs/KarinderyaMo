<?php

require_once __DIR__ . '/db.php';

session_start();
require_once __DIR__ . '/includes/settings.php';


if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: main/login.html');
    exit();
}

$selected_customer = null;

try {
    $pdo = getPDO();
    

    $stmt = $pdo->query("
        SELECT id, name, email, phone, delivery_address, created_at, status
        FROM users 
        WHERE role = 'customer'
        ORDER BY created_at DESC
    ");
    $customers = $stmt->fetchAll();


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

            $stmt = $pdo->prepare("
                SELECT o.id, o.order_number, o.total_amount, o.status, o.payment_method, o.delivery_address, o.created_at
                FROM orders o
                WHERE o.customer_id = ?
                ORDER BY o.created_at DESC
            ");
            $stmt->execute([$customer_id]);
            $selected_customer['orders'] = $stmt->fetchAll();


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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Customer Management - <?php echo htmlspecialchars(get_setting('site_name', 'Karinderya Mo')); ?> Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/design.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #f8f9fa; }
        .admin-container { display: flex; min-height: 100vh; }
        .sidebar { width: 280px; background: linear-gradient(135deg, #FF8B54 0%, #FF6B54 100%); padding: 20px; color: white; overflow-y: auto; }
        .sidebar h2 { margin-bottom: 20px; font-size: 20px; }
        .sidebar a { display: block; padding: 12px 15px; color: white; text-decoration: none; border-radius: 6px; margin-bottom: 8px; transition: background 0.2s; }
        .sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,0.2); }
        .main-content { flex: 1; padding: 20px; }
        .header { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .header h1 { margin: 0; font-size: 24px; color: #333; }
        .logout-btn { background: #FF6B54; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; }
        .customer-list-section { display: grid; grid-template-columns: 300px 1fr; gap: 20px; }
        .customer-list { background: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); max-height: 600px; overflow-y: auto; }
        .customer-item { padding: 15px; border-bottom: 1px solid #f0f0f0; cursor: pointer; transition: background 0.2s; }
        .customer-item:hover { background: #f9f9f9; }
        .customer-item.active { background: #fff3e0; border-left: 4px solid #FF8B54; }
        .customer-item .name { font-weight: 600; color: #333; }
        .customer-item .email { font-size: 12px; color: #666; margin-top: 2px; }
        .customer-details { background: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); padding: 20px; }
        .customer-details h2 { margin-bottom: 20px; color: #333; border-bottom: 2px solid #FF8B54; padding-bottom: 10px; }
        .detail-row { margin-bottom: 15px; }
        .detail-label { font-weight: 600; color: #666; font-size: 13px; }
        .detail-value { color: #333; margin-top: 3px; }
        .orders-section { margin-top: 30px; }
        .orders-section h3 { margin-bottom: 15px; color: #333; border-bottom: 2px solid #FF8B54; padding-bottom: 8px; }
        .order-card { background: #f9f9f9; padding: 15px; border-radius: 6px; margin-bottom: 12px; border-left: 4px solid #FF8B54; }
        .order-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
        .order-number { font-weight: 700; color: #333; }
        .order-status { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .order-status.pending { background: #fff3cd; color: #856404; }
        .order-status.approved { background: #d1ecf1; color: #0c5460; }
        .order-status.preparing { background: #e7d4f5; color: #5a3f78; }
        .order-status.out_for_delivery { background: #d4edff; color: #0a4a7a; }
        .order-status.delivered { background: #d4edda; color: #155724; }
        .order-status.rejected { background: #f8d7da; color: #721c24; }
        .order-items { margin-top: 8px; font-size: 12px; }
        .order-item-row { display: flex; gap: 10px; align-items: center; margin-bottom: 6px; }
        .order-item-img { width: 40px; height: 40px; border-radius: 4px; overflow: hidden; background: white; border: 1px solid #ddd; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .order-item-img img { width: 100%; height: 100%; object-fit: cover; }
        .order-item-details { flex: 1; }
        .order-item-name { font-weight: 600; color: #333; }
        .order-item-qty { color: #666; font-size: 11px; }
        .order-meta { display: flex; justify-content: space-between; align-items: center; margin-top: 8px; font-size: 11px; color: #666; }
        .order-total { font-weight: 700; color: #FF8B54; }
        .no-details { color: #999; text-align: center; padding: 40px 20px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .badge.active { background: #d4edda; color: #155724; }
        .badge.inactive { background: #f8d7da; color: #721c24; }
        @media (max-width: 1024px) {
            .customer-list-section { grid-template-columns: 1fr; }
            .customer-list { max-height: 300px; }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="sidebar">
            <h2>🔧 Admin Panel</h2>
            <a href="admin_dashboard.php">📊 Dashboard</a>
            <a href="orders.php">📦 Orders</a>
            <a href="delivery.php">🚚 Deliveries</a>
            <a href="admin_customers.php" class="active">👥 Customers</a>
            <a href="menu.php">🍽️ Menu Items</a>
            <a href="users.php">👨‍💼 Staff</a>
            <hr style="border: none; border-top: 1px solid rgba(255,255,255,0.3); margin: 20px 0;">
            <a href="main/login.html" style="color: #ffaaaa;">🚪 Logout</a>
        </div>

        <div class="main-content">
            <div class="header">
                <h1>👥 Customer Profiles & Orders</h1>
                <button class="logout-btn" onclick="window.location.href='main/login.html'">Logout</button>
            </div>

            <?php if (empty($customers)): ?>
                <div class="customer-details">
                    <div class="no-details">
                        <p style="font-size: 48px; margin-bottom: 10px;">📭</p>
                        <p>No customers found</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="customer-list-section">
                    <div class="customer-list">
                        <?php foreach ($customers as $customer): ?>
                            <div class="customer-item <?= (isset($_GET['customer_id']) && $_GET['customer_id'] == $customer['id']) ? 'active' : '' ?>" 
                                 onclick="window.location.href='?customer_id=<?= $customer['id'] ?>'">
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
    </div>
        <script>
        (function(){
            function applySiteMeta(meta){
                if(!meta) return;
                try{
                    const m = typeof meta === 'string' ? JSON.parse(meta) : meta;
                    if(m.site_name) document.querySelectorAll('.site-name').forEach(el=>el.textContent = m.site_name);
                    if(m.site_description) document.querySelectorAll('.site-desc').forEach(el=>el.textContent = m.site_description);
                    if(m.site_name){
                        if(document.title && document.title.indexOf('-')!==-1){
                            const left = document.title.split('-')[0].trim();
                            document.title = left + ' - ' + m.site_name;
                        } else {
                            document.title = m.site_name + ' - ' + document.title;
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
