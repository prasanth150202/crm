<?php
// includes/SubscriptionMiddleware.php

/**
 * Subscription Middleware
 * Provides helper methods for subscription validation and enforcement
 */

class SubscriptionMiddleware {
    private $pdo;
    private $planFeatureChecker;
    private $organizationId;

    public function __construct(PDO $pdo, PlanFeatureChecker $planFeatureChecker, int $organizationId) {
        $this->pdo = $pdo;
        $this->planFeatureChecker = $planFeatureChecker;
        $this->organizationId = $organizationId;
    }

    /**
     * Check if organization can add more users
     * 
     * @return array ['allowed' => bool, 'message' => string, 'upgrade_required' => bool, 'current_count' => int, 'limit' => int|null]
     */
    public function canAddUser(): array {
        $userLimit = $this->planFeatureChecker->getUserLimit();
        $currentUserCount = $this->planFeatureChecker->getCurrentUserCount();

        // Count pending invitations
        $stmtPending = $this->pdo->prepare("
            SELECT COUNT(id) FROM `invitation_tokens` 
            WHERE org_id = :org_id 
            AND used_at IS NULL 
            AND revoked_at IS NULL 
            AND expires_at > NOW()
        ");
        $stmtPending->execute([':org_id' => $this->organizationId]);
        $pendingCount = (int)$stmtPending->fetchColumn();

        $totalCount = $currentUserCount + $pendingCount;

        // Enterprise plan has unlimited users
        if ($userLimit === null) {
            return [
                'allowed' => true,
                'message' => 'Unlimited users allowed',
                'upgrade_required' => false,
                'current_count' => $totalCount,
                'limit' => null
            ];
        }

        // Check if limit is reached
        if ($totalCount >= $userLimit) {
            $planDetails = $this->planFeatureChecker->getPlanDetails();
            $planName = $planDetails['name'] ?? 'Current Plan';
            $pricePerUser = $this->planFeatureChecker->getPricePerAdditionalUser('monthly');

            return [
                'allowed' => false,
                'message' => "User limit reached. Your {$planName} allows {$userLimit} users. You currently have {$currentUserCount} users and {$pendingCount} pending invitations.",
                'upgrade_required' => true,
                'current_count' => $totalCount,
                'limit' => $userLimit,
                'price_per_additional_user' => $pricePerUser,
                'plan_name' => $planName
            ];
        }

        return [
            'allowed' => true,
            'message' => 'User can be added',
            'upgrade_required' => false,
            'current_count' => $totalCount,
            'limit' => $userLimit
        ];
    }

    /**
     * Check if organization can use external webhooks
     * 
     * @return array ['allowed' => bool, 'message' => string, 'upgrade_required' => bool]
     */
    public function canUseWebhooks(): array {
        $canUse = $this->planFeatureChecker->canUseExternalWebhooks();

        if (!$canUse) {
            return [
                'allowed' => false,
                'message' => 'External webhooks are only available on the Enterprise plan. Please upgrade to use this feature.',
                'upgrade_required' => true,
                'required_plan' => 'Enterprise Plan'
            ];
        }

        return [
            'allowed' => true,
            'message' => 'External webhooks are available',
            'upgrade_required' => false
        ];
    }

    /**
     * Check if organization can use a specific feature
     * 
     * @param string $featureKey The feature knob key to check
     * @param string $featureName Human-readable feature name for error messages
     * @return array ['allowed' => bool, 'message' => string, 'upgrade_required' => bool]
     */
    public function canUseFeature(string $featureKey, string $featureName = 'This feature'): array {
        $hasFeature = $this->planFeatureChecker->hasFeature($featureKey);

        if (!$hasFeature) {
            $planDetails = $this->planFeatureChecker->getPlanDetails();
            $currentPlanName = $planDetails['name'] ?? 'your current plan';

            return [
                'allowed' => false,
                'message' => "{$featureName} is not available on {$currentPlanName}. Please upgrade to access this feature.",
                'upgrade_required' => true,
                'feature_key' => $featureKey
            ];
        }

        return [
            'allowed' => true,
            'message' => "{$featureName} is available",
            'upgrade_required' => false
        ];
    }

    /**
     * Get upgrade prompt data for frontend
     * 
     * @param string $featureName The feature that requires upgrade
     * @param string $requiredPlan The plan required for this feature
     * @return array Upgrade prompt data
     */
    public function getUpgradePrompt(string $featureName, string $requiredPlan = 'Professional or Enterprise'): array {
        $currentPlan = $this->planFeatureChecker->getPlanDetails();

        return [
            'feature_name' => $featureName,
            'required_plan' => $requiredPlan,
            'current_plan' => $currentPlan['name'] ?? 'No Plan',
            'upgrade_message' => "Upgrade to {$requiredPlan} to unlock {$featureName}",
            'contact_sales' => $requiredPlan === 'Enterprise Plan'
        ];
    }

    /**
     * Check if organization has an active subscription
     * 
     * @return bool
     */
    public function hasActiveSubscription(): bool {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(id) FROM `subscriptions` 
            WHERE organization_id = :organization_id 
            AND status IN ('active', 'trialing')
        ");
        $stmt->execute([':organization_id' => $this->organizationId]);
        return $stmt->fetchColumn() > 0;
    }
}

// Helper function to get SubscriptionMiddleware instance
function getSubscriptionMiddleware(PDO $pdo, PlanFeatureChecker $planFeatureChecker, int $organizationId): SubscriptionMiddleware {
    static $instance = null;
    if ($instance === null || $instance->organizationId !== $organizationId) {
        $instance = new SubscriptionMiddleware($pdo, $planFeatureChecker, $organizationId);
    }
    return $instance;
}
