<?php
require_once '../../../../includes/api_common.php';
require_once '../../admin_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::error('Method not allowed', 405);
}

$data    = json_decode(file_get_contents('php://input'), true) ?? [];
$plan_id = (int)($data['plan_id'] ?? 0);

if (!$plan_id) ApiResponse::error('Plan ID is required', 400);

// Block deletion if orgs are currently assigned to this plan
$stmt = $pdo->prepare("SELECT COUNT(*) FROM organizations WHERE current_plan_id = ?");
$stmt->execute([$plan_id]);
if ((int)$stmt->fetchColumn() > 0) {
    ApiResponse::error('Cannot deactivate plan while organizations are assigned to it', 409);
}

$pdo->prepare("UPDATE plans SET is_active = 0 WHERE id = ?")->execute([$plan_id]);
ApiResponse::success(['message' => 'Plan deactivated']);
