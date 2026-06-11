<?php
/**
 * Get field configurations for an organization
 */
header("Content-Type: application/json");
require_once '../../config/db.php';

require_once '../../includes/auth_check.php';

// Use session org_id as primary source
$org_id = $_SESSION['org_id'] ?? ($_GET['org_id'] ?? null);

if (!$org_id) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Missing organization ID"]);
    exit;
}

try {
    // Get organization settings
    $stmt = $pdo->prepare("SELECT settings FROM organizations WHERE id = ?");
    $stmt->execute([$org_id]);
    $org = $stmt->fetch();
    
    $settings = $org && $org['settings'] ? json_decode($org['settings'], true) : [];
    
    // Default field configurations
    $defaultConfig = [
        'source' => [
            'type' => 'select',
            'options' => ['Direct', 'Website', 'LinkedIn', 'Referral', 'Ads', 'Cold Call'],
            'label' => 'Source'
        ],
        'stage_id' => [
            'type' => 'select',
            'options' => ['new', 'contacted', 'qualified', 'proposal', 'won', 'lost'],
            'label' => 'Stage'
        ]
    ];
    
    // Merge with saved settings
    $fieldConfig = isset($settings['field_config']) ? $settings['field_config'] : [];
    $fieldConfig = array_merge($defaultConfig, $fieldConfig);
    
    echo json_encode([
        'success' => true,
        'fields' => $fieldConfig
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

