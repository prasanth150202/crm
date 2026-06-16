<?php
require_once '../../../includes/api_common.php';
require_once '../admin_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::error('Method not allowed', 405);
}

$data             = json_decode(file_get_contents('php://input'), true) ?? [];
$organization_id  = (int)($data['organization_id'] ?? 0);
$plan_id          = (int)($data['plan_id'] ?? 0);
$billing_interval = in_array($data['billing_interval'] ?? '', ['monthly', 'yearly']) ? $data['billing_interval'] : 'monthly';
$status           = in_array($data['status'] ?? '', ['trialing', 'active', 'halted', 'cancelled', 'past_due', 'completed']) ? $data['status'] : 'trialing';
$trial_starts_at  = !empty($data['trial_starts_at']) ? $data['trial_starts_at'] : null;
$trial_ends_at    = !empty($data['trial_ends_at']) ? $data['trial_ends_at'] : null;
$current_period_start = !empty($data['current_period_start']) ? $data['current_period_start'] : null;
$current_period_end   = !empty($data['current_period_end']) ? $data['current_period_end'] : null;
$notes            = trim($data['notes'] ?? '');

if (!$organization_id) ApiResponse::error('organization_id is required', 400);
if (!$plan_id)         ApiResponse::error('plan_id is required', 400);

// Compute trial end from plan.trial_days if not provided but status is trialing
if ($status === 'trialing' && !$trial_ends_at) {
    $stmt = $pdo->prepare("SELECT trial_days FROM plans WHERE id = ?");
    $stmt->execute([$plan_id]);
    $trialDays = (int)($stmt->fetchColumn() ?: 14);
    if (!$trial_starts_at) $trial_starts_at = date('Y-m-d H:i:s');
    $trial_ends_at = date('Y-m-d H:i:s', strtotime($trial_starts_at . " + $trialDays days"));
}

$stmt = $pdo->prepare(
    "INSERT INTO subscriptions
        (organization_id, plan_id, billing_interval, status,
         trial_starts_at, trial_ends_at, current_period_start, current_period_end, notes, managed_by)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
);
$stmt->execute([
    $organization_id, $plan_id, $billing_interval, $status,
    $trial_starts_at, $trial_ends_at, $current_period_start, $current_period_end,
    $notes ?: null, $currentUser['id']
]);

// Update org.current_plan_id
$pdo->prepare("UPDATE organizations SET current_plan_id = ? WHERE id = ?")
    ->execute([$plan_id, $organization_id]);

ApiResponse::success(['subscription_id' => (int)$pdo->lastInsertId(), 'message' => 'Subscription created']);
