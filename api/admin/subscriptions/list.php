<?php
require_once '../../../includes/api_common.php';
require_once '../admin_check.php';

$status = $_GET['status'] ?? '';
$limit  = max(1, min(200, (int)($_GET['limit'] ?? 50)));
$offset = max(0, (int)($_GET['offset'] ?? 0));

$where  = '';
$params = [];
if ($status !== '') {
    $where  = 'WHERE s.status = ?';
    $params[] = $status;
}

// Detect whether the tax_treatment column exists (added via ALTER TABLE)
$colCheck     = $pdo->query("SHOW COLUMNS FROM plans LIKE 'tax_treatment'");
$taxColSelect = $colCheck->rowCount() > 0 ? ', p.tax_treatment' : ", 'none' AS tax_treatment";

$sql = "SELECT s.*, o.name AS org_name,
               p.name AS plan_name, p.base_price_monthly, p.base_price_yearly
               $taxColSelect, p.currency AS plan_currency
        FROM subscriptions s
        JOIN organizations o ON o.id = s.organization_id
        JOIN plans p ON p.id = s.plan_id
        $where
        ORDER BY s.created_at DESC
        LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$subscriptions = $stmt->fetchAll();

$countSql  = "SELECT COUNT(*) FROM subscriptions s $where";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

ApiResponse::success(['subscriptions' => $subscriptions, 'meta' => ['total' => $total, 'limit' => $limit, 'offset' => $offset]]);
