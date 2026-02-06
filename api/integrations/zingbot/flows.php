<?php
/**
 * Proxy to fetch available flows from Zingbot API
 */

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/auth_check.php';

header('Content-Type: application/json');

try {
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT org_id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    $org_id = $user['org_id'];

    // Fetch Zingbot Settings
    $stmt = $pdo->prepare("SELECT api_key, api_endpoint FROM zingbot_settings WHERE org_id = ? AND is_active = 1");
    $stmt->execute([$org_id]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$settings || empty($settings['api_key'])) {
        echo json_encode(['success' => false, 'error' => 'Zingbot is not configured or active']);
        exit;
    }

    $api_key = $settings['api_key'];
    $cleanEndpoint = rtrim($settings['api_endpoint'], '/');
    
    // Ensure endpoint starts with https
    if (strpos($cleanEndpoint, 'http') === false) {
        $cleanEndpoint = 'https://' . $cleanEndpoint;
    }

    // Ensure the URL correctly points to the flow endpoint
    $url = rtrim($cleanEndpoint, '/') . '/accounts/flows';

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-ACCESS-TOKEN: ' . $api_key,
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        $flows = json_decode($response, true);
        echo json_encode(['success' => true, 'flows' => $flows]);
    } else {
        error_log("Zingbot API Error ($http_code): " . $response);
        echo json_encode([
            'success' => false, 
            'error' => 'Failed to fetch flows from Zingbot',
            'status' => $http_code
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server Error: ' . $e->getMessage()]);
}
