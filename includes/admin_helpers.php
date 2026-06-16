<?php
/**
 * Shared helpers for admin operations.
 * Safe to require_once from multiple files — all functions guard with function_exists.
 */

if (!function_exists('assignDefaultRolePermissions')) {
    /**
     * Assign all plan feature knobs to owner+admin roles for an org.
     */
    function assignDefaultRolePermissions($pdo, $orgId, $planId) {
        $stmt = $pdo->prepare("SELECT knob_key FROM plan_features WHERE plan_id = ?");
        $stmt->execute([$planId]);
        $features = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!$features) return;
        $roles = ['owner', 'admin'];
        foreach ($roles as $role) {
            foreach ($features as $knobKey) {
                $stmt2 = $pdo->prepare(
                    "INSERT IGNORE INTO role_permissions (role, knob_key, is_enabled, org_id, updated_at)
                     VALUES (?, ?, 1, ?, NOW())"
                );
                $stmt2->execute([$role, $knobKey, $orgId]);
            }
        }
    }
}

if (!function_exists('assignAllPlanFeaturesToUser')) {
    /**
     * Assign plan feature knobs directly to a user's permission record.
     */
    function assignAllPlanFeaturesToUser($pdo, $userId, $planId) {
        $stmt = $pdo->prepare("SELECT knob_key FROM plan_features WHERE plan_id = ?");
        $stmt->execute([$planId]);
        $features = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!$features) return;
        foreach ($features as $knobKey) {
            $stmt2 = $pdo->prepare(
                "INSERT IGNORE INTO user_permissions (user_id, knob_key, is_enabled, updated_at)
                 VALUES (?, ?, 1, NOW())"
            );
            $stmt2->execute([$userId, $knobKey]);
        }
    }
}
