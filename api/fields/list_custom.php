<?php
// api/fields/list_custom.php
// List custom fields for the organization

header("Content-Type: application/json");
require_once '../../config/db.php';

// Check authentication
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

// Get current user
$stmt = $pdo->prepare("SELECT org_id FROM users WHERE id = :id");
$stmt->execute([':id' => $_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(401);
    echo json_encode(["error" => "User not found"]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, field_name, field_type, field_options 
        FROM custom_fields 
        WHERE org_id = :org_id AND is_active = 1
        ORDER BY created_at ASC
    ");
    $stmt->execute([':org_id' => $user['org_id']]);
    $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format options for select fields
    foreach ($fields as &$field) {
        if ($field['field_type'] === 'select') {
            $field['options'] = $field['field_options']; // Keep as string or explode if needed, frontend expects string split
        }
    }
    
    echo json_encode([
        'success' => true,
        'fields' => $fields
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch custom fields: ' . $e->getMessage()
    ]);
}
?>
