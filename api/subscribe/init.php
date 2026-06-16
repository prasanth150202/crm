<?php
/**
 * POST /api/subscribe/init.php
 * Public endpoint — creates a Razorpay Order for the subscription payment.
 * Returns: { order_id, amount, currency, key_id, plan_name }
 */

require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/response.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::error('Method not allowed', 405);
}

$data   = json_decode(file_get_contents('php://input'), true) ?? [];
$token  = trim($data['token'] ?? '');

// ── Verify token ─────────────────────────────────────────────────────────────
$parts = explode('.', $token);
if (count($parts) !== 2) ApiResponse::error('Invalid subscription link', 400);

[$payloadB64, $sig] = $parts;
$secret      = Env::get('RAZORPAY_KEY_SECRET') ?: 'crm-sub-link-secret-fallback';
$expectedSig = substr(hash_hmac('sha256', $payloadB64, $secret), 0, 32);
if (!hash_equals($expectedSig, $sig)) ApiResponse::error('Invalid or tampered link', 400);

$payload = json_decode(base64_decode(strtr($payloadB64, '-_', '+/')), true);
if (!$payload) ApiResponse::error('Corrupt subscription link', 400);
if (($payload['exp'] ?? 0) < time()) ApiResponse::error('This subscription link has expired', 400);

$amount        = (float)($payload['amount'] ?? 0);
$currency      = strtoupper(trim($payload['currency'] ?? 'INR'));
$tax_treatment = $payload['tax_treatment'] ?? 'none';
$tax_rate      = (float)($payload['tax_rate'] ?? 18);

// Compute what client actually pays
$tax_amount = 0;
if ($tax_treatment === 'exclusive' && $amount > 0) {
    $tax_amount = round($amount * ($tax_rate / 100), 2);
}
$total_amount = $amount + $tax_amount;

// ── For free / trial-only plans skip Razorpay ────────────────────────────────
if ($total_amount <= 0) {
    ApiResponse::success([
        'order_id'      => null,
        'amount'        => 0,
        'currency'      => $currency,
        'key_id'        => null,
        'plan_name'     => $payload['plan_name'] ?? '',
        'tax_treatment' => $tax_treatment,
        'tax_amount'    => 0,
        'base_amount'   => $amount,
        'free'          => true,
    ]);
}

// ── Create Razorpay Order ─────────────────────────────────────────────────────
$keyId     = Env::get('RAZORPAY_KEY_ID');
$keySecret = Env::get('RAZORPAY_KEY_SECRET');
if (!$keyId || !$keySecret) ApiResponse::error('Payment gateway not configured', 500);

// Amount in paise (INR) — Razorpay expects smallest currency unit
$amountInPaise = (int)round($total_amount * 100);

$receiptId = 'sub_' . uniqid();

$orderData = [
    'amount'          => $amountInPaise,
    'currency'        => $currency,
    'receipt'         => $receiptId,
    'payment_capture' => 1,
];

$ch = curl_init('https://api.razorpay.com/v1/orders');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_USERPWD        => "$keyId:$keySecret",
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => json_encode($orderData),
    CURLOPT_TIMEOUT        => 15,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    $err = json_decode($response, true);
    ApiResponse::error('Payment gateway error: ' . ($err['error']['description'] ?? 'Unknown'), 502);
}

$order = json_decode($response, true);
if (empty($order['id'])) ApiResponse::error('Failed to create payment order', 502);

ApiResponse::success([
    'order_id'      => $order['id'],
    'amount'        => $amountInPaise,
    'currency'      => $currency,
    'key_id'        => $keyId,
    'plan_name'     => $payload['plan_name'] ?? '',
    'tax_treatment' => $tax_treatment,
    'tax_rate'      => $tax_rate,
    'tax_amount'    => $tax_amount,
    'base_amount'   => $amount,
    'total_amount'  => $total_amount,
    'free'          => false,
]);
