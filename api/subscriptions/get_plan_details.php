<?php
// api/subscriptions/get_plan_details.php



// Prevent any output before JSON
ob_start();

require_once '../../includes/api_common.php';
require_once '../../includes/PlanFeatureChecker.php';

// Clean buffer to remove any warnings/notices from includes
ob_clean();

// Allow CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

try {
    // Ensure user is authenticated
    if (!isset($_SESSION['user_id'])) {
        ApiResponse::error('Unauthorized', 401);
    }

    $user = $currentUser; // from api_common.php
    $pdo = getDb();

    $planFeatureChecker = getPlanFeatureChecker($pdo, $user);

    $planDetails = $planFeatureChecker->getPlanDetails();
    $userLimit = $planFeatureChecker->getUserLimit();
    $userCount = $planFeatureChecker->getCurrentUserCount();
    $features = array_keys($planFeatureChecker->getAllFeatures());

    ApiResponse::success([
        'success' => true,
        'data' => [
            'plan' => $planDetails,
            'user_count' => $userCount,
            'user_limit' => $userLimit,
            'features' => $features,
            'subscription_status' => $planFeatureChecker->getSubscriptionStatus()
        ]
    ]);

} catch (Exception $e) {
    ApiResponse::error('Failed to fetch plan details: ' . $e->getMessage(), 500);
}
