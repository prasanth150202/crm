<?php
// api/leads/get_import_fields.php - Get available fields for import mapping
header("Content-Type: application/json");
require_once '../../config/db.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");

$org_id = isset($_GET['org_id']) ? (int)$_GET['org_id'] : 0;

if (!$org_id) {
    http_response_code(400);
    echo json_encode(["error" => "Missing org_id"]);
    exit;
}

try {
    // Standard CRM fields
    $standardFields = [
        ['id' => 'name', 'label' => 'Name', 'required' => true],
        ['id' => 'email', 'label' => 'Email', 'required' => false],
        ['id' => 'phone', 'label' => 'Phone', 'required' => false],
        ['id' => 'company', 'label' => 'Company', 'required' => false],
        ['id' => 'title', 'label' => 'Title', 'required' => false],
        ['id' => 'lead_value', 'label' => 'Lead Value', 'required' => false],
        ['id' => 'source', 'label' => 'Source', 'required' => false],
        ['id' => 'stage_id', 'label' => 'Status/Stage', 'required' => false]
    ];

    // Get custom fields from custom_fields table
    $customFields = [];
    try {
        $stmt = $pdo->prepare("SELECT field_name, field_type FROM custom_fields WHERE org_id = ? AND is_active = 1 ORDER BY field_name");
        $stmt->execute([$org_id]);
        
        while ($row = $stmt->fetch()) {
            $customFields[] = [
                'id' => 'custom_' . $row['field_name'],
                'label' => $row['field_name'],
                'required' => false,
                'type' => 'custom'
            ];
        }
        
        // Add create new option
        $customFields[] = [
            'id' => 'create_new',
            'label' => '+ Create New Custom Field',
            'required' => false,
            'type' => 'action'
        ];
    } catch (Exception $e) {
        $customFields = [[
            'id' => 'create_new',
            'label' => '+ Create New Custom Field',
            'required' => false,
            'type' => 'action'
        ]];
    }

    echo json_encode([
        "success" => true,
        "standardFields" => $standardFields,
        "customFields" => $customFields,
        "allFields" => array_merge($standardFields, $customFields)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to get fields: " . $e->getMessage()]);
}
?>