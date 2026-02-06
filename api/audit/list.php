<?php
/**
 * Audit Log List API
 * GET /api/audit/list.php
 */

require_once '../../includes/api_common.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ApiResponse::error('Method not allowed', 405);
}

try {
    // Check permission
    if (!$permissionManager->hasFeature('view_activity_log')) {
        ApiResponse::error('Permission denied. You do not have access to view activity logs.', 403);
    }

    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $perPage = isset($_GET['per_page']) ? min(100, max(1, (int)$_GET['per_page'])) : 50;
    $offset = ($page - 1) * $perPage;
    
    $actionType = isset($_GET['action_type']) ? $_GET['action_type'] : null;
    $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;

    // Build query
    $where = "al.org_id = :org_id";
    $params = [':org_id' => $user['org_id']];

    if ($actionType) {
        $where .= " AND al.action_type = :action_type";
        $params[':action_type'] = $actionType;
    }

    if ($userId) {
        $where .= " AND al.user_id = :user_id";
        $params[':user_id'] = $userId;
    }

    // Get total count
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM activity_log al WHERE $where");
    $countStmt->execute($params);
    $total = $countStmt->fetchColumn();

    // Get logs
    $stmt = $pdo->prepare("
        SELECT 
            al.*,
            u.full_name as user_name,
            l.name as lead_name
        FROM activity_log al
        LEFT JOIN users u ON al.user_id = u.id
        LEFT JOIN leads l ON al.lead_id = l.id
        WHERE $where
        ORDER BY al.created_at DESC
        LIMIT :limit OFFSET :offset
    ");

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $logs,
        'pagination' => [
            'total' => (int)$total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage)
        ]
    ]);

} catch (Exception $e) {
    ApiResponse::error('Server error: ' . $e->getMessage(), 500);
}
