<?php
// Deactivate an organization by ID (soft delete)

require_once '../../config/db.php';
require_once '../../config/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_response(['success' => false, 'error' => 'Invalid request method.'], 405);
}

$data = json_decode(file_get_contents('php://input'), true);
$org_id = isset($data['org_id']) ? (int)$data['org_id'] : 0;

if (!$org_id) {
    send_response(['success' => false, 'error' => 'Organization ID is required.'], 400);
}

/* ---------- HARD SAFETY RULES ---------- */

// Never deactivate default org
if ($org_id === 1) {
    send_response([
        'success' => false,
        'error' => 'Default organization cannot be deactivated.'
    ], 400);
}

/* ---------- CHECK ORG EXISTS ---------- */
$stmt = $pdo->prepare('SELECT id, is_active FROM organizations WHERE id = ?');
$stmt->execute([$org_id]);
$org = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$org) {
    send_response(['success' => false, 'error' => 'Organization not found.'], 404);
}

if ((int)$org['is_active'] === 0) {
    send_response(['success' => false, 'error' => 'Organization already inactive.'], 400);
}

/* ---------- DEACTIVATE (SOFT DELETE) ---------- */
$stmt = $pdo->prepare(
    'UPDATE organizations 
     SET is_active = 0,
         status = "inactive",
         updated_at = NOW()
     WHERE id = ?'
);

if ($stmt->execute([$org_id])) {
    send_response([
        'success' => true,
        'message' => 'Organization deactivated successfully.'
    ]);
}

send_response(['success' => false, 'error' => 'Failed to deactivate organization.'], 500);
