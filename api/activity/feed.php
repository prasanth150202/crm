<?php
// api/activity/feed.php
// Get activity feed for organization

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

// Get query parameters
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$lead_id = isset($_GET['lead_id']) ? (int)$_GET['lead_id'] : null;

try {
    $where = "a.org_id = :org_id";
    $params = [':org_id' => $currentUser['org_id']];
    
    if ($lead_id) {
        $where .= " AND a.lead_id = :lead_id";
        $params[':lead_id'] = $lead_id;
    }
    
    // Get activity feed
    $stmt = $pdo->prepare("
        SELECT 
            a.*,
            u.full_name as user_name,
            u.email as user_email,
            u.avatar_url as user_avatar,
            l.name as lead_name,
            l.email as lead_email
        FROM activity_log a
        LEFT JOIN users u ON a.user_id = u.id
        LEFT JOIN leads l ON a.lead_id = l.id
        WHERE $where
        ORDER BY a.created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $activities = $stmt->fetchAll();
    
    // Get total count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM activity_log a
        WHERE $where
    ");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $total = $stmt->fetch()['total'];
    
    // Sanitize activities output to prevent XSS
    $sanitized_activities = array_map(function($activity) {
        foreach ($activity as $key => $value) {
            if (is_string($value)) {
                $activity[$key] = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
        }
        return $activity;
    }, $activities);
    echo json_encode([
        'success' => true,
        'activities' => $sanitized_activities,
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    // Do not echo raw exception message to user
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch activity feed.'
    ]);
}
