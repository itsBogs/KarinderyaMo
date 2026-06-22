<?php

require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
$role = $_SESSION['user_role'] ?? '';

if (!$user_id || $role !== 'rider') {
    echo '<div class="alert alert-danger">Unauthorized Access</div>';
    exit;
}

$message = '';
$message_type = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
  $newName = trim($_POST['name'] ?? '');
  $newEmail = trim($_POST['email'] ?? '');
  $newPhone = trim($_POST['phone'] ?? '');
  $newPassword = $_POST['password'] ?? '';

  if ($newName === '' || $newEmail === '') {
    $message = 'Name and email are required.';
    $message_type = 'danger';
  } else {
    try {
      $pdo = getPDO();

      $check = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
      $check->execute([$newEmail, $user_id]);
      if ($check->fetch()) {
        $message = 'Email is already used by another account.';
        $message_type = 'danger';
      } else {
        if ($newPassword !== '') {
          $upd = $pdo->prepare('UPDATE users SET name = ?, email = ?, phone = ?, password = ? WHERE id = ?');
          $upd->execute([$newName, $newEmail, $newPhone, $newPassword, $user_id]);
        } else {
          $upd = $pdo->prepare('UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?');
          $upd->execute([$newName, $newEmail, $newPhone, $user_id]);
        }
        $_SESSION['user_name'] = $newName;
        $_SESSION['user_email'] = $newEmail;
        $_SESSION['user_phone'] = $newPhone;
        $message = 'Profile updated successfully.';
        $message_type = 'success';
      }
    } catch (Exception $e) {
      $message = 'Update failed: ' . $e->getMessage();
      $message_type = 'danger';
    }
  }
}

try {
    $pdo = getPDO();
    

    $stmt = $pdo->prepare('SELECT id, name, email, phone, created_at FROM users WHERE id = ? AND role = "rider"');
    $stmt->execute([$user_id]);
    $rider = $stmt->fetch();
    
    if (!$rider) {
        echo '<div class="alert alert-warning">Rider profile not found</div>';
        exit;
    }
    

    $statsStmt = $pdo->prepare('SELECT COUNT(*) as total FROM orders WHERE rider_id = ?');
    $statsStmt->execute([$user_id]);
    $total_deliveries = $statsStmt->fetch()['total'];
    
    $completedStmt = $pdo->prepare('SELECT COUNT(*) as total FROM orders WHERE rider_id = ? AND status = "delivered"');
    $completedStmt->execute([$user_id]);
    $completed = $completedStmt->fetch()['total'];
    
    $earningsStmt = $pdo->prepare('SELECT SUM(amount) as total FROM deliveries WHERE rider_id = ? AND status = "delivered"');
    $earningsStmt->execute([$user_id]);
    $total_earnings = $earningsStmt->fetch()['total'] ?? 0;
    
} catch (Exception $e) {
    $rider = null;
    $total_deliveries = 0;
    $completed = 0;
    $total_earnings = 0;
}
?>
<div class="container-fluid">
  <div class="mb-4">
    <h3 class="mb-0">👤 Rider Profile</h3>
    <p class="text-muted">Manage your profile information</p>
  </div>

  <?php if ($message): ?>
    <div class="alert alert-<?=$message_type?>"><?=htmlspecialchars($message)?></div>
  <?php endif; ?>

  <?php if ($rider): ?>
    <div class="row g-3">
      
      <div class="col-md-6">
        <div class="card shadow-sm" style="border-radius: 12px; overflow: hidden;">
          <div style="background: linear-gradient(135deg, var(--accent), var(--strong-accent)); padding: 24px; color: white; text-align: center;">
            <div style="font-size: 56px; margin-bottom: 12px;">👤</div>
            <h3 style="margin: 0 0 4px 0; font-weight: 800;"><?=htmlspecialchars($rider['name'])?></h3>
            <p style="margin: 0; opacity: 0.9;">Rider Member</p>
          </div>
          <div style="padding: 24px;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
              <div>
                <small style="color: var(--text); font-weight: 600; opacity: 0.7;">EMAIL</small>
                <div style="font-weight: 600; margin-top: 4px;"><?=htmlspecialchars($rider['email'])?></div>
              </div>
              <div>
                <small style="color: var(--text); font-weight: 600; opacity: 0.7;">PHONE</small>
                <div style="font-weight: 600; margin-top: 4px;"><?=htmlspecialchars($rider['phone'] ?? 'Not set')?></div>
              </div>
              <div style="grid-column: 1 / -1;">
                <small style="color: var(--text); font-weight: 600; opacity: 0.7;">MEMBER SINCE</small>
                <div style="font-weight: 600; margin-top: 4px;"><?=date('F d, Y', strtotime($rider['created_at']))?></div>
              </div>
            </div>
            <form method="POST">
              <input type="hidden" name="action" value="update_profile">
              <div class="mb-3 text-start">
                <label class="form-label small fw-bold">Full Name</label>
                <input type="text" class="form-control" name="name" value="<?=htmlspecialchars($rider['name'])?>" required>
              </div>
              <div class="mb-3 text-start">
                <label class="form-label small fw-bold">Email</label>
                <input type="email" class="form-control" name="email" value="<?=htmlspecialchars($rider['email'])?>" required>
              </div>
              <div class="mb-3 text-start">
                <label class="form-label small fw-bold">Phone</label>
                <input type="text" class="form-control" name="phone" value="<?=htmlspecialchars($rider['phone'] ?? '')?>">
              </div>
              <div class="mb-3 text-start">
                <label class="form-label small fw-bold">New Password (optional)</label>
                <input type="password" class="form-control" name="password" placeholder="Leave blank to keep current">
              </div>
              <button class="btn w-100" style="background: linear-gradient(90deg, var(--accent), var(--strong-accent)); color: white; border: none; font-weight: 600; padding: 10px; border-radius: 8px; cursor: pointer;">Save Changes</button>
            </form>
          </div>
        </div>
      </div>

      
      <div class="col-md-6">
        <div style="display: flex; flex-direction: column; gap: 12px;">
          <div class="card shadow-sm p-3" style="border-left: 4px solid var(--accent); border-radius: 8px;">
            <small style="color: var(--text); font-weight: 600; opacity: 0.7;">📦 TOTAL DELIVERIES</small>
            <div style="font-size: 32px; font-weight: 800; color: var(--strong-accent); margin-top: 8px;"><?=$total_deliveries?></div>
            <div class="text-muted small" style="margin-top: 4px;">All time deliveries</div>
          </div>

          <div class="card shadow-sm p-3" style="border-left: 4px solid #4CAF50; border-radius: 8px;">
            <small style="color: var(--text); font-weight: 600; opacity: 0.7;">✓ COMPLETED</small>
            <div style="font-size: 32px; font-weight: 800; color: #4CAF50; margin-top: 8px;"><?=$completed?></div>
            <div class="text-muted small" style="margin-top: 4px;">Success rate: <?=$total_deliveries > 0 ? round(($completed / $total_deliveries) * 100) : 0?>%</div>
          </div>

          <div class="card shadow-sm p-3" style="border-left: 4px solid #2196F3; border-radius: 8px;">
            <small style="color: var(--text); font-weight: 600; opacity: 0.7;">💰 TOTAL EARNINGS</small>
            <div style="font-size: 32px; font-weight: 800; color: #2196F3; margin-top: 8px;">₱<?=number_format($total_earnings, 2)?></div>
            <div class="text-muted small" style="margin-top: 4px;">Lifetime earnings</div>
          </div>
        </div>
      </div>
    </div>
  <?php else: ?>
    <div class="alert alert-danger">Unable to load rider profile</div>
  <?php endif; ?>

</div>
