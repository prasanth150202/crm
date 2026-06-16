<?php
require_once '../../../includes/api_common.php';
require_once '../admin_check.php';

$lead_id = (int)($_GET['id'] ?? 0);
if (!$lead_id) ApiResponse::error('Lead ID is required', 400);

$stmt = $pdo->prepare(
    "SELECT l.*, o.name AS org_name, u.full_name AS assigned_to_name
     FROM leads l
     JOIN organizations o ON o.id = l.org_id
     LEFT JOIN users u ON u.id = l.assigned_to
     WHERE l.id = ?"
);
$stmt->execute([$lead_id]);
$lead = $stmt->fetch();
if (!$lead) ApiResponse::error('Lead not found', 404);

// Notes
$stmt = $pdo->prepare(
    "SELECT ln.*, u.full_name AS author_name
     FROM lead_notes ln
     LEFT JOIN users u ON u.id = ln.author_id
     WHERE ln.lead_id = ?
     ORDER BY ln.created_at DESC"
);
$stmt->execute([$lead_id]);
$lead['notes'] = $stmt->fetchAll();

// Recent activities
$stmt = $pdo->prepare(
    "SELECT a.type, a.content, a.created_at, u.full_name AS user_name
     FROM activities a
     LEFT JOIN users u ON u.id = a.user_id
     WHERE a.lead_id = ?
     ORDER BY a.created_at DESC LIMIT 20"
);
$stmt->execute([$lead_id]);
$lead['activities'] = $stmt->fetchAll();

ApiResponse::success(['lead' => $lead]);
