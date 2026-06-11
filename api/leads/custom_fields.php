<?php
// api/leads/custom_fields.php
// Discover custom field definitions for an organization by inspecting lead custom_data

header("Content-Type: application/json");
require_once '../../config/db.php';

// Allow CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

// Allow both authenticated and test access
session_start();
$org_id = isset($_GET['org_id']) ? (int)$_GET['org_id'] : 0;

// For system check, allow org_id=1 without auth
if (!$org_id || ($org_id !== 1 && !isset($_SESSION['user_id']))) {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(["error" => "Unauthorized"]);
        exit;
    }
}

// Use org_id from query or default to 1 for system check
if (!$org_id) {
    $org_id = (int)($_SESSION['org_id'] ?? 1);
}

try {
    // Fetch current user
    $stmt = $pdo->prepare("SELECT id, org_id, is_super_admin FROM users WHERE id = :id");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(401);
        echo json_encode(["error" => "User not found"]);
        exit;
    }

    // Permission: super admin can view all, others must belong to the org
    if (!$user['is_super_admin'] && (int)$user['org_id'] !== $org_id) {
        $membership = $pdo->prepare("
            SELECT 1 FROM organization_members 
            WHERE user_id = :user_id AND org_id = :org_id
        ");
        $membership->execute([
            ':user_id' => $user['id'],
            ':org_id' => $org_id
        ]);

        if (!$membership->fetch()) {
            http_response_code(403);
            echo json_encode(["error" => "You do not have access to this organization"]);
            exit;
        }
    }

    // Pull all custom_data blobs for the org
    $stmt = $pdo->prepare("
        SELECT custom_data 
        FROM leads 
        WHERE org_id = :org_id 
          AND custom_data IS NOT NULL 
          AND custom_data != ''
    ");
    $stmt->execute([':org_id' => $org_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $fields = [];

    foreach ($rows as $row) {
        $custom = json_decode($row['custom_data'], true);
        if (!is_array($custom)) {
            continue;
        }

        foreach ($custom as $fieldName => $value) {
            if (!isset($fields[$fieldName])) {
                $fields[$fieldName] = [
                    'name' => $fieldName,
                    'type' => 'text',
                    'options' => []
                ];
            }

            // Capture simple option values to help rebuild dropdowns
            if (is_string($value) && $value !== '') {
                // Detect simple date values
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                    $fields[$fieldName]['type'] = 'date';
                }

                if (count($fields[$fieldName]['options']) < 20 && !in_array($value, $fields[$fieldName]['options'])) {
                    $fields[$fieldName]['options'][] = $value;
                }
            }
        }
    }

    echo json_encode([
        "success" => true,
        "fields" => array_values($fields)
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to load custom fields: " . $e->getMessage()]);
}

