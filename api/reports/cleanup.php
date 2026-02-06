<?php
// api/reports/cleanup.php
header("Content-Type: application/json");
require_once '../../config/db.php';

try {
    // Allowed chart types
    $allowed_types = ['table', 'pie', 'bar', 'line', 'area', 'doughnut', 'funnel', 'combo', 'scorecard'];
    
    // Create placeholders
    $placeholders = implode(',', array_fill(0, count($allowed_types), '?'));
    
    // Delete charts with invalid types
    $stmt = $pdo->prepare("DELETE FROM custom_charts WHERE chart_type NOT IN ($placeholders)");
    $stmt->execute($allowed_types);
    
    $deleted_count = $stmt->rowCount();
    
    // Also fix any weird NULL sort orders to 0
    $pdo->query("UPDATE custom_charts SET sort_order = 0 WHERE sort_order IS NULL");
    
    echo json_encode([
        "success" => true, 
        "message" => "Cleanup complete.", 
        "deleted_count" => $deleted_count
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Cleanup failed: " . $e->getMessage()]);
}
?>
