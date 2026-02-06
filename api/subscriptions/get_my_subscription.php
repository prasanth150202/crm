<?php
/**
 * Get My Subscription API
 * GET /api/subscriptions/get_my_subscription.php
 *
 * Fetches the current subscription details for the authenticated user's organization.
 */

require_once '../../includes/api_common.php'; // Common API setup (authentication, $pdo, $currentUser)

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ApiResponse::error('Method not allowed', 405);
}

// Ensure user is authenticated and part of an organization
if (!isset($currentUser['id']) || !isset($currentUser['organization_id'])) {
    ApiResponse::error('Authentication required or user not associated with an organization.', 401);
}

$organizationId = $currentUser['organization_id'];

try {
    $stmt = $pdo->prepare("
        SELECT 
            s.id AS subscription_id,
            s.razorpay_subscription_id,
            s.status,
            s.billing_interval,
            s.billed_users_count,
            s.trial_starts_at,
            s.trial_ends_at,
            s.current_period_start,
            s.current_period_end,
            s.canceled_at,
            s.ends_at,
            p.id AS plan_id,
            p.name AS plan_name,
            p.description AS plan_description,
            p.base_price_monthly,
            p.included_users,
            p.price_per_additional_user_monthly,
            p.base_price_yearly,
            p.price_per_additional_user_yearly,
            p.currency,
            p.features AS plan_features_json
        FROM `subscriptions` s
        JOIN `plans` p ON s.plan_id = p.id
        WHERE s.organization_id = :organization_id
        AND (s.status = 'active' OR s.status = 'trialing') -- Fetch active or trialing subscription
        ORDER BY s.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([':organization_id' => $organizationId]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$subscription) {
        ApiResponse::success(['message' => 'No active or trialing subscription found for this organization.'], 200);
    }

    // Decode features JSON for better usability
    if (isset($subscription['plan_features_json'])) {
        $subscription['plan_features'] = json_decode($subscription['plan_features_json'], true);
        unset($subscription['plan_features_json']);
    }

    ApiResponse::success($subscription);

} catch (Exception $e) {
    ApiResponse::error('Failed to fetch subscription details: ' . $e->getMessage(), 500);
}
