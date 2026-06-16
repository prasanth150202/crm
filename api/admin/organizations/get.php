<?php
require_once '../../../includes/api_common.php';
require_once '../admin_check.php';

$org_id = (int)($_GET['id'] ?? 0);
if (!$org_id) ApiResponse::error('Organization ID is required', 400);

$stmt = $pdo->prepare(
    "SELECT o.*, p.name AS plan_name
     FROM organizations o
     LEFT JOIN plans p ON p.id = o.current_plan_id
     WHERE o.id = ?"
);
$stmt->execute([$org_id]);
$org = $stmt->fetch();
if (!$org) ApiResponse::error('Organization not found', 404);

// Active subscription
$stmt = $pdo->prepare(
    "SELECT s.*, p.name AS plan_name
     FROM subscriptions s
     JOIN plans p ON p.id = s.plan_id
     WHERE s.organization_id = ? AND s.status NOT IN ('cancelled','completed')
     ORDER BY s.created_at DESC LIMIT 1"
);
$stmt->execute([$org_id]);
$org['subscription'] = $stmt->fetch() ?: null;

// Members
$stmt = $pdo->prepare(
    "SELECT u.id, u.full_name, u.email, uo.role
     FROM user_organizations uo
     JOIN users u ON u.id = uo.user_id
     WHERE uo.org_id = ?
     ORDER BY uo.role, u.full_name"
);
$stmt->execute([$org_id]);
$org['members'] = $stmt->fetchAll();

// Usage stats
$stmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE org_id = ?");
$stmt->execute([$org_id]);
$org['lead_count'] = (int)$stmt->fetchColumn();

$org['user_count'] = count($org['members']);

ApiResponse::success(['organization' => $org]);
