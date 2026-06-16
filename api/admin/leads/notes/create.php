<?php
require_once '../../../../includes/api_common.php';
require_once '../../admin_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::error('Method not allowed', 405);
}

$data    = json_decode(file_get_contents('php://input'), true) ?? [];
$lead_id = (int)($data['lead_id'] ?? 0);
$note    = trim($data['note'] ?? '');

if (!$lead_id) ApiResponse::error('lead_id is required', 400);
if ($note === '') ApiResponse::error('Note cannot be empty', 400);

// Get lead org_id
$stmt = $pdo->prepare("SELECT org_id FROM leads WHERE id = ?");
$stmt->execute([$lead_id]);
$lead = $stmt->fetch();
if (!$lead) ApiResponse::error('Lead not found', 404);

$pdo->prepare(
    "INSERT INTO lead_notes (lead_id, org_id, author_id, note) VALUES (?, ?, ?, ?)"
)->execute([$lead_id, $lead['org_id'], $currentUser['id'], $note]);

ApiResponse::success(['note_id' => (int)$pdo->lastInsertId(), 'message' => 'Note added']);
