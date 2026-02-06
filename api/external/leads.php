<?php
// api/external/leads.php
header("Content-Type: application/json");
require_once '../../config/db.php';
require_once 'middleware.php';

corsHeaders();

$org_id = validateApiKey($pdo);
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGetLeads($pdo, $org_id);
        break;
    case 'POST':
        handleCreateLead($pdo, $org_id);
        break;
    case 'PUT':
        handleUpdateLead($pdo, $org_id);
        break;
    case 'DELETE':
        handleDeleteLead($pdo, $org_id);
        break;
    default:
        http_response_code(405);
        echo json_encode(["error" => "Method not allowed"]);
}

function handleGetLeads($pdo, $org_id) {
    $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 100) : 50;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $stage_id = isset($_GET['stage_id']) ? $_GET['stage_id'] : '';
    
    try {
        $sql = "SELECT l.*, u.email as owner_email,
                       CASE 
                           WHEN l.stage_id = 1 THEN 'NEW'
                           WHEN l.stage_id = 2 THEN 'CONTACTED'
                           WHEN l.stage_id = 3 THEN 'QUALIFIED'
                           WHEN l.stage_id = 4 THEN 'PROPOSAL'
                           WHEN l.stage_id = 5 THEN 'CLOSED WON'
                           ELSE CONCAT('Stage ', l.stage_id)
                       END as stage_name
                FROM leads l 
                LEFT JOIN users u ON l.owner_id = u.id 
                WHERE l.org_id = :org_id";
        $params = [':org_id' => $org_id];
        
        if ($search) {
            $sql .= " AND (l.name LIKE :search OR l.company LIKE :search OR l.email LIKE :search)";
            $params[':search'] = "%$search%";
        }
        
        if ($stage_id) {
            $sql .= " AND l.stage_id = :stage_id";
            $params[':stage_id'] = $stage_id;
        }
        
        $sql .= " ORDER BY l.created_at DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $leads = $stmt->fetchAll();
        
        // Decode custom_data for each lead
        foreach ($leads as &$lead) {
            if ($lead['custom_data']) {
                $lead['custom_data'] = json_decode($lead['custom_data'], true);
            }
        }
        
        // Get total count
        $count_sql = str_replace("SELECT l.*, u.email as owner_email", "SELECT COUNT(*)", $sql);
        $count_sql = preg_replace('/ORDER BY.*/', '', $count_sql);
        $count_sql = preg_replace('/LIMIT.*/', '', $count_sql);
        
        $count_stmt = $pdo->prepare($count_sql);
        foreach ($params as $key => $value) {
            $count_stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $count_stmt->execute();
        $total = $count_stmt->fetchColumn();
        
        echo json_encode([
            "success" => true,
            "data" => $leads,
            "meta" => ["total" => $total, "limit" => $limit, "offset" => $offset]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to fetch leads: " . $e->getMessage()]);
    }
}

function handleCreateLead($pdo, $org_id) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['name']) || !isset($input['email'])) {
        http_response_code(400);
        echo json_encode(["error" => "Name and email are required"]);
        return;
    }
    
    try {
        // Check for duplicate email in same organization
        $checkStmt = $pdo->prepare("SELECT id FROM leads WHERE email = ? AND org_id = ?");
        $checkStmt->execute([$input['email'], $org_id]);
        $existing = $checkStmt->fetch();
        
        if ($existing) {
            http_response_code(409);
            echo json_encode(["error" => "Lead with this email already exists", "existing_lead_id" => $existing['id']]);
            return;
        }
        
        // Prepare custom_data as JSON
        $custom_data = null;
        if (isset($input['custom_fields']) && is_array($input['custom_fields'])) {
            // Get defined custom fields for this org to validate/normalize names
            $fieldsStmt = $pdo->prepare("SELECT field_name FROM custom_fields WHERE org_id = ?");
            $fieldsStmt->execute([$org_id]);
            $definedFields = array_column($fieldsStmt->fetchAll(), 'field_name');
            
            // Build normalized custom_data
            $normalizedData = [];
            foreach ($input['custom_fields'] as $key => $value) {
                // Try to find matching field (case-insensitive)
                $matchedField = null;
                foreach ($definedFields as $defField) {
                    if (strtolower($defField) === strtolower($key)) {
                        $matchedField = $defField;
                        break;
                    }
                }
                // Use matched field name (preserves casing) or original if not found
                $normalizedData[$matchedField ?? $key] = $value;
            }
            $custom_data = json_encode($normalizedData);
        }
        
        $stmt = $pdo->prepare("INSERT INTO leads (org_id, name, title, email, company, phone, source, stage_id, lead_value, custom_data, created_at) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
            $org_id,
            $input['name'],
            $input['title'] ?? null,
            $input['email'],
            $input['company'] ?? null,
            $input['phone'] ?? null,
            $input['source'] ?? 'API',
            $input['stage_id'] ?? 1,
            $input['lead_value'] ?? 0,
            $custom_data
        ]);
        
        $lead_id = $pdo->lastInsertId();
        
        echo json_encode([
            "success" => true,
            "lead_id" => $lead_id,
            "message" => "Lead created successfully"
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to create lead: " . $e->getMessage()]);
    }
}

function handleUpdateLead($pdo, $org_id) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id'])) {
        http_response_code(400);
        echo json_encode(["error" => "Lead ID is required"]);
        return;
    }
    
    try {
        $fields = [];
        $params = [];
        
        foreach (['name', 'title', 'email', 'company', 'phone', 'source', 'stage_id', 'lead_value'] as $field) {
            if (isset($input[$field])) {
                $fields[] = "$field = ?";
                $params[] = $input[$field];
            }
        }
        
        if (!empty($fields)) {
            $params[] = $input['id'];
            $params[] = $org_id;
            
            $sql = "UPDATE leads SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ? AND org_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(["error" => "Lead not found"]);
                return;
            }
        }
        
        // Handle custom_fields update (merge with existing)
        if (isset($input['custom_fields']) && is_array($input['custom_fields'])) {
            // Get defined custom fields for this org to validate/normalize names
            $fieldsStmt = $pdo->prepare("SELECT field_name FROM custom_fields WHERE org_id = ?");
            $fieldsStmt->execute([$org_id]);
            $definedFields = array_column($fieldsStmt->fetchAll(), 'field_name');
            
            // Get existing custom data
            $getStmt = $pdo->prepare("SELECT custom_data FROM leads WHERE id = ? AND org_id = ?");
            $getStmt->execute([$input['id'], $org_id]);
            $lead = $getStmt->fetch();
            
            $customData = [];
            if ($lead && $lead['custom_data']) {
                $customData = json_decode($lead['custom_data'], true) ?: [];
            }
            
            // Merge new custom fields with normalization
            foreach ($input['custom_fields'] as $key => $value) {
                // Try to find matching field (case-insensitive)
                $matchedField = null;
                foreach ($definedFields as $defField) {
                    if (strtolower($defField) === strtolower($key)) {
                        $matchedField = $defField;
                        break;
                    }
                }
                $customData[$matchedField ?? $key] = $value;
            }
            
            $updateStmt = $pdo->prepare("UPDATE leads SET custom_data = ? WHERE id = ? AND org_id = ?");
            $updateStmt->execute([json_encode($customData), $input['id'], $org_id]);
        }
        
        echo json_encode(["success" => true, "message" => "Lead updated successfully"]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to update lead: " . $e->getMessage()]);
    }
}

function handleDeleteLead($pdo, $org_id) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id'])) {
        http_response_code(400);
        echo json_encode(["error" => "Lead ID is required"]);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM leads WHERE id = ? AND org_id = ?");
        $stmt->execute([$input['id'], $org_id]);
        
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(["error" => "Lead not found"]);
            return;
        }
        
        echo json_encode(["success" => true, "message" => "Lead deleted successfully"]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to delete lead: " . $e->getMessage()]);
    }
}