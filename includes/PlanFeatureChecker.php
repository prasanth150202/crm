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

        $this->planFeatures = []; // Default to empty array if no plan or features found

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
}

// Helper function to get an instance of PlanFeatureChecker globally
// Assumes $pdo and $currentUser are available from api_common.php
function getPlanFeatureChecker(PDO $pdo, array $currentUser): PlanFeatureChecker {
    if (!isset($currentUser['organization_id'])) {
        // If no organization, or not authenticated, return a checker that grants no features
        return new PlanFeatureChecker($pdo, 0); // Use 0 or similar to represent no organization
    }
    // Static instance to avoid re-creating on every call in the same request
    static $instance = null;
    if ($instance === null || $instance->organizationId !== $currentUser['organization_id']) {
        $instance = new PlanFeatureChecker($pdo, $currentUser['organization_id']);
    }
    return $instance;
}
