<?php
// api/leads/field_visibility.php
// Manage field visibility (hide/show fields)

session_start();
header("Content-Type: application/json");
require_once '../../config/db.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$user_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("SELECT id, org_id, is_super_admin FROM users WHERE id = :id");
    $stmt->execute([':id' => $user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(401);
        echo json_encode(["error" => "User not found"]);
        exit;
    }

    $org_id = (int)($_SESSION['org_id'] ?? $user['org_id']);

    if ($method === 'GET') {
        // Check if table exists
        try {
            $stmt = $pdo->prepare("
                SELECT field_name, field_type, is_visible 
                FROM field_visibility 
                WHERE org_id = :org_id
            ");
            $stmt->execute([':org_id' => $org_id]);
            $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                "success" => true,
                "settings" => $settings
            ]);
        } catch (PDOException $e) {
            // Table doesn't exist yet - return empty settings
            echo json_encode([
                "success" => true,
                "settings" => []
            ]);
        }
    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $field_name = $input['field_name'] ?? '';
        $field_type = $input['field_type'] ?? 'custom';
        $is_visible = isset($input['is_visible']) ? (bool)$input['is_visible'] : true;

        if (empty($field_name)) {
            http_response_code(400);
            echo json_encode(["error" => "Field name is required"]);
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO field_visibility (org_id, field_name, field_type, is_visible)
            VALUES (:org_id, :field_name, :field_type, :is_visible)
            ON DUPLICATE KEY UPDATE is_visible = VALUES(is_visible), field_type = VALUES(field_type)
        ");
        
        $stmt->execute([
            ':org_id' => $org_id,
            ':field_name' => $field_name,
            ':field_type' => $field_type,
            ':is_visible' => $is_visible ? 1 : 0
        ]);

        echo json_encode([
            "success" => true,
            "message" => "Field visibility updated"
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log("Field visibility error: " . $e->getMessage());
    echo json_encode(["error" => $e->getMessage()]);
}
