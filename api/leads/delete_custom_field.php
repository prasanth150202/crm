<?php
// api/leads/delete_custom_field.php
header("Content-Type: application/json");
require_once '../../config/db.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, DELETE");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$org_id = isset($data['org_id']) ? (int)$data['org_id'] : 0;
$field_name = isset($data['field_name']) ? trim($data['field_name']) : '';

if (!$org_id || !$field_name) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required fields: org_id and field_name"]);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Step 1: Delete the custom field from custom_fields table FIRST
    $deleteStmt = $pdo->prepare("DELETE FROM custom_fields WHERE org_id = ? AND field_name = ?");
    $deleteStmt->execute([$org_id, $field_name]);
    $deleted_rows = $deleteStmt->rowCount();
    
    // Step 2: Remove custom field data from all leads
    $stmt = $pdo->prepare("SELECT id, custom_data FROM leads WHERE org_id = ? AND custom_data IS NOT NULL AND custom_data != ''");
    $stmt->execute([$org_id]);
    
    $updated_leads = 0;
    while ($row = $stmt->fetch()) {
        $customData = json_decode($row['custom_data'], true);
        if (is_array($customData) && isset($customData[$field_name])) {
            // Remove the field from custom data
            unset($customData[$field_name]);
            
            // Update the lead - set to NULL if empty, otherwise JSON encode
            $updateStmt = $pdo->prepare("UPDATE leads SET custom_data = ? WHERE id = ?");
            $newData = empty($customData) ? NULL : json_encode($customData);
            $updateStmt->execute([$newData, $row['id']]);
            $updated_leads++;
        }
    }
    
    $pdo->commit();
    
    echo json_encode([
        "success" => true,
        "deleted" => $deleted_rows > 0,
        "updated_leads" => $updated_leads,
        "message" => "Custom field deleted successfully from {$updated_leads} leads"
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        "error" => "Failed to delete custom field: " . $e->getMessage()
    ]);
}
