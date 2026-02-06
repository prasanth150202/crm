<?php
// api/leads/update.php
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

if (!isset($data['id']) || !isset($data['org_id'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required fields: id, org_id"]);
    exit;
}

$id = (int)$data['id'];
$org_id = (int)$data['org_id'];

// Allowed fields to update
$allowed_fields = ['name', 'title', 'email', 'phone', 'company', 'lead_value', 'source', 'owner_id', 'pipeline_id', 'stage_id', 'custom_data', 'assigned_to'];
$updates = [];
$params = [];

foreach ($allowed_fields as $field) {
    if (isset($data[$field])) {
        if ($field === 'custom_data') {
            $updates[] = "$field = ?";
            $params[] = json_encode($data[$field]);
        } else {
            $updates[] = "$field = ?";
            $params[] = $data[$field];
        }
    }
}

if (empty($updates)) {
    echo json_encode(["message" => "No changes provided"]);
    exit;
}

// Add updated_at
$updates[] = "updated_at = NOW()";

// Add WHERE clause params
$params[] = $id;
$params[] = $org_id;

try {
    // Enforce unique email per org when email changes
    if (isset($data['email']) && $data['email']) {
        $check = $pdo->prepare("SELECT id FROM leads WHERE org_id = ? AND email = ? AND id != ?");
        $check->execute([$org_id, $data['email'], $id]);
        if ($check->fetch()) {
            http_response_code(409);
            echo json_encode(["error" => "A lead with this email already exists in this organization"]);
            exit;
        }
    }

    // 1. Fetch OLD lead data before update
    $stmtObj = $pdo->prepare("SELECT * FROM leads WHERE id = ? AND org_id = ?");
    $stmtObj->execute([$id, $org_id]);
    $oldLead = $stmtObj->fetch(PDO::FETCH_ASSOC);

    if (!$oldLead) {
        echo json_encode(["message" => "Lead not found"]);
        exit;
    }

    $sql = "UPDATE leads SET " . implode(", ", $updates) . " WHERE id = ? AND org_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    if ($stmt->rowCount() > 0) {
        // Log Activity (Simplified)
        $activity_sql = "INSERT INTO activities (org_id, lead_id, type, content, created_at) VALUES (?, ?, 'status_change', 'Lead updated', NOW())";
        $act_stmt = $pdo->prepare($activity_sql);
        $act_stmt->execute([$org_id, $id]);

        // --- AUTOMATION TRIGGERS ---
        try {
            require_once __DIR__ . '/../../includes/AutomationEngine.php';
            
            // 2. Fetch NEW lead data (or construct it)
            // Fetching is safer to get DB-generated values if any, but mostly for cleanness
            $stmtObj->execute([$id, $org_id]);
            $newLead = $stmtObj->fetch(PDO::FETCH_ASSOC);
            
            $engine = new AutomationEngine($pdo, $org_id);
            $context = ['lead_id' => $id, 'lead' => $newLead, 'old_lead' => $oldLead];

            // Trigger: Lead Stage Changed
            if ($oldLead['stage_id'] !== $newLead['stage_id']) {
                $engine->trigger('lead_stage_changed', $context);
            }

            // Trigger: Lead Assigned
            // assigned_to can be null, strict comparison is good
            if ($oldLead['assigned_to'] !== $newLead['assigned_to']) {
                $engine->trigger('lead_assigned', $context);
            }

            // Trigger: Field Changed (Generic)
            // We check specific fields that might be watched
            // For efficiency, we could pass the changed fields list to the engine
            // But our engine currently evaluates *all* 'field_changed' workflows and checks conditions
            // So we just fire the event if anything relevant changed
            $changedFields = [];
            foreach ($allowed_fields as $field) {
                if (array_key_exists($field, $data)) {
                     // Handle custom data comparison separately or just trigger
                     if ($field === 'custom_data') {
                         if ($oldLead['custom_data'] !== json_encode($data[$field])) {
                             $changedFields[] = $field;
                             // We might want to pass WHICH custom field changed, but for now generic trigger
                         }
                     } elseif ($oldLead[$field] != $data[$field]) { // Loose comparison for numbers/strings
                         $changedFields[] = $field;
                     }
                }
            }
            
            if (!empty($changedFields)) {
                $engine->trigger('field_changed', $context + ['changed_fields' => $changedFields]);
            }

        } catch (Exception $e) {
            error_log("Automation Error (update): " . $e->getMessage());
        }
        // ---------------------------

        echo json_encode(["message" => "Lead updated successfully"]);
    } else {
        // Could be no changes or lead not found
        echo json_encode(["message" => "No changes made or lead not found"]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to update lead: " . $e->getMessage()]);
}
