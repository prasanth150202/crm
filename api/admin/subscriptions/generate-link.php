<?php
require_once '../../../includes/api_common.php';
require_once '../admin_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::error('Method not allowed', 405);
}

$data = json_decode(file_get_contents('php://input'), true) ?? [];

$plan_id       = (int)($data['plan_id'] ?? 0);
$billing       = in_array($data['billing'] ?? '', ['monthly', 'yearly']) ? $data['billing'] : 'monthly';
$amount        = max(0, (float)($data['amount'] ?? 0));
$currency      = strtoupper(trim($data['currency'] ?? 'INR'));
$trial_days    = max(0, (int)($data['trial_days'] ?? 0));
$expires_days  = max(1, min(30, (int)($data['expires_days'] ?? 7)));
$max_users     = max(1, (int)($data['max_users'] ?? 1));
$message       = trim($data['message'] ?? '');
$tax_treatment = in_array($data['tax_treatment'] ?? '', ['none','exclusive','inclusive']) ? $data['tax_treatment'] : 'none';
$tax_rate      = 18;

if (!$plan_id) ApiResponse::error('Plan ID is required', 400);

$stmt = $pdo->prepare("SELECT id, name, currency FROM plans WHERE id = ? AND is_active = 1");
$stmt->execute([$plan_id]);
$plan = $stmt->fetch();
if (!$plan) ApiResponse::error('Invalid or inactive plan', 400);

$payload = [
    'v'             => 1,
    'plan_id'       => $plan_id,
    'plan_name'     => $plan['name'],
    'billing'       => $billing,
    'amount'        => $amount,
    'currency'      => $currency ?: $plan['currency'],
    'trial_days'    => $trial_days,
    'max_users'     => $max_users,
    'msg'           => $message,
    'tax_treatment' => $tax_treatment,
    'tax_rate'      => $tax_rate,
    'exp'           => time() + ($expires_days * 86400),
    'by'            => $currentUser['id'],
];

$secret     = Env::get('RAZORPAY_KEY_SECRET') ?: 'crm-sub-link-secret-fallback';
$payloadB64 = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
$sig        = substr(hash_hmac('sha256', $payloadB64, $secret), 0, 32);
$token      = $payloadB64 . '.' . $sig;

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $scheme . '://' . $_SERVER['HTTP_HOST'];
$root   = rtrim(Env::getProjectRoot(), '/');
$url    = $host . $root . '/subscribe.php?t=' . urlencode($token);

ApiResponse::success([
    'url'        => $url,
    'expires_at' => date('Y-m-d H:i:s', $payload['exp']),
]);
