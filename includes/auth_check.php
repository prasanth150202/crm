<?php
/**
 * Authentication Check Middleware
 * Redirects to login page if user is not authenticated.
 * Handles API requests by returning 401 Unauthorized.
 */

require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/db.php';

// Securely start the session
Security::secureSession();

// Global Organization Gate
if (isset($_SESSION['user_id']) && isset($_SESSION['org_id'])) {
    // Only check if not a super admin
    if (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin']) {
        $stmt = $pdo->prepare("SELECT is_active, status FROM organizations WHERE id = ?");
        $stmt->execute([$_SESSION['org_id']]);
        $org = $stmt->fetch();

        if ($org && (!$org['is_active'] || in_array($org['status'], ['pending_payment', 'suspended']))) {
            // Check if this is an API request
            $isApi = (strpos($_SERVER['REQUEST_URI'], '/api/') !== false);
            $isPaymentPage = (strpos($_SERVER['SCRIPT_NAME'], 'payment_required.php') !== false);
            
            if ($isApi) {
                http_response_code(402);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'Organization Restricted',
                    'message' => 'Payment required to activate or reactivate your organization.',
                    'org_status' => $org['status']
                ]);
                exit;
            } elseif (!$isPaymentPage) {
                // Redirect to payment required page for both pending and suspended
                header('Location: payment_required.php');
                exit;
            }
        }
    }
}

if (!isset($_SESSION['user_id'])) {
    // Check if this is an API request
    $isApi = (strpos($_SERVER['REQUEST_URI'], '/api/') !== false) || 
             (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
             (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest');

    if ($isApi) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Unauthorized access'
        ]);
        exit;
    } else {
        // Redirect to login page
        // Use relative path or determine project root
        header('Location: login.php');
        exit;
    }
}
