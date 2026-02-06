<?php
// api/auth/register.php
header("Content-Type: application/json");
require_once '../../config/db.php';

// Allow CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['email']) || !isset($data['password']) || !isset($data['org_name']) || !isset($data['plan_id'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required fields: email, password, org_name, plan_id"]);
    exit;
}

$email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
$password = $data['password'];
$org_name = htmlspecialchars($data['org_name']);
$plan_id = (int)$data['plan_id'];
$billing_interval = isset($data['billing_interval']) && strtolower($data['billing_interval']) === 'yearly' ? 'yearly' : 'monthly';

try {

        // Helper: assign all plan features to owner/admin roles for this org
        function assignDefaultRolePermissions($pdo, $orgId, $planId) {
            $stmt = $pdo->prepare("SELECT knob_key FROM plan_features WHERE plan_id = ?");
            $stmt->execute([$planId]);
            $features = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (!$features) return;
            $roles = ['owner', 'admin'];
            foreach ($roles as $role) {
                foreach ($features as $knobKey) {
                    $stmt2 = $pdo->prepare("INSERT IGNORE INTO role_permissions (role, knob_key, is_enabled, org_id, updated_at) VALUES (?, ?, 1, ?, NOW())");
                    $stmt2->execute([$role, $knobKey, $orgId]);
                }
            }
        }

        // Helper: assign all plan features as enabled in user_permissions for a user
        function assignAllPlanFeaturesToUser($pdo, $userId, $planId) {
            // Assign ALL possible features from feature_knobs, not just plan_features
            $stmt = $pdo->prepare("SELECT knob_key FROM feature_knobs");
            $stmt->execute();
            $features = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (!$features) {
                error_log('[register] No features found in feature_knobs table.');
                return;
            }
            foreach ($features as $knobKey) {
                $stmt2 = $pdo->prepare("INSERT IGNORE INTO user_permissions (user_id, knob_key, is_enabled, updated_at) VALUES (?, ?, 1, NOW())");
                $stmt2->execute([$userId, $knobKey]);
            }
            error_log('[register] Assigned ALL features to user_permissions for user_id=' . $userId . ', features=' . json_encode($features));
        }
    $pdo->beginTransaction();

    // 1. Validate the Plan
    $stmt = $pdo->prepare("SELECT * FROM plans WHERE id = ? AND is_active = TRUE");
    $stmt->execute([$plan_id]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$plan) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(["error" => "Invalid or inactive plan selected."]);
        exit;
    }

    // 2. Check if user already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(["error" => "User already exists"]);
        exit;
    }

    // 3. Create Organization
    $stmt = $pdo->prepare("INSERT INTO organizations (name) VALUES (?)");
    $stmt->execute([$org_name]);
    $org_id = $pdo->lastInsertId();

    // Assign default permissions for owner/admin roles for all plan features
    assignDefaultRolePermissions($pdo, $org_id, $plan_id);

    // 4. Create User (Super Admin for the new Org)
    $password_hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("INSERT INTO users (org_id, email, password_hash, role) VALUES (?, ?, ?, 'admin')");
    $stmt->execute([$org_id, $email, $password_hash]);
    $user_id = $pdo->lastInsertId();

    // Assign all plan features as enabled in user_permissions for this user (UI gates for this user)
    assignAllPlanFeaturesToUser($pdo, $user_id, $plan_id);


    // 5. Create Subscription

    $is_free_plan = ($plan['base_price_monthly'] == 0);
    $status = $is_free_plan ? 'active' : 'trialing';
    $trial_starts_at = $is_free_plan ? null : date('Y-m-d H:i:s');
    $trial_ends_at = $is_free_plan ? null : date('Y-m-d H:i:s', strtotime('+14 days'));
    $razorpay_subscription_id = null;
    $razorpay_checkout_url = null;

    if ($is_free_plan) {
        // Free plan: no payment required
        $razorpay_subscription_id = 'free_' . $org_id . '_' . time();
    } else {
        // Paid plan: create Razorpay subscription and prepare checkout
        require_once __DIR__ . '/../../includes/RazorpayService.php';
        $razorpay = new RazorpayService();
        $customerDetails = [
            'organization_name' => $org_name,
            'email' => $email,
            'contact' => $data['contact'] ?? null
        ];
        $planId = $billing_interval === 'yearly' ? $plan['razorpay_plan_id_yearly'] : $plan['razorpay_plan_id_monthly'];
        $totalUsers = 1; // Default for new org
        $pricePerAdditionalUser = $billing_interval === 'yearly' ? ($plan['price_per_additional_user_yearly'] ?? 0) : ($plan['price_per_additional_user_monthly'] ?? 0);
        $includedUsers = $plan['included_users'] ?? 1;
        $subscription = $razorpay->createSubscription($planId, $customerDetails, $totalUsers, $billing_interval, $pricePerAdditionalUser, $includedUsers);
        $razorpay_subscription_id = $subscription['id'];
        // Prepare checkout URL for frontend (Razorpay recommends using Checkout.js on frontend)
        $razorpay_checkout_url = null; // Set by frontend using subscription_id
    }

    $stmt = $pdo->prepare(
        "INSERT INTO subscriptions (organization_id, plan_id, razorpay_subscription_id, status, billing_interval, trial_starts_at, trial_ends_at, created_at, updated_at) 
         VALUES (?, ?, ?, ?, 'monthly', ?, ?, NOW(), NOW())"
    );
    $stmt->execute([$org_id, $plan_id, $razorpay_subscription_id, $status, $trial_starts_at, $trial_ends_at]);


    // 6. Create Default Pipeline
    $default_stages = json_encode([
        ["id" => "new", "name" => "New", "color" => "#3b82f6"],
        ["id" => "contacted", "name" => "Contacted", "color" => "#f59e0b"],
        ["id" => "qualified", "name" => "Qualified", "color" => "#10b981"],
        ["id" => "lost", "name" => "Lost", "color" => "#ef4444"],
        ["id" => "won", "name" => "Won", "color" => "#8b5cf6"]
    ]);
    $stmt = $pdo->prepare("INSERT INTO pipelines (org_id, name, stages) VALUES (?, 'Default Pipeline', ?)");
    $stmt->execute([$org_id, $default_stages]);

    $pdo->commit();


    $response = [
        "message" => "Registration successful",
        "org_id" => $org_id,
        "user_id" => $user_id,
        "plan_id" => $plan_id,
        "is_free_plan" => $is_free_plan,
        "razorpay_subscription_id" => $razorpay_subscription_id
    ];
    if (!$is_free_plan) {
        $response["razorpay_checkout_required"] = true;
        $response["razorpay_checkout_url"] = $razorpay_checkout_url; // Frontend should use subscription_id for Checkout.js
    }
    echo json_encode($response);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(["error" => "Registration failed: " . $e->getMessage()]);
}
