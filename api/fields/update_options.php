<?php
// api/fields/update_options.php
// Update options for a custom select field

header("Content-Type: application/json");
require_once '../../config/db.php';
require_once '../../config/permissions.php';

// Check authentication
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

// Get current user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
$stmt->execute([':id' => $_SESSION['user_id']]);
$currentUser = $stmt->fetch();

if (!$currentUser) {
    http_response_code(401);
    echo json_encode(["error" => "User not found"]);
    exit;
}

// Check permission
$pm = getPermissionManager($pdo, $currentUser);
$pm->requirePermission('manage_custom_fields');

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['field_id']) || !isset($data['options'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Missing required fields: field_id, options'
    ]);
    exit;
}

try {
    $fieldId = $data['field_id'];
    $options = $data['options'];
    
    if (!is_array($options)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Options must be an array'
        ]);
        exit;
    }
    
    // Convert array to comma-separated string
    $optionsStr = implode(',', array_map('trim', $options));
    
    // Verify field belongs to user's organization
    $stmt = $pdo->prepare("SELECT id FROM custom_fields WHERE id = :id AND org_id = :org_id");
    $stmt->execute([
        ':id' => $fieldId,
        ':org_id' => $currentUser['org_id']
    ]);
    
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Field not found'
        ]);
        exit;
    }
    
    // Update field
    $stmt = $pdo->prepare("UPDATE custom_fields SET field_options = :options WHERE id = :id");
    $stmt->execute([
        ':options' => $optionsStr,
        ':id' => $fieldId
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Field options updated successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to update field: ' . $e->getMessage()
    ]);
}
?>
