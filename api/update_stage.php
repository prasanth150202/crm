<?php
// api/update_stage.php
header("Content-Type: application/json");
require_once '../config/db.php';

// Allow CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['id']) || !isset($input['status'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing lead id or status"]);
    exit;
}

$lead_id = (int)$input['id'];
$status = $input['status'];

// Allowed statuses
$allowed_statuses = ['new', 'contacted', 'qualified', 'won', 'lost'];
if (!in_array($status, $allowed_statuses)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid status"]);
    exit;
}

try {
    $pdo->beginTransaction();

    // Get current status for logging (and verify lead exists)
    $stmt = $pdo->prepare("SELECT org_id, stage_id FROM leads WHERE id = :id");
    $stmt->bindValue(':id', $lead_id, PDO::PARAM_INT);
    $stmt->execute();
    $lead = $stmt->fetch();

    if (!$lead) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(["error" => "Lead not found"]);
        exit;
    }

    $old_status = $lead['stage_id'];
    
    // Only update if status changed
    if ($old_status !== $status) {
        $updateStmt = $pdo->prepare("UPDATE leads SET stage_id = :status, updated_at = NOW() WHERE id = :id");
        $updateStmt->bindValue(':status', $status);
        $updateStmt->bindValue(':id', $lead_id, PDO::PARAM_INT);
        $updateStmt->execute();

        // Log activity
        $logStmt = $pdo->prepare("INSERT INTO activities (org_id, lead_id, type, content, metadata) VALUES (:org_id, :lead_id, :type, :content, :metadata)");
        $logStmt->bindValue(':org_id', $lead['org_id']);
        $logStmt->bindValue(':lead_id', $lead_id);
        $logStmt->bindValue(':type', 'status_change');
        $logStmt->bindValue(':content', "Stage changed from $old_status to $status");
        $logStmt->bindValue(':metadata', json_encode(['from' => $old_status, 'to' => $status]));
        $logStmt->execute();
    }

    $pdo->commit();

    // --- AUTOMATION TRIGGERS ---
    if ($old_status !== $status) {
        try {
            require_once __DIR__ . '/../includes/AutomationEngine.php';
            
            // Fetch fresh lead data for context
            $freshStmt = $pdo->prepare("SELECT * FROM leads WHERE id = ?");
            $freshStmt->execute([$lead_id]);
            $newLead = $freshStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($newLead) {
                $engine = new AutomationEngine($pdo, $lead['org_id']);
                $context = [
                    'lead_id' => $lead_id, 
                    'lead' => $newLead, 
                    'old_lead' => array_merge($newLead, ['stage_id' => $old_status]),
                    'changed_fields' => ['stage_id']
                ];
                
                $engine->trigger('lead_stage_changed', $context);
                $engine->trigger('field_changed', $context);
            }
        } catch (Exception $e) {
            error_log("Automation Error (update_stage): " . $e->getMessage());
        }
    }
    // ---------------------------

    echo json_encode(["success" => true, "message" => "Lead status updated"]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
