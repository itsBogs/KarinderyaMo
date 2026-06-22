<?php
// delivery.php - Manage deliveries and assign riders
require_once __DIR__ . '/db.php';

session_start();

// Handle rider assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    $order_id = $_POST['order_id'] ?? null;
    $rider_id = $_POST['rider_id'] ?? null;
    
    try {
        $pdo = getPDO();
        
        if (($action === 'assign' || $action === 'assign_rider') && $order_id && $rider_id) {
            // Start transaction
            $pdo->beginTransaction();
            
            // Create/update delivery record
            $stmt = $pdo->prepare('
                INSERT INTO deliveries (order_id, rider_id, status, created_at)
                VALUES (?, ?, "assigned", NOW())
                ON DUPLICATE KEY UPDATE rider_id = ?, status = "assigned"
            ');
            $stmt->execute([$order_id, $rider_id, $rider_id]);
            
            // Update order status to preparing (waiting for rider to accept)
            $updateStmt = $pdo->prepare('UPDATE orders SET status = "preparing", rider_id = ? WHERE id = ?');
            $updateStmt->execute([$rider_id, $order_id]);
            
            // Commit transaction
            $pdo->commit();
            
            echo json_encode(['success' => true, 'message' => 'Rider assigned successfully']);
            exit;
        } else {
            throw new Exception('Missing required parameters');
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

try {
    $pdo = getPDO();
    
    // Get approved orders ready for rider assignment (not yet assigned)
    $stmt = $pdo->query('
        SELECT o.id, o.order_number, u.name as customer_name, o.total_amount, o.delivery_address, 
               o.status, o.created_at, o.rider_id, r.name as rider_name
        FROM orders o 
        JOIN users u ON o.customer_id = u.id 
        LEFT JOIN users r ON o.rider_id = r.id
        WHERE o.status = "approved"
        ORDER BY o.created_at DESC
    ');
    $deliveries = $stmt->fetchAll();
    
    // Get all riders
    $riderStmt = $pdo->query('SELECT id, name FROM users WHERE role = "rider" ORDER BY name');
    $riders = $riderStmt->fetchAll();
} catch (Exception $e) {
    $deliveries = [];
    $riders = [];
}
?>

<div class="card p-3">
  <h3>Delivery Management</h3>
  <div class="small mb-2">Assign riders to approved orders and track deliveries.</div>

  <div class="table-responsive">
    <table class="table table-striped table-bordered align-middle">
      <thead class="table-light">
        <tr>
          <th>Order ID</th>
          <th>Customer Name</th>
          <th>Address</th>
          <th>Amount</th>
          <th>Assigned Rider</th>
          <th>Status</th>
          <th style="text-align:right">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($deliveries) > 0): ?>
          <?php foreach ($deliveries as $delivery): ?>
            <tr>
              <td><strong>#<?=htmlspecialchars($delivery['order_number'])?></strong></td>
              <td><?=htmlspecialchars($delivery['customer_name'])?></td>
              <td><small><?=htmlspecialchars(substr($delivery['delivery_address'], 0, 30))?>...</small></td>
              <td>₱<?=number_format($delivery['total_amount'], 2)?></td>
              <td><?=htmlspecialchars($delivery['rider_name'] ?? '—')?></td>
              <td>
                <span class="badge bg-info">
                  <?php echo !empty($delivery['rider_id']) ? 'Assigned to ' . htmlspecialchars($delivery['rider_name']) : 'Ready for Assignment'; ?>
                </span>
              </td>
              <td style="text-align:right">
                <?php if (empty($delivery['rider_id'])): ?>
                  <select id="rider_<?=$delivery['id']?>" class="form-select form-select-sm" style="display:inline-block; width:auto;">
                    <option value="">Select Rider...</option>
                    <?php foreach ($riders as $rider): ?>
                      <option value="<?=$rider['id']?>"><?=htmlspecialchars($rider['name'])?></option>
                    <?php endforeach; ?>
                  </select>
                  <button class="btn btn-sm btn-primary" onclick="assignRider(<?=$delivery['id']?>, this)">Assign</button>
                <?php else: ?>
                  <button class="btn btn-sm btn-secondary" disabled>✓ Assigned</button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="7" class="text-center text-muted">No approved orders to assign</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
// This script is for when delivery.php is accessed directly (not via admin dashboard)
// When loaded via AJAX in admin_dashboard.php, the global assignRider function there is used instead
if (typeof assignRider === 'undefined') {
    function assignRider(orderId, buttonElement) {
        const select = document.getElementById('rider_' + orderId);
        const riderId = select ? select.value : null;
        
        if (!riderId) {
            alert('Please select a rider');
            return;
        }
        
        if (buttonElement) {
            buttonElement.disabled = true;
            buttonElement.textContent = '⏳ Assigning...';
        }
        
        const formData = new FormData();
        formData.append('action', 'assign');
        formData.append('order_id', orderId);
        formData.append('rider_id', riderId);
        
        fetch('delivery.php', { method: 'POST', body: formData })
            .then(res => {
                if (!res.ok) throw new Error('Network response was not ok');
                return res.json();
            })
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    throw new Error(data.message || 'Failed to assign rider');
                }
            })
            .catch(err => {
                alert('Error: ' + err.message);
                if (buttonElement) {
                    buttonElement.disabled = false;
                    buttonElement.textContent = 'Assign';
                }
            });
    }
}
</script>