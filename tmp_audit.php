<?php
require_once 'config/db.php';
$org_id = 10;

// Existing charts
$stmt = $pdo->query("SELECT id, title, chart_type, x_axis, data_field, aggregation, sort_order FROM custom_charts WHERE org_id = $org_id ORDER BY sort_order ASC");
$charts = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "=== EXISTING CHARTS (org 10) ===\n";
foreach ($charts as $c) {
    echo "[{$c['id']}] {$c['title']} | type={$c['chart_type']} | x={$c['x_axis']} | field={$c['data_field']} | agg={$c['aggregation']} | order={$c['sort_order']}\n";
}

// Sources in leads
$stmt2 = $pdo->query("SELECT COALESCE(source,'Unknown') as src, COUNT(*) as cnt FROM leads WHERE org_id=$org_id GROUP BY source ORDER BY cnt DESC");
echo "\n=== LEAD SOURCES ===\n";
foreach ($stmt2->fetchAll(PDO::FETCH_ASSOC) as $r) echo $r['src'].' = '.$r['cnt']."\n";

// Stages
$stmt3 = $pdo->query("SELECT stage_id, COUNT(*) as cnt FROM leads WHERE org_id=$org_id GROUP BY stage_id ORDER BY cnt DESC");
echo "\n=== LEAD STAGES ===\n";
foreach ($stmt3->fetchAll(PDO::FETCH_ASSOC) as $r) echo $r['stage_id'].' = '.$r['cnt']."\n";

echo "\n=== TOTAL LEADS ===\n";
echo $pdo->query("SELECT COUNT(*) FROM leads WHERE org_id=$org_id")->fetchColumn()."\n";
