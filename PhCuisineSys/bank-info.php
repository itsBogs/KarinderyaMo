<?php

require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo '<div class="alert alert-danger">Unauthorized access.</div>';
    exit;
}

$message = '';
$message_type = 'info';


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_bank_info') {
    try {
        $pdo = getPDO();
        
        $settings_to_save = [
            'gcash_name' => $_POST['gcash_name'] ?? '',
            'gcash_number' => $_POST['gcash_number'] ?? '',
            'bank_name' => $_POST['bank_name'] ?? '',
            'bank_account_name' => $_POST['bank_account_name'] ?? '',
            'bank_account_number' => $_POST['bank_account_number'] ?? '',
        ];

        $stmt = $pdo->prepare("
            INSERT INTO settings (key_name, value) 
            VALUES (:key_name, :value) 
            ON DUPLICATE KEY UPDATE value = :value
        ");

        foreach ($settings_to_save as $key => $value) {
            $stmt->execute(['key_name' => $key, 'value' => $value]);
        }
        
        $message = '✅ Payment information saved successfully!';
        $message_type = 'success';

    } catch (Exception $e) {
        $message = '❌ Error saving information: ' . $e->getMessage();
        $message_type = 'danger';
    }
}


$current_settings = [];
$user_options = [];
try {
    $pdo = getPDO();
    $keys = ['gcash_name', 'gcash_number', 'bank_name', 'bank_account_name', 'bank_account_number'];
    $in_clause = implode(',', array_fill(0, count($keys), '?'));
    
    $stmt = $pdo->prepare("SELECT key_name, value FROM settings WHERE key_name IN ($in_clause)");
    $stmt->execute($keys);
    $results = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    foreach ($keys as $key) {
        $current_settings[$key] = $results[$key] ?? '';
    }


    $user_stmt = $pdo->query("SELECT id, name, phone FROM users ORDER BY name ASC");
    $user_options = $user_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $message = '❌ Error fetching settings: ' . $e->getMessage();
    $message_type = 'danger';
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">🏦 Bank & Online Payment Info</h3>
    </div>
    <p class="text-muted">Enter the account details where customers will send their online payments. This information will be displayed at checkout.</p>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body p-4">
            <form id="bankInfoForm" method="POST">
                <input type="hidden" name="action" value="save_bank_info">
                
                <h5 class="mb-3" style="color: var(--strong-accent);">GCash Details</h5>

                <div class="row g-3 mb-2">
                    <div class="col-md-6">
                        <label class="form-label">Quick-fill from registered user</label>
                        <select id="user_quick_fill" class="form-select">
                            <option value="">-- Select user (optional) --</option>
                            <?php foreach ($user_options as $u): ?>
                                <option value="<?= htmlspecialchars($u['id']) ?>" data-name="<?= htmlspecialchars($u['name']) ?>" data-phone="<?= htmlspecialchars($u['phone'] ?? '') ?>">
                                    <?= htmlspecialchars($u['name']) ?><?php if (!empty($u['phone'])): ?> (<?= htmlspecialchars($u['phone']) ?>)<?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="gcash_name" class="form-label">GCash Account Name</label>
                        <input type="text" class="form-control" id="gcash_name" name="gcash_name" value="<?= htmlspecialchars($current_settings['gcash_name']) ?>" placeholder="e.g., Juan D. Cruz">
                    </div>
                    <div class="col-md-6">
                        <label for="gcash_number" class="form-label">GCash Account Number</label>
                        <input type="text" class="form-control" id="gcash_number" name="gcash_number" value="<?= htmlspecialchars($current_settings['gcash_number']) ?>" placeholder="e.g., 09123456789">
                    </div>
                </div>

                <hr class="my-4">

                <h5 class="mb-3" style="color: var(--strong-accent);">Bank Account Details</h5>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="bank_name" class="form-label">Bank Name</label>
                        <input type="text" class="form-control" id="bank_name" name="bank_name" value="<?= htmlspecialchars($current_settings['bank_name']) ?>" placeholder="e.g., BDO Unibank">
                    </div>
                    <div class="col-md-4">
                        <label for="bank_account_name" class="form-label">Bank Account Name</label>
                        <input type="text" class="form-control" id="bank_account_name" name="bank_account_name" value="<?= htmlspecialchars($current_settings['bank_account_name']) ?>" placeholder="e.g., Juan Dela Cruz">
                    </div>
                    <div class="col-md-4">
                        <label for="bank_account_number" class="form-label">Bank Account Number</label>
                        <input type="text" class="form-control" id="bank_account_number" name="bank_account_number" value="<?= htmlspecialchars($current_settings['bank_account_number']) ?>" placeholder="e.g., 001234567890">
                    </div>
                </div>

                <div class="mt-4 text-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Save Information
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm mt-4">
        <div class="card-body">
            <h5 class="card-title mb-3">Registered Bank Wallet Accounts</h5>
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>User ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Contact</th>
                            <th>Address</th>
                            <th>Civil Status</th>
                            <th>Nationality</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            $rows = $pdo->query("SELECT b.*, u.name AS user_name FROM bank_accounts b JOIN users u ON b.user_id = u.id ORDER BY b.created_at DESC")->fetchAll();
                        } catch (Exception $e) {
                            $rows = [];
                        }
                        if (count($rows) === 0): ?>
                            <tr><td colspan="8" class="text-center text-muted">No bank wallet accounts yet</td></tr>
                        <?php else: foreach ($rows as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['user_id']) ?></td>
                                <td><?= htmlspecialchars($row['full_name']) ?></td>
                                <td><?= htmlspecialchars($row['email']) ?></td>
                                <td><?= htmlspecialchars($row['contact']) ?></td>
                                <td><?= htmlspecialchars($row['address']) ?></td>
                                <td><?= htmlspecialchars($row['civil_status']) ?></td>
                                <td><?= htmlspecialchars($row['nationality']) ?></td>
                                <td><?= htmlspecialchars($row['created_at']) ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>


<script>

document.getElementById('user_quick_fill')?.addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    const name = opt?.dataset?.name || '';
    const phone = opt?.dataset?.phone || '';
    if (name) {
        document.getElementById('gcash_name').value = name;
    }
    if (phone) {
        document.getElementById('gcash_number').value = phone;
    }
});
</script>
