<?php
// api/reports/summary.php
header("Content-Type: application/json");
require_once '../../config/db.php';

// Allow CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");

if (!isset($_GET['org_id'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing org_id"]);
    exit;
}

$org_id = (int)$_GET['org_id'];

try {
    // Detect optional columns for backwards compatibility
    $hasLeadValue = false;
    $hasPriority = false;
    try {
        $colCheck = $pdo->query("SHOW COLUMNS FROM leads LIKE 'lead_value'");
        $hasLeadValue = $colCheck && $colCheck->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $hasLeadValue = false;
    }
    try {
        $colCheck2 = $pdo->query("SHOW COLUMNS FROM leads LIKE 'priority'");
        $hasPriority = $colCheck2 && $colCheck2->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $hasPriority = false;
    }

    $valueSelect = $hasLeadValue
        ? "SUM(CASE WHEN lead_value IS NOT NULL THEN lead_value ELSE 0 END)"
        : "0";
    $prioritySelect = $hasPriority ? "COALESCE(priority, 'Unknown')" : "'Unknown'";

    // 1. Total Leads
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE org_id = :org_id");
    $stmt->execute([':org_id' => $org_id]);
    $total_leads = $stmt->fetchColumn();

    // 2. Leads by Stage (Count & Value)
    $stmt = $pdo->prepare("
        SELECT stage_id, COUNT(*) as count, {$valueSelect} as total_value
        FROM leads 
        WHERE org_id = :org_id
        GROUP BY stage_id
    ");
    $stmt->execute([':org_id' => $org_id]);
    $leads_by_stage = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Leads by Source (Count & Value)
    $stmt = $pdo->prepare("
        SELECT 
            source, 
            COUNT(*) as count,
            {$valueSelect} as total_value
        FROM leads 
        WHERE org_id = :org_id
        GROUP BY source
        ORDER BY COUNT(*) DESC
    ");
    $stmt->execute([':org_id' => $org_id]);
    $leads_by_source = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Leads by Priority (Count & Value) - tolerant if priority column missing
    $stmt = $pdo->prepare("
        SELECT {$prioritySelect} as priority, COUNT(*) as count, {$valueSelect} as total_value
        FROM leads
        WHERE org_id = :org_id
        " . ($hasPriority ? "GROUP BY COALESCE(priority, 'Unknown')" : "") . "
    ");
    $stmt->execute([':org_id' => $org_id]);
    $leads_by_priority = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. Custom Fields Aggregation
    // Fetch all leads with custom_data
    $stmt = $pdo->prepare("SELECT custom_data FROM leads WHERE org_id = :org_id AND custom_data IS NOT NULL");
    $stmt->execute([':org_id' => $org_id]);
    $leads_custom = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Aggregate custom field values
    $custom_field_stats = [];
    foreach ($leads_custom as $row) {
        $custom_data = json_decode($row['custom_data'], true);
        if (!$custom_data) continue;
        
        foreach ($custom_data as $field_name => $field_value) {
            if (!isset($custom_field_stats[$field_name])) {
                $custom_field_stats[$field_name] = [];
            }
            
            // Count occurrences of each value
            $value_key = (string)$field_value;
            if (!isset($custom_field_stats[$field_name][$value_key])) {
                $custom_field_stats[$field_name][$value_key] = 0;
            }
            $custom_field_stats[$field_name][$value_key]++;
        }
    }
    
    // Convert to array format for frontend
    $custom_fields_data = [];
    foreach ($custom_field_stats as $field_name => $values) {
        $data_points = [];
        foreach ($values as $value => $count) {
            $data_points[] = ['value' => $value, 'count' => $count];
        }
        $custom_fields_data[$field_name] = $data_points;
    }
    
    echo json_encode([
        "total_leads" => $total_leads,
        "by_stage" => $leads_by_stage,
        "by_source" => $leads_by_source,
        "by_priority" => $leads_by_priority,
        "custom_fields" => $custom_fields_data
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
