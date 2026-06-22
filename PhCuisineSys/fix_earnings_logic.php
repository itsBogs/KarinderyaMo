<?php






require_once __DIR__ . '/db.php';

try {
    $pdo = getPDO();
    
    echo "<h3>Fixing Rider Earnings...</h3>";
    


    $sql = "
        UPDATE deliveries d
        INNER JOIN orders o ON d.order_id = o.id
        SET d.amount = o.shipping_fee
        WHERE d.status = 'delivered'
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    $rowsUpdated = $stmt->rowCount();
    
    echo "✅ Migration Complete!<br>";
    echo "Updated {$rowsUpdated} delivery records to use Shipping Fee (₱58.00) as earnings.<br><br>";
    

    echo "<strong>Updated Records (Sample):</strong><br>";
    $summary = $pdo->query("
        SELECT 
            d.id,
            o.order_number,
            d.amount as rider_earnings,
            o.total_amount as order_total,
            o.shipping_fee
        FROM deliveries d
        INNER JOIN orders o ON d.order_id = o.id
        WHERE d.status = 'delivered'
        LIMIT 10
    ");
    
    echo "<table border='1' cellpadding='10'><tr><th>Order #</th><th>Rider Earnings</th><th>Order Total</th><th>Shipping Fee</th></tr>";
    while ($row = $summary->fetch()) {
        echo "<tr>";
        echo "<td>" . $row['order_number'] . "</td>";
        echo "<td>₱" . number_format($row['rider_earnings'], 2) . "</td>";
        echo "<td>₱" . number_format($row['order_total'], 2) . "</td>";
        echo "<td>₱" . number_format($row['shipping_fee'], 2) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
