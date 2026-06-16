<?php
require_once '../../../includes/api_common.php';
require_once '../admin_check.php';

$status = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');
$limit  = max(1, min(200, (int)($_GET['limit'] ?? 50)));
$offset = max(0, (int)($_GET['offset'] ?? 0));

$conditions = [];
$params     = [];

if ($status !== '') {
    $conditions[] = 'o.status = ?';
    $params[]     = $status;
}
if ($search !== '') {
    $conditions[] = '(o.name LIKE ? OR u.email LIKE ?)';
    $params[]     = "%$search%";
    $params[]     = "%$search%";
}

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$sql = "SELECT o.id, o.name, o.status, o.is_active, o.created_at, o.current_plan_id,
               p.name AS plan_name,
               s.status AS subscription_status, s.trial_ends_at,
               owner.email AS owner_email, owner.full_name AS owner_name,
               (SELECT COUNT(*) FROM user_organizations uo WHERE uo.org_id = o.id) AS user_count,
               (SELECT COUNT(*) FROM leads l WHERE l.org_id = o.id) AS lead_count
        FROM organizations o
        LEFT JOIN plans p ON p.id = o.current_plan_id
        LEFT JOIN subscriptions s ON s.organization_id = o.id AND s.status NOT IN ('cancelled','completed')
        LEFT JOIN user_organizations uoo ON uoo.org_id = o.id AND uoo.role = 'owner'
        LEFT JOIN users owner ON owner.id = uoo.user_id
        $where
        GROUP BY o.id
        ORDER BY o.created_at DESC
        LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orgs = $stmt->fetchAll();

$countSql  = "SELECT COUNT(DISTINCT o.id) FROM organizations o
              LEFT JOIN user_organizations uoo ON uoo.org_id = o.id AND uoo.role = 'owner'
              LEFT JOIN users u ON u.id = uoo.user_id
              $where";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

ApiResponse::success(['organizations' => $orgs, 'meta' => ['total' => $total, 'limit' => $limit, 'offset' => $offset]]);
