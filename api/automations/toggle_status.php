<?php
/**
 * Toggle Workflow Status (Active/Inactive)
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/permissions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT id, org_id, role, is_super_admin FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'User not found']);
        exit;
    }
    
    if (empty($data['workflow_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Workflow ID is required']);
        exit;
    }
    
    $workflow_id = intval($data['workflow_id']);
    $is_active = isset($data['is_active']) && $data['is_active'] ? 1 : 0;
    
    // Fetch existing workflow
    $stmt = $pdo->prepare("SELECT * FROM automation_workflows WHERE id = ? AND org_id = ?");
    $stmt->execute([$workflow_id, $user['org_id']]);
    $workflow = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$workflow) {
        http_response_code(404);
        echo json_encode(['error' => 'Workflow not found']);
        exit;
    }
    
    // Check permissions
    $pm = getPermissionManager($pdo, $user);
    $isAdmin = in_array($user['role'], ['admin', 'owner']) || $user['is_super_admin'];
    
    $canEditAll = $isAdmin || $pm->hasPermission('edit_all_automations');
    $isOwner = $workflow['created_by'] == $user_id;
    
    if (!$canEditAll && !$isOwner) {
        http_response_code(403);
        echo json_encode(['error' => 'Permission denied']);
        exit;
    }
    
    // Update status
    $stmt = $pdo->prepare("UPDATE automation_workflows SET is_active = ? WHERE id = ?");
    $stmt->execute([$is_active, $workflow_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Workflow status updated',
        'is_active' => $is_active
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update status: ' . $e->getMessage()]);
}
