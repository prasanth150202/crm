<?php
// api/external/webhooks.php
header("Content-Type: application/json");
require_once '../../config/db.php';
require_once 'middleware.php';

corsHeaders();

$org_id = validateApiKey($pdo);
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGetWebhooks($pdo, $org_id);
        break;
    case 'POST':
        handleCreateWebhook($pdo, $org_id);
        break;
    case 'DELETE':
        handleDeleteWebhook($pdo, $org_id);
        break;
    default:
        http_response_code(405);
        echo json_encode(["error" => "Method not allowed"]);
}

function handleGetWebhooks($pdo, $org_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM webhooks WHERE org_id = ? ORDER BY created_at DESC");
        $stmt->execute([$org_id]);
        $webhooks = $stmt->fetchAll();
        
        echo json_encode(["success" => true, "data" => $webhooks]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to fetch webhooks: " . $e->getMessage()]);
    }
}

function handleCreateWebhook($pdo, $org_id) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['url']) || !isset($input['events'])) {
        http_response_code(400);
        echo json_encode(["error" => "URL and events are required"]);
        return;
    }
    
    $allowed_events = ['lead.created', 'lead.updated', 'lead.deleted', 'lead.stage_changed'];
    $events = array_intersect($input['events'], $allowed_events);
    
    if (empty($events)) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid events specified"]);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO webhooks (org_id, url, events, secret, active, created_at) VALUES (?, ?, ?, ?, 1, NOW())");
        $secret = bin2hex(random_bytes(16));
        $stmt->execute([$org_id, $input['url'], json_encode($events), $secret]);
        
        echo json_encode([
            "success" => true,
            "webhook_id" => $pdo->lastInsertId(),
            "secret" => $secret,
            "message" => "Webhook created successfully"
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to create webhook: " . $e->getMessage()]);
    }
}

function handleDeleteWebhook($pdo, $org_id) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id'])) {
        http_response_code(400);
        echo json_encode(["error" => "Webhook ID is required"]);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM webhooks WHERE id = ? AND org_id = ?");
        $stmt->execute([$input['id'], $org_id]);
        
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(["error" => "Webhook not found"]);
            return;
        }
        
        echo json_encode(["success" => true, "message" => "Webhook deleted successfully"]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to delete webhook: " . $e->getMessage()]);
    }
}