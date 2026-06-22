<?php
// register.php - create account for owner/admin/rider/customer
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/settings.php';

$errors = [];
$success = '';

// Allowed roles (admin/owner cannot self-register)
$roles = ['rider','customer'];

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $delivery_address = trim($_POST['delivery_address'] ?? '');
    $role = $_POST['role'] ?? 'customer';

    if(empty($name)){
        $errors[] = 'Please enter your full name.';
    }
    if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
        $errors[] = 'Please enter a valid email address.';
    }
    if(empty($phone) || strlen($phone) < 10){
        $errors[] = 'Please enter a valid mobile number (at least 10 digits).';
    }
    if(strlen($password) < 6){
        $errors[] = 'Password must be at least 6 characters.';
    }
    if($role === 'customer' && empty($delivery_address)){
        $errors[] = 'Delivery address is required for customers.';
    }
    if(!in_array($role, $roles, true)){
      $errors[] = 'Invalid role selected.';
    }

    if(empty($errors)){
        try{
            $pdo = getPDO();
            // check duplicate email
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            if($stmt->fetch()){
                $errors[] = 'An account with that email already exists.';
            }else{
                // Insert with phone and delivery address
                $stmt = $pdo->prepare('INSERT INTO users (name, email, phone, password, delivery_address, role, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$name, $email, $phone, $password, $delivery_address, $role, 'active']);
                $success = 'Account created successfully! You can now log in.';
                // Clear form
                $_POST = [];
            }
        }catch(Exception $e){
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Register - <?php echo htmlspecialchars(get_setting('site_name', 'Karinderya Mo')); ?></title>
  <link href="css/login.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: linear-gradient(135deg, #6B5FFF 0%, #9B7FFF 50%, #FF8B54 100%); }
    .register-container { display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; }
    .register-card { background: white; border-radius: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); max-width: 450px; width: 100%; padding: 40px; }
    .register-header { background: linear-gradient(135deg, #FF8B54 0%, #FF6B54 100%); color: white; padding: 30px; margin: -40px -40px 30px -40px; border-radius: 20px 20px 0 0; text-align: center; }
    .register-header h2 { margin: 0; font-size: 24px; font-weight: 700; }
    .register-header p { margin: 4px 0 0 0; font-size: 12px; opacity: 0.9; }
    .form-control { border-radius: 8px; border: 1px solid #E3E3E3; padding: 12px; }
    .form-control:focus { border-color: #FF8B54; box-shadow: 0 0 0 0.2rem rgba(255, 139, 84, 0.25); }
    .btn-register { background: linear-gradient(135deg, #FF8B54 0%, #FF6B54 100%); color: white; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 700; width: 100%; }
    .btn-register:hover { color: white; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(255, 107, 84, 0.4); }
    .alert { border-radius: 8px; }
    .register-footer { text-align: center; margin-top: 20px; font-size: 13px; }
    .register-footer a { color: #FF8B54; text-decoration: none; font-weight: 600; }
  </style>
</head>
<body>
  <div class="register-container">
    <div class="register-card">
      <div class="register-header">
        <h2>Create Account</h2>
        <p>Join <span class="site-name"><?php echo htmlspecialchars(get_setting('site_name', 'Karinderya Mo')); ?></span> today</p>
      </div>

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

      <form method="post" novalidate>
        <div class="mb-3">
          <label class="form-label">Full Name *</label>
          <input class="form-control" name="name" type="text" required value="<?=htmlspecialchars($_POST['name'] ?? '')?>">
        </div>
        <div class="mb-3">
          <label class="form-label">Email Address *</label>
          <input class="form-control" name="email" type="email" required value="<?=htmlspecialchars($_POST['email'] ?? '')?>">
          <div class="small text-muted mt-1">For login and order receipts</div>
        </div>
        <div class="mb-3">
          <label class="form-label">Mobile Number *</label>
          <input class="form-control" name="phone" type="tel" placeholder="09xxxxxxxxx" required value="<?=htmlspecialchars($_POST['phone'] ?? '')?>">
          <div class="small text-muted mt-1">For delivery communication</div>
        </div>
        <div class="mb-3">
          <label class="form-label">Password *</label>
          <input class="form-control" name="password" type="password" required>
          <div class="small text-muted mt-1">Minimum 6 characters</div>
        </div>
        <div class="mb-3">
          <label class="form-label">Account Type *</label>
          <select class="form-select" name="role">
            <?php foreach($roles as $r): ?>
              <option value="<?=htmlspecialchars($r)?>" <?=((($_POST['role'] ?? '') === $r)? 'selected':'')?>><?=htmlspecialchars(ucfirst($r))?></option>
            <?php endforeach; ?>
          </select>
          <div class="small text-muted mt-1">Only Customer or Rider accounts can be created here.</div>
        </div>
        <div class="mb-3" id="delivery-address-group" style="display:none;">
          <label class="form-label">Delivery Address *</label>
          <textarea class="form-control" name="delivery_address" rows="3" placeholder="Enter your complete delivery address"><?=htmlspecialchars($_POST['delivery_address'] ?? '')?></textarea>
          <div class="small text-muted mt-1">Complete and accurate address for order delivery</div>
        </div>

        <button class="btn-register" type="submit">Create Account</button>
      </form>

      <script>
        // Show delivery address field only for customer role
        const roleSelect = document.querySelector('select[name="role"]');
        const deliveryAddressGroup = document.getElementById('delivery-address-group');
        const deliveryAddressInput = document.querySelector('textarea[name="delivery_address"]');
        
        function toggleDeliveryAddress() {
          if (roleSelect.value === 'customer') {
            deliveryAddressGroup.style.display = 'block';
            deliveryAddressInput.required = true;
          } else {
            deliveryAddressGroup.style.display = 'none';
            deliveryAddressInput.required = false;
          }
        }
        
        roleSelect.addEventListener('change', toggleDeliveryAddress);
        // Initial check on page load
        window.addEventListener('load', toggleDeliveryAddress);
      </script>

      <div class="register-footer">
        Already have an account? <a href="main/login.html">Sign In</a><br>
        <a href="index.php" style="margin-top: 8px; display: block;">← Back to Home</a>
      </div>
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
