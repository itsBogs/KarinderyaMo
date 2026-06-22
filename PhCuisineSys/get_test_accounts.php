<?php
require_once 'db.php';

try {
    $pdo = getPDO();
    
    echo "=== TEST ACCOUNTS ===\n\n";
    
    // Get one customer
    $stmt = $pdo->query("SELECT email, password FROM users WHERE role = 'customer' LIMIT 1");
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($customer) {
        echo "Customer: {$customer['email']} / {$customer['password']}\n";
    } else {
        echo "Customer: (no account found)\n";
    }
    
    // Get one rider
    $stmt = $pdo->query("SELECT email, password FROM users WHERE role = 'rider' LIMIT 1");
    $rider = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($rider) {
        echo "Rider: {$rider['email']} / {$rider['password']}\n";
    } else {
        echo "Rider: (no account found)\n";
    }
    
    echo "\nAdmin: (password only) / admin123\n";
    echo "Owner: (password only) / owner123\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
