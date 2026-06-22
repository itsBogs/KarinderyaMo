<?php
// orders.php - Display all orders from database with admin actions
require_once __DIR__ . '/db.php';

session_start();

// Handle admin actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $order_id = $_POST['order_id'] ?? null;
    $success_message = '';
    
    try {
        $pdo = getPDO();
        
        if ($action === 'approve' && $order_id) {
            // This handles both COD 'pending' and online 'pending_payment'
            $stmt = $pdo->prepare('UPDATE orders SET status = "approved" WHERE id = ? AND (status = "pending" OR status = "pending_payment")');
            $stmt->execute([$order_id]);
            $success_message = 'Order approved! It is now ready for rider assignment in the Delivery section.';
        } elseif ($action === 'reject' && $order_id) {
            $stmt = $pdo->prepare('UPDATE orders SET status = "rejected" WHERE id = ?');
            $stmt->execute([$order_id]);
            $success_message = 'Order rejected successfully.';
        }

        // AJAX response for success
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode(['success' => true, 'message' => $success_message]);
            exit;
        }

    } catch (Exception $e) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
        // For non-AJAX, the error will just prevent the success message from showing
    }
}

try {
    $pdo = getPDO();
    $stmt = $pdo->query('
        SELECT o.id, o.order_number, u.name as customer_name, o.total_amount, o.status, o.created_at, o.delivery_address, o.payment_method, o.payment_ref_no
        FROM orders o 
        JOIN users u ON o.customer_id = u.id 
        ORDER BY FIELD(o.status, "pending_payment", "pending"), o.created_at DESC
    ');
    $orders = $stmt->fetchAll();
} catch (Exception $e) {
    $orders = [];
}
?>

<div class="card p-3">
  <h3>Orders Management</h3>
  <div class="small mb-2">View and manage all customer orders. Accept or Reject orders.</div>

  <?php if (!empty($success_message)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <?php echo htmlspecialchars($success_message); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <div class="table-responsive">
    <table class="table table-striped table-bordered align-middle">
      <thead class="table-light">
        <tr>
          <th>Order ID</th>
          <th>Customer</th>
          <th>Details</th>
          <th>Status</th>
          <th>Date</th>
          <th style="text-align:right">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($orders) > 0): ?>
          <?php foreach ($orders as $order): ?>
            <tr id="order-row-<?= $order['id'] ?>">
              <?php
                $paymentChannel = '';
                $paymentProof = '';
                if (!empty($order['payment_ref_no'])) {
                    $parts = explode('|', $order['payment_ref_no'], 2);
                    if (count($parts) === 2) {
                        $paymentChannel = $parts[0];
                        $paymentProof = $parts[1];
                    } else {
                        $paymentProof = $order['payment_ref_no'];
                    }
                }
              ?>
              <td>
                <strong>#<?=htmlspecialchars($order['order_number'])?></strong>
                <div class="small text-muted"><?= htmlspecialchars($order['customer_name']) ?></div>
              </td>
              <td>
                <div class="fw-bold">₱<?=number_format($order['total_amount'], 2)?></div>
                <div class="small">
                  <?php if($order['payment_method'] === 'online' || $order['payment_method'] === 'wallet'): ?>
                    <span class="badge bg-primary">Online Payment</span>
                  <?php else: ?>
                    <span class="badge bg-secondary">COD</span>
                  <?php endif; ?>
                </div>
              </td>
              <td>
                <?php if($order['payment_method'] === 'online'): ?>
                  <?php if($paymentChannel): ?>
                    <div class="small">Channel: <strong><?= htmlspecialchars(strtoupper($paymentChannel)) ?></strong></div>
                  <?php endif; ?>
                  <?php if($paymentProof): ?>
                    <div class="small"><a href="<?= htmlspecialchars($paymentProof) ?>" target="_blank" rel="noopener">View payment proof</a></div>
                  <?php endif; ?>
                <?php elseif($order['payment_method'] === 'wallet' && $order['payment_ref_no']): ?>
                  <div class="small">Ref #: <strong><?= htmlspecialchars($order['payment_ref_no']) ?></strong></div>
                <?php endif; ?>
                <div class="small text-muted" title="<?= htmlspecialchars($order['delivery_address']) ?>">
                    <i class="bi bi-geo-alt-fill"></i> <?= substr(htmlspecialchars($order['delivery_address']), 0, 30) ?>...
                </div>
              </td>
              <td>
                <span class="badge bg-<?php 
                  if ($order['status'] === 'approved') echo 'success';
                  elseif ($order['status'] === 'pending') echo 'warning';
                  elseif ($order['status'] === 'pending_payment') echo 'info';
                  elseif ($order['status'] === 'rejected') echo 'danger';
                  elseif ($order['status'] === 'delivered') echo 'dark';
                  else echo 'secondary';
                ?>">
                  <?=ucfirst(str_replace('_', ' ', $order['status']))?>
                </span>
              </td>
              <td><?=date('M d, Y H:i', strtotime($order['created_at']))?></td>
              <td style="text-align:right">
                <?php if ($order['status'] === 'pending'): ?>
                  <button class="btn btn-sm btn-success" onclick="handleOrderAction(<?=$order['id']?>, 'approve')"><i class="bi bi-check-circle"></i> Approve</button>
                  <button class="btn btn-sm btn-danger" onclick="handleOrderAction(<?=$order['id']?>, 'reject')"><i class="bi bi-x-circle"></i> Reject</button>
                <?php elseif ($order['status'] === 'pending_payment'): ?>
                    <button class="btn btn-sm btn-primary" onclick="handleOrderAction(<?=$order['id']?>, 'approve')"><i class="bi bi-patch-check"></i> Confirm Payment</button>
                    <button class="btn btn-sm btn-danger" onclick="handleOrderAction(<?=$order['id']?>, 'reject')"><i class="bi bi-x-circle"></i> Reject</button>
                <?php elseif ($order['status'] === 'approved'): ?>
                  <button class="btn btn-sm btn-info" onclick="loadPage('delivery.php')"><i class="bi bi-truck"></i> Assign Rider</button>
                <?php else: ?>
                  <span class="badge bg-light text-dark"><?=ucfirst($order['status'])?></span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="6" class="text-center text-muted">No orders yet</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
// This script runs when orders.php is loaded standalone (not via admin_dashboard)
if (typeof handleOrderAction === 'undefined') {
    function handleOrderAction(orderId, action) {
        const formData = new FormData();
        formData.append('order_id', orderId);
        formData.append('action', action);

        const row = document.getElementById('order-row-' + orderId);
        if (row) row.style.opacity = '0.5';

        fetch('orders.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert('Action failed: ' + data.message);
                if (row) row.style.opacity = '1';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An unexpected error occurred.');
            if (row) row.style.opacity = '1';
        });
    }
}
</script>

