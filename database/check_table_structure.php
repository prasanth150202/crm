<?php
require_once '../config/db.php';

try {
    $stmt = $pdo->query("DESCRIBE custom_charts");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "custom_charts table structure:\n";
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>