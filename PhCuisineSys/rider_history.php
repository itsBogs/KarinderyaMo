<?php
// Fragment: Rider Order History
require_once __DIR__ . '/db.php';

// Start session if not already started (Required for AJAX)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    // Return empty or error message for AJAX
    echo '<div class="alert alert-danger">Session expired. Please refresh the page.</div>';
    exit; 
}

try {
    $pdo = getPDO();
    
    // AUTO-FIX: Update any delivered records that have 0 amount
    // This ensures historical data is corrected automatically when the rider views this page
    // FIX: Use shipping_fee instead of total_amount for rider earnings
    $pdo->exec("
        UPDATE deliveries d 
        INNER JOIN orders o ON d.order_id = o.id 
        SET d.amount = o.shipping_fee 
        WHERE (d.amount = 0 OR d.amount IS NULL OR d.amount = o.total_amount) AND d.status = 'delivered'
    ");
    
    // Get delivered orders for this rider
    $stmt = $pdo->prepare('
        SELECT o.id, o.order_number, u.name as customer_name, o.total_amount, o.status, o.created_at, d.delivered_at, 
               CASE WHEN d.amount IS NULL OR d.amount = 0 THEN o.shipping_fee ELSE d.amount END as delivery_earnings
        FROM orders o
        JOIN users u ON o.customer_id = u.id
        LEFT JOIN deliveries d ON o.id = d.order_id
        WHERE o.rider_id = ? AND o.status IN ("delivered", "cancelled")
        ORDER BY o.created_at DESC
    ');
    $stmt->execute([$user_id]);
    $history_orders = $stmt->fetchAll();
    
    // Get statistics
    $statsStmt = $pdo->prepare('SELECT COUNT(*) as total FROM orders WHERE rider_id = ?');
    $statsStmt->execute([$user_id]);
    $total_orders = $statsStmt->fetch()['total'];
    
    $completedStmt = $pdo->prepare('SELECT COUNT(*) as total FROM orders WHERE rider_id = ? AND status = "delivered"');
    $completedStmt->execute([$user_id]);
    $completed_orders = $completedStmt->fetch()['total'];
    
    // Calculate earnings: use delivery.amount if set, otherwise use order.shipping_fee
    $earningsStmt = $pdo->prepare('
        SELECT SUM(CASE WHEN d.amount IS NULL OR d.amount = 0 THEN o.shipping_fee ELSE d.amount END) as total 
        FROM orders o
        LEFT JOIN deliveries d ON o.id = d.order_id
        WHERE o.rider_id = ? AND o.status = "delivered"
    ');
    $earningsStmt->execute([$user_id]);
    $total_earnings = $earningsStmt->fetch()['total'] ?? 0;
    
} catch (Exception $e) {
    // In a real application, you might log this error.
    $history_orders = [];
    $total_orders = 0;
    $completed_orders = 0;
    $total_earnings = 0;
}

$success_rate = $total_orders > 0 ? round(($completed_orders / $total_orders) * 100) : 0;
?>
<div class="container-fluid">
  <div class="mb-4">
    <h3 class="mb-0">📦 Delivery History</h3>
    <p class="text-muted">Track all your completed and cancelled deliveries</p>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-md-3">
      <div class="card p-3 shadow-sm" style="border-left: 4px solid var(--accent);">
        <small class="text-muted" style="font-weight: 600;">Total Deliveries</small>
        <h4 class="mt-2" style="color: var(--strong-accent);"><?=number_format($total_orders)?></h4> 
        <small class="text-muted">All time assignments</small>
      </div>
    </div>
    <div class="col-sm-6 col-md-3">
      <div class="card p-3 shadow-sm" style="border-left: 4px solid #4CAF50;">
        <small class="text-muted" style="font-weight: 600;">Completed</small>
        <h4 class="mt-2" style="color: #4CAF50;"><?=number_format($completed_orders)?></h4> 
        <small class="text-success"><?=$success_rate?>% success rate</small>
      </div>
    </div>
    <div class="col-sm-6 col-md-3">
      <div class="card p-3 shadow-sm" style="border-left: 4px solid #2196F3;">
        <small class="text-muted" style="font-weight: 600;">Total Earnings</small>
        <h4 class="mt-2" style="color: #2196F3;">₱<?=number_format($total_earnings, 2)?></h4>
        <small class="text-muted">From deliveries</small>
      </div>
    </div>
    <div class="col-sm-6 col-md-3">
      <div class="card p-3 shadow-sm" style="border-left: 4px solid #FF9800;">
        <small class="text-muted" style="font-weight: 600;">Avg Per Delivery</small>
        <h4 class="mt-2" style="color: #FF9800;">₱<?=number_format($completed_orders > 0 ? $total_earnings / $completed_orders : 0, 2)?></h4>
        <small class="text-muted">Income per completed delivery</small>
      </div>
    </div>
  </div>

  <?php if (count($history_orders) > 0): ?>
    <div style="display: flex; flex-direction: column; gap: 12px;">
      <?php foreach ($history_orders as $order): ?>
        <div class="card shadow-sm" style="border-left: 4px solid <?=$order['status'] === 'delivered' ? '#4CAF50' : '#FF5252'?>; border-radius: 8px;">
          <div class="card-body" style="padding: 16px;">
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 16px; align-items: center;">
              <div>
                <h6 class="mb-1" style="color: var(--strong-accent); font-weight: 700;">Order #<?=htmlspecialchars($order['order_number'])?></h6>
                <div class="text-muted small">Customer: <?=htmlspecialchars($order['customer_name'])?></div>
              </div>
              <div>
                <small class="text-muted" style="font-weight: 600;">Date Assigned</small>
                <div style="font-weight: 600; color: var(--text);">📅 <?=date('M d, Y', strtotime($order['created_at']))?></div>
                <div class="text-muted small"><?=date('h:i A', strtotime($order['created_at']))?></div>
              </div>
              <div>
                <small class="text-muted" style="font-weight: 600;">Status</small>
                <div style="display: inline-block; padding: 4px 12px; border-radius: 6px; background: <?=$order['status'] === 'delivered' ? 'rgba(76, 175, 80, 0.1)' : 'rgba(255, 82, 82, 0.1)'?>; color: <?=$order['status'] === 'delivered' ? '#4CAF50' : '#FF5252'?>; font-weight: 600; font-size: 12px; margin-top: 6px;">
                  <?=$order['status'] === 'delivered' ? '✓ Delivered' : '✗ Cancelled'?>
                </div>
              </div>
              <div style="text-align: right;">
                <small class="text-muted" style="font-weight: 600;">Your Earnings</small>
                <div style="font-size: 18px; font-weight: 800; color: var(--accent);">₱<?=number_format($order['delivery_earnings'] ?? 0, 2)?></div>
                <div class="text-muted small">Order Total: ₱<?=number_format($order['total_amount'], 2)?></div>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="alert alert-info" style="border-radius: 8px; border-left: 4px solid var(--accent);">
      <i class="bi bi-info-circle"></i> No delivery history yet. Complete your first delivery to see it here!
    </div>
  <?php endif; ?>

</div>