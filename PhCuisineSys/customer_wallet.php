<?php

require 'db.php';
session_start();
require_once __DIR__ . '/includes/settings.php';


if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
    header('Location: main/login.html');
    exit;
}

$pdo = getPDO();
$user_id = $_SESSION['user_id'];
$message = '';
$message_type = 'info';
$bank_unlocked = isset($_SESSION['bank_unlocked']) && $_SESSION['bank_unlocked'] === true;
$wallet_user_id = $_SESSION['bank_unlocked_user_id'] ?? $user_id;


$bank_account = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM bank_accounts WHERE user_id = ?");
    $stmt->execute([$wallet_user_id]);
    $bank_account = $stmt->fetch();
} catch (Exception $e) {
    $bank_account = null;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'register_bank') {
    $full_name = trim($_POST['full_name'] ?? '');
    $age = intval($_POST['age'] ?? 0);
    $gender = trim($_POST['gender'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $civil_status = trim($_POST['civil_status'] ?? '');
    $nationality = trim($_POST['nationality'] ?? '');
    $pin = trim($_POST['pin'] ?? '');

    if (!$full_name || !$age || !$gender || !$contact || !$address || !$email || !$civil_status || !$nationality || strlen($pin) < 4) {
        $message = '❌ Please complete all fields (PIN must be at least 4 digits).';
        $message_type = 'danger';
    } elseif ($bank_account) {
        $message = '❌ Bank account already exists.';
        $message_type = 'danger';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO bank_accounts (user_id, full_name, age, gender, contact, address, email, civil_status, nationality, pin_hash) VALUES (?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$user_id, $full_name, $age, $gender, $contact, $address, $email, $civil_status, $nationality, password_hash($pin, PASSWORD_BCRYPT)]);
            $message = '✅ Bank account created. Use your PIN to unlock.';
            $message_type = 'success';
            $bank_account = [
                'full_name' => $full_name,
                'age' => $age,
                'gender' => $gender,
                'contact' => $contact,
                'address' => $address,
                'email' => $email,
                'civil_status' => $civil_status,
                'nationality' => $nationality,
            ];
        } catch (Exception $e) {
            $message = '❌ Error creating bank account: ' . $e->getMessage();
            $message_type = 'danger';
        }
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'unlock_bank') {
    $pin = trim($_POST['pin'] ?? '');
    $unlock_account = null;
    try {
        $rows = $pdo->query("SELECT * FROM bank_accounts")->fetchAll();
        foreach ($rows as $row) {
            if (password_verify($pin, $row['pin_hash'])) {
                $unlock_account = $row;
                break;
            }
        }
    } catch (Exception $e) {
        $unlock_account = null;
    }

    if (!$unlock_account) {
        $message = '❌ Invalid PIN or no matching account.';
        $message_type = 'danger';
    } else {
        $_SESSION['bank_unlocked'] = true;
        $_SESSION['bank_unlocked_user_id'] = $unlock_account['user_id'];
        $wallet_user_id = $unlock_account['user_id'];
        $bank_unlocked = true;
        $message = '✅ Bank wallet unlocked.';
        $message_type = 'success';
        $bank_account = $unlock_account;
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'lock_bank') {
    unset($_SESSION['bank_unlocked']);
    unset($_SESSION['bank_unlocked_user_id']);
    $wallet_user_id = $user_id;
    $bank_unlocked = false;
    $message = '🔒 Bank wallet locked. Enter PIN to unlock again.';
    $message_type = 'info';
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'deposit') {
    if (!$bank_unlocked) {
        $message = '❌ Unlock your bank account with PIN before adding funds.';
        $message_type = 'danger';
    } else {
    $amount = floatval($_POST['amount'] ?? 0);
    
    if ($amount <= 0) {
        $message = '❌ Please enter a valid amount.';
        $message_type = 'danger';
    } elseif ($amount > 50000) {
        $message = '❌ Maximum deposit is ₱50,000 per transaction.';
        $message_type = 'danger';
    } else {
        try {
            $pdo->beginTransaction();
            

            $stmt = $pdo->prepare("SELECT balance FROM wallet WHERE user_id = ? FOR UPDATE");
            $stmt->execute([$wallet_user_id]);
            $wallet = $stmt->fetch();
            
            if (!$wallet) {

                $stmt = $pdo->prepare("INSERT INTO wallet (user_id, balance) VALUES (?, 0.00)");
                $stmt->execute([$wallet_user_id]);
                $balance_before = 0.00;
            } else {
                $balance_before = $wallet['balance'];
            }
            
            $balance_after = $balance_before + $amount;
            

            $stmt = $pdo->prepare("UPDATE wallet SET balance = ? WHERE user_id = ?");
            $stmt->execute([$balance_after, $wallet_user_id]);
            

            $stmt = $pdo->prepare("
                INSERT INTO wallet_transactions 
                (user_id, transaction_type, amount, balance_before, balance_after, reference_type, description) 
                VALUES (?, 'deposit', ?, ?, ?, 'manual', 'Cash deposit to wallet')
            ");
            $stmt->execute([$wallet_user_id, $amount, $balance_before, $balance_after]);
            
            $pdo->commit();
            
            $message = "✅ Successfully added ₱" . number_format($amount, 2) . " to your wallet!";
            $message_type = 'success';
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = '❌ Error processing deposit: ' . $e->getMessage();
            $message_type = 'danger';
        }
    }
    }
}


try {
    $stmt = $pdo->prepare("SELECT balance FROM wallet WHERE user_id = ?");
    $stmt->execute([$wallet_user_id]);
    $wallet = $stmt->fetch();
    $balance = $wallet ? $wallet['balance'] : 0.00;
    

    if (!$wallet) {
        $stmt = $pdo->prepare("INSERT INTO wallet (user_id, balance) VALUES (?, 0.00)");
        $stmt->execute([$wallet_user_id]);
        $balance = 0.00;
    }
} catch (Exception $e) {
    $balance = 0.00;
}


try {
    $stmt = $pdo->prepare("
        SELECT * FROM wallet_transactions 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 20
    ");
    $stmt->execute([$wallet_user_id]);
    $transactions = $stmt->fetchAll();
} catch (Exception $e) {
    $transactions = [];
}


try {
    $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
    $stmt->execute([$wallet_user_id]);
    $user = $stmt->fetch();
} catch (Exception $e) {
    $user = ['name' => 'Customer', 'email' => ''];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>My Wallet — <?php echo htmlspecialchars(get_setting('site_name', 'Karinderya Mo')); ?></title>
    <link rel="stylesheet" href="css/design.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .wallet-card {
            background: linear-gradient(135deg, #6B5FFF, #FF8B54);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .balance-amount {
            font-size: 3rem;
            font-weight: bold;
            margin: 20px 0;
        }
        .transaction-item {
            border-bottom: 1px solid #eee;
            padding: 15px 0;
        }
        .transaction-item:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>
    <div class="app">
        <header>
            <div class="brand">
                <div class="logo">KM</div>
                <div>
                    <h1 class="site-name"><?php echo htmlspecialchars(get_setting('site_name', 'Karinderya Mo')); ?></h1>
                    <p class="lead">My Wallet</p>
                </div>
            </div>
            <div class="actions">
                <a href="index.php" class="icon-btn" title="Back to Menu">⬅ Menu</a>
                <a href="cart.php" class="icon-btn" title="Cart">🛒 Cart</a>
            </div>
        </header>

        <main class="container my-5">
            <?php if ($message): ?>
                <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-6">
                    <div class="wallet-card">
                        <div class="d-flex align-items-center mb-3">
                            <i class="bi bi-wallet2 fs-1 me-3"></i>
                            <div>
                                <h5 class="mb-0">Wallet / Bank Account</h5>
                                <small><?= htmlspecialchars($user['name']) ?></small>
                            </div>
                        </div>
                        <div class="balance-amount">₱<?= number_format($balance, 2) ?></div>
                        <p class="mb-0"><small>Available Balance</small></p>
                    </div>

                    <?php if (!$bank_account): ?>
                        <div class="card shadow-sm border-0 mb-3" style="background: linear-gradient(135deg,#fef8f0,#ffe8cc);">
                            <div class="card-body d-flex flex-wrap gap-2 align-items-center">
                                <div>
                                    <h5 class="mb-1">Wallet Access</h5>
                                    <div class="text-muted small">Choose to log in with PIN or register a new wallet account.</div>
                                </div>
                                <div class="ms-auto d-flex gap-2">
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="showLoginBtn">Log In</button>
                                    <button type="button" class="btn btn-primary btn-sm" id="showRegisterBtn">Register</button>
                                </div>
                            </div>
                        </div>

                        <div id="loginSection" class="card shadow-sm border-0 mb-3" style="display:none;background: linear-gradient(135deg,#e9f5ff,#d6e8ff);">
                            <div class="card-body">
                                <h5 class="card-title mb-2">Unlock with PIN</h5>
                                <p class="text-muted small">Enter any wallet PIN to unlock that wallet (even if logged in as another customer).</p>
                                <form method="POST" class="row g-2">
                                    <input type="hidden" name="action" value="unlock_bank">
                                    <div class="col-12">
                                        <label class="form-label">Enter PIN</label>
                                        <input type="password" name="pin" class="form-control" minlength="4" maxlength="6" required>
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary w-100">Unlock</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div id="registerSection" class="card shadow-sm border-0" style="display:none;background: linear-gradient(135deg,#fff7ec,#ffe1c7);">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Register Bank Account</h5>
                                <p class="text-muted small">Create your PIN-protected wallet to use for online payments.</p>
                                <form method="POST" class="gy-2 row">
                                    <input type="hidden" name="action" value="register_bank">
                                    <div class="col-12">
                                        <label class="form-label">Full Name</label>
                                        <input name="full_name" class="form-control" required>
                                    </div>
                                    <div class="col-4">
                                        <label class="form-label">Age</label>
                                        <input type="number" name="age" class="form-control" min="1" required>
                                    </div>
                                    <div class="col-4">
                                        <label class="form-label">Gender</label>
                                        <select name="gender" class="form-select" required>
                                            <option value="">Select</option>
                                            <option value="Male">Male</option>
                                            <option value="Female">Female</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                    <div class="col-4">
                                        <label class="form-label">Civil Status</label>
                                        <select name="civil_status" class="form-select" required>
                                            <option value="">Select</option>
                                            <option value="Single">Single</option>
                                            <option value="Married">Married</option>
                                            <option value="Separated">Separated</option>
                                            <option value="Widowed">Widowed</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Contact</label>
                                        <input name="contact" class="form-control" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Address</label>
                                        <textarea name="address" class="form-control" rows="2" required></textarea>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Email</label>
                                        <input type="email" name="email" class="form-control" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Nationality</label>
                                        <input name="nationality" class="form-control" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">4-6 Digit PIN</label>
                                        <input type="password" name="pin" class="form-control" minlength="4" maxlength="6" required>
                                    </div>
                                    <div class="col-12 mt-2">
                                        <button type="submit" class="btn btn-primary w-100">Create Bank Account</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php elseif (!$bank_unlocked): ?>
                        <div class="card shadow-sm border-0" style="background: linear-gradient(135deg,#e9f5ff,#d6e8ff);">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Unlock with PIN</h5>
                                <p class="text-muted small">Enter your PIN to access balance and add funds.</p>
                                <form method="POST" class="row g-2">
                                    <input type="hidden" name="action" value="unlock_bank">
                                    <div class="col-12">
                                        <label class="form-label">Enter PIN</label>
                                        <input type="password" name="pin" class="form-control" minlength="4" maxlength="6" required>
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary w-100">Unlock</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="card shadow-sm mb-3 border-0" style="background: linear-gradient(135deg,#f7f9ff,#e8f0ff);">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h5 class="card-title mb-0">Account Info</h5>
                                    <form method="POST" class="m-0">
                                        <input type="hidden" name="action" value="lock_bank">
                                        <button class="btn btn-outline-secondary btn-sm">Sign Out</button>
                                    </form>
                                </div>
                                <div class="small text-muted">Full Name</div>
                                <div class="fw-bold mb-2"><?= htmlspecialchars($bank_account['full_name']) ?></div>
                                <div class="row g-2 small">
                                    <div class="col-4">Age: <strong><?= htmlspecialchars($bank_account['age']) ?></strong></div>
                                    <div class="col-4">Gender: <strong><?= htmlspecialchars($bank_account['gender']) ?></strong></div>
                                    <div class="col-4">Civil Status: <strong><?= htmlspecialchars($bank_account['civil_status']) ?></strong></div>
                                    <div class="col-12">Contact: <strong><?= htmlspecialchars($bank_account['contact']) ?></strong></div>
                                    <div class="col-12">Email: <strong><?= htmlspecialchars($bank_account['email']) ?></strong></div>
                                    <div class="col-12">Address: <strong><?= htmlspecialchars($bank_account['address']) ?></strong></div>
                                    <div class="col-12">Nationality: <strong><?= htmlspecialchars($bank_account['nationality']) ?></strong></div>
                                </div>
                            </div>
                        </div>

                        <div class="card shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title mb-4"><i class="bi bi-plus-circle"></i> Add Money to Wallet</h5>
                                <form method="POST">
                                    <input type="hidden" name="action" value="deposit">
                                    <div class="mb-3">
                                        <label for="amount" class="form-label">Amount (₱)</label>
                                        <input type="number" class="form-control" id="amount" name="amount" 
                                               placeholder="Enter amount" min="1" max="50000" step="0.01" required>
                                        <small class="text-muted">Maximum: ₱50,000 per transaction</small>
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-cash-stack"></i> Add Money
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title mb-4"><i class="bi bi-clock-history"></i> Transaction History</h5>
                            <div style="max-height: 500px; overflow-y: auto;">
                                <?php if (count($transactions) > 0): ?>
                                    <?php foreach ($transactions as $txn): ?>
                                        <div class="transaction-item">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong>
                                                        <?php if ($txn['transaction_type'] === 'deposit'): ?>
                                                            <i class="bi bi-arrow-down-circle text-success"></i> Deposit
                                                        <?php elseif ($txn['transaction_type'] === 'payment'): ?>
                                                            <i class="bi bi-cart-check text-primary"></i> Payment
                                                        <?php else: ?>
                                                            <i class="bi bi-arrow-up-circle text-info"></i> Refund
                                                        <?php endif; ?>
                                                    </strong>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?= date('M d, Y h:i A', strtotime($txn['created_at'])) ?>
                                                    </small>
                                                    <?php if ($txn['description']): ?>
                                                        <br><small class="text-muted"><?= htmlspecialchars($txn['description']) ?></small>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-end">
                                                    <strong class="<?= $txn['transaction_type'] === 'deposit' || $txn['transaction_type'] === 'refund' ? 'text-success' : 'text-danger' ?>">
                                                        <?= $txn['transaction_type'] === 'deposit' || $txn['transaction_type'] === 'refund' ? '+' : '-' ?>₱<?= number_format($txn['amount'], 2) ?>
                                                    </strong>
                                                    <br>
                                                    <small class="text-muted">Balance: ₱<?= number_format($txn['balance_after'], 2) ?></small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted py-5">
                                        <i class="bi bi-inbox fs-1"></i>
                                        <p class="mt-3">No transactions yet</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <footer>
            <span class="site-name"><?php echo htmlspecialchars(get_setting('site_name', 'Karinderya Mo')); ?></span> - <?php echo htmlspecialchars(get_setting('site_description', 'Lasapin ang sarap Pinoy!')); ?>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    (function(){
        const loginBtn = document.getElementById('showLoginBtn');
        const registerBtn = document.getElementById('showRegisterBtn');
        const loginSection = document.getElementById('loginSection');
        const registerSection = document.getElementById('registerSection');
        if (loginBtn && registerBtn && loginSection && registerSection) {
            loginBtn.addEventListener('click', () => {
                loginSection.style.display = 'block';
                registerSection.style.display = 'none';
            });
            registerBtn.addEventListener('click', () => {
                registerSection.style.display = 'block';
                loginSection.style.display = 'none';
            });

        }
    })();
    </script>
        </script>
        <script>
        (function(){
            function applySiteMeta(meta){
                if(!meta) return;
                try{
                    const m = typeof meta === 'string' ? JSON.parse(meta) : meta;
                    if(m.site_name) document.querySelectorAll('.site-name').forEach(el=>el.textContent = m.site_name);
                    if(m.site_description) document.querySelectorAll('.site-desc').forEach(el=>el.textContent = m.site_description);
                    if(m.site_name){
                        if(document.title && document.title.indexOf('—')!==-1){
                            const left = document.title.split('—')[0].trim();
                            document.title = left + ' — ' + m.site_name;
                        } else {
                            document.title = m.site_name + ' — ' + document.title;
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
