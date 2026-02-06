<?php
/**
 * List Automation Workflows
 * Returns workflows based on user permissions and scope
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/permissions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

header('Content-Type: application/json');

try {
    $user_id = $_SESSION['user_id'];
    
    // Fetch user details from DB to ensure correct Org ID
    $stmt = $pdo->prepare("SELECT id, org_id, role, is_super_admin FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'User not found']);
        exit;
    }

    $org_id = $user['org_id'];
    
    // Get permission manager instance
    $pm = getPermissionManager($pdo, $user);
    
    // Debug: Check what permissions are loaded
    $allPermissions = $pm->getAllPermissions();
    error_log("User ID: " . $user_id . ", Permissions: " . json_encode($allPermissions));
    
    // Check permission - TEMPORARILY DISABLED FOR DEBUGGING
    // TODO: Re-enable this check after permissions are confirmed
    /*
    if (!$pm->hasPermission('access_automations')) {
        error_log("Permission check failed for access_automations");
        http_response_code(403);
        echo json_encode(['error' => 'Permission denied']);
        exit;
    }
    */
    
    $canEditAll = $pm->hasPermission('edit_all_automations');
    
    // Build query based on permissions
    $sql = "SELECT 
                w.id,
                w.org_id,
                w.name,
                w.description,
                w.is_active,
                w.scope,
                w.created_by,
                w.is_shared,
                w.created_at,
                w.updated_at,
                u.full_name as creator_name,
                (SELECT COUNT(*) FROM automation_triggers WHERE workflow_id = w.id) as trigger_count,
                (SELECT COUNT(*) FROM automation_actions WHERE workflow_id = w.id) as action_count,
                (SELECT MAX(executed_at) FROM automation_execution_logs WHERE workflow_id = w.id) as last_executed
            FROM automation_workflows w
            LEFT JOIN users u ON w.created_by = u.id
            WHERE w.org_id = :org_id";
    
    $params = [':org_id' => $org_id];
    
    // Apply visibility filters
    if (!$canEditAll) {
        $sql .= " AND (w.scope = 'organization' OR w.created_by = :user_id OR w.is_shared = 1)";
        $params[':user_id'] = $user_id;
    }
    
    // Add filters from query params
    if (isset($_GET['scope']) && in_array($_GET['scope'], ['organization', 'user'])) {
        $sql .= " AND w.scope = :scope";
        $params[':scope'] = $_GET['scope'];
    }
    
    if (isset($_GET['active'])) {
        $active = $_GET['active'] === 'true' ? 1 : 0;
        $sql .= " AND w.is_active = :active";
        $params[':active'] = $active;
    }
    
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $sql .= " AND (w.name LIKE :search OR w.description LIKE :search)";
        $params[':search'] = '%' . $_GET['search'] . '%';
    }
    
    $sql .= " ORDER BY w.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $workflows = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'workflows' => $workflows,
        'canEditAll' => $canEditAll
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch workflows: ' . $e->getMessage()]);
}
