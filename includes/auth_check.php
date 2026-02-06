<?php
/**
 * Authentication check for protected pages
 */
require_once __DIR__ . '/../config/security.php';

Security::secureSession();

// Check if this is an API request
$isApi = (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) || 
         (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) ||
         (strpos($_SERVER['REQUEST_URI'], '/api/') !== false);

if (!isset($_SESSION['user_id'])) {
    if ($isApi) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized - No session found']);
        exit;
    }
    // Redirect to login at root
    $projectRoot = '/leads2'; // Fallback if not detected
    header("Location: $projectRoot/login.php");
    exit;
}

// Update last activity
$_SESSION['last_activity'] = time();

// Payment/Subscription status check
require_once __DIR__ . '/../config/db.php';
$pdo = getDb();
$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare('SELECT s.status FROM subscriptions s JOIN users u ON s.organization_id = u.org_id WHERE u.id = ? ORDER BY s.id DESC LIMIT 1');
$stmt->execute([$userId]);
$sub = $stmt->fetch();
if (!$sub || !in_array($sub['status'], ['active', 'trialing'])) {
    if ($isApi) {
        http_response_code(402);
        echo json_encode(['success' => false, 'error' => 'Payment required or subscription inactive']);
        exit;
    }
    // Optionally, you can show a message or handle inactive subscriptions here.
    // No redirect to pricing.html; user stays on the current page or handle as needed.
}
