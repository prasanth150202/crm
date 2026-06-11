<?php
// api/dashboard/apply_layout_all.php — admin only: copy current user's layout to every user in the org
require_once '../../includes/auth_check.php';
require_once '../../config/db.php';
require_once '../../config/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(405, ['message' => 'Method Not Allowed']);
    exit;
}

// Only admins
$role          = $_SESSION['role'] ?? '';
$is_super_admin = !empty($_SESSION['is_super_admin']);
if ($role !== 'admin' && !$is_super_admin) {
    json_response(403, ['message' => 'Forbidden']);
    exit;
}

try {
    $org_id   = $_SESSION['org_id'];
    $user_id  = $_SESSION['user_id'];

    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        json_response(400, ['message' => 'Invalid JSON']);
        exit;
    }

    // Fetch all user IDs in this org
    $stmt = $pdo->prepare("SELECT id FROM users WHERE org_id = ?");
    $stmt->execute([$org_id]);
    $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $upsert = $pdo->prepare("
        INSERT INTO dashboard_layouts (user_id, org_id, layout)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE layout = VALUES(layout)
    ");

    foreach ($userIds as $uid) {
        $upsert->execute([$uid, $org_id, $json_data]);
    }

    json_response(200, ['message' => 'Layout applied to ' . count($userIds) . ' user(s).']);

} catch (Exception $e) {
    json_response(500, ['error' => $e->getMessage()]);
}
