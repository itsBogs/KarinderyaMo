<?php
require_once 'db.php';

try {
    $pdo = getPDO();
    
    echo "=== DATABASE CHECK ===\n\n";
    
    // Check total orders
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM orders');
    echo "Total orders: " . $stmt->fetch()['count'] . "\n\n";
    
    // Check orders by status
    $stmt = $pdo->query('SELECT status, COUNT(*) as count FROM orders GROUP BY status');
    echo "Orders by status:\n";
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($data)) {
        echo "  (no orders found)\n";
    } else {
        foreach($data as $row) {
            echo "  {$row['status']}: {$row['count']}\n";
        }
    }
    
    echo "\nJSON for chart: " . json_encode($stmt->fetchAll(PDO::FETCH_KEY_PAIR)) . "\n\n";
    
    // Check daily revenue
    $stmt = $pdo->query("
        SELECT DATE(created_at) as date, SUM(total_amount) as revenue 
        FROM orders 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    echo "Daily revenue (last 7 days):\n";
    $revenue = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($revenue)) {
        echo "  (no revenue data)\n";
    } else {
        foreach($revenue as $row) {
            echo "  {$row['date']}: ₱{$row['revenue']}\n";
        }
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
