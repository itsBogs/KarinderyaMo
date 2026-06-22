<?php
// main/login.php - Authentication handler
require '../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $pdo = getPDO();
    
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role = trim($_POST['role'] ?? 'customer');

    // Allowed roles
    $allowedRoles = ['customer','rider','admin','owner'];
    if (!in_array($role, $allowedRoles, true)) {
        echo json_encode(['success' => false, 'message' => '❌ Invalid role']);
        exit;
    }

    // Admin/Owner: password-only login
    if ($role === 'admin' || $role === 'owner') {
        $expectedPass = $role === 'admin' ? 'admin123' : 'owner123';
        if ($password !== $expectedPass) {
            echo json_encode(['success' => false, 'message' => '❌ Invalid ' . $role . ' password']);
            exit;
        }

        // Fetch the existing account for the role
        $stmt = $pdo->prepare("SELECT * FROM users WHERE role = ? ORDER BY id ASC LIMIT 1");
        $stmt->execute([$role]);
        $user = $stmt->fetch();

        if (!$user) {
            // Auto-create a default owner account if none exists
            $placeholderEmail = 'owner@example.com';
            $insert = $pdo->prepare("INSERT INTO users (name, email, password, role, status, created_at) VALUES (?, ?, ?, 'owner', 'active', NOW())");
            $insert->execute(['Owner Account', $placeholderEmail, 'owner123']);
            $userId = $pdo->lastInsertId();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
        }

        if (($user['status'] ?? '') === 'inactive') {
            echo json_encode(['success' => false, 'message' => '⛔ This account was deactivated. Please contact support.']);
            exit;
        }

        // Set session - clear existing session first for admin/owner
        session_unset();
        session_destroy();
        session_start();
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_phone'] = $user['phone'] ?? '';
        $_SESSION['user_address'] = $user['delivery_address'] ?? '';

        echo json_encode([
            'success' => true,
            'message' => '✅ Login successful!',
            'redirect' => '../admin_dashboard.php'
        ]);
        exit;
    }

    // Non-admin: require email + password
    if (!$email || !$password) {
        echo json_encode(['success' => false, 'message' => '❌ Email and password are required']);
        exit;
    }

    // Check user in database (plain text password comparison)
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? AND role = ?');
    $stmt->execute([$email, $role]);
    $user = $stmt->fetch();

    if (!$user) {
        echo json_encode(['success' => false, 'message' => '❌ Invalid email, password, or role']);
        exit;
    }

    // Check password (plain text comparison - NO HASHING)
    if ($user['password'] !== $password) {
        echo json_encode(['success' => false, 'message' => '❌ Invalid email or password']);
        exit;
    }

    // Block login when account is inactive
    if ($user['status'] === 'inactive') {
        echo json_encode(['success' => false, 'message' => '⛔ This account was deactivated by an admin. Please contact support.']);
        exit;
    }

    // Login successful - clear any existing session and create new one
    session_unset(); // Clear all session variables
    session_destroy(); // Destroy old session
    session_start(); // Start fresh session
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_phone'] = $user['phone'] ?? '';
    $_SESSION['user_address'] = $user['delivery_address'] ?? '';

    // Determine redirect based on role
    $redirect = match($role) {
        'rider' => '../rider_panel.php',
        default => '../index.php'
    };

    echo json_encode([
        'success' => true,
        'message' => '✅ Login successful!',
        'redirect' => $redirect
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '❌ Server error: ' . $e->getMessage()]);
}
?>
