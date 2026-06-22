<?php
// rider_deliveries.php - Deliveries list (Content Only) - Modified for Admin Panel Access
require_once __DIR__ . '/db.php'; 

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['user_role'] ?? 'customer';

// Function to send JSON response and exit
function sendJsonResponse($success, $message, $type = 'info') {
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'message' => $message, 'type' => $type]);
    exit;
}

// Redirect non-authorized users (Now allows 'rider' and 'admin')
if (!$user_id || ($user_role !== 'rider' && $user_role !== 'admin')) {
    http_response_code(401);
    sendJsonResponse(false, "Unauthorized Access.", 'danger'); 
}

// Determine if the current user is a rider or an admin
$is_rider = ($user_role === 'rider');
$rider_id = $is_rider ? $user_id : null; // Only set rider_id if the user is a rider

$message = '';
$messageType = 'info';

// Check if this is an AJAX request
$is_ajax = isset($_POST['is_ajax']) && $_POST['is_ajax'] === 'true';

// --- BEGIN: Handle status update (Only Riders or Admins can perform these actions) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $order_id = $_POST['order_id'] ?? null;
    
    // For accept/decline, we assume only an Admin can assign a rider, 
    // or a Rider can accept an order assigned to them (as per original logic).

    try {
        $pdo = getPDO();
        $success = false;
        
        if ($action === 'accept' && $order_id) {
            
            // ADMIN SCENARIO: An Admin might 'accept' by assigning a specific rider (not implemented here)
            // RIDER SCENARIO: A Rider accepts an unassigned order
            
            if ($is_rider) {
                 // Original Rider Logic: check if order is still available (approved)
                $checkStmt = $pdo->prepare('SELECT status, total_amount, shipping_fee FROM orders WHERE id = ?');
                $checkStmt->execute([$order_id]);
                $orderData = $checkStmt->fetch();
                $current_status = $orderData['status'];
                $total_amount = $orderData['total_amount'];
                $shipping_fee = $orderData['shipping_fee'] ?? 58.00; // Get shipping fee

                if ($current_status === 'approved') {
                    // 1. Update orders status to 'preparing', assign rider_id
                    $updateOrder = $pdo->prepare('UPDATE orders SET status = "preparing", rider_id = ? WHERE id = ?');
                    $updateOrder->execute([$rider_id, $order_id]);
                    
                    // 2. Update deliveries table with rider and amount (shipping_fee only)
                    $updateDelivery = $pdo->prepare('UPDATE deliveries SET status = "accepted", rider_id = ?, amount = ? WHERE order_id = ?');
                    $updateDelivery->execute([$rider_id, $shipping_fee, $order_id]);
                    
                    $message = "✅ Order #{$order_id} accepted! Status is now Preparing.";
                    $messageType = 'success';
                    $success = true;
                } else {
                     $message = "❌ Error: Order #{$order_id} status is already {$current_status} or not available.";
                     $messageType = 'warning';
                }
            } else {
                $message = "❌ Error: Only Riders can use the Accept/Decline function from this view.";
                $messageType = 'danger';
            }

        } elseif ($action === 'decline' && $order_id) {
             if ($is_rider) {
                // Original Rider Logic: 
                // 1. Update order status back to 'approved', rider_id is NULL
                $updateOrder = $pdo->prepare('UPDATE orders SET status = "approved", rider_id = NULL WHERE id = ?');
                $updateOrder->execute([$order_id]);
                
                // 2. Update deliveries status to 'declined'
                $updateDelivery = $pdo->prepare('UPDATE deliveries SET status = "declined", rider_id = NULL WHERE order_id = ?');
                $updateDelivery->execute([$order_id]);

                $message = "❌ Order #{$order_id} declined!";
                $messageType = 'danger';
                $success = true;
            } else {
                $message = "❌ Error: Only Riders can use the Accept/Decline function from this view.";
                $messageType = 'danger';
            }
            
        } elseif ($action === 'update_status' && $order_id) {
            $new_status = $_POST['new_status'] ?? '';
            
            // First, check if the order exists and get its current rider_id
            $checkOrderStmt = $pdo->prepare('SELECT id, status, rider_id FROM orders WHERE id = ?');
            $checkOrderStmt->execute([$order_id]);
            $orderInfo = $checkOrderStmt->fetch();
            
            error_log("Order check - order_id: $order_id, order_rider_id: " . ($orderInfo['rider_id'] ?? 'NULL') . ", session_user_id: $user_id");
            
            // Check authorization: Rider can only update status for orders assigned to them.
            if ($is_rider && $orderInfo && intval($orderInfo['rider_id']) !== intval($user_id)) {
                $message = "❌ Error: This order is not assigned to you. Order rider: {$orderInfo['rider_id']}, Your ID: $user_id";
                $messageType = 'danger';
                if ($is_ajax) {
                    sendJsonResponse(false, $message, $messageType);
                }
            } else {
                // Proceed with the update
                $updateOrder = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ? AND status <> 'delivered'");
                $updateOrder->execute([$new_status, $order_id]);
                
                $rowCount = $updateOrder->rowCount();
                error_log("Update rowCount: $rowCount");
                
                // If the update was successful, proceed to delivery status
                if ($rowCount > 0) { 
                    // Update deliveries status to match new_status if delivered
                    if ($new_status === 'delivered') {
                        // Get order shipping_fee to ensure delivery amount is set (in case it was 0)
                        $orderCheck = $pdo->prepare('SELECT total_amount, shipping_fee FROM orders WHERE id = ?');
                        $orderCheck->execute([$order_id]);
                        $orderData = $orderCheck->fetch();
                        $shipping_fee = $orderData['shipping_fee'] ?? 58.00;
                        
                        // Update deliveries with status, amount (if still 0), and delivery timestamp
                        // This ensures the rider gets credited even if they didn't formally "accept" first
                        $updateDelivery = $pdo->prepare('
                            UPDATE deliveries 
                            SET status = "delivered", 
                                amount = CASE WHEN amount = 0 OR amount IS NULL THEN ? ELSE amount END,
                                delivered_at = NOW()
                            WHERE order_id = ?
                        ');
                        $updateDelivery->execute([$shipping_fee, $order_id]);
                        } elseif ($new_status === 'cancelled') {
                          $updateDelivery = $pdo->prepare('
                            UPDATE deliveries 
                            SET status = "cancelled", amount = 0, delivered_at = NULL
                            WHERE order_id = ?
                          ');
                          $updateDelivery->execute([$order_id]);
                    }
                    
                    $status_messages = [
                        'preparing' => '👨‍🍳 Order marked as Preparing',
                        'out_for_delivery' => '🚚 Order marked as Out for Delivery',
                          'delivered' => '✅ Order marked as Delivered. Great job!',
                          'cancelled' => '❌ Order marked as Cancelled.'
                    ];
                    
                    $message = $status_messages[$new_status] ?? 'Status updated!';
                    $messageType = 'success';
                    $success = true;
                } else {
                    $message = "❌ Error: Order not found or status already delivered.";
                    $messageType = 'danger';
                }
            } // end else (authorized)
        }
        
        // Send JSON response for AJAX requests
        if ($is_ajax) {
            sendJsonResponse($success, $message, $messageType);
        }
        
    } catch (Exception $e) {
        error_log("Delivery action error: " . $e->getMessage());
        $message = "❌ A database error occurred: " . htmlspecialchars($e->getMessage());
        $messageType = 'danger';
        
        if ($is_ajax) {
            sendJsonResponse(false, $message, $messageType);
        }
    }
}
// --- END: Handle status update ---


// --- BEGIN: Fetch deliveries ---
$deliveries = [];
if ($user_id) {
    try {
        $pdo = getPDO();
        
        // Define the WHERE clause based on user role
        $whereClause = "WHERE o.status NOT IN ('delivered', 'cancelled')";
        $params = [];

        if ($is_rider) {
            // Rider view: only show deliveries assigned to this rider
            $whereClause .= " AND o.rider_id = ?";
            $params[] = $rider_id;
            $title = '🚚 Active Assignments';
        } else {
            // Admin view: show all active deliveries (currently 'approved' and unassigned)
            $whereClause .= " AND o.status IN ('approved', 'preparing', 'out_for_delivery')";
            $title = '📦 All Active Deliveries (Admin View)';
        }
        
        $stmt = $pdo->prepare("
            SELECT 
                o.id as order_id, d.status as delivery_status, o.status as order_status, 
                o.order_number, o.delivery_address, o.total_amount, 
                u.name as customer_name, u.phone as customer_phone,
                r.name as rider_name 
            FROM orders o
            LEFT JOIN deliveries d ON o.id = d.order_id
            JOIN users u ON o.customer_id = u.id
            LEFT JOIN users r ON o.rider_id = r.id -- Join for rider name
            {$whereClause}
            ORDER BY o.created_at DESC
        ");
        $stmt->execute($params);
        $deliveries = $stmt->fetchAll();
    } catch (Exception $e) {
        $message = "Failed to load deliveries: " . htmlspecialchars($e->getMessage());
        $messageType = 'danger';
    }
}
// --- END: Fetch deliveries ---
?>

<div class="container py-3">
  
  <?php if (!empty($message) && !$is_ajax): // Only show alert if not AJAX request ?>
      <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
          <?=htmlspecialchars($message)?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
  <?php endif; ?>

  <h4 class="mb-3"><?=$title ?? 'Deliveries'?></h4>
  
  <div id="alertPlaceholder"></div> 

  
  <?php if (!empty($deliveries)): ?>
    <div class="row g-3" id="deliveriesList">
      <?php foreach ($deliveries as $delivery): ?>
        <?php 
          $delivery_status = $delivery['delivery_status'] ?? 'N/A';
          $order_status = $delivery['order_status'];
          $rider_name = $delivery['rider_name'] ?? 'Unassigned';
          
          // Determine badge color
          $badge_color = 'secondary';
          if ($order_status === 'approved') $badge_color = 'warning';
          if ($order_status === 'preparing') $badge_color = 'info';
          if ($order_status === 'out_for_delivery') $badge_color = 'primary';
          if ($order_status === 'cancelled') $badge_color = 'danger';
          
          $status_display = ucfirst(str_replace('_', ' ', $order_status));
        ?>
        <div class="col-md-6">
          <div class="card shadow-sm border-<?= $badge_color ?> delivery-card" data-order-id="<?=$delivery['order_id']?>">
            <div class="card-body">
              <h5 class="card-title">Order #<?=$delivery['order_number']?> 
                <span class="badge bg-<?= $badge_color ?> status-badge" data-status-badge="<?=$delivery['order_id']?>"><?=$status_display?></span>
              </h5>
              <p class="card-subtitle mb-2 text-muted">
                Total: <strong>₱<?=number_format($delivery['total_amount'], 2)?></strong>
                <br>Rider: <strong><?=$rider_name?></strong>
              </p>
              
              <hr>

              <p class="mb-1"><strong>👤 Customer:</strong> <?=$delivery['customer_name']?></p>
              <p class="mb-1"><strong>📱 Phone:</strong> <?=$delivery['customer_phone']?></p>
              <p class="mb-1"><strong>📍 Address:</strong> <?=$delivery['delivery_address']?></p>

              <div class="mt-3">
                
                <?php if ($order_status === 'approved' && !$is_rider): ?>
                  <div class="alert alert-warning p-2 mb-0">Order awaiting rider assignment.</div>

                <?php elseif ($order_status === 'approved' && $is_rider): ?>
                  <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-success w-100" onclick="riderAction('accept', <?=$delivery['order_id']?>)">
                      ✓ Accept Delivery
                    </button>
                    <button class="btn btn-sm btn-danger w-100" onclick="riderAction('decline', <?=$delivery['order_id']?>)">
                      ✕ Decline
                    </button>
                  </div>
                   <div class="mt-2">
                      <label class="form-label" style="font-size:12px;">Upload Proof (photo) — you can upload even before Accept</label>
                      <input type="file" accept="image/*" class="form-control form-control-sm" id="proof_<?=$delivery['order_id']?>">
                      <button class="btn btn-sm btn-outline-secondary mt-2" onclick="uploadProof(<?=$delivery['order_id']?>)">Upload Proof</button>
                      <div class="text-muted small" id="proof_msg_<?=$delivery['order_id']?>"></div>
                    </div>
                
                <?php elseif ($order_status !== 'delivered' && $is_rider): ?>
                  <div class="d-flex gap-2 align-items-center">
                    <select id="status_<?=$delivery['order_id']?>" class="form-select form-select-sm" data-order-id="<?=$delivery['order_id']?>">
                      <option value="preparing" <?= $order_status === 'preparing' ? 'selected' : '' ?>>
                        👨‍🍳 Preparing
                      </option>
                      <option value="out_for_delivery" <?= $order_status === 'out_for_delivery' ? 'selected' : '' ?>>
                        🚚 Out for Delivery
                      </option>
                      <option value="delivered" <?= $order_status === 'delivered' ? 'selected' : '' ?>>
                        ✅ Delivered
                      </option>
                      <option value="cancelled" <?= $order_status === 'cancelled' ? 'selected' : '' ?>>
                        ❌ Cancelled
                      </option>
                    </select>
                    <button class="btn btn-sm btn-primary" onclick="updateRiderStatus(<?=$delivery['order_id']?>)" style="white-space: nowrap;">
                      Update
                    </button>
                  </div>
                  <small class="text-muted d-block mt-1">
                    💡 Select status and click Update to notify customer
                  </small>
                  <div class="mt-2">
                    <label class="form-label" style="font-size:12px;">Upload Proof (photo)</label>
                    <input type="file" accept="image/*" class="form-control form-control-sm" id="proof_<?=$delivery['order_id']?>">
                    <button class="btn btn-sm btn-outline-secondary mt-2" onclick="uploadProof(<?=$delivery['order_id']?>)">Upload Proof</button>
                    <div class="text-muted small" id="proof_msg_<?=$delivery['order_id']?>"></div>
                  </div>

                <?php elseif ($order_status !== 'delivered' && !$is_rider): ?>
                  <div class="alert alert-info p-2 mb-0">Currently **<?=$status_display?>**. Assigned to **<?=$rider_name?>**.</div>
                
                <?php else: ?>
                  <div class="alert alert-success p-2 mb-0">Order Completed</div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="card shadow-sm">
      <div class="card-body text-center text-muted py-5">
        <p class="mb-0">🔭 No active deliveries found.</p>
      </div>
    </div>
  <?php endif; ?>
  </div> </div>

<script>
// Function to show dynamic alerts
function showAlert(message, type = 'info') {
    const placeholder = document.getElementById('alertPlaceholder');
    const alertHTML = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
    placeholder.innerHTML = alertHTML;
    // Auto-remove alert after 5 seconds
    setTimeout(() => {
        const alertElement = placeholder.querySelector('.alert');
        if (alertElement) {
            alertElement.remove();
        }
    }, 5000);
}

// Map statuses to badge classes and readable labels
const statusMeta = {
  approved:  { badge: 'warning',  label: 'Approved' },
  preparing: { badge: 'info',     label: 'Preparing' },
  out_for_delivery: { badge: 'primary', label: 'Out for delivery' },
  delivered: { badge: 'success',  label: 'Delivered' },
  cancelled: { badge: 'danger',   label: 'Cancelled' },
  declined:  { badge: 'danger',   label: 'Declined' }
};

function removeDeliveryCard(orderId) {
  const card = document.querySelector(`.delivery-card[data-order-id="${orderId}"]`);
  if (card) {
    card.parentElement?.remove(); // remove column wrapper
  }
  const list = document.getElementById('deliveriesList');
  if (list && list.children.length === 0) {
    list.innerHTML = '<div class="card shadow-sm"><div class="card-body text-center text-muted py-5"><p class="mb-0">🔭 No active deliveries found.</p></div></div>';
  }
}

function applyCardStatus(orderId, status) {
  const meta = statusMeta[status] || { badge: 'secondary', label: status };
  const badge = document.querySelector(`.status-badge[data-status-badge="${orderId}"]`);
  if (badge) {
    badge.textContent = meta.label;
    badge.className = `badge bg-${meta.badge} status-badge`;
  }

  const card = document.querySelector(`.delivery-card[data-order-id="${orderId}"]`);
  if (card) {
    card.className = card.className.replace(/border-[^\s]+/g, '').trim();
    card.classList.add(`border-${meta.badge}`);
  }

  const select = document.getElementById(`status_${orderId}`);
  if (select && select.value !== status) {
    select.value = status;
  }
}

// Function to reload deliveries (now optimized for AJAX)
function loadRiderDeliveries() {
    // Note: We're keeping the name loadRiderDeliveries but it now loads for Admin/Rider
    fetch('rider_deliveries.php')
    .then(res => res.text())
    .then(html => {
        const mainContentDiv = document.getElementById('mainContent');
        if (mainContentDiv) {
             const parser = new DOMParser();
             const doc = parser.parseFromString(html, 'text/html');
             
             const contentDiv = doc.querySelector('.container.py-3');
             const scriptTag = doc.querySelector('script');

             if (contentDiv) {
                 // Update the current mainContent's inner HTML with the content
                 let newContent = contentDiv.outerHTML;
                 if (scriptTag) {
                     // Include the script tag to ensure JS functions are available
                     newContent += scriptTag.outerHTML;
                 }
                 mainContentDiv.innerHTML = newContent;
             }
        }
    })
    .catch(err => {
        console.error('Failed to load deliveries:', err);
    });
}



// Accept or Decline action (Used by RIDER)
function riderAction(action, orderId) {
  let confirmMsg = '';
  if (action === 'accept') confirmMsg = 'Accept this delivery?';
  else if (action === 'decline') confirmMsg = 'Decline this delivery?';
  
  if (confirm(confirmMsg)) {
    const formData = new FormData();
    formData.append('action', action);
    formData.append('order_id', orderId);
    formData.append('is_ajax', 'true'); // Tell PHP to return JSON message

    fetch('rider_deliveries.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json()) // Expect JSON response
    .then(data => {
        showAlert(data.message, data.type);
        if (data.success) {
        const nextStatus = action === 'accept' ? 'preparing' : 'approved';
        applyCardStatus(orderId, nextStatus);
      if (nextStatus === 'delivered' || nextStatus === 'cancelled') {
        setTimeout(() => removeDeliveryCard(orderId), 400);
      }
        }
    })
    .catch(err => {
        showAlert('Action failed: Could not connect to server or invalid response.', 'danger');
        console.error('Action failed:', err);
    });
  }
}

// Update status from dropdown (Used by RIDER)
function updateRiderStatus(orderId) {
  const selectElement = document.getElementById('status_' + orderId);
  const newStatus = selectElement.value;
  
  // Get current selected option text for confirmation
  const selectedText = selectElement.options[selectElement.selectedIndex].text;
  
    const btn = selectElement.nextElementSibling;
    if (btn) { btn.disabled = true; btn.textContent = 'Saving...'; }

    const formData = new FormData();
    formData.append('action', 'update_status');
    formData.append('order_id', orderId);
    formData.append('new_status', newStatus);
    formData.append('is_ajax', 'true'); // Tell PHP to return JSON message

    fetch('rider_deliveries.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.json()) // Expect JSON response
    .then(data => {
      showAlert(data.message, data.type);
      if (data.success) {
            applyCardStatus(orderId, newStatus);
            if (newStatus === 'delivered' || newStatus === 'cancelled') {
              // Remove from list so rider no longer sees it in current deliveries
              setTimeout(() => removeDeliveryCard(orderId), 400);
            }
      }
    })
    .catch(err => {
      showAlert('Update failed: Could not connect to server or invalid response.', 'danger');
      console.error('Update failed:', err);
    })
    .finally(() => {
      if (btn) { btn.disabled = false; btn.textContent = 'Update'; }
    });
}

  // Upload delivery proof image
  function uploadProof(orderId) {
    const fileInput = document.getElementById('proof_' + orderId);
    const msg = document.getElementById('proof_msg_' + orderId);
    if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
      showAlert('Please select an image to upload.', 'warning');
      return;
    }

    const formData = new FormData();
    formData.append('order_id', orderId);
    formData.append('proof', fileInput.files[0]);

    fetch('api/upload_delivery_proof.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        showAlert('✅ Proof uploaded.', 'success');
        if (msg) msg.textContent = 'Proof uploaded. Customers will see this in Track Order.';
      } else {
        showAlert('❌ ' + (data.message || 'Upload failed'), 'danger');
        if (msg) msg.textContent = data.message || '';
      }
    })
    .catch(err => {
      console.error(err);
      showAlert('❌ Upload failed. Please retry.', 'danger');
    });
  }
</script>