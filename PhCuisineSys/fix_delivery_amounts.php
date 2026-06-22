<?php
/**
 * Migration Script: Fix Delivery Amounts
 * This script updates all delivery records where amount = 0 or NULL
 * with the correct total_amount from the associated order
 * 
 * Run this ONCE to fix historical data, then can be deleted
 */

require_once __DIR__ . '/db.php';

try {
    $pdo = getPDO();
    
    // Update all deliveries with amount = 0 or NULL to use order's total_amount
    $sql = "
        UPDATE deliveries d
        INNER JOIN orders o ON d.order_id = o.id
        SET d.amount = o.total_amount
        WHERE (d.amount = 0 OR d.amount IS NULL) AND d.status = 'delivered'
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    $rowsUpdated = $stmt->rowCount();
    
    echo "✅ Migration Complete!<br>";
    echo "Updated {$rowsUpdated} delivery records with correct amounts from orders.<br><br>";
    
    // Show summary
    echo "<strong>Updated Records:</strong><br>";
    $summary = $pdo->query("
        SELECT 
            d.id,
            d.order_id,
            o.order_number,
            d.amount,
            d.status,
            d.rider_id
        FROM deliveries d
        INNER JOIN orders o ON d.order_id = o.id
        WHERE d.status = 'delivered'
        LIMIT 10
    ");
    
    echo "<table border='1' cellpadding='10'><tr><th>Delivery ID</th><th>Order #</th><th>Amount (₱)</th><th>Status</th><th>Rider ID</th></tr>";
    while ($row = $summary->fetch()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['order_number'] . "</td>";
        echo "<td>" . number_format($row['amount'], 2) . "</td>";
        echo "<td>" . $row['status'] . "</td>";
        echo "<td>" . ($row['rider_id'] ?? 'N/A') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
