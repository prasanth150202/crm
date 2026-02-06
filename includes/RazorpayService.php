<?php
// includes/RazorpayService.php

require_once __DIR__ . '/../vendor/autoload.php'; // Path to Composer's autoload
require_once __DIR__ . '/../config/env.php'; // Assuming your Env class is here

use Razorpay\Api\Api;
use Razorpay\Api\Errors\BadRequestError;
use Razorpay\Api\Errors\SignatureVerificationError;

class RazorpayService {
    private $api;

    public function __construct() {
        $keyId = Env::get('RAZORPAY_KEY_ID');
        $keySecret = Env::get('RAZORPAY_KEY_SECRET');

        if (!$keyId || !$keySecret) {
            throw new Exception("Razorpay API Key ID or Key Secret is not set in environment variables.");
        }

        $this->api = new Api($keyId, $keySecret);
    }

    /**
     * Create a Razorpay Plan for the base subscription amount.
     * Razorpay plans have a fixed amount. For per-user billing, we'll primarily use the 'add-on' feature
     * or adjust the subscription amount directly for additional users. This plan is for the base cost.
     *
     * @param string $planName Name of the plan (e.g., "Basic Plan Base Monthly")
     * @param string $billingInterval "monthly" or "yearly"
     * @param float $baseAmount The base amount of the plan in smallest currency unit (e.g., paise for INR)
     * @return array Razorpay Plan object as an array
     * @throws Exception
     */
    public function createPlan(string $planName, string $billingInterval, float $baseAmount): array {
        try {
            $interval = ($billingInterval === 'monthly') ? 'month' : 'year';
            $period = ($billingInterval === 'monthly') ? 1 : 1; // Assuming 1 month/year for simplicity

            $plan = $this->api->plan->create([
                'period' => $interval,
                'interval' => $period,
                'item' => [
                    'name' => $planName,
                    'amount' => (int)($baseAmount * 100), // Amount in smallest currency unit (e.g., paise)
                    'currency' => 'INR', // Assuming INR
                    'description' => "Base amount for {$planName} subscription."
                ],
                'notes' => [
                    'description' => "This is the base plan for the {$planName} subscription. Additional users are billed separately."
                ]
            ]);
            return $plan->toArray();
        } catch (BadRequestError $e) {
            throw new Exception("Razorpay Plan creation failed: " . $e->getMessage() . " (Code: " . $e->getCode() . ")");
        } catch (Exception $e) {
            throw new Exception("An unexpected error occurred during Razorpay Plan creation: " . $e->getMessage());
        }
    }

    /**
     * Create a Razorpay Subscription for an organization.
     *
     * @param string $planId The Razorpay Plan ID for the base amount.
     * @param array $customerDetails Customer details (e.g., 'name', 'email', 'contact')
     * @param int $totalUsers The initial total number of users for this subscription (for calculation purposes)
     * @param string $billingInterval "monthly" or "yearly"
     * @param float $pricePerAdditionalUser The price per additional user for this plan (in smallest currency unit)
     * @param int $includedUsers The number of users included in the base plan
     * @param string $couponId Optional coupon ID
     * @return array Razorpay Subscription object as an array
     * @throws Exception
     */
    public function createSubscription(string $planId, array $customerDetails, int $totalUsers, string $billingInterval, float $pricePerAdditionalUser, int $includedUsers, string $couponId = null): array {
        try {
            $addons = [];
            $initialAddonsAmount = 0;

            if ($totalUsers > $includedUsers) {
                $additionalUsers = $totalUsers - $includedUsers;
                $initialAddonsAmount = $additionalUsers * (int)($pricePerAdditionalUser * 100);

                $addons[] = [
                    'item' => [
                        'name' => 'Additional Users',
                        'amount' => $initialAddonsAmount,
                        'currency' => 'INR',
                        'description' => "Charge for {$additionalUsers} additional users."
                    ]
                ];
            }

            $subscriptionData = [
                'plan_id' => $planId,
                'customer_notify' => 1,
                'quantity' => 1,
                'total_count' => 12, // 12 billing cycles for monthly/yearly, adjust as needed
                'addons' => $addons,
                'notes' => [
                    'organization_name' => $customerDetails['organization_name'] ?? 'N/A',
                    'initial_users' => $totalUsers,
                    'billing_interval' => $billingInterval,
                ]
            ];

            // Only add customer_id if present
            if (!empty($customerDetails['id'])) {
                $subscriptionData['customer_id'] = $customerDetails['id'];
            }
            // Only add contact if present
            if (!empty($customerDetails['contact'])) {
                $subscriptionData['contact'] = $customerDetails['contact'];
            }
            if ($couponId) {
                $subscriptionData['offer_id'] = $couponId;
            }

            $subscription = $this->api->subscription->create($subscriptionData);
            return $subscription->toArray();
        } catch (BadRequestError $e) {
            throw new Exception("Razorpay Subscription creation failed: " . $e->getMessage() . " (Code: " . $e->getCode() . ")");
        } catch (Exception $e) {
            throw new Exception("An unexpected error occurred during Razorpay Subscription creation: " . $e->getMessage());
        }
    }

    /**
     * Fetch a Razorpay Subscription.
     *
     * @param string $subscriptionId
     * @return array Razorpay Subscription object as an array
     * @throws Exception
     */
    public function fetchSubscription(string $subscriptionId): array {
        try {
            $subscription = $this->api->subscription->fetch($subscriptionId);
            return $subscription->toArray();
        } catch (BadRequestError $e) {
            throw new Exception("Razorpay Subscription fetch failed: " . $e->getMessage() . " (Code: " . $e->getCode() . ")");
        } catch (Exception $e) {
            throw new Exception("An unexpected error occurred during Razorpay Subscription fetch: " . $e->getMessage());
        }
    }

    /**
     * Cancel a Razorpay Subscription.
     *
     * @param string $subscriptionId
     * @param bool $atCycleEnd True to cancel at the end of the current billing cycle, false to cancel immediately.
     * @return array Razorpay Subscription object as an array
     * @throws Exception
     */
    public function cancelSubscription(string $subscriptionId, bool $atCycleEnd = true): array {
        try {
            $subscription = $this->api->subscription->fetch($subscriptionId)->cancel(['cancel_at_cycle_end' => $atCycleEnd]);
            return $subscription->toArray();
        } catch (BadRequestError $e) {
            throw new Exception("Razorpay Subscription cancellation failed: " . $e->getMessage() . " (Code: " . $e->getCode() . ")");
        } catch (Exception $e) {
            throw new Exception("An unexpected error occurred during Razorpay Subscription cancellation: " . $e->getMessage());
        }
    }

    /**
     * Add an Addon to an existing subscription (e.g., for additional users).
     *
     * @param string $subscriptionId
     * @param string $itemName Name of the addon item (e.g., "Additional User")
     * @param float $amount The amount for the addon (e.g., price for 1 additional user in smallest currency unit)
     * @param int $quantity Quantity of the addon (e.g., number of additional users)
     * @return array Razorpay Addon object as an array
     * @throws Exception
     */
    public function addAddonToSubscription(string $subscriptionId, string $itemName, float $amount, int $quantity = 1): array {
        try {
            $addon = $this->api->subscription->fetch($subscriptionId)->addon->create([
                'item' => [
                    'name' => $itemName,
                    'amount' => (int)($amount * 100 * $quantity), // Total amount for the addon
                    'currency' => 'INR',
                    'description' => "Addon for {$quantity} x {$itemName}"
                ],
                'quantity' => $quantity, // Quantity of the addon
            ]);
            return $addon->toArray();
        } catch (BadRequestError $e) {
            throw new Exception("Razorpay Addon creation failed: " . $e->getMessage() . " (Code: " . $e->getCode() . ")");
        } catch (Exception $e) {
            throw new Exception("An unexpected error occurred during Razorpay Addon creation: " . $e->getMessage());
        }
    }

    /**
     * Verify a Razorpay webhook signature.
     *
     * @param string $razorpaySignature The value of the 'X-Razorpay-Signature' header
     * @param string $payload The raw webhook payload (JSON string)
     * @param string $webhookSecret The secret key configured for the webhook in Razorpay dashboard
     * @return bool True if signature is valid, false otherwise.
     * @throws Exception
     */
    public function verifyWebhookSignature(string $razorpaySignature, string $payload, string $webhookSecret): bool {
        try {
            $this->api->utility->verifyWebhookSignature($payload, $razorpaySignature, $webhookSecret);
            return true;
        } catch (SignatureVerificationError $e) {
            // Log the error for debugging
            error_log("Webhook Signature Verification Failed: " . $e->getMessage());
            return false;
        } catch (Exception $e) {
            error_log("An unexpected error occurred during webhook signature verification: " . $e->getMessage());
            throw new Exception("Error verifying webhook signature: " . $e->getMessage());
        }
    }
}
