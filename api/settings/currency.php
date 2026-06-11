<?php
/**
 * Get or save the organization currency setting.
 */
header("Content-Type: application/json");
require_once '../../config/db.php';
require_once '../../config/middleware.php';
require_once '../../config/response.php';

Middleware::apply(['session' => true]);
$user = Middleware::requireAuth();

$org_id = $user['org_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->prepare("SELECT settings FROM organizations WHERE id = ?");
        $stmt->execute([$org_id]);
        $org = $stmt->fetch();
        $settings = ($org && $org['settings']) ? json_decode($org['settings'], true) : [];
        $currency = $settings['currency'] ?? 'USD';
        echo json_encode(['success' => true, 'currency' => $currency]);
    } catch (Exception $e) {
        ApiResponse::error('Failed to get currency', 500);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($user['role'] !== 'admin' && $user['role'] !== 'owner') {
        ApiResponse::error('Permission denied', 403);
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $currency = isset($data['currency']) ? strtoupper(trim($data['currency'])) : null;

    $allowed = ['USD', 'EUR', 'GBP', 'INR', 'AUD', 'CAD', 'SGD', 'AED', 'JPY', 'CNY'];
    if (!$currency || !in_array($currency, $allowed)) {
        ApiResponse::error('Invalid currency code', 400);
    }

    try {
        $stmt = $pdo->prepare("SELECT settings FROM organizations WHERE id = ?");
        $stmt->execute([$org_id]);
        $org = $stmt->fetch();
        $settings = ($org && $org['settings']) ? json_decode($org['settings'], true) : [];
        if (!is_array($settings)) $settings = [];

        $settings['currency'] = $currency;

        $stmt = $pdo->prepare("UPDATE organizations SET settings = ? WHERE id = ?");
        $stmt->execute([json_encode($settings), $org_id]);

        ApiResponse::success(['currency' => $currency], 'Currency saved successfully');
    } catch (Exception $e) {
        ApiResponse::error('Failed to save currency', 500);
    }
    exit;
}

ApiResponse::error('Method not allowed', 405);
