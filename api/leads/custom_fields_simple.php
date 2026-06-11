<?php
header("Content-Type: application/json");
require_once '../../config/db.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");

$org_id = isset($_GET['org_id']) ? (int)$_GET['org_id'] : 1;

try {
    $stmt = $pdo->prepare("SELECT field_name, field_type, field_options FROM custom_fields WHERE org_id = ? AND is_active = 1 ORDER BY field_name");
    $stmt->execute([$org_id]);
    
    $fields = [];
    while ($row = $stmt->fetch()) {
        $fields[] = [
            'name' => $row['field_name'],
            'type' => $row['field_type'] ?: 'text',
            'options' => $row['field_options'] ? explode(',', $row['field_options']) : []
        ];
    }
    
    echo json_encode([
        "success" => true,
        "fields" => $fields
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage(),
        "fields" => []
    ]);
}
?>