<?php
/**
 * Check plan_features table contents
 */

require_once __DIR__ . '/../../config/db.php';

$pdo = getDb();

echo "\nChecking plan_features table...\n\n";

// Count features per plan
$stmt = $pdo->prepare("SELECT plan_id, COUNT(*) as feature_count FROM plan_features GROUP BY plan_id");
$stmt->execute();
$counts = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Features per plan:\n";
foreach ($counts as $row) {
    echo "  Plan ID {$row['plan_id']}: {$row['feature_count']} features\n";
}

echo "\n";

// Show all features
$stmt = $pdo->prepare("SELECT pf.plan_id, p.name as plan_name, pf.knob_key FROM plan_features pf LEFT JOIN plans p ON pf.plan_id = p.id ORDER BY pf.plan_id");
$stmt->execute();
$features = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "All plan features:\n";
$currentPlan = null;
foreach ($features as $feat) {
    if ($currentPlan !== $feat['plan_id']) {
        $currentPlan = $feat['plan_id'];
        echo "\n{$feat['plan_name']} (ID: {$feat['plan_id']}):\n";
    }
    echo "  - {$feat['knob_key']}\n";
}
