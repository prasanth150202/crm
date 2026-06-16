<?php
require_once '../../../../includes/api_common.php';
require_once '../../admin_check.php';

$stmt = $pdo->query(
    "SELECT knob_key, knob_name, description, category
     FROM feature_knobs
     ORDER BY category, knob_name"
);
$rows = $stmt->fetchAll();

$grouped = [];
foreach ($rows as $row) {
    $grouped[$row['category']][] = $row;
}

ApiResponse::success(['feature_knobs' => $grouped]);
