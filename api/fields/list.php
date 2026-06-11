<?php
// api/fields/list.php
// List custom fields for the organization - Standardized endpoint

header("Content-Type: application/json");
require_once '../../config/db.php';
require_once '../../includes/auth_check.php';

$user_id = $_SESSION['user_id'];

// Fetch user to ensure we have correct org_id
$stmt = $pdo->prepare("SELECT id, org_id, role, is_super_admin FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit;
}

$org_id = $user['org_id'];

try {
    // 1. Fetch defined custom fields
    $stmt = $pdo->prepare("
        SELECT id, field_name as name, field_type as type, field_options as options
        FROM custom_fields 
        WHERE org_id = :org_id AND is_active = 1
        ORDER BY created_at ASC
    ");
    $stmt->execute([':org_id' => $org_id]);
    $definedFields = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Index defined fields by name for easy lookup
    $fieldMap = [];
    foreach ($definedFields as $df) {
        $fieldMap[$df['name']] = $df;
    }
    
    error_log("Field Discovery [Org: $org_id]: Defined fields count: " . count($definedFields));

    // 2. Discover fields from existing lead data (Legacy/Ad-hoc support)
    // This handles fields that are used in JSON but not explicitly defined in custom_fields table
    $stmt = $pdo->prepare("
        SELECT custom_data 
        FROM leads 
        WHERE org_id = :org_id 
          AND custom_data IS NOT NULL 
          AND custom_data != ''
          AND custom_data != '[]' 
          AND custom_data != '{}'
        LIMIT 1000
    ");
    // Limit scan to recent 1000 leads for performance
    $stmt->execute([':org_id' => $org_id]);
    
    $discoveredCount = 0;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $custom = json_decode($row['custom_data'], true);
        if (is_array($custom)) {
            foreach ($custom as $key => $val) {
                if (!isset($fieldMap[$key])) {
                    // Add discovered field
                    $fieldMap[$key] = [
                        'id' => null, // No definition ID
                        'name' => $key,
                        'type' => 'text', // Default to text
                        'options' => null
                    ];
                    $discoveredCount++;
                }
            }
        }
    }
    error_log("Field Discovery [Org: $org_id]: Discovered fields count: $discoveredCount");
    
    $fields = array_values($fieldMap);

    
    echo json_encode([
        'success' => true,
        'fields' => $fields,
        'debug_info' => [
            'user_id' => $_SESSION['user_id'] ?? 'unset',
            'session_org_id' => $_SESSION['org_id'] ?? 'unset',
            'user_array_org_id' => $user['org_id'] ?? 'unset',
            'defined_count' => count($definedFields)
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch fields: ' . $e->getMessage()
    ]);
}
