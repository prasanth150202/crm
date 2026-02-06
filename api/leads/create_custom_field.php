<?php
// api/leads/create_custom_field.php
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
$field_type = isset($data['field_type']) ? $data['field_type'] : 'text';
$field_options = isset($data['field_options']) ? $data['field_options'] : '';

if (!$org_id || !$field_name) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required fields"]);
    exit;
}

// Validate field type (align with DB enum + extra common types)
$allowedTypes = ['text', 'textarea', 'date', 'select', 'number', 'email', 'phone'];
if (!in_array($field_type, $allowedTypes)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid field type"]);
    exit;
}

// Map common frontend aliases to DB enum types
if (in_array($field_type, ['number', 'email', 'phone'])) {
    $field_type = 'text'; // Scale back to text if not in DB enum
}
if ($field_type === 'long_text') {
    $field_type = 'textarea';
}

// Check if field already exists (idempotent behavior)
try {
    $stmt = $pdo->prepare("SELECT id FROM custom_fields WHERE org_id = ? AND field_name = ?");
    $stmt->execute([$org_id, $field_name]);
    $existing = $stmt->fetch();

    if ($existing) {
        // Return success if already exists to avoid 409 during repeated imports
        echo json_encode([
            "success" => true,
            "field_id" => $existing['id'],
            "message" => "Custom field already exists"
        ]);
        exit;
    }

    // Save to custom_fields table
    $stmt = $pdo->prepare("INSERT INTO custom_fields (org_id, field_name, field_type, field_options) VALUES (?, ?, ?, ?)");
    $stmt->execute([$org_id, $field_name, $field_type, $field_options]);

    echo json_encode([
        "success" => true,
        "field_id" => $pdo->lastInsertId(),
        "message" => "Custom field created successfully"
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to create custom field: " . $e->getMessage()]);
}