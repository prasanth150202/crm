<?php
// api/reports/chart_data.php - Flexible data endpoint for custom charts
header("Content-Type: application/json");
require_once '../../config/db.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$org_id = $_SESSION['org_id'];
if (!$org_id) {
    http_response_code(400);
    echo json_encode(["error" => "Missing org_id"]);
    exit;
}
$field = $_GET['field'] ?? 'stage_id';
$aggregation = $_GET['aggregation'] ?? 'count';
$chart_id = $_GET['chart_id'] ?? null;

// If chart_id is provided, get chart config
if ($chart_id) {
    $stmt = $pdo->prepare("SELECT * FROM custom_charts WHERE id = ? AND org_id = ?");
    $stmt->execute([$chart_id, $org_id]);
    $chart = $stmt->fetch();
    
    if ($chart) {
        $field = $chart['x_axis'] ?? 'stage_id';
        $aggregation = $chart['aggregation'] ?? 'count';
    }
}

try {
    // Build dynamic query based on parameters
    if ($field === 'stage_id') {
        // Handle stage_id specially
        $query = "SELECT stage_id as label, COUNT(*) as count, SUM(lead_value) as total_value FROM leads WHERE org_id = :org_id GROUP BY stage_id ORDER BY count DESC";
    } else if ($field === 'source') {
        $query = "SELECT COALESCE(source, 'Unknown') as label, COUNT(*) as count, SUM(lead_value) as total_value FROM leads WHERE org_id = :org_id GROUP BY source ORDER BY count DESC";
    } else {
        $query = "SELECT $field as label, COUNT(*) as count FROM leads WHERE org_id = :org_id GROUP BY $field ORDER BY count DESC";
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([':org_id' => $org_id]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format for Chart.js
    $labels = [];
    $values = [];
    
    foreach ($results as $row) {
        $label = $row['label'];
        if ($field === 'stage_id') {
            // Convert stage_id to readable names
            $stageNames = ['new' => 'New', 'contacted' => 'Contacted', 'qualified' => 'Qualified', 'won' => 'Won', 'lost' => 'Lost'];
            $label = $stageNames[$label] ?? ucfirst($label);
        }
        $labels[] = $label ?? 'Unknown';
        
        if ($aggregation === 'sum' && isset($row['total_value'])) {
            $values[] = (float)$row['total_value'];
        } else if ($aggregation === 'avg' && isset($row['total_value']) && $row['count'] > 0) {
            $values[] = (float)$row['total_value'] / (float)$row['count'];
        } else {
            $values[] = (float)$row['count'];
        }
    }
    
    echo json_encode([
        "labels" => $labels,
        "datasets" => [[
            "data" => $values,
            "backgroundColor" => ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6'],
            "borderColor" => ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6']
        ]],
        "comboCounts" => array_column($results, 'count'),
        "comboValues" => array_column($results, 'total_value')
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
