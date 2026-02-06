<?php
/**
 * Cancel Subscription API
 * POST /api/subscriptions/cancel_subscription.php
 *
 * Allows an authenticated user (organization owner/admin) to cancel their organization's active subscription.
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

// Permission check: Only the organization owner or a super admin can cancel subscriptions.
// Assuming your 'organizations' table has an 'owner_user_id' and 'users' table has 'role'
// You might need to adjust this permission check based on your actual permission system.
$stmt = $pdo->prepare("SELECT owner_user_id FROM `organizations` WHERE id = :organization_id");
$stmt->execute([':organization_id' => $organizationId]);
$organization = $stmt->fetch(PDO::FETCH_ASSOC);

$isOwner = ($organization && $organization['owner_user_id'] == $userId);
// Assuming $permissionManager is available from api_common.php and has a check for admin roles
// $isSuperAdmin = $permissionManager->hasRole('admin'); // Example: check for a global admin role

if (!$isOwner /*&& !$isSuperAdmin*/) { // Uncomment and adjust for super admin check if applicable
    ApiResponse::error('Permission denied. Only the organization owner can cancel the subscription.', 403);
}

$input = json_decode(file_get_contents('php://input'), true);
$cancelAtCycleEnd = $input['cancel_at_cycle_end'] ?? true; // Default to cancel at cycle end

try {
    $stmt = $pdo->prepare("SELECT * FROM `subscriptions` WHERE organization_id = :organization_id AND (status = 'active' OR status = 'trialing')");
    $stmt->execute([':organization_id' => $organizationId]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$subscription) {
        ApiResponse::error('No active or trialing subscription found for this organization to cancel.', 404);
    }

    $razorpayService = new RazorpayService();
    $razorpaySubscription = $razorpayService->cancelSubscription($subscription['razorpay_subscription_id'], $cancelAtCycleEnd);

    // Update your database based on Razorpay's response
    $newStatus = $razorpaySubscription['status']; // Should be 'cancelled' or 'halted'
    $canceledAt = date('Y-m-d H:i:s');
    $endsAt = date('Y-m-d H:i:s', $razorpaySubscription['end_at'] ?? time()); // Use Razorpay's end_at

    $stmt_update = $pdo->prepare("
        UPDATE `subscriptions`
        SET status = :status, canceled_at = :canceled_at, ends_at = :ends_at
        WHERE id = :subscription_id
    ");
    $stmt_update->execute([
        ':status' => $newStatus,
        ':canceled_at' => $canceledAt,
        ':ends_at' => $endsAt,
        ':subscription_id' => $subscription['id']
    ]);

    // If cancelled immediately, remove plan_id from organization
    if (!$cancelAtCycleEnd || ($newStatus === 'cancelled' && $razorpaySubscription['current_period_end'] <= time())) { // Assuming Razorpay's current_period_end is UTC timestamp
        $stmt_update_org = $pdo->prepare("UPDATE `organizations` SET current_plan_id = NULL WHERE id = :organization_id");
        $stmt_update_org->execute([':organization_id' => $organizationId]);
    }


    ApiResponse::success(['message' => 'Subscription cancelled successfully.', 'new_status' => $newStatus]);

} catch (Exception $e) {
    ApiResponse::error('Failed to cancel subscription: ' . $e->getMessage(), 500);
}
