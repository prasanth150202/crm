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

// Helper: assign all plan features to owner/admin roles for this org
if (!function_exists('assignDefaultRolePermissions')) {
    function assignDefaultRolePermissions($pdo, $orgId, $planId) {
        $stmt = $pdo->prepare("SELECT knob_key FROM plan_features WHERE plan_id = ?");
        $stmt->execute([$planId]);
        $features = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!$features) return;
        $roles = ['owner', 'admin'];
        foreach ($roles as $role) {
            foreach ($features as $knobKey) {
                $stmt2 = $pdo->prepare("INSERT IGNORE INTO role_permissions (role, knob_key, is_enabled, org_id) VALUES (?, ?, 1, ?)");
                $stmt2->execute([$role, $knobKey, $orgId]);
            }
        }
    }
}

try {
    // Get current user
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        http_response_code(401);
        echo json_encode(["error" => "User not found"]);
        exit;
    }

    // Permission check for Super Admin or specific permission could go here
    // For now, any logged in user can create an org as per current flow

    // Validate name
    $orgName = isset($data['name']) ? trim($data['name']) : '';
    if (!$orgName) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing required field: name']);
        exit;
    }

    // Validate plan_id
    $planId = isset($data['plan_id']) ? (int)$data['plan_id'] : 0;
    if (!$planId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing required field: plan_id']);
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
        ':name' => $orgName,
        ':is_active' => isset($data['is_active']) ? (bool)$data['is_active'] : true,
        ':settings' => isset($data['settings']) ? json_encode($data['settings']) : null,
        ':plan_id' => $planId
    ]);
    $orgId = $pdo->lastInsertId();

    // Assign default permissions
    assignDefaultRolePermissions($pdo, $orgId, $planId);

    // Assign creator as owner
    $stmt = $pdo->prepare("INSERT INTO user_organizations (user_id, org_id, role, is_active) VALUES (?, ?, 'owner', 1)");
    $stmt->execute([$_SESSION['user_id'], $orgId]);

    // Fetch plan details for Razorpay
    $stmt = $pdo->prepare("SELECT * FROM plans WHERE id = ?");
    $stmt->execute([$planId]);
    $plan = $stmt->fetch();
    
    if (!$plan) {
        throw new Exception("Selected plan not found in database.");
    }

    $razorpayPlanId = $plan['razorpay_plan_id_monthly'] ?? null;
    
    // Create Razorpay subscription if plan ID is available
    $subscriptionId = null;
    if ($razorpayPlanId) {
        try {
            require_once '../../includes/RazorpayService.php';
            $razorpay = new RazorpayService();
            $customerDetails = [
                'name' => $user['full_name'] ?? $user['email'],
                'email' => $user['email'],
                'organization_name' => $orgName
            ];
            
            $subscription = $razorpay->createSubscription(
                $razorpayPlanId,
                $customerDetails,
                1, 
                'monthly',
                (float)($plan['price_per_additional_user_monthly'] ?? 0),
                (int)($plan['included_users'] ?? 1)
            );
            $subscriptionId = $subscription['id'];

            // Insert subscription record
            $stmt = $pdo->prepare("INSERT INTO subscriptions (organization_id, plan_id, razorpay_subscription_id, status, billing_interval, billed_users_count, created_at, updated_at) VALUES (?, ?, ?, 'trialing', 'monthly', 1, NOW(), NOW())");
            $stmt->execute([$orgId, $planId, $subscriptionId]);
        } catch (Throwable $re) {
            // Log but don't fail the whole request if only Razorpay fails? 
            // Actually, for a production app, we might want to rollback the org creation if subscription fails.
            // For now, let's just surface the error.
            throw new Exception("Razorpay error: " . $re->getMessage());
        }
    }

    echo json_encode([
        'success' => true,
        'org_id' => $orgId,
        'razorpay_subscription_id' => $subscriptionId,
        'message' => 'Organization created successfully' . ($subscriptionId ? '' : ' (Razorpay skipped)')
    ]);
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'System error: ' . $e->getMessage()
    ]);
}
