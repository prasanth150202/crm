<?php
/**
 * Zingbot Integration Settings
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/permissions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

header('Content-Type: application/json');

try {
    // Robust Auth: Fetch user details from DB based on session ID
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT id, org_id, role, is_super_admin FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'User not found or session expired']);
        exit;
    }

    $org_id = $user['org_id'];

    // Permission Check
    $pm = getPermissionManager($pdo, $user);
    $isAdmin = in_array($user['role'], ['admin', 'owner']) || $user['is_super_admin'];
    
    // Check if user should be able to manage integration
    if (!$isAdmin && !$pm->hasPermission('manage_integrations')) {
        http_response_code(403);
        echo json_encode(['error' => 'Permission denied. Only admins can manage integrations.']);
        exit;
    }

    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        // Get settings
        $stmt = $pdo->prepare("SELECT api_key, api_endpoint, is_active FROM zingbot_settings WHERE org_id = ?");
        $stmt->execute([$org_id]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($settings) {
            // Mask API Key for security
            if (!empty($settings['api_key'])) {
                $settings['api_key'] = substr($settings['api_key'], 0, 4) . '****************';
            }
            echo json_encode(['success' => true, 'settings' => $settings]);
        } else {
            echo json_encode(['success' => true, 'settings' => null]);
        }

    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Check if distinct action (like 'test')
        if (isset($_GET['action']) && $_GET['action'] === 'test') {
            $apiKey = $data['api_key'] ?? null;
            $endpoint = $data['api_endpoint'] ?? null;

            if (!$apiKey || strpos($apiKey, '*') !== false) {
                 $stmt = $pdo->prepare("SELECT api_key FROM zingbot_settings WHERE org_id = ?");
                 $stmt->execute([$org_id]);
                 $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                 if ($existing) $apiKey = $existing['api_key'];
            }
            
            if (!$apiKey || !$endpoint) {
                echo json_encode(['success' => false, 'error' => 'Missing API configuration']);
                exit;
            }

            // Real Connectivity Test: Try to fetch flows
            $cleanEndpoint = rtrim($endpoint, '/');
            if (strpos($cleanEndpoint, 'http') === false) {
                $cleanEndpoint = 'https://' . $cleanEndpoint;
            }
            
            // Ensure the URL correctly points to the flow endpoint
            // If the user provided app.zingbot.io/api/, we want to use that base
            $testUrl = rtrim($cleanEndpoint, '/') . '/accounts/flows';

            $ch = curl_init($testUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'X-ACCESS-TOKEN: ' . $apiKey,
                'Accept: application/json'
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code === 200) {
                 echo json_encode(['success' => true, 'message' => 'Connection successful! API Key is valid.']);
            } else {
                 $errorData = json_decode($response, true);
                 $errorMsg = $errorData['error']['message'] ?? ($errorData['message'] ?? "Connection failed (HTTP $http_code)");
                 // If 404, maybe retry without /api prefix or with it if missing
                 echo json_encode(['success' => false, 'error' => "Zingbot API ($http_code): $errorMsg (Tried: $testUrl)"]);
            }
            exit;
        }

        // Save settings
        $apiKey = $data['api_key'] ?? '';
        $endpoint = $data['api_endpoint'] ?? '';
        $isActive = isset($data['is_active']) ? (int)$data['is_active'] : 1;

        $updates = [];
        $params = [];
        
        // Only update API key if it's not the masked value
        if (!empty($apiKey) && strpos($apiKey, '*') === false) {
            $updates[] = "api_key = ?";
            $params[] = $apiKey;
        }
        
        $updates[] = "api_endpoint = ?";
        $params[] = $endpoint;
        
        $updates[] = "is_active = ?";
        $params[] = $isActive;
        
        // Check if settings already exist for this org
        $stmt = $pdo->prepare("SELECT id FROM zingbot_settings WHERE org_id = ?");
        $stmt->execute([$org_id]);
        $exists = $stmt->fetchColumn();

        if ($exists) {
            $sql = "UPDATE zingbot_settings SET " . implode(', ', $updates) . " WHERE org_id = ?";
            $params[] = $org_id;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        } else {
            // New Integration
            if (empty($apiKey)) {
                echo json_encode(['success' => false, 'error' => 'API Key is required for initial setup']);
                exit;
            }
            $stmt = $pdo->prepare("INSERT INTO zingbot_settings (org_id, api_key, api_endpoint, is_active) VALUES (?, ?, ?, ?)");
            $stmt->execute([$org_id, $apiKey, $endpoint, $isActive]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Zingbot settings saved successfully']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server Error: ' . $e->getMessage()]);
}
