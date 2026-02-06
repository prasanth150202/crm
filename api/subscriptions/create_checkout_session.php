<?php
/**
 * Create Checkout Session API
 * POST /api/subscriptions/create_checkout_session.php
 *
 * Initiates a new Razorpay subscription checkout.
 * Requires plan_id and billing_interval (monthly/yearly)
 */

require_once '../../includes/api_common.php'; // Common API setup (authentication, $pdo, $currentUser)
require_once '../../includes/RazorpayService.php'; // Our Razorpay integration service

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::error('Method not allowed', 405);
}

// Ensure user is authenticated and part of an organization
if (!isset($currentUser['id']) || !isset($currentUser['organization_id'])) {
    ApiResponse::error('Authentication required or user not associated with an organization.', 401);
}

$organizationId = $currentUser['organization_id'];
$userId = $currentUser['id'];

$input = json_decode(file_get_contents('php://input'), true);

$planId = $input['plan_id'] ?? null;
$billingInterval = $input['billing_interval'] ?? null; // 'monthly' or 'yearly'
$initialUserCount = $input['initial_user_count'] ?? 1; // Default to 1, or fetch actual active users for the org

if (!$planId || !$billingInterval) {
    ApiResponse::error('Missing plan_id or billing_interval', 400);
}

if (!in_array($billingInterval, ['monthly', 'yearly'])) {
    ApiResponse::error('Invalid billing_interval. Must be "monthly" or "yearly".', 400);
}

try {
    $stmt = $pdo->prepare("SELECT * FROM `plans` WHERE id = :plan_id AND is_active = TRUE");
    $stmt->execute([':plan_id' => $planId]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$plan) {
        ApiResponse::error('Selected plan not found or not active.', 404);
    }

    // --- Determine pricing based on billing interval ---
    $basePrice = ($billingInterval === 'monthly') ? $plan['base_price_monthly'] : $plan['base_price_yearly'];
    $pricePerAdditionalUser = ($billingInterval === 'monthly') ? $plan['price_per_additional_user_monthly'] : $plan['price_per_additional_user_yearly'];
    $includedUsers = $plan['included_users'];

    if ($basePrice === null || ($initialUserCount > $includedUsers && $pricePerAdditionalUser === null)) {
        ApiResponse::error("Pricing for selected billing interval or additional users not defined for this plan.", 400);
    }
    
    // --- Check if organization already has an active subscription ---
    $stmt = $pdo->prepare("SELECT id FROM `subscriptions` WHERE organization_id = :organization_id AND (status = 'active' OR status = 'trialing')");
    $stmt->execute([':organization_id' => $organizationId]);
    if ($stmt->fetch()) {
        ApiResponse::error('Your organization already has an active or trialing subscription. Please manage existing subscription.', 409);
    }

    // --- Fetch customer details (assuming it's tied to the user/org owner) ---
    // You might fetch the user's name, email, and contact for Razorpay
    $stmt = $pdo->prepare("SELECT name, email FROM `users` WHERE id = :user_id");
    $stmt->execute([':user_id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $customerDetails = [
        'name' => $user['name'] ?? 'Guest User',
        'email' => $user['email'] ?? null,
        'contact' => null, // You might need to fetch this from user profile or org settings
        'organization_name' => $input['organization_name'] ?? 'N/A', // If not passed, use current org name
    ];

    // --- Create Razorpay Subscription ---
    $razorpayService = new RazorpayService();
    $razorpaySubscription = $razorpayService->createSubscription(
        $plan['razorpay_plan_id_' . $billingInterval], // Use the correct Razorpay plan ID
        $customerDetails,
        $initialUserCount,
        $billingInterval,
        $pricePerAdditionalUser,
        $includedUsers
    );

    // --- Store subscription details in your database ---
    $sql = "
        INSERT INTO `subscriptions` (
            organization_id, plan_id, razorpay_subscription_id, status, billing_interval, 
            billed_users_count, trial_starts_at, trial_ends_at, current_period_start, current_period_end
        ) VALUES (
            :organization_id, :plan_id, :razorpay_subscription_id, :status, :billing_interval, 
            :billed_users_count, :trial_starts_at, :trial_ends_at, :current_period_start, :current_period_end
        )
    ";
    // DEBUG: Print the SQL query being executed
    error_log("SQL Query for subscriptions insert: " . preg_replace('/\s+/', ' ', $sql)); // Log to PHP error log

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':organization_id' => $organizationId,
        ':plan_id' => $planId,
        ':razorpay_subscription_id' => $razorpaySubscription['id'],
        ':status' => $razorpaySubscription['status'], // 'created', 'active', 'trialing' etc.
        ':billing_interval' => $billingInterval,
        ':billed_users_count' => $initialUserCount,
        ':trial_starts_at' => date('Y-m-d H:i:s', $razorpaySubscription['start_at']), // Use Razorpay's start_at for trial
        ':trial_ends_at' => date('Y-m-d H:i:s', $razorpaySubscription['trial_end']),
        ':current_period_start' => date('Y-m-d H:i:s', $razorpaySubscription['current_period_start']),
        ':current_period_end' => date('Y-m-d H:i:s', $razorpaySubscription['current_period_end']),
    ]);

    // Update organizations table with the new plan
    $stmt_update_org = $pdo->prepare("UPDATE `organizations` SET current_plan_id = :plan_id WHERE id = :organization_id");
    $stmt_update_org->execute([
        ':plan_id' => $planId,
        ':organization_id' => $organizationId
    ]);


    ApiResponse::success([
        'message' => 'Subscription created successfully. Redirect to Razorpay checkout.',
        'razorpay_order_id' => $razorpaySubscription['id'], // Subscription ID is used for checkout
        'razorpay_key_id' => Env::get('RAZORPAY_KEY_ID'), // Frontend needs this
        'amount' => $razorpaySubscription['total_amount'], // Total amount of subscription (base + initial addons)
        'currency' => $razorpaySubscription['currency'],
        'description' => "Subscription for {$plan['name']}",
        'customer_name' => $customerDetails['name'],
        'customer_email' => $customerDetails['email'],
    ]);

} catch (Exception $e) {
    ApiResponse::error('Failed to create subscription: ' . $e->getMessage(), 500);
}
