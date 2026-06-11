<?php
header("Content-Type: application/json");
require_once '../../config/db.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$org_id = isset($data['org_id']) ? (int)$data['org_id'] : 0;
$field_name = isset($data['field_name']) ? trim($data['field_name']) : '';

if (!$org_id || !$field_name) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required fields"]);
    exit;
}

try {
    // Get all leads with custom_data for this org
    $stmt = $pdo->prepare("SELECT id, custom_data FROM leads WHERE org_id = ? AND custom_data IS NOT NULL AND custom_data != ''");
    $stmt->execute([$org_id]);
    
    $updated = 0;
    while ($row = $stmt->fetch()) {
        $customData = json_decode($row['custom_data'], true);
        if (is_array($customData) && isset($customData[$field_name])) {
            // Remove the field from custom data
            unset($customData[$field_name]);
            
            // Update the lead
            $updateStmt = $pdo->prepare("UPDATE leads SET custom_data = ? WHERE id = ?");
            $updateStmt->execute([json_encode($customData), $row['id']]);
            $updated++;
        }
    }
    
    echo json_encode([
        "success" => true,
        "updated_leads" => $updated,
        "message" => "Custom field data deleted from $updated leads"
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to delete field data: " . $e->getMessage()]);
}
?>