<?php
require_once '../../../includes/api_common.php';
require_once '../admin_check.php';

$org_id = (int)($_GET['org_id'] ?? 0);
$stage  = trim($_GET['stage'] ?? '');
$search = trim($_GET['search'] ?? '');
$limit  = max(1, min(200, (int)($_GET['limit'] ?? 50)));
$offset = max(0, (int)($_GET['offset'] ?? 0));

$conditions = [];
$params     = [];

if ($org_id) {
    $conditions[] = 'l.org_id = ?';
    $params[]     = $org_id;
}
if ($stage !== '') {
    $conditions[] = 'l.stage_id = ?';
    $params[]     = $stage;
}
if ($search !== '') {
    $conditions[] = '(l.name LIKE ? OR l.email LIKE ? OR l.company LIKE ?)';
    $s = "%$search%";
    $params[]     = $s;
    $params[]     = $s;
    $params[]     = $s;
}

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$sql = "SELECT l.id, l.name, l.email, l.phone, l.company, l.stage_id, l.org_id,
               l.created_at, o.name AS org_name,
               u.full_name AS assigned_to_name
        FROM leads l
        JOIN organizations o ON o.id = l.org_id
        LEFT JOIN users u ON u.id = l.assigned_to
        $where
        ORDER BY l.created_at DESC
        LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$leads = $stmt->fetchAll();

$countSql  = "SELECT COUNT(*) FROM leads l $where";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

ApiResponse::success(['leads' => $leads, 'meta' => ['total' => $total, 'limit' => $limit, 'offset' => $offset]]);
