<?php
require_once '../config/db.php';

try {
    // Get the first organization ID
    $stmt = $pdo->query("SELECT id FROM organizations LIMIT 1");
    $org_id = $stmt->fetchColumn();
    
    if (!$org_id) {
        echo "No organization found\n";
        exit;
    }
    
    // Insert sample charts
    $charts = [
        [
            'title' => 'Leads by Status',
            'chart_type' => 'pie',
            'data_field' => 'leads',
            'aggregation' => 'count',
            'x_axis' => 'stage_id'
        ],
        [
            'title' => 'Leads Over Time',
            'chart_type' => 'line',
            'data_field' => 'leads',
            'aggregation' => 'count',
            'x_axis' => 'stage_id'
        ],
        [
            'title' => 'Lead Sources',
            'chart_type' => 'bar',
            'data_field' => 'leads',
            'aggregation' => 'count',
            'x_axis' => 'source'
        ]
    ];
    
    $stmt = $pdo->prepare("INSERT INTO custom_charts (org_id, title, chart_type, data_field, aggregation, x_axis, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    foreach ($charts as $index => $chart) {
        $stmt->execute([$org_id, $chart['title'], $chart['chart_type'], $chart['data_field'], $chart['aggregation'], $chart['x_axis'], $index]);
    }
    
    echo "Sample charts created successfully\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>