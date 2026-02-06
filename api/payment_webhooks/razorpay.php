<?php
// api/payment_webhooks/razorpay.php
// Handles Razorpay payment webhooks for subscription and payment events

require_once __DIR__ . '/../../includes/RazorpayService.php';
require_once __DIR__ . '/../../config/db.php';

// Read and verify the webhook payload
$input = file_get_contents('php://input');
$headers = getallheaders();
$signature = $headers['X-Razorpay-Signature'] ?? '';

$webhookSecret = getenv('RAZORPAY_WEBHOOK_SECRET');
if (!$webhookSecret) {
    http_response_code(500);
    echo 'Webhook secret not configured.';
    exit;
}

try {
    \Razorpay\Api\Utility::verifyWebhookSignature($input, $signature, $webhookSecret);
} catch (Exception $e) {
    http_response_code(400);
    echo 'Invalid signature.';
    exit;
}

$event = json_decode($input, true);
$eventType = $event['event'] ?? '';
$payload = $event['payload'] ?? [];

// Connect to DB
$pdo = getDb();

switch ($eventType) {
    case 'subscription.charged':
    case 'payment.captured':
        // Payment successful, update subscription and store payment details
        $subscriptionId = $payload['subscription']['entity']['id'] ?? null;
        $paymentId = $payload['payment']['entity']['id'] ?? null;
        $amount = $payload['payment']['entity']['amount'] ?? null;
        $userId = null;
        if ($subscriptionId) {
            $stmt = $pdo->prepare('SELECT organization_id FROM subscriptions WHERE razorpay_subscription_id = ?');
            $stmt->execute([$subscriptionId]);
            $org = $stmt->fetch();
            if ($org) {
                $userId = $org['organization_id'];
                // Mark subscription active
                $pdo->prepare('UPDATE subscriptions SET status = ? WHERE razorpay_subscription_id = ?')
                    ->execute(['active', $subscriptionId]);
                // Store payment record
                $pdo->prepare('INSERT INTO payments (user_id, subscription_id, payment_id, amount, status, paid_at) VALUES (?, ?, ?, ?, ?, NOW())')
                    ->execute([$userId, $subscriptionId, $paymentId, $amount, 'success']);
            }
        }
        break;
    case 'subscription.halted':
    case 'payment.failed':
        // Payment failed, mark subscription as past_due
        $subscriptionId = $payload['subscription']['entity']['id'] ?? null;
        if ($subscriptionId) {
            $pdo->prepare('UPDATE subscriptions SET status = ? WHERE razorpay_subscription_id = ?')
                ->execute(['past_due', $subscriptionId]);
        }
        break;
    case 'subscription.cancelled':
        // Subscription cancelled, mark as inactive
        $subscriptionId = $payload['subscription']['entity']['id'] ?? null;
        if ($subscriptionId) {
            $pdo->prepare('UPDATE subscriptions SET status = ? WHERE razorpay_subscription_id = ?')
                ->execute(['cancelled', $subscriptionId]);
        }
        break;
    default:
        // Ignore other events
        break;
}

http_response_code(200);
echo 'Webhook processed.';
