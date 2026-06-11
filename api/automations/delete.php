<?php
/**
 * Delete Automation Workflow
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
    
    // Check permissions
    $pm = getPermissionManager($pdo, $user);
    $isAdmin = in_array($user['role'], ['admin', 'owner']) || $user['is_super_admin'];
    
    // Check if user has delete permission
    if (!$isAdmin && !$pm->hasPermission('delete_automations')) {
        http_response_code(403);
        echo json_encode(['error' => 'Permission denied']);
        exit;
    }
    
    // Fetch existing workflow
    $stmt = $pdo->prepare("SELECT * FROM automation_workflows WHERE id = ? AND org_id = ?");
    $stmt->execute([$workflow_id, $user['org_id']]);
    $workflow = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$workflow) {
        http_response_code(404);
        echo json_encode(['error' => 'Workflow not found']);
        exit;
    }
    
    // Check if user can delete (owner or has edit_all permission or admin)
    $canEditAll = $isAdmin || $pm->hasPermission('edit_all_automations');
    $isOwner = $workflow['created_by'] == $user_id;
    
    if (!$canEditAll && !$isOwner) {
        http_response_code(403);
        echo json_encode(['error' => 'Permission denied. You can only delete your own workflows']);
        exit;
    }
    
    // Delete workflow (CASCADE will delete triggers and actions)
    $stmt = $pdo->prepare("DELETE FROM automation_workflows WHERE id = ?");
    $stmt->execute([$workflow_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Workflow deleted successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to delete workflow: ' . $e->getMessage()]);
}
