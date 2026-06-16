<?php
require_once '../../../includes/api_common.php';
require_once '../admin_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::error('Method not allowed', 405);
}

$data   = json_decode(file_get_contents('php://input'), true) ?? [];
$org_id = (int)($data['id'] ?? 0);
if (!$org_id) ApiResponse::error('Organization ID is required', 400);

$fields = [];
$params = [];

if (isset($data['name']) && trim($data['name']) !== '') {
    $fields[] = 'name = ?';
    $params[] = trim($data['name']);
}
if (isset($data['status']) && in_array($data['status'], ['active', 'suspended', 'trial'])) {
    $fields[] = 'status = ?';
    $params[] = $data['status'];
    // Keep is_active in sync: suspended = 0, everything else = 1
    $fields[] = 'is_active = ?';
    $params[] = ($data['status'] === 'suspended') ? 0 : 1;
}
if (isset($data['current_plan_id'])) {
    $fields[] = 'current_plan_id = ?';
    $params[] = (int)$data['current_plan_id'] ?: null;
}

if (!$fields) ApiResponse::error('No fields to update', 400);

$params[] = $org_id;
$pdo->prepare("UPDATE organizations SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ?")
    ->execute($params);

// If plan changed, also update active subscription
if (isset($data['current_plan_id']) && $data['current_plan_id']) {
    $pdo->prepare(
        "UPDATE subscriptions SET plan_id = ?, updated_at = NOW()
         WHERE organization_id = ? AND status NOT IN ('cancelled','completed')"
    )->execute([(int)$data['current_plan_id'], $org_id]);
}

ApiResponse::success(['message' => 'Organization updated']);
