<?php
require_once '../config/db.php';

try {
    // Drop and recreate with the correct structure that reports expects
    $pdo->exec("DROP TABLE IF EXISTS custom_charts");
    
    $sql = "CREATE TABLE custom_charts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        org_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        chart_type VARCHAR(50) NOT NULL,
        data_field VARCHAR(100) NOT NULL,
        aggregation VARCHAR(50) NOT NULL,
        x_axis VARCHAR(100) NOT NULL,
        color_scheme VARCHAR(50) DEFAULT 'default',
        chart_sort VARCHAR(50) DEFAULT 'default',
        show_total TINYINT(1) DEFAULT 0,
        sort_order INT DEFAULT 0,
        global TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (org_id) REFERENCES organizations(id) ON DELETE CASCADE,
        INDEX idx_org_order (org_id, sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
    echo "custom_charts table recreated with correct structure\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>