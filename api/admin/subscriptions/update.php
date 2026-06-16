<?php
require_once '../../../includes/api_common.php';
require_once '../admin_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::error('Method not allowed', 405);
}

$data  = json_decode(file_get_contents('php://input'), true) ?? [];
$id    = (int)($data['id'] ?? 0);
if (!$id) ApiResponse::error('Subscription ID is required', 400);

$fields  = [];
$params  = [];

$allowed_statuses = ['trialing', 'active', 'halted', 'cancelled', 'past_due', 'completed'];
if (isset($data['status']) && in_array($data['status'], $allowed_statuses)) {
    $fields[] = 'status = ?';
    $params[] = $data['status'];
}
if (isset($data['plan_id'])) {
    $fields[] = 'plan_id = ?';
    $params[] = (int)$data['plan_id'];
}
if (isset($data['billing_interval']) && in_array($data['billing_interval'], ['monthly', 'yearly'])) {
    $fields[] = 'billing_interval = ?';
    $params[] = $data['billing_interval'];
}
if (isset($data['current_period_start'])) {
    $fields[] = 'current_period_start = ?';
    $params[] = $data['current_period_start'] ?: null;
}
if (isset($data['current_period_end'])) {
    $fields[] = 'current_period_end = ?';
    $params[] = $data['current_period_end'] ?: null;
}
if (isset($data['trial_starts_at'])) {
    $fields[] = 'trial_starts_at = ?';
    $params[] = $data['trial_starts_at'] ?: null;
}
if (isset($data['trial_ends_at'])) {
    $fields[] = 'trial_ends_at = ?';
    $params[] = $data['trial_ends_at'] ?: null;
}
if (isset($data['notes'])) {
    $fields[] = 'notes = ?';
    $params[] = trim($data['notes']) ?: null;
}

if (!$fields) ApiResponse::error('No fields to update', 400);

$fields[] = 'managed_by = ?';
$params[] = $currentUser['id'];
$params[] = $id;

$sql = "UPDATE subscriptions SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ?";
$pdo->prepare($sql)->execute($params);

// If plan changed, sync org.current_plan_id
if (isset($data['plan_id'])) {
    $stmt = $pdo->prepare("SELECT organization_id FROM subscriptions WHERE id = ?");
    $stmt->execute([$id]);
    $orgId = (int)$stmt->fetchColumn();
    if ($orgId) {
        $pdo->prepare("UPDATE organizations SET current_plan_id = ? WHERE id = ?")
            ->execute([(int)$data['plan_id'], $orgId]);
    }
}

ApiResponse::success(['message' => 'Subscription updated']);
