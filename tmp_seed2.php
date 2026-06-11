<?php
require_once 'config/db.php';
$org_id = 10;

// Charts that already exist — skip these types
$existing = $pdo->query("SELECT chart_type FROM custom_charts WHERE org_id = $org_id")
    ->fetchAll(PDO::FETCH_COLUMN);

$toInsert = [
    // 4 KPI scorecards mapping to boss's stat cards
    [
        'title'       => 'Total Leads',
        'chart_type'  => 'scorecard',
        'data_field'  => 'leads',
        'aggregation' => 'count',
        'x_axis'      => 'stage_id',
        'color_scheme'=> 'default',
        'chart_sort'  => 'default',
        'show_total'  => 0,
        'sort_order'  => 20,
        'skip_if_type'=> 'scorecard',  // already have one
    ],
    // Channel-wise funnel table (source × stage breakdown)
    [
        'title'       => 'Channel × Stage Breakdown',
        'chart_type'  => 'channel_stage_matrix',
        'data_field'  => 'leads',
        'aggregation' => 'count',
        'x_axis'      => 'source',
        'color_scheme'=> 'default',
        'chart_sort'  => 'default',
        'show_total'  => 0,
        'sort_order'  => 21,
        'skip_if_type'=> 'channel_stage_matrix',
    ],
    // Weekly trend line
    [
        'title'       => 'Weekly Lead Trend',
        'chart_type'  => 'line',
        'data_field'  => 'leads',
        'aggregation' => 'count',
        'x_axis'      => 'created_week',
        'color_scheme'=> 'cool',
        'chart_sort'  => 'default',
        'show_total'  => 0,
        'sort_order'  => 22,
        'skip_if_type'=> null,
    ],
    // Source bar chart
    [
        'title'       => 'Leads by Source',
        'chart_type'  => 'bar',
        'data_field'  => 'leads',
        'aggregation' => 'count',
        'x_axis'      => 'source',
        'color_scheme'=> 'cool',
        'chart_sort'  => 'value_desc',
        'show_total'  => 0,
        'sort_order'  => 23,
        'skip_if_type'=> null,
    ],
    // Stage pie
    [
        'title'       => 'Stage Distribution',
        'chart_type'  => 'pie',
        'data_field'  => 'leads',
        'aggregation' => 'count',
        'x_axis'      => 'stage_id',
        'color_scheme'=> 'default',
        'chart_sort'  => 'default',
        'show_total'  => 0,
        'sort_order'  => 24,
        'skip_if_type'=> null,
    ],
    // Revenue scorecard
    [
        'title'       => 'Revenue Closed',
        'chart_type'  => 'scorecard',
        'data_field'  => 'lead_value',
        'aggregation' => 'sum',
        'x_axis'      => 'stage_id',
        'color_scheme'=> 'green',
        'chart_sort'  => 'default',
        'show_total'  => 0,
        'sort_order'  => 25,
        'skip_if_type'=> null, // different data_field so not a duplicate
    ],
];

$stmt = $pdo->prepare("
    INSERT INTO custom_charts (org_id, global, title, chart_type, data_field, aggregation, x_axis, color_scheme, chart_sort, show_total, sort_order)
    VALUES (?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

// Check by title to avoid true duplicates
$existingTitles = $pdo->query("SELECT LOWER(title) FROM custom_charts WHERE org_id = $org_id")
    ->fetchAll(PDO::FETCH_COLUMN);

foreach ($toInsert as $c) {
    $titleLower = strtolower($c['title']);
    if (in_array($titleLower, $existingTitles)) {
        echo "SKIP (title exists): {$c['title']}\n";
        continue;
    }
    $stmt->execute([
        $org_id,
        $c['title'],
        $c['chart_type'],
        $c['data_field'],
        $c['aggregation'],
        $c['x_axis'],
        $c['color_scheme'],
        $c['chart_sort'],
        $c['show_total'],
        $c['sort_order'],
    ]);
    echo "INSERTED [{$pdo->lastInsertId()}]: {$c['title']} ({$c['chart_type']})\n";
    $existingTitles[] = $titleLower;
}

echo "\n=== FINAL CHART LIST (org 10) ===\n";
$all = $pdo->query("SELECT id, title, chart_type, sort_order FROM custom_charts WHERE org_id=$org_id ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC);
foreach ($all as $r) echo "[{$r['id']}] {$r['title']} ({$r['chart_type']}) order={$r['sort_order']}\n";
