<?php
// includes/PlanFeatureChecker.php

class PlanFeatureChecker {
    private $pdo;
    private $organizationId;
    private $planFeatures = null; // Cache features for the current organization
    private $currentPlanId = null; // Cache the organization's current plan ID

    public function __construct(PDO $pdo, int $organizationId) {
        $this->pdo = $pdo;
        $this->organizationId = $organizationId;
    }

    private function loadPlanFeatures() {
        if ($this->planFeatures !== null) {
            return; // Already loaded
        }

        $this->planFeatures = []; // Default to empty array

        if ($this->organizationId <= 0) {
            $this->currentPlanId = null;
            return;
        }

        // Get the current plan ID for the organization
        $stmtOrgPlan = $this->pdo->prepare("SELECT current_plan_id FROM `organizations` WHERE id = :organization_id");
        $stmtOrgPlan->execute([':organization_id' => $this->organizationId]);
        $this->currentPlanId = $stmtOrgPlan->fetchColumn();

        if (!$this->currentPlanId) {
            // No plan assigned to the organization, so no features are available.
            return;
        }

        // Fetch features directly from plan_features using the organization's current_plan_id
        $stmt = $this->pdo->prepare("
            SELECT pf.knob_key
            FROM `plan_features` pf
            WHERE pf.plan_id = :plan_id
        ");
        $stmt->execute([':plan_id' => $this->currentPlanId]);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->planFeatures[$row['knob_key']] = true;
        }
    }

    /**
     * Checks if a specific feature (knob_key) is enabled for the current organization's plan.
     *
     * @param string $knobKey The unique key of the feature to check.
     * @return bool True if the feature is enabled, false otherwise.
     */
    public function hasFeature(string $knobKey): bool {
        $this->loadPlanFeatures(); // Ensure features are loaded

        // First, check if the requested feature is enabled for the current plan
        $featureEnabledForPlan = isset($this->planFeatures[$knobKey]) && $this->planFeatures[$knobKey] === true;

        if (!$featureEnabledForPlan) {
            return false; // Feature not part of the plan
        }

        // Then, check if the organization has an active/trialing subscription.
        // This prevents access even if current_plan_id is set, but subscription is cancelled/expired.
        $stmt = $this->pdo->prepare("
            SELECT COUNT(id) FROM `subscriptions` 
            WHERE organization_id = :organization_id 
            AND status IN ('active', 'trialing')
        ");
        $stmt->execute([':organization_id' => $this->organizationId]);
        $hasActiveSubscription = $stmt->fetchColumn() > 0;

        return $hasActiveSubscription;
    }

    /**
     * Get all enabled features for the current organization's plan.
     *
     * @return array An associative array where keys are knob_keys and values are true.
     */
    public function getAllFeatures(): array {
        $this->loadPlanFeatures();
        
        // Also check if the organization has an active/trialing subscription.
        // If no active subscription, return empty array for features.
        $stmt = $this->pdo->prepare("
            SELECT COUNT(id) FROM `subscriptions` 
            WHERE organization_id = :organization_id 
            AND status IN ('active', 'trialing')
        ");
        $stmt->execute([':organization_id' => $this->organizationId]);
        $hasActiveSubscription = $stmt->fetchColumn() > 0;

        return $hasActiveSubscription ? $this->planFeatures : [];
    }

    /**
     * Get the maximum number of users allowed for the current plan.
     *
     * @return int|null The user limit, or null if unlimited (Enterprise plan)
     */
    public function getUserLimit(): ?int {
        $this->loadPlanFeatures();

        if (!$this->currentPlanId) {
            return 0; // No plan = no users allowed
        }

        // Check if plan has unlimited_users feature (Enterprise)
        if ($this->hasFeature('unlimited_users')) {
            return null; // null means unlimited
        }

        // Fetch the included_users from the plan
        $stmt = $this->pdo->prepare("SELECT included_users FROM `plans` WHERE id = :plan_id");
        $stmt->execute([':plan_id' => $this->currentPlanId]);
        $includedUsers = $stmt->fetchColumn();

        return $includedUsers ? (int)$includedUsers : 0;
    }

    /**
     * Get the price per additional user for the current plan.
     *
     * @param string $billingInterval 'monthly' or 'yearly'
     * @return float The price per additional user
     */
    public function getPricePerAdditionalUser(string $billingInterval = 'monthly'): float {
        $this->loadPlanFeatures();

        if (!$this->currentPlanId) {
            return 0.0;
        }

        $column = $billingInterval === 'yearly' 
            ? 'price_per_additional_user_yearly' 
            : 'price_per_additional_user_monthly';

        $stmt = $this->pdo->prepare("SELECT {$column} FROM `plans` WHERE id = :plan_id");
        $stmt->execute([':plan_id' => $this->currentPlanId]);
        $price = $stmt->fetchColumn();

        return $price ? (float)$price : 0.0;
    }

    /**
     * Check if the current plan allows external webhooks.
     * Only Enterprise plan has this feature.
     *
     * @return bool True if external webhooks are allowed, false otherwise
     */
    public function canUseExternalWebhooks(): bool {
        return $this->hasFeature('external_webhooks');
    }

    /**
     * Get detailed information about the current plan.
     *
     * @return array|null Plan details or null if no plan assigned
     */
    public function getPlanDetails(): ?array {
        $this->loadPlanFeatures();

        if (!$this->currentPlanId) {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT 
                id,
                name,
                description,
                base_price_monthly,
                included_users,
                price_per_additional_user_monthly,
                base_price_yearly,
                price_per_additional_user_yearly,
                currency,
                features
            FROM `plans` 
            WHERE id = :plan_id
        ");
        $stmt->execute([':plan_id' => $this->currentPlanId]);
        $plan = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($plan && isset($plan['features'])) {
            $plan['features'] = json_decode($plan['features'], true);
        }

        return $plan ?: null;
    }

    /**
     * Get the current active user count for the organization.
     *
     * @return int Number of active users
     */
    public function getCurrentUserCount(): int {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM `users` 
            WHERE org_id = :organization_id
        ");
        $stmt->execute([':organization_id' => $this->organizationId]);
        return (int)$stmt->fetchColumn();
    }

    public function getOrganizationId(): int {
        return $this->organizationId;
    }

    /**
     * Get the current subscription status for the organization.
     *
     * @return string The subscription status (active, trialing, past_due, etc.) or 'none'
     */
    public function getSubscriptionStatus(): string {
        $stmt = $this->pdo->prepare("
            SELECT status FROM `subscriptions` 
            WHERE organization_id = :organization_id 
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([':organization_id' => $this->organizationId]);
        $status = $stmt->fetchColumn();
        return $status ?: 'none';
    }
}

// Helper function to get an instance of PlanFeatureChecker globally
// Assumes $pdo and $currentUser are available from api_common.php
function getPlanFeatureChecker(PDO $pdo, array $currentUser): PlanFeatureChecker {
    // Support both 'org_id' (users table column) and 'organization_id' field names
    $orgId = (int)($currentUser['org_id'] ?? $currentUser['organization_id'] ?? 0);
    if (!$orgId) {
        // If no organization, or not authenticated, return a checker that grants no features
        return new PlanFeatureChecker($pdo, 0);
    }
    // Static instance to avoid re-creating on every call in the same request
    static $instance = null;
    if ($instance === null || $instance->getOrganizationId() !== $orgId) {
        $instance = new PlanFeatureChecker($pdo, $orgId);
    }
    return $instance;
}
