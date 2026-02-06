<?php
// api/leads/get.php
header("Content-Type: application/json");
require_once '../../config/db.php';

// Allow CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

if (!isset($_GET['id']) || !isset($_GET['org_id'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing id or org_id"]);
    exit;
}

$id = (int)$_GET['id'];
$org_id = (int)$_GET['org_id'];

try {
    $sql = "SELECT l.*, u.email as owner_email, p.name as pipeline_name,
                   asgn.full_name as assigned_to_name, asgn.email as assigned_to_email
            FROM leads l 
            LEFT JOIN users u ON l.owner_id = u.id 
            LEFT JOIN users asgn ON l.assigned_to = asgn.id
            LEFT JOIN pipelines p ON l.pipeline_id = p.id
            WHERE l.id = ? AND l.org_id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id, $org_id]);
    $lead = $stmt->fetch();

    if ($lead) {
        // Decode JSON fields for cleaner API response
        if ($lead['custom_data']) {
            $customData = json_decode($lead['custom_data'], true);
            $lead['custom_data'] = is_array($customData) ? $customData : [];
        } else {
            $lead['custom_data'] = [];
        }
        echo json_encode($lead);
    } else {
        http_response_code(404);
        echo json_encode(["error" => "Lead not found"]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to fetch lead: " . $e->getMessage()]);
}
