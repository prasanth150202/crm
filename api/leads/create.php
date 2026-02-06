<?php
// api/leads/create.php
header("Content-Type: application/json");
require_once '../../config/db.php';

// Allow CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

// Basic Validation
if (!isset($data['org_id']) || !isset($data['name'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required fields: org_id, name"]);
    exit;
}

// In a real app, validate that the user has permission to create leads for this org_id
// For now, we trust the input (MVP)

$org_id = (int)$data['org_id'];
$name = htmlspecialchars($data['name']);
$title = isset($data['title']) ? htmlspecialchars($data['title']) : null;
$email = isset($data['email']) ? filter_var($data['email'], FILTER_SANITIZE_EMAIL) : null;
$phone = isset($data['phone']) ? htmlspecialchars($data['phone']) : null;
$company = isset($data['company']) ? htmlspecialchars($data['company']) : null;
$lead_value = isset($data['lead_value']) ? (float)$data['lead_value'] : 0.00;
$source = isset($data['source']) ? htmlspecialchars($data['source']) : 'Direct';
$owner_id = isset($data['owner_id']) ? (int)$data['owner_id'] : null;
$pipeline_id = isset($data['pipeline_id']) ? (int)$data['pipeline_id'] : null;
$stage_id = isset($data['stage_id']) ? htmlspecialchars($data['stage_id']) : 'new';

// Custom Data (JSON)
$custom_data = isset($data['custom_data']) ? json_encode($data['custom_data']) : null;

try {
    // Enforce unique email per org when email is provided
    if ($email) {
        $check = $pdo->prepare("SELECT id FROM leads WHERE org_id = ? AND email = ?");
        $check->execute([$org_id, $email]);
        if ($check->fetch()) {
            http_response_code(409);
            echo json_encode(["error" => "A lead with this email already exists in this organization"]);
            exit;
        }
    }

    $sql = "INSERT INTO leads (org_id, name, title, email, phone, company, lead_value, source, owner_id, pipeline_id, stage_id, custom_data, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$org_id, $name, $title, $email, $phone, $company, $lead_value, $source, $owner_id, $pipeline_id, $stage_id, $custom_data]);
    
    $lead_id = $pdo->lastInsertId();

    // Log Activity
    $activity_sql = "INSERT INTO activities (org_id, lead_id, type, content, created_at) VALUES (?, ?, 'status_change', 'Lead created', NOW())";
    $act_stmt = $pdo->prepare($activity_sql);
    $act_stmt->execute([$org_id, $lead_id]);

    // --- AUTOMATION TRIGGER: lead_created ---
    try {
        require_once __DIR__ . '/../../includes/AutomationEngine.php';
        
        // Prepare lead data context
        $leadData = [
            'id' => $lead_id,
            'org_id' => $org_id,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'company' => $company,
            'status' => 'New', // Default status/stage name logic might be needed
            'stage_id' => $stage_id,
            'source' => $source,
            'owner_id' => $owner_id,
            'lead_value' => $lead_value,
            'custom_data' => $custom_data ?? []
        ];

        $engine = new AutomationEngine($pdo, $org_id);
        $engine->trigger('lead_created', [
            'lead_id' => $lead_id,
            'lead' => $leadData
        ]);
    } catch (Exception $e) {
        // Don't fail the lead creation if automation fails, just log it
        error_log("Automation Error (lead_created): " . $e->getMessage());
    }
    // ----------------------------------------

    echo json_encode([
        "message" => "Lead created successfully",
        "lead_id" => $lead_id
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to create lead: " . $e->getMessage()]);
}
