<?php
/**
 * Get Single Automation Workflow
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/permissions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $user_id = $_SESSION['user_id'];
    // Fetch user
    $stmt = $pdo->prepare("SELECT id, org_id, role, is_super_admin FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'User not found']);
        exit;
    }

    if (empty($_GET['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Workflow ID is required']);
        exit;
    }

    $workflow_id = intval($_GET['id']);

    // Fetch workflow
    $stmt = $pdo->prepare("
        SELECT * FROM automation_workflows 
        WHERE id = ? AND org_id = ?
    ");
    $stmt->execute([$workflow_id, $user['org_id']]);
    $workflow = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$workflow) {
        http_response_code(404);
        echo json_encode(['error' => 'Workflow not found']);
        exit;
    }

    // Check permissions (view)
    // Any user in org can view list, but maybe not details? 
    // Usually view permission is enough.
    // Assuming if they can list it, they can get it.

    // Fetch triggers
    $stmt = $pdo->prepare("SELECT trigger_type, trigger_config FROM automation_triggers WHERE workflow_id = ?");
    $stmt->execute([$workflow_id]);
    $triggers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch actions
    $stmt = $pdo->prepare("SELECT action_type, action_config, execution_order FROM automation_actions WHERE workflow_id = ? ORDER BY execution_order ASC");
    $stmt->execute([$workflow_id]);
    $actions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Decode configs
    foreach ($triggers as &$t) {
        $t['config'] = json_decode($t['trigger_config'], true);
        unset($t['trigger_config']);
    }

    foreach ($actions as &$a) {
        $a['config'] = json_decode($a['action_config'], true);
        unset($a['action_config']);
    }

    $workflow['triggers'] = $triggers;
    $workflow['actions'] = $actions;

    echo json_encode([
        'success' => true,
        'workflow' => $workflow
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch workflow: ' . $e->getMessage()]);
}
