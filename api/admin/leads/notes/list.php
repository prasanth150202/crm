<?php
require_once '../../../../includes/api_common.php';
require_once '../../admin_check.php';

$lead_id = (int)($_GET['lead_id'] ?? 0);
if (!$lead_id) ApiResponse::error('lead_id is required', 400);

$stmt = $pdo->prepare(
    "SELECT ln.*, u.full_name AS author_name
     FROM lead_notes ln
     LEFT JOIN users u ON u.id = ln.author_id
     WHERE ln.lead_id = ?
     ORDER BY ln.created_at DESC"
);
$stmt->execute([$lead_id]);
$notes = $stmt->fetchAll();

ApiResponse::success(['notes' => $notes]);
