<?php
// organizations/update.php
// Updates the is_active status of an organization

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/response.php';

header('Content-Type: application/json');


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::error('Invalid request method.', 405);
}

$data = json_decode(file_get_contents('php://input'), true);
$org_id = isset($data['org_id']) ? intval($data['org_id']) : 0;
$is_active = isset($data['is_active']) ? (int)$data['is_active'] : null;


if (!$org_id || !isset($is_active)) {
    ApiResponse::error('Missing org_id or is_active.', 400);
}

try {
    $stmt = $pdo->prepare('UPDATE organizations SET is_active = :is_active WHERE id = :org_id');
    $stmt->execute([
        ':is_active' => $is_active,
        ':org_id' => $org_id
    ]);

    if ($stmt->rowCount() > 0) {
        ApiResponse::success();
    } else {
        ApiResponse::error('No changes made or organization not found.', 404);
    }
} catch (Exception $e) {
    ApiResponse::error($e->getMessage(), 500);
}
