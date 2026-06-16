<?php
/**
 * POST /api/subscribe/complete.php
 * Public endpoint — verifies Razorpay payment and provisions org + user.
 * Body: { token, org_name, full_name, email, password, phone?,
 *         razorpay_order_id, razorpay_payment_id, razorpay_signature }
 * Returns: { redirect }
 */

require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../includes/admin_helpers.php';

header('Content-Type: application/json');
Security::secureSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::error('Method not allowed', 405);
}

$data = json_decode(file_get_contents('php://input'), true) ?? [];

$token     = trim($data['token'] ?? '');
$org_name  = trim($data['org_name'] ?? '');
$full_name = trim($data['full_name'] ?? '');
$email     = strtolower(trim($data['email'] ?? ''));
$password  = $data['password'] ?? '';
$phone     = trim($data['phone'] ?? '');

$rzp_order_id   = trim($data['razorpay_order_id']   ?? '');
$rzp_payment_id = trim($data['razorpay_payment_id'] ?? '');
$rzp_signature  = trim($data['razorpay_signature']  ?? '');

// ── Basic validation ──────────────────────────────────────────────────────────
if (!$org_name)  ApiResponse::error('Organization name is required', 400);
if (!$full_name) ApiResponse::error('Your name is required', 400);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) ApiResponse::error('Valid email is required', 400);
if (strlen($password) < 6) ApiResponse::error('Password must be at least 6 characters', 400);

// ── Verify token ──────────────────────────────────────────────────────────────
$parts = explode('.', $token);
if (count($parts) !== 2) ApiResponse::error('Invalid subscription link', 400);

[$payloadB64, $sig] = $parts;
$secret      = Env::get('RAZORPAY_KEY_SECRET') ?: 'crm-sub-link-secret-fallback';
$expectedSig = substr(hash_hmac('sha256', $payloadB64, $secret), 0, 32);
if (!hash_equals($expectedSig, $sig)) ApiResponse::error('Invalid or tampered link', 400);

$payload = json_decode(base64_decode(strtr($payloadB64, '-_', '+/')), true);
if (!$payload) ApiResponse::error('Corrupt subscription link', 400);
if (($payload['exp'] ?? 0) < time()) ApiResponse::error('This subscription link has expired', 400);

$plan_id    = (int)($payload['plan_id']    ?? 0);
$billing    = $payload['billing']    ?? 'monthly';
$amount     = (float)($payload['amount']   ?? 0);
$currency   = strtoupper(trim($payload['currency'] ?? 'INR'));
$trial_days = (int)($payload['trial_days'] ?? 0);

if (!$plan_id) ApiResponse::error('Invalid plan in subscription link', 400);

// ── Verify Razorpay payment (skip for free plans) ─────────────────────────────
if ($amount > 0) {
    if (!$rzp_order_id || !$rzp_payment_id || !$rzp_signature) {
        ApiResponse::error('Payment details are missing', 400);
    }
    $keySecret     = Env::get('RAZORPAY_KEY_SECRET');
    $expectedRzpSig = hash_hmac('sha256', $rzp_order_id . '|' . $rzp_payment_id, $keySecret);
    if (!hash_equals($expectedRzpSig, $rzp_signature)) {
        ApiResponse::error('Payment verification failed — please contact support', 400);
    }
}

// ── Check plan ────────────────────────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT id, trial_days FROM plans WHERE id = ? AND is_active = 1");
$stmt->execute([$plan_id]);
$plan = $stmt->fetch();
if (!$plan) ApiResponse::error('The plan is no longer available', 400);

if ($trial_days <= 0) $trial_days = (int)$plan['trial_days'];

// ── Check email not already registered ───────────────────────────────────────
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) ApiResponse::error('This email is already registered. Please log in instead.', 409);

// ── Provision org + user ──────────────────────────────────────────────────────
try {
    $pdo->beginTransaction();

    // 1. Create organization — is_active MUST be 1 or login will reject it
    $org_status = $trial_days > 0 ? 'trial' : 'active';
    $stmt = $pdo->prepare(
        "INSERT INTO organizations (name, plan_tier, status, current_plan_id, is_active) VALUES (?, 'free', ?, ?, 1)"
    );
    $stmt->execute([$org_name, $org_status, $plan_id]);
    $org_id = (int)$pdo->lastInsertId();

    // 2. Create owner user
    $password_hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare(
        "INSERT INTO users (email, full_name, password_hash, role, org_id) VALUES (?, ?, ?, 'owner', ?)"
    );
    $stmt->execute([$email, $full_name, $password_hash, $org_id]);
    $user_id = (int)$pdo->lastInsertId();

    // 3. Link user to org
    $pdo->prepare(
        "INSERT INTO user_organizations (user_id, org_id, role) VALUES (?, ?, 'owner')"
    )->execute([$user_id, $org_id]);

    // 4. Assign default role permissions from plan features
    assignDefaultRolePermissions($pdo, $org_id, $plan_id);

    // 5. Create subscription record
    $sub_status    = $trial_days > 0 ? 'trialing' : 'active';
    $trial_starts  = date('Y-m-d H:i:s');
    $trial_ends    = $trial_days > 0 ? date('Y-m-d H:i:s', strtotime("+{$trial_days} days")) : null;

    $sub_notes    = $rzp_payment_id ? 'Razorpay payment: ' . $rzp_payment_id : null;
    $period_start = date('Y-m-d H:i:s');
    $period_end   = $billing === 'yearly'
        ? date('Y-m-d H:i:s', strtotime('+365 days'))
        : date('Y-m-d H:i:s', strtotime('+30 days'));

    $pdo->prepare(
        "INSERT INTO subscriptions
             (organization_id, plan_id, billing_interval, status,
              trial_starts_at, trial_ends_at, notes,
              current_period_start, current_period_end)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    )->execute([
        $org_id, $plan_id, $billing, $sub_status,
        $trial_starts, $trial_ends, $sub_notes,
        $period_start, $period_end,
    ]);

    $pdo->commit();
} catch (PDOException $e) {
    $pdo->rollBack();
    ApiResponse::error('Account creation failed: ' . $e->getMessage(), 500);
}

// ── Auto-login ────────────────────────────────────────────────────────────────
$_SESSION['user_id']       = $user_id;
$_SESSION['org_id']        = $org_id;
$_SESSION['email']         = $email;
$_SESSION['full_name']     = $full_name;
$_SESSION['role']          = 'owner';
$_SESSION['is_super_admin'] = false;

$projectRoot = rtrim(Env::getProjectRoot(), '/');

ApiResponse::success([
    'redirect'  => $projectRoot . '/dashboard.php',
    'org_id'    => $org_id,
    'user_id'   => $user_id,
    'org_name'  => $org_name,
]);
