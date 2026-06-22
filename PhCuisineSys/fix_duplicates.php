<?php
/**
 * Migration Script: Fix Duplicate Deliveries
 * 1. Removes duplicate delivery records (keeping the latest one)
 * 2. Adds UNIQUE constraint on order_id to prevent future duplicates
 */

require_once __DIR__ . '/db.php';

try {
    $pdo = getPDO();
    
    echo "<h3>Fixing Duplicate Deliveries...</h3>";
    
    // 1. Identify duplicates
    $sql = "
        SELECT order_id, COUNT(*) as count 
        FROM deliveries 
        GROUP BY order_id 
        HAVING count > 1
    ";
    $stmt = $pdo->query($sql);
    $duplicates = $stmt->fetchAll();
    
    echo "Found " . count($duplicates) . " orders with duplicate delivery records.<br>";
    
    foreach ($duplicates as $dup) {
        $order_id = $dup['order_id'];
        
        // Get all delivery records for this order, ordered by ID desc (latest first)
        $stmt = $pdo->prepare("SELECT id FROM deliveries WHERE order_id = ? ORDER BY id DESC");
        $stmt->execute([$order_id]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Keep the first one (latest), delete the rest
        $keep_id = array_shift($ids);
        
        if (!empty($ids)) {
            $ids_str = implode(',', $ids);
            $pdo->exec("DELETE FROM deliveries WHERE id IN ($ids_str)");
            echo "Fixed Order #$order_id: Kept delivery #$keep_id, deleted " . count($ids) . " duplicates.<br>";
        }
    }
    
    echo "<br>Duplicates removed. Adding UNIQUE constraint...<br>";
    
    // 2. Add UNIQUE constraint
    // Check if index already exists to avoid error
    $checkIndex = $pdo->query("SHOW INDEX FROM deliveries WHERE Key_name = 'order_id_unique'");
    if ($checkIndex->rowCount() == 0) {
        try {
            $pdo->exec("ALTER TABLE deliveries ADD CONSTRAINT order_id_unique UNIQUE (order_id)");
            echo "✅ UNIQUE constraint added successfully.<br>";
        } catch (Exception $e) {
            echo "⚠️ Could not add constraint (might already exist as another name): " . $e->getMessage() . "<br>";
        }
    } else {
        echo "ℹ️ UNIQUE constraint already exists.<br>";
    }
    
    echo "<br><strong>Done!</strong> Please check Rider Deliveries page.";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
