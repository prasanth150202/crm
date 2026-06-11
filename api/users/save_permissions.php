<?php
// api/users/save_permissions.php
// Save permissions for a specific user (Admin only)

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
$pm->requirePermission('manage_users');

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['user_id']) || !isset($data['permissions'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Missing required fields: user_id, permissions'
    ]);
    exit;
}

try {
    $userId = $data['user_id'];
    $permissions = $data['permissions'];
    
    // Begin transaction
    $pdo->beginTransaction();
    
    // Delete existing permissions for this user
    $stmt = $pdo->prepare("DELETE FROM user_permissions WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $userId]);
    
    // Insert new permissions
    $stmt = $pdo->prepare("
        INSERT INTO user_permissions (user_id, knob_key, is_enabled, granted_by)
        VALUES (:user_id, :knob_key, :is_enabled, :granted_by)
    ");
    
    $savedCount = 0;
    foreach ($permissions as $knobKey => $isEnabled) {
        // Only save enabled permissions to keep table smaller
        if ($isEnabled) {
            $stmt->execute([
                ':user_id' => $userId,
                ':knob_key' => $knobKey,
                ':is_enabled' => 1,
                ':granted_by' => $currentUser['id']
            ]);
            $savedCount++;
        }
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Log activity
    $pm->logActivity('permissions_updated', null, "Updated permissions for user ID: $userId ($savedCount permissions granted)");
    
    echo json_encode([
        'success' => true,
        'message' => 'Permissions saved successfully',
        'count' => $savedCount
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to save permissions: ' . $e->getMessage()
    ]);
}
