<?php
// api/admin/feature_knobs/bulk_update.php
// Bulk update role permissions (Admin only)

header("Content-Type: application/json");
require_once '../../../config/db.php';
require_once '../../../config/permissions.php';
require_once '../../../config/feature_knobs.php';

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

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($data['role']) || !isset($data['permissions'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Missing required fields: role, permissions'
    ]);
    exit;
}

try {
    $fkm = getFeatureKnobManager($pdo, $currentUser);
    $org_id = $data['org_id'] ?? null;
    
    $result = $fkm->bulkUpdateRolePermissions(
        $data['role'],
        $data['permissions'],
        $org_id
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'Permissions updated successfully',
        'count' => count($data['permissions'])
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to update permissions: ' . $e->getMessage()
    ]);
}
