<?php
/**
 * Update Automation Workflow
 * Updates workflow properties, triggers, and actions
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
    
    if (empty($data['workflow_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Workflow ID is required']);
        exit;
    }
    
    $workflow_id = intval($data['workflow_id']);
    
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
        echo json_encode(['error' => 'Permission denied. You can only edit your own workflows']);
        exit;
    }

    // Check for unique name in org (excluding self)
    if (isset($data['name'])) {
        $checkStmt = $pdo->prepare("SELECT id FROM automation_workflows WHERE org_id = ? AND name = ? AND id != ?");
        $checkStmt->execute([$user['org_id'], $data['name'], $workflow_id]);
        if ($checkStmt->fetch()) {
            http_response_code(409);
            echo json_encode(['error' => 'A workflow with this name already exists in your organization']);
            exit;
        }
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Update workflow
    $updates = [];
    $params = [];
    
    if (isset($data['name'])) {
        $updates[] = 'name = ?';
        $params[] = $data['name'];
    }
    
    if (isset($data['description'])) {
        $updates[] = 'description = ?';
        $params[] = $data['description'];
    }
    
    if (isset($data['is_active'])) {
        $updates[] = 'is_active = ?';
        $params[] = $data['is_active'] ? 1 : 0;
    }
    
    if (isset($data['is_shared'])) {
        $updates[] = 'is_shared = ?';
        $params[] = $data['is_shared'] ? 1 : 0;
    }
    
    if (!empty($updates)) {
        $sql = "UPDATE automation_workflows SET " . implode(', ', $updates) . " WHERE id = ?";
        $params[] = $workflow_id;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }
    
    // Update triggers if provided
    if (isset($data['triggers'])) {
        // Delete existing triggers
        $pdo->prepare("DELETE FROM automation_triggers WHERE workflow_id = ?")->execute([$workflow_id]);
        
        // Insert new triggers
        if (!empty($data['triggers'])) {
            $triggerStmt = $pdo->prepare("INSERT INTO automation_triggers (workflow_id, trigger_type, trigger_config) VALUES (?, ?, ?)");
            
            foreach ($data['triggers'] as $trigger) {
                if (empty($trigger['trigger_type'])) continue;
                
                $trigger_config = isset($trigger['config']) ? json_encode($trigger['config']) : null;
                $triggerStmt->execute([$workflow_id, $trigger['trigger_type'], $trigger_config]);
            }
        }
    }
    
    // Update actions if provided
    if (isset($data['actions'])) {
        // Delete existing actions
        $pdo->prepare("DELETE FROM automation_actions WHERE workflow_id = ?")->execute([$workflow_id]);
        
        // Insert new actions
        if (!empty($data['actions'])) {
            $actionStmt = $pdo->prepare("INSERT INTO automation_actions (workflow_id, action_type, action_config, execution_order) VALUES (?, ?, ?, ?)");
            
            $order = 0;
            foreach ($data['actions'] as $action) {
                if (empty($action['action_type'])) continue;
                
                $action_config = isset($action['config']) ? json_encode($action['config']) : null;
                $actionStmt->execute([$workflow_id, $action['action_type'], $action_config, $order]);
                $order++;
            }
        }
    }
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Workflow updated successfully'
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update workflow: ' . $e->getMessage()]);
}
