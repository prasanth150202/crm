<?php
// api/external/custom_fields.php
header("Content-Type: application/json");
require_once '../../config/db.php';
require_once 'middleware.php';

corsHeaders();

$org_id = validateApiKey($pdo);
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGetCustomFields($pdo, $org_id);
        break;
    case 'POST':
        handleCreateCustomField($pdo, $org_id);
        break;
    case 'DELETE':
        handleDeleteCustomField($pdo, $org_id);
        break;
    default:
        http_response_code(405);
        echo json_encode(["error" => "Method not allowed"]);
}

function handleGetCustomFields($pdo, $org_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM custom_fields WHERE org_id = ? ORDER BY field_order");
        $stmt->execute([$org_id]);
        $fields = $stmt->fetchAll();
        
        echo json_encode(["success" => true, "data" => $fields]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to fetch custom fields: " . $e->getMessage()]);
    }
}

function handleCreateCustomField($pdo, $org_id) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['field_name']) || !isset($input['field_type'])) {
        http_response_code(400);
        echo json_encode(["error" => "field_name and field_type are required"]);
        return;
    }
    
    $allowed_types = ['text', 'number', 'date', 'select', 'textarea'];
    if (!in_array($input['field_type'], $allowed_types)) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid field type"]);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO custom_fields (org_id, field_name, field_type, field_options, is_required, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
            $org_id,
            $input['field_name'],
            $input['field_type'],
            $input['field_options'] ?? null,
            $input['is_required'] ?? 0
        ]);
        
        echo json_encode([
            "success" => true,
            "field_id" => $pdo->lastInsertId(),
            "message" => "Custom field created successfully"
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to create custom field: " . $e->getMessage()]);
    }
}

function handleDeleteCustomField($pdo, $org_id) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id'])) {
        http_response_code(400);
        echo json_encode(["error" => "Field ID is required"]);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM custom_fields WHERE id = ? AND org_id = ?");
        $stmt->execute([$input['id'], $org_id]);
        
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(["error" => "Custom field not found"]);
            return;
        }
        
        echo json_encode(["success" => true, "message" => "Custom field deleted successfully"]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to delete custom field: " . $e->getMessage()]);
    }
}