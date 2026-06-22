<?php

require_once __DIR__ . '/db.php';
session_start();

$errors = [];
$success = '';
$roles = ['rider','customer'];


if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_status') {
  $userId = intval($_POST['user_id'] ?? 0);
  $targetStatus = $_POST['target_status'] === 'inactive' ? 'inactive' : 'active';

  if ($userId <= 0) {
    $errors[] = 'Invalid user selected.';
  } else {
    try {
      $pdo = getPDO();
      $stmt = $pdo->prepare('UPDATE users SET status = ? WHERE id = ?');
      $stmt->execute([$targetStatus, $userId]);
      $success = $targetStatus === 'inactive' ? 'User deactivated.' : 'User activated.';
    } catch (Exception $e) {
      $errors[] = 'Could not update status: ' . $e->getMessage();
    }
  }
}


if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_user'){
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'customer';

    if(empty($name)){
        $errors[] = 'Name is required.';
    }
    if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
        $errors[] = 'Please enter a valid email address.';
    }
    if(strlen($password) < 6){
        $errors[] = 'Password must be at least 6 characters.';
    }
    if(!in_array($role, $roles, true)){
        $errors[] = 'Invalid role selected.';
    }

    if(empty($errors)){
        try{
            $pdo = getPDO();
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            if($stmt->fetch()){
                $errors[] = 'An account with that email already exists.';
            }else{

                $stmt = $pdo->prepare('INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$name, $email, $password, $role, 'active']);
                $success = 'Account created successfully!';

                $_POST = [];
            }
        }catch(Exception $e){
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}


try{
    $pdo = getPDO();
    $stmt = $pdo->query('SELECT id,name,email,role,status,created_at FROM users ORDER BY id DESC');
    $users = $stmt->fetchAll();
}catch(Exception $e){
    $users = [];
    $errors[] = 'Could not fetch users: ' . $e->getMessage();
}
?>

<div class="row g-3">
  <div class="col-md-5">
    <div class="card p-3">
      <h4 class="mb-2">Create account</h4>
      <div class="small mb-3">Create an account for Rider, Admin or Owner.</div>

      <?php if($errors): ?>
        <div class="alert alert-danger">
          <ul class="mb-0">
          <?php foreach($errors as $e): ?>
            <li><?=htmlspecialchars($e)?></li>
          <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if($success): ?>
        <div class="alert alert-success"><?=htmlspecialchars($success)?></div>
      <?php endif; ?>

  <form method="post" action="users.php">
        <input type="hidden" name="action" value="create_user">
        <div class="mb-2">
          <label class="form-label">Full name (optional)</label>
          <input class="form-control" name="name" value="<?=htmlspecialchars($_POST['name'] ?? '')?>">
        </div>
        <div class="mb-2">
          <label class="form-label">Email</label>
          <input class="form-control" name="email" type="email" required value="<?=htmlspecialchars($_POST['email'] ?? '')?>">
        </div>
        <div class="mb-2">
          <label class="form-label">Password</label>
          <input class="form-control" name="password" type="password" required>
          <div class="small text-muted">Use at least 6 characters.</div>
        </div>
        <div class="mb-3">
          <label class="form-label">Role</label>
          <select class="form-select" name="role">
            <?php foreach($roles as $r): ?>
              <option value="<?=htmlspecialchars($r)?>" <?=((($_POST['role'] ?? '') === $r)? 'selected':'')?>><?=htmlspecialchars(ucfirst($r))?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="d-flex gap-2">
          <button class="btn btn-primary" type="submit">Create account</button>
          <button class="btn btn-outline-secondary" type="reset">Reset</button>
        </div>
      </form>
    </div>
  </div>

  <div class="col-md-7">
    <div class="card p-3">
      <h4 class="mb-2">Users</h4>
      <div class="small mb-3">Manage user accounts. View details, deactivate, or reset passwords.</div>

      <div class="table-responsive">
        <table class="table table-striped table-bordered align-middle">
          <thead class="table-light">
            <tr>
              <th>User ID</th>
              <th>Name</th>
              <th>Email</th>
              <th>Role</th>
              <th>Status</th>
              <th style="text-align:right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if(empty($users)): ?>
              <tr><td colspan="6" class="text-center">No users found.</td></tr>
            <?php else: ?>
              <?php foreach($users as $u): ?>
                <tr>
                  <td>#U<?=str_pad($u['id'],3,'0',STR_PAD_LEFT)?></td>
                  <td><?=htmlspecialchars($u['name'] ?? '')?></td>
                  <td><?=htmlspecialchars($u['email'])?></td>
                  <td><?=htmlspecialchars(ucfirst($u['role']))?></td>
                  <td>
                    <?php if($u['status'] === 'active'): ?>
                      <span class="badge bg-success">Active</span>
                    <?php elseif($u['status'] === 'inactive'): ?>
                      <span class="badge bg-danger">Inactive</span>
                    <?php else: ?>
                      <span class="badge bg-secondary"><?=htmlspecialchars($u['status'])?></span>
                    <?php endif; ?>
                  </td>
                  <td style="text-align:right">
                    <form method="post" action="users.php" class="d-inline" onsubmit="return confirm('Are you sure?');">
                      <input type="hidden" name="action" value="toggle_status">
                      <input type="hidden" name="user_id" value="<?=intval($u['id'])?>">
                      <?php if($u['status'] === 'active'): ?>
                        <input type="hidden" name="target_status" value="inactive">
                        <button class="btn btn-sm btn-danger" type="submit">Deactivate</button>
                      <?php else: ?>
                        <input type="hidden" name="target_status" value="active">
                        <button class="btn btn-sm btn-success" type="submit">Activate</button>
                      <?php endif; ?>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
