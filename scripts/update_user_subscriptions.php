<?php
// scripts/update_user_subscriptions.php
// This script is intended to be run as a daily cron job.

echo "Running cron job: update_user_subscriptions...\n";

// Load necessary files
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/db.php'; // Ensure $pdo is available
require_once __DIR__ . '/../includes/RazorpayService.php';

// Define a basic logger for cron output if not already available
if (!function_exists('cronLog')) {
    function cronLog($message, $level = 'info') {
        echo date('[Y-m-d H:i:s]') . " [{$level}] " . $message . "\n";
        // Optionally, write to a log file
        // error_log(date('[Y-m-d H:i:s]') . " [{$level}] " . $message . "\n", 3, __DIR__ . '/../logs/cron.log');
    }
}

try {
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new Exception("PDO connection not established.");
    }

    $razorpayService = new RazorpayService();

    // 1. Fetch all active/trialing subscriptions
    $stmt = $pdo->prepare(
        "SELECT 
            s.id AS subscription_db_id,
            s.organization_id,
            s.plan_id,
            s.razorpay_subscription_id,
            s.billed_users_count,
            s.billing_interval,
            p.name AS plan_name,
            p.included_users,
            p.base_price_monthly,
            p.price_per_additional_user_monthly,
            p.base_price_yearly,
            p.price_per_additional_user_yearly
        FROM `subscriptions` s
        JOIN `plans` p ON s.plan_id = p.id
        WHERE s.status IN ('active', 'trialing')"
    );
    $stmt->execute();
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    cronLog("Found " . count($subscriptions) . " active/trialing subscriptions.");

    foreach ($subscriptions as $subscription) {
        $pdo->beginTransaction(); // Use transaction per subscription update
        try {
            // 2. Count current active users for the organization
            // Assuming 'is_active' column in 'users' table indicates active status
            $stmt = $pdo->prepare("SELECT COUNT(id) FROM `users` WHERE organization_id = :organization_id AND is_active = 1");
            $stmt->execute([':organization_id' => $subscription['organization_id']]);
            $actualUserCount = $stmt->fetchColumn();

            if ($actualUserCount === false) { // Handle case where fetchColumn might return false
                $actualUserCount = 0;
            }

            cronLog("Org ID {$subscription['organization_id']} (Sub ID: {$subscription['subscription_db_id']}): Actual users: {$actualUserCount}, Billed users: {$subscription['billed_users_count']}.");

            // 3. Compare with billed_users_count
            if ((int)$actualUserCount !== (int)$subscription['billed_users_count']) {
                cronLog("User count mismatch for subscription ID {$subscription['subscription_db_id']}. Updating Razorpay and database.");

                $pricePerAdditionalUser = ($subscription['billing_interval'] === 'monthly')
                    ? $subscription['price_per_additional_user_monthly']
                    : $subscription['price_per_additional_user_yearly'];
                $includedUsers = $subscription['included_users'];

                // Calculate the difference in additional users
                $oldAdditionalUsers = max(0, (int)$subscription['billed_users_count'] - (int)$includedUsers);
                $newAdditionalUsers = max(0, (int)$actualUserCount - (int)$includedUsers);
                
                $changeInAddons = $newAdditionalUsers - $oldAdditionalUsers;

                if ($changeInAddons !== 0 && $pricePerAdditionalUser > 0) {
                    $addonAmountPerUser = (float)$pricePerAdditionalUser; // amount in currency units
                    $totalAddonAmountChange = $addonAmountPerUser * $changeInAddons;

                    // Razorpay's API for managing addons requires creating new ones or cancelling old ones.
                    // This is a simplified approach for demonstration: we'll create a new addon that
                    // represents the *total* current additional user charges, or remove old ones.
                    // A more robust solution might involve:
                    // 1. Fetching existing addons for additional users.
                    // 2. Cancelling/deleting them.
                    // 3. Creating a new addon reflecting the current newAdditionalUsers count.
                    //
                    // For simplicity, we are demonstrating adding an addon for new users.
                    // Razorpay often handles the final amount calculation itself based on addons.
                    //
                    // A direct subscription amount update through Razorpay API `edit` or `update`
                    // endpoint is typically preferred if available, but for per-unit billing,
                    // addons are the common mechanism.

                    // If changeInAddons > 0, add addons for newly added users
                    if ($changeInAddons > 0) {
                        $addon = $razorpayService->addAddonToSubscription(
                            $subscription['razorpay_subscription_id'],
                            "Additional User",
                            $addonAmountPerUser, // Amount per additional user
                            $changeInAddons // Quantity of users to add
                        );
                        cronLog("Razorpay: Added addon for {$changeInAddons} users to subscription {$subscription['razorpay_subscription_id']}. Addon ID: {$addon['id']}");
                    } else { 
                        // Handle removal of addons. Razorpay Addon API does not have a direct 'remove' function.
                        // Typically you would fetch existing addons, mark them inactive, or adjust the quantity of an existing one.
                        // This would require more sophisticated logic involving fetching subscription addons and managing them.
                        // For a real-world scenario, you might need to adjust the Razorpay subscription directly (if it supports quantity changes)
                        // or manage multiple addon items.
                        // Placeholder: log that users were removed. This part needs careful implementation in production.
                        cronLog("Razorpay: Note: Removing {$changeInAddons * -1} users from subscription {$subscription['razorpay_subscription_id']} needs manual addon management via Razorpay API. (Not implemented in this demo script).");
                        // As a workaround for reducing users, you might create a "credit" addon for the next cycle
                        // or update the Razorpay subscription amount directly if their API allows.
                    }
                } else if ($pricePerAdditionalUser == 0 && $changeInAddons != 0) {
                    cronLog("Plan '{$subscription['plan_name']}' has 0 price per additional user, skipping Razorpay addon update.");
                }

                // 4. Update billed_users_count in your `subscriptions` table
                $stmt_update_db = $pdo->prepare(
                    "UPDATE `subscriptions`
                    SET billed_users_count = :billed_users_count
                    WHERE id = :subscription_db_id"
                );
                $stmt_update_db->execute([
                    ':billed_users_count' => $actualUserCount,
                    ':subscription_db_id' => $subscription['subscription_db_id']
                ]);
                cronLog("Database: Updated billed_users_count for subscription {$subscription['subscription_db_id']} to {$actualUserCount}.");
            }

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            cronLog("Error processing subscription ID {$subscription['subscription_db_id']}: " . $e->getMessage(), 'error');
        }
    }

    cronLog("Cron job: update_user_subscriptions completed.");

} catch (Exception $e) {
    cronLog("Critical error in update_user_subscriptions cron job: " . $e->getMessage(), 'critical');
}

