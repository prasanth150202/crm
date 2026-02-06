<?php
/**
 * Shared API Initialization
 * Fetches current user and validates session
 */

// Enable error display for debugging if needed
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/permissions.php';
require_once __DIR__ . '/../config/response.php';
require_once __DIR__ . '/auth_check.php'; // Now handles API 401 correctly
require_once __DIR__ . '/PlanFeatureChecker.php'; // NEW: Include our PlanFeatureChecker

// Session is already started and checked by auth_check.php

try {
    $pdo = getDb();
    
    // Safety check for session variable (should be caught by auth_check.php but just in case)
    if (!isset($_SESSION['user_id'])) {
         ApiResponse::error('Unauthorized access - session missing', 401);
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $currentUser = $stmt->fetch();

    if (!$currentUser) {
        ApiResponse::error('User session invalid - user not found', 401);
    }

    // NEW: Map 'org_id' to 'organization_id' for consistency with PlanFeatureChecker and other logic
    if (isset($currentUser['org_id'])) {
        $currentUser['organization_id'] = $currentUser['org_id'];
    } else {
        // Handle case where user might not be associated with an organization (e.g., super_admin)
        // For now, if no org_id, set to NULL which PlanFeatureChecker handles
        $currentUser['organization_id'] = null; 
    }

    // Ensure $user is the user array, not the DB username string from db.php
    $user = $currentUser;
    $permissionManager = getPermissionManager($pdo, $user);

    // NEW: Initialize PlanFeatureChecker
    // The getPlanFeatureChecker function handles cases where 'organization_id' might not be directly in $currentUser
    $planFeatureChecker = getPlanFeatureChecker($pdo, $currentUser);


} catch (PDOException $e) {
    ApiResponse::error('Database error in initialization: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
    ApiResponse::error('System error in initialization: ' . $e->getMessage(), 500);
}
