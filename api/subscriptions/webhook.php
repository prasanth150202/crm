<?php
/**
 * Razorpay Webhook API
 * POST /api/subscriptions/webhook.php
 *
 * Receives and processes webhook events from Razorpay.
 * Updates subscription status and handles billing events.
 */

// This file typically doesn't use api_common.php directly as it needs to be accessible
// without authentication for Razorpay to send events. Authentication is handled by
// verifying the webhook signature.

require_once '../../includes/RazorpayService.php'; // Our Razorpay integration service
require_once '../../config/env.php'; // For RAZORPAY_WEBHOOK_SECRET
// require_once '../../config/logger.php'; // Uncomment if you have a logger for webhook events
// require_once '../../config/response.php'; // Assuming ApiResponse is defined here or globally

// Define a basic ApiResponse class if not already included via config/response.php
if (!class_exists('ApiResponse')) {
    class ApiResponse {
        public static function success($data = [], $status = 200) {
            http_response_code($status);
            echo json_encode(['success' => true, 'data' => $data]);
            exit();
        }
        public static function error($message, $status = 400) {
            http_response_code($status);
            echo json_encode(['success' => false, 'error' => $message]);
            exit();
        }
    }
}

header('Content-Type: application/json');

$webhookSecret = Env::get('RAZORPAY_WEBHOOK_SECRET');
if (!$webhookSecret) {
    ApiResponse::error('Webhook secret not configured.', 500);
}

$payload = file_get_contents('php://input');
$razorpaySignature = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';

if (empty($payload) || empty($razorpaySignature)) {
    ApiResponse::error('Missing payload or signature.', 400);
}

try {
    $razorpayService = new RazorpayService();
    $isValidSignature = $razorpayService->verifyWebhookSignature($razorpaySignature, $payload, $webhookSecret);

    if (!$isValidSignature) {
        ApiResponse::error('Webhook signature verification failed.', 401);
    }

    $event = json_decode($payload, true);
    $eventType = $event['event'] ?? null;
    $entity = $event['payload']['subscription'] ?? $event['payload']['payment'] ?? null; // Get the relevant entity

    if (!$eventType || !$entity) {
        ApiResponse::error('Invalid webhook event structure.', 400);
    }

    // Attempt to establish PDO connection for database operations
    // This part is duplicated from migrations/seeders but is necessary as api_common.php is not included
    $dbConfigPath = __DIR__ . '/../../config/db.php';
    if (!file_exists($dbConfigPath)) {
        ApiResponse::error("Error: Database configuration file not found.", 500);
    }
    require_once $dbConfigPath; 
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        ApiResponse::error("Error: PDO connection could not be established for webhook.", 500);
    }

    // Log webhook event for debugging and audit purposes (optional)
    // if (function_exists('logMessage')) {
    //     logMessage("Razorpay Webhook: Event {$eventType} received. Entity ID: " . ($entity['entity']['id'] ?? 'N/A'), 'info');
    // }

    // Use a transaction for database updates to ensure atomicity
    try {
        $pdo->beginTransaction();

        switch ($eventType) {
            case 'subscription.activated':
            case 'subscription.created':
            case 'subscription.halted':
            case 'subscription.cancelled':
            case 'subscription.charged': // Occurs when a payment is successful for a subscription
            case 'subscription.completed':
            case 'subscription.expired':
            case 'payment.failed':
                $subscriptionId = $entity['entity']['id'] ?? null;
                $newStatus = $entity['entity']['status'] ?? 'unknown'; // Razorpay status

                if (!$subscriptionId) {
                    throw new Exception("Webhook payload missing subscription ID.");
                }

                // Map Razorpay status to your internal ENUM status
                $internalStatus = $newStatus;
                if ($newStatus == 'created' || $newStatus == 'authenticated') {
                    $internalStatus = 'trialing'; // Assume new subscription starts as trialing
                } elseif ($newStatus == 'active' && $entity['entity']['trial_end'] > time()) {
                    $internalStatus = 'trialing'; // Still within trial period
                } elseif ($newStatus == 'active' && $entity['entity']['trial_end'] <= time()) {
                    $internalStatus = 'active';
                } elseif ($newStatus == 'pending') {
                    $internalStatus = 'past_due';
                }

                $updateFields = [
                    'status' => $internalStatus,
                    'current_period_start' => date('Y-m-d H:i:s', $entity['entity']['current_period_start'] ?? time()),
                    'current_period_end' => date('Y-m-d H:i:s', $entity['entity']['current_period_end'] ?? time()),
                    'trial_starts_at' => date('Y-m-d H:i:s', $entity['entity']['start_at'] ?? time()),
                    'trial_ends_at' => date('Y-m-d H:i:s', $entity['entity']['trial_end'] ?? 0),
                    // Add other fields as needed, like `cancelled_at`, `ends_at`
                ];

                if ($newStatus == 'cancelled' || $newStatus == 'halted' || $newStatus == 'expired') {
                    $updateFields['canceled_at'] = date('Y-m-d H:i:s');
                    $updateFields['ends_at'] = date('Y-m-d H:i:s', $entity['entity']['ended_at'] ?? time());
                }

                $setClause = [];
                $params = [':razorpay_subscription_id' => $subscriptionId];
                foreach ($updateFields as $field => $value) {
                    $setClause[] = "`{$field}` = :{$field}";
                    $params[":{$field}"] = $value;
                }

                $stmt = $pdo->prepare("UPDATE `subscriptions` SET " . implode(', ', $setClause) . " WHERE razorpay_subscription_id = :razorpay_subscription_id");
                $stmt->execute($params);

                // Update organizations.current_plan_id if subscription changes to cancelled/expired
                if (in_array($internalStatus, ['cancelled', 'completed', 'expired'])) {
                    $stmt_update_org = $pdo->prepare("UPDATE `organizations` SET current_plan_id = NULL WHERE id = (SELECT organization_id FROM `subscriptions` WHERE razorpay_subscription_id = :razorpay_subscription_id)");
                    $stmt_update_org->execute([':razorpay_subscription_id' => $subscriptionId]);
                }
                
                // For subscription.charged, you might want to log successful payments or
                // specifically trigger renewal logic, if not handled by status changes.
                break;

            case 'payment.captured': // When a payment is successfully captured
                // This might be for a one-time payment, or a subscription payment.
                // If it's a subscription payment, it usually means subscription.charged would also fire.
                // You might update a `payment_history` table here.
                break;

            default:
                // Log unknown events for review
                // if (function_exists('logMessage')) {
                //     logMessage("Razorpay Webhook: Unhandled event type {$eventType}", 'warning');
                // }
                break;
        }

        $pdo->commit();
        ApiResponse::success(['message' => 'Webhook processed successfully.']);

    } catch (Exception $e) {
        $pdo->rollBack();
        // if (function_exists('logMessage')) {
        //     logMessage("Razorpay Webhook processing failed: " . $e->getMessage(), 'error');
        // }
        ApiResponse::error('Webhook processing failed: ' . $e->getMessage(), 500);
    }

} catch (Exception $e) {
    ApiResponse::error('Webhook error: ' . $e->getMessage(), 500);
}
