<?php
// api/external/reports.php
header("Content-Type: application/json");
require_once '../../config/db.php';
require_once 'middleware.php';

corsHeaders();

$org_id = validateApiKey($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

$report_type = $_GET['type'] ?? 'summary';

switch ($report_type) {
    case 'summary':
        handleSummaryReport($pdo, $org_id);
        break;
    case 'conversion':
        handleConversionReport($pdo, $org_id);
        break;
    case 'source':
        handleSourceReport($pdo, $org_id);
        break;
    default:
        http_response_code(400);
        echo json_encode(["error" => "Invalid report type"]);
}

function handleSummaryReport($pdo, $org_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_leads,
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as leads_last_30_days,
                COUNT(CASE WHEN stage_id = (SELECT MAX(id) FROM stages WHERE org_id = ?) THEN 1 END) as converted_leads,
                AVG(lead_value) as avg_lead_value,
                SUM(lead_value) as total_value
            FROM leads WHERE org_id = ?
        ");
        $stmt->execute([$org_id, $org_id]);
        $summary = $stmt->fetch();
        
        echo json_encode(["success" => true, "data" => $summary]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to generate summary report: " . $e->getMessage()]);
    }
}

function handleConversionReport($pdo, $org_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                s.name as stage_name,
                COUNT(l.id) as lead_count,
                AVG(l.lead_value) as avg_value
            FROM stages s
            LEFT JOIN leads l ON s.id = l.stage_id AND l.org_id = ?
            WHERE s.org_id = ?
            GROUP BY s.id, s.name
            ORDER BY s.order_position
        ");
        $stmt->execute([$org_id, $org_id]);
        $conversion = $stmt->fetchAll();
        
        echo json_encode(["success" => true, "data" => $conversion]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to generate conversion report: " . $e->getMessage()]);
    }
}

function handleSourceReport($pdo, $org_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                source,
                COUNT(*) as lead_count,
                AVG(lead_value) as avg_value,
                SUM(lead_value) as total_value
            FROM leads 
            WHERE org_id = ? AND source IS NOT NULL
            GROUP BY source
            ORDER BY lead_count DESC
        ");
        $stmt->execute([$org_id]);
        $sources = $stmt->fetchAll();
        
        echo json_encode(["success" => true, "data" => $sources]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to generate source report: " . $e->getMessage()]);
    }
}