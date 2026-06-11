<?php
/**
 * List Plans API
 * GET /api/plans/list.php
 *
 * Returns a list of active subscription plans.
 * Does not require authentication for public display.
 */

// We don't require api_common.php here if this is a public endpoint
// as it should be accessible to unauthenticated users on a pricing page.
require_once '../../config/db.php'; // For $pdo
// require_once '../../config/response.php'; // Assuming ApiResponse is defined here or globally

// Define a basic ApiResponse class if not already included via config/response.php
if (!class_exists('ApiResponse')) {
    class ApiResponse {
        public static function success($data = [], $status = 200) {
            http_response_code($status);
            echo json_encode(['success' => true, 'data' => $data]);
            exit();
        }
        public static function error($message, $status = 400) {
            http_response_code($status);
            echo json_encode(['success' => false, 'error' => $message]);
            exit();
        }
    }
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ApiResponse::error('Method not allowed', 405);
}

try {
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        // Attempt to establish PDO connection if not already available from db.php
        require_once '../../config/db.php'; 
        if (!isset($pdo) || !($pdo instanceof PDO)) {
            ApiResponse::error("Error: PDO connection could not be established.", 500);
        }
    }

    $stmt = $pdo->query("SELECT id, name, description, features, base_price_monthly, included_users, price_per_additional_user_monthly, base_price_yearly, price_per_additional_user_yearly, currency FROM `plans` WHERE is_active = TRUE ORDER BY id ASC");
    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Decode JSON features for frontend consumption
    foreach ($plans as &$plan) {
        if (isset($plan['features'])) {
            $plan['features'] = json_decode($plan['features'], true);
        }
    }

    ApiResponse::success($plans);

} catch (Exception $e) {
    ApiResponse::error('Failed to retrieve plans: ' . $e->getMessage(), 500);
}
