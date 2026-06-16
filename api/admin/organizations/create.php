<?php
require_once '../../../includes/api_common.php';
require_once '../admin_check.php';
require_once __DIR__ . '/../../../includes/admin_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::error('Method not allowed', 405);
}

$data             = json_decode(file_get_contents('php://input'), true) ?? [];
$org_name         = trim($data['org_name'] ?? '');
$owner_email      = trim($data['owner_email'] ?? '');
$owner_name       = trim($data['owner_name'] ?? '');
$owner_password   = $data['owner_password'] ?? '';
$plan_id          = (int)($data['plan_id'] ?? 0);
$billing_interval = in_array($data['billing_interval'] ?? '', ['monthly', 'yearly']) ? $data['billing_interval'] : 'monthly';
$status           = in_array($data['status'] ?? '', ['active', 'suspended', 'trial']) ? $data['status'] : 'active';
$trial_days       = (int)($data['trial_days'] ?? 14);

if ($org_name === '')      ApiResponse::error('Organization name is required', 400);
if ($owner_email === '')   ApiResponse::error('Owner email is required', 400);
if ($owner_name === '')    ApiResponse::error('Owner name is required', 400);
if (strlen($owner_password) < 6) ApiResponse::error('Password must be at least 6 characters', 400);
if (!$plan_id)             ApiResponse::error('Plan ID is required', 400);

// Validate email
if (!filter_var($owner_email, FILTER_VALIDATE_EMAIL)) {
    ApiResponse::error('Invalid email address', 400);
}

// Check plan exists
$stmt = $pdo->prepare("SELECT id, trial_days FROM plans WHERE id = ? AND is_active = 1");
$stmt->execute([$plan_id]);
$plan = $stmt->fetch();
if (!$plan) ApiResponse::error('Invalid or inactive plan', 400);

// Use plan trial_days if not overridden
if ($trial_days <= 0) $trial_days = (int)$plan['trial_days'];

// Check if email already in use
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$owner_email]);
if ($stmt->fetch()) {
    ApiResponse::error('Email is already registered', 409);
}

try {
    $pdo->beginTransaction();

    // 1. Create organization
    $stmt = $pdo->prepare(
        "INSERT INTO organizations (name, plan_tier, status, current_plan_id) VALUES (?, 'free', ?, ?)"
    );
    $stmt->execute([$org_name, $status, $plan_id]);
    $org_id = (int)$pdo->lastInsertId();

    // 2. Create owner user
    $password_hash = password_hash($owner_password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare(
        "INSERT INTO users (email, full_name, password_hash, role, org_id) VALUES (?, ?, ?, 'owner', ?)"
    );
    $stmt->execute([$owner_email, $owner_name, $password_hash, $org_id]);
    $user_id = (int)$pdo->lastInsertId();

    // 3. Link user to org
    $pdo->prepare(
        "INSERT INTO user_organizations (user_id, org_id, role) VALUES (?, ?, 'owner')"
    )->execute([$user_id, $org_id]);

    // 4. Assign default role permissions from plan features
    assignDefaultRolePermissions($pdo, $org_id, $plan_id);

    // 5. Create subscription
    $sub_status = ($status === 'trial') ? 'trialing' : 'active';
    $trial_starts = date('Y-m-d H:i:s');
    $trial_ends   = date('Y-m-d H:i:s', strtotime("+$trial_days days"));

    $pdo->prepare(
        "INSERT INTO subscriptions
            (organization_id, plan_id, billing_interval, status, trial_starts_at, trial_ends_at, managed_by)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    )->execute([$org_id, $plan_id, $billing_interval, $sub_status, $trial_starts, $trial_ends, $currentUser['id']]);

    $pdo->commit();

    ApiResponse::success([
        'org_id'  => $org_id,
        'user_id' => $user_id,
        'message' => 'Organization and owner created successfully'
    ]);
} catch (PDOException $e) {
    $pdo->rollBack();
    ApiResponse::error('Database error: ' . $e->getMessage(), 500);
}
