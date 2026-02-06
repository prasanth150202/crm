<?php
/**
 * List Meetings API - Debug Version
 * GET /api/meetings/list.php?lead_id=123 (optional)
 */


header('Content-Type: application/json');

try {
    require_once '../../includes/api_common.php';
    
    // Check permission
    if (!$permissionManager->hasFeature('view_meetings')) {
        ApiResponse::error('Permission denied. You do not have access to view meetings.', 403);
    }
    
    $leadId = isset($_GET['lead_id']) ? (int)$_GET['lead_id'] : null;
    $meetingId = isset($_GET['id']) ? (int)$_GET['id'] : null;
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $perPage = isset($_GET['per_page']) ? min(100, max(1, (int)$_GET['per_page'])) : 20;
    $offset = ($page - 1) * $perPage;
    
    // Build query
    $where = "m.org_id = :org_id";
    $params = [':org_id' => $user['org_id']];
    
    if ($leadId) {
        $where .= " AND m.lead_id = :lead_id";
        $params[':lead_id'] = $leadId;
    }
    
    if ($meetingId) {
        $where .= " AND m.id = :meeting_id";
        $params[':meeting_id'] = $meetingId;
    }
    
    // Get total count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM meetings m
        WHERE $where
    ");
    $stmt->execute($params);
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get meetings
    $stmt = $pdo->prepare("
        SELECT 
            m.*,
            l.name as lead_name,
            l.email as lead_email,
            u.full_name as created_by_name
        FROM meetings m
        LEFT JOIN leads l ON m.lead_id = l.id
        LEFT JOIN users u ON m.created_by = u.id
        WHERE $where
        ORDER BY m.meeting_date DESC
        LIMIT :limit OFFSET :offset
    ");
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $meetings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'meetings' => $meetings,
        'pagination' => [
            'total' => (int)$total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage)
        ]
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Exception: ' . $e->getMessage(), 'trace' => $e->getTraceAsString()]);
} catch (Error $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Fatal error: ' . $e->getMessage(), 'trace' => $e->getTraceAsString()]);
}
