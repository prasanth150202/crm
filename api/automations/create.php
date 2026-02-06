<?php
/**
 * Create Automation Workflow
 * Creates a new workflow with triggers and actions
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
    // Fetch user to ensure we have correct permissions and org_id
    $stmt = $pdo->prepare("SELECT id, org_id, role, is_super_admin FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'User not found']);
        exit;
    }
    
    $org_id = $user['org_id'];
    
    // Validate required fields
    // Check for unique name in org
    $checkStmt = $pdo->prepare("SELECT id FROM automation_workflows WHERE org_id = ? AND name = ?");
    $checkStmt->execute([$org_id, $data['name']]);
    if ($checkStmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'A workflow with this name already exists in your organization']);
        exit;
    }
    
    $scope = $data['scope'] ?? 'user';
    $triggers = $data['triggers'] ?? [];
    $actions = $data['actions'] ?? [];
    
    // Get permission manager
    $pm = getPermissionManager($pdo, $user);
    $isAdmin = in_array($user['role'], ['admin', 'owner']) || $user['is_super_admin'];
    
    // Check permissions based on scope
    if (!$isAdmin) {
        if ($scope === 'organization') {
            if (!$pm->hasPermission('create_org_automations')) {
                http_response_code(403);
                echo json_encode(['error' => 'Permission denied. You need create_org_automations permission']);
                exit;
            }
        } else {
            if (!$pm->hasPermission('create_automations')) {
                http_response_code(403);
                echo json_encode(['error' => 'Permission denied. You need create_automations permission']);
                exit;
            }
        }
    }
    
    // Validate scope
    if (!in_array($scope, ['organization', 'user'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid scope. Must be organization or user']);
        exit;
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Create workflow
    $stmt = $pdo->prepare("INSERT INTO automation_workflows (org_id, name, description, scope, created_by, is_shared, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $description = $data['description'] ?? '';
    $is_shared = isset($data['is_shared']) && $data['is_shared'] ? 1 : 0;
    $is_active = isset($data['is_active']) && $data['is_active'] ? 1 : 0;
    
    $stmt->execute([$org_id, $data['name'], $description, $scope, $user_id, $is_shared, $is_active]);
    $workflow_id = $pdo->lastInsertId();
    
    // Create triggers
    if (!empty($triggers)) {
        $triggerStmt = $pdo->prepare("INSERT INTO automation_triggers (workflow_id, trigger_type, trigger_config) VALUES (?, ?, ?)");
        
        foreach ($triggers as $trigger) {
            if (empty($trigger['trigger_type'])) continue;
            
            $trigger_config = isset($trigger['config']) ? json_encode($trigger['config']) : null;
            $triggerStmt->execute([$workflow_id, $trigger['trigger_type'], $trigger_config]);
        }
    }
    
    // Create actions
    if (!empty($actions)) {
        $actionStmt = $pdo->prepare("INSERT INTO automation_actions (workflow_id, action_type, action_config, execution_order) VALUES (?, ?, ?, ?)");
        
        $order = 0;
        foreach ($actions as $action) {
            if (empty($action['action_type'])) continue;
            
            $action_config = isset($action['config']) ? json_encode($action['config']) : null;
            $actionStmt->execute([$workflow_id, $action['action_type'], $action_config, $order]);
            $order++;
        }
    }
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'workflow_id' => $workflow_id,
        'message' => 'Workflow created successfully'
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create workflow: ' . $e->getMessage()]);
}
