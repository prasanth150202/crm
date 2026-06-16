<?php
require_once '../../../../includes/api_common.php';
require_once '../../admin_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::error('Method not allowed', 405);
}

$data = json_decode(file_get_contents('php://input'), true) ?? [];

$plan_id            = (int)($data['id'] ?? 0);
$name               = trim($data['name'] ?? '');
$description        = trim($data['description'] ?? '');
$base_price_monthly = (float)($data['base_price_monthly'] ?? 0);
$base_price_yearly  = (float)($data['base_price_yearly'] ?? 0);
$included_users     = (int)($data['included_users'] ?? 1);
$price_per_additional_user_monthly = (float)($data['price_per_additional_user_monthly'] ?? 0);
$price_per_additional_user_yearly  = (float)($data['price_per_additional_user_yearly'] ?? 0);
$trial_days         = (int)($data['trial_days'] ?? 14);
$currency           = strtoupper(trim($data['currency'] ?? 'INR'));
$is_custom          = (int)!empty($data['is_custom']);
$is_active          = isset($data['is_active']) ? (int)(bool)$data['is_active'] : 1;
$custom_org_id      = !empty($data['custom_org_id']) ? (int)$data['custom_org_id'] : null;
$tax_treatment      = in_array($data['tax_treatment'] ?? '', ['none','exclusive','inclusive']) ? $data['tax_treatment'] : 'none';
$feature_keys       = is_array($data['feature_keys'] ?? null) ? $data['feature_keys'] : [];

if (!$plan_id) ApiResponse::error('Plan ID is required', 400);
if ($name === '') ApiResponse::error('Plan name is required', 400);

// Check if tax_treatment column exists (added via ALTER TABLE)
$colCheck  = $pdo->query("SHOW COLUMNS FROM plans LIKE 'tax_treatment'");
$hasTaxCol = $colCheck->rowCount() > 0;

try {
    $pdo->beginTransaction();

    if ($hasTaxCol) {
        $stmt = $pdo->prepare(
            "UPDATE plans SET name=?, description=?, base_price_monthly=?, base_price_yearly=?,
                included_users=?, price_per_additional_user_monthly=?, price_per_additional_user_yearly=?,
                trial_days=?, currency=?, is_custom=?, custom_org_id=?, is_active=?, tax_treatment=?
             WHERE id=?"
        );
        $stmt->execute([
            $name, $description, $base_price_monthly, $base_price_yearly,
            $included_users, $price_per_additional_user_monthly, $price_per_additional_user_yearly,
            $trial_days, $currency, $is_custom, $custom_org_id, $is_active, $tax_treatment, $plan_id
        ]);
    } else {
        $stmt = $pdo->prepare(
            "UPDATE plans SET name=?, description=?, base_price_monthly=?, base_price_yearly=?,
                included_users=?, price_per_additional_user_monthly=?, price_per_additional_user_yearly=?,
                trial_days=?, currency=?, is_custom=?, custom_org_id=?, is_active=?
             WHERE id=?"
        );
        $stmt->execute([
            $name, $description, $base_price_monthly, $base_price_yearly,
            $included_users, $price_per_additional_user_monthly, $price_per_additional_user_yearly,
            $trial_days, $currency, $is_custom, $custom_org_id, $is_active, $plan_id
        ]);
    }

    // Replace feature set
    $pdo->prepare("DELETE FROM plan_features WHERE plan_id = ?")->execute([$plan_id]);
    if ($feature_keys) {
        $ins = $pdo->prepare("INSERT IGNORE INTO plan_features (plan_id, knob_key) VALUES (?, ?)");
        foreach ($feature_keys as $key) {
            $ins->execute([$plan_id, $key]);
        }
    }

    $pdo->commit();
    ApiResponse::success(['message' => 'Plan updated successfully']);
} catch (PDOException $e) {
    $pdo->rollBack();
    ApiResponse::error('Database error: ' . $e->getMessage(), 500);
}
