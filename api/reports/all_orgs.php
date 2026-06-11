<?php
// api/reports/all_orgs.php
// Get analytics from all organizations (super admin only)

header("Content-Type: application/json");
require_once '../../config/db.php';

// Check authentication
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

try {
    // Get current user
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        http_response_code(401);
        echo json_encode(["error" => "User not found"]);
        exit;
    }
    
    // Check if super admin
    if (!$user['is_super_admin']) {
        http_response_code(403);
        echo json_encode(["error" => "Permission denied. Super admin access required."]);
        exit;
    }
    
    // Get summary stats across all organizations
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_leads,
            SUM(CASE WHEN stage = 'new' THEN 1 ELSE 0 END) as new_leads,
            SUM(CASE WHEN stage = 'contacted' THEN 1 ELSE 0 END) as contacted_leads,
            SUM(CASE WHEN stage = 'qualified' THEN 1 ELSE 0 END) as qualified_leads,
            SUM(CASE WHEN stage = 'won' THEN 1 ELSE 0 END) as won_leads,
            SUM(CASE WHEN stage = 'lost' THEN 1 ELSE 0 END) as lost_leads,
            SUM(value) as total_pipeline_value
        FROM leads
    ");
    $summary = $stmt->fetch();
    
    // Get leads by organization
    $stmt = $pdo->query("
        SELECT 
            o.name as org_name,
            COUNT(l.id) as lead_count,
            SUM(l.value) as pipeline_value
        FROM organizations o
        LEFT JOIN leads l ON o.id = l.org_id
        GROUP BY o.id
        ORDER BY lead_count DESC
    ");
    $by_org = $stmt->fetchAll();
    
    // Get leads by source (all orgs)
    $stmt = $pdo->query("
        SELECT 
            source,
            COUNT(*) as count
        FROM leads
        GROUP BY source
        ORDER BY count DESC
    ");
    $by_source = $stmt->fetchAll();
    
    // Get leads by stage (all orgs)
    $stmt = $pdo->query("
        SELECT 
            stage,
            COUNT(*) as count
        FROM leads
        GROUP BY stage
        ORDER BY FIELD(stage, 'new', 'contacted', 'qualified', 'won', 'lost')
    ");
    $by_stage = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'summary' => $summary,
        'by_organization' => $by_org,
        'by_source' => $by_source,
        'by_stage' => $by_stage
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch analytics: ' . $e->getMessage()
    ]);
}
