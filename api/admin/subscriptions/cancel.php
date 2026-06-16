<?php
require_once '../../../includes/api_common.php';
require_once '../admin_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::error('Method not allowed', 405);
}

$data            = json_decode(file_get_contents('php://input'), true) ?? [];
$subscription_id = (int)($data['subscription_id'] ?? 0);
if (!$subscription_id) ApiResponse::error('subscription_id is required', 400);

$pdo->prepare(
    "UPDATE subscriptions SET status = 'cancelled', canceled_at = NOW(), managed_by = ?, updated_at = NOW() WHERE id = ?"
)->execute([$currentUser['id'], $subscription_id]);

ApiResponse::success(['message' => 'Subscription cancelled']);
