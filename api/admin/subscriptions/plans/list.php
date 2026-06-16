<?php
require_once '../../../../includes/api_common.php';
require_once '../../admin_check.php';

$stmt = $pdo->query(
    "SELECT p.*,
            GROUP_CONCAT(pf.knob_key ORDER BY pf.knob_key SEPARATOR ',') AS feature_keys
     FROM plans p
     LEFT JOIN plan_features pf ON pf.plan_id = p.id
     GROUP BY p.id
     ORDER BY p.is_custom ASC, p.base_price_monthly ASC"
);
$plans = $stmt->fetchAll();

foreach ($plans as &$plan) {
    $plan['feature_keys'] = $plan['feature_keys'] ? explode(',', $plan['feature_keys']) : [];
}
unset($plan);

ApiResponse::success(['plans' => $plans]);
