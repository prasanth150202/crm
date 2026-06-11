<?php
require_once '../config/db.php';

try {
    // Check what tables exist with 'chart' in the name
    $stmt = $pdo->query("SHOW TABLES LIKE '%chart%'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Tables with 'chart' in name:\n";
    foreach ($tables as $table) {
        echo "- $table\n";
        
        // Show structure of each table
        $stmt2 = $pdo->query("DESCRIBE $table");
        $columns = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $column) {
            echo "  - " . $column['Field'] . " (" . $column['Type'] . ")\n";
        }
        
        // Show sample data
        $stmt3 = $pdo->query("SELECT * FROM $table LIMIT 3");
        $data = $stmt3->fetchAll(PDO::FETCH_ASSOC);
        echo "  Sample data: " . count($data) . " rows\n";
        if (count($data) > 0) {
            echo "  First row: " . json_encode($data[0]) . "\n";
        }
        echo "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>