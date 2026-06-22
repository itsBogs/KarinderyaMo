<?php
// sales-reports.php - Sales dashboard with payment breakdown and printable report
require_once __DIR__ . '/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$totalStats = ['orders' => 0, 'revenue' => 0];
$paymentStats = [];
$statusStats = [];
$recentOrders = [];
$loadError = '';

try {
  $pdo = getPDO();

  // Aggregate totals (delivered orders only)
  $totalStats = $pdo->query("SELECT COUNT(*) as orders, COALESCE(SUM(total_amount),0) as revenue FROM orders WHERE status = 'delivered'")->fetch();

  $paymentStatsStmt = $pdo->query("SELECT payment_method, COUNT(*) as orders, COALESCE(SUM(total_amount),0) as revenue FROM orders WHERE status = 'delivered' GROUP BY payment_method");
  $paymentStats = $paymentStatsStmt->fetchAll();

  $statusStatsStmt = $pdo->query("SELECT status, COUNT(*) as orders FROM orders GROUP BY status");
  $statusStats = $statusStatsStmt->fetchAll();

  $recentStmt = $pdo->prepare("
    SELECT o.order_number,
         o.customer_id,
         o.total_amount,
         o.payment_method,
         o.created_at,
         GROUP_CONCAT(DISTINCT mi.name ORDER BY mi.name SEPARATOR ', ') AS products
    FROM orders o
    LEFT JOIN order_items oi ON oi.order_id = o.id
    LEFT JOIN menu_items mi ON mi.id = oi.menu_item_id
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT 50
  ");
  $recentStmt->execute();
  $recentOrders = $recentStmt->fetchAll();
} catch (Exception $e) {
  $loadError = 'Could not load sales data: ' . $e->getMessage();
}

function peso($v) { return '₱' . number_format((float)$v, 2); }
?>

<style>
  .metric-card { border: 1px solid #f2e8d5; border-radius: 12px; padding: 16px; background: #fffef8; box-shadow: 0 2px 6px rgba(0,0,0,0.05); }
  .metric-label { font-size: 13px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; }
  .metric-value { font-size: 22px; font-weight: 800; color: #2b2b2b; }
  .print-hidden { display: inline-block; }
  @media print {
    /* Only print the sales report content, hide dashboard chrome */
    body * { visibility: hidden; }
    #salesReport, #salesReport * { visibility: visible; }
    #salesReport { position: absolute; left: 0; top: 0; width: 100%; background: #fff; padding: 0; margin: 0; box-shadow: none; border: none; }
    .print-hidden { display: none !important; }
    .card { border: none !important; box-shadow: none !important; }
  }
</style>

<div class="card p-3" id="salesReport">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h3 class="mb-0">Sales & Reports</h3>
      <div class="small text-muted">Delivered orders breakdown by payment method with totals.</div>
    </div>
    <button class="btn btn-primary print-hidden" onclick="window.print()">
      <i class="bi bi-file-earmark-pdf"></i> Download PDF
    </button>
  </div>

  <?php if($loadError): ?>
    <div class="alert alert-danger"><?=htmlspecialchars($loadError)?></div>
  <?php endif; ?>

  <div class="row g-3 mb-3">
    <div class="col-md-4">
      <div class="metric-card">
        <div class="metric-label">Total Delivered Orders</div>
        <div class="metric-value"><?=intval($totalStats['orders'])?></div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="metric-card">
        <div class="metric-label">Total Delivered Revenue</div>
        <div class="metric-value"><?=peso($totalStats['revenue'])?></div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="metric-card">
        <div class="metric-label">Statuses (All Orders)</div>
        <div class="metric-value" style="font-size:16px; line-height:1.4;">
          <?php foreach($statusStats as $s): ?>
            <span class="badge bg-light text-dark me-1 mb-1"><?=htmlspecialchars(ucfirst(str_replace('_',' ', $s['status'])))?>: <?=intval($s['orders'])?></span>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <h5 class="mt-2">Payment Method Breakdown (Delivered)</h5>
  <div class="row g-3 mb-3">
    <?php if(empty($paymentStats)): ?>
      <div class="col-12 text-muted">No delivered orders yet.</div>
    <?php else: ?>
      <?php foreach($paymentStats as $p): ?>
        <div class="col-md-4">
          <div class="metric-card">
            <div class="metric-label"><?=strtoupper($p['payment_method'])?> Orders</div>
            <div class="metric-value" style="font-size:18px;">Orders: <?=intval($p['orders'])?></div>
            <div class="metric-value" style="font-size:18px; color:#FF8B54;">Revenue: <?=peso($p['revenue'])?></div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <h5 class="mt-3">Recent Orders (Print-Friendly)</h5>
  <div class="table-responsive">
    <table class="table table-striped table-bordered align-middle">
      <thead class="table-light">
        <tr>
          <th>Order #</th>
          <th>Products</th>
          <th>Payment</th>
          <th>Total</th>
          <th>Created</th>
        </tr>
      </thead>
      <tbody>
        <?php if(empty($recentOrders)): ?>
          <tr><td colspan="5" class="text-center text-muted">No orders yet.</td></tr>
        <?php else: ?>
          <?php foreach($recentOrders as $o): ?>
            <tr>
              <td><?=htmlspecialchars($o['order_number'])?></td>
              <td><?=htmlspecialchars($o['products'] ?: '—')?></td>
              <td><?=strtoupper(htmlspecialchars($o['payment_method']))?></td>
              <td><?=peso($o['total_amount'])?></td>
              <td><?=date('M d, Y H:i', strtotime($o['created_at']))?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
