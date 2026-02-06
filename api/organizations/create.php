<?php
// api/organizations/create.php
// Create new organization (super admin only)

header("Content-Type: application/json");
require_once '../../config/db.php';

// Check authentication
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($data['name'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Missing required field: name'
    ]);
    exit;
}

try {
        // Helper: assign all plan features to owner/admin roles for this org
        function assignDefaultRolePermissions($pdo, $orgId, $planId) {
            // Get all features for this plan
            $stmt = $pdo->prepare("SELECT knob_key FROM plan_features WHERE plan_id = ?");
            $stmt->execute([$planId]);
            $features = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (!$features) return;
            $roles = ['owner', 'admin'];
            foreach ($roles as $role) {
                foreach ($features as $knobKey) {
                    // Insert or ignore if already exists
                    $stmt2 = $pdo->prepare("INSERT IGNORE INTO role_permissions (role, knob_key, is_enabled, org_id, updated_at) VALUES (?, ?, 1, ?, NOW())");
                    $stmt2->execute([$role, $knobKey, $orgId]);
                }
            }
        }
    // Get current user
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user) {
        http_response_code(401);
        echo json_encode(["error" => "User not found"]);
        exit;
    }

    // Check required fields
    if (!isset($data['plan_id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Missing required field: plan_id'
        ]);
        exit;
    }

    // Check if organization name already exists
    $stmt = $pdo->prepare("SELECT id FROM organizations WHERE name = :name");
    $stmt->execute([':name' => $data['name']]);
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Organization name already exists'
        ]);
        exit;
    }


    // Insert organization with selected plan
    $stmt = $pdo->prepare("
        INSERT INTO organizations (name, is_active, settings, current_plan_id)
        VALUES (:name, :is_active, :settings, :plan_id)
    ");
    $stmt->execute([
        ':name' => $data['name'],
        ':is_active' => isset($data['is_active']) ? (bool)$data['is_active'] : true,
        ':settings' => isset($data['settings']) ? json_encode($data['settings']) : null,
        ':plan_id' => $data['plan_id']
    ]);
    $orgId = $pdo->lastInsertId();

    // Assign default permissions for owner/admin roles for all plan features
    assignDefaultRolePermissions($pdo, $orgId, $data['plan_id']);

    // Assign creator as owner/admin
    $stmt = $pdo->prepare("INSERT INTO user_organizations (user_id, org_id, role, is_active) VALUES (?, ?, 'owner', 1)");
    $stmt->execute([$_SESSION['user_id'], $orgId]);

    // Fetch plan details for Razorpay
    $stmt = $pdo->prepare("SELECT * FROM plans WHERE id = ?");
    $stmt->execute([$data['plan_id']]);
    $plan = $stmt->fetch();
    if (!$plan) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Plan not found'
        ]);
        exit;
    }

    // Create Razorpay subscription
    require_once '../../includes/RazorpayService.php';
    $razorpay = new RazorpayService();
    $customerDetails = [
        'name' => $user['full_name'],
        'email' => $user['email'],
        'organization_name' => $data['name']
    ];
    $razorpayPlanId = $plan['razorpay_plan_id_monthly']; // or yearly, based on user choice
    $subscription = $razorpay->createSubscription(
        $razorpayPlanId,
        $customerDetails,
        1, // initial users
        'monthly', // or 'yearly'
        $plan['price_per_additional_user_monthly'],
        $plan['included_users']
    );

    // Insert subscription record
    $stmt = $pdo->prepare("INSERT INTO subscriptions (organization_id, plan_id, razorpay_subscription_id, status, billing_interval, billed_users_count, created_at, updated_at) VALUES (?, ?, ?, 'trialing', 'monthly', 1, NOW(), NOW())");
    $stmt->execute([$orgId, $plan['id'], $subscription['id']]);

    echo json_encode([
        'success' => true,
        'org_id' => $orgId,
        'razorpay_subscription_id' => $subscription['id'],
        'message' => 'Organization and subscription created successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to create organization: ' . $e->getMessage()
    ]);
}
