<?php
/**
 * Login API Endpoint - Upgraded Version
 * Uses new middleware, validation, and response systems
 */

// Load configuration
require_once '../../config/db.php';
require_once '../../config/middleware.php';
require_once '../../config/validator.php';
require_once '../../config/response.php';

// Apply middleware
Middleware::apply([
    'cors_origins' => getenv('CORS_ALLOWED_ORIGINS'),
    'rate_limit' => true,
    'rate_limit_max' => 30, // Allow 30 login attempts per window
    'rate_limit_window' => 300, // 5 minutes
    'session' => true,
    'error_handler' => true
]);

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::error('Method not allowed', 405);
}

// Get and validate input
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    ApiResponse::error('Invalid JSON format', 400);
}

// Validate input
$validation = Validator::validate($data, [
    'email' => 'required|email|max:255',
    'password' => 'required|min:6'
]);

if (!$validation['valid']) {
    ApiResponse::validationError($validation['errors']);
}

$email = $validation['data']['email'];
$password = $data['password']; // Don't sanitize password

try {
    // Get user and organization status from database
    $stmt = $pdo->prepare("
        SELECT u.id, u.org_id, u.password_hash, u.role, u.is_super_admin, 
               u.full_name, o.name as org_name, u.is_active as user_active,
               o.is_active as org_active, o.status as org_status
        FROM users u 
        LEFT JOIN organizations o ON u.org_id = o.id 
        WHERE u.email = ?
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        // Log failed login attempt
        Logger::warning('Failed login attempt', [
            'email' => $email,
            'ip' => Security::getClientIp()
        ]);
        
        // Use generic message to prevent user enumeration
        ApiResponse::error('Invalid credentials', 401);
    }
    
    // Check if account/org is active
    if (isset($user['user_active']) && !$user['user_active']) {
        ApiResponse::error('Your user account is inactive. Please contact your administrator.', 403);
    }

    if (isset($user['org_active']) && !$user['org_active']) {
        if ($user['org_status'] === 'pending_payment') {
            ApiResponse::error('Subscription payment pending. Please complete your registration payment to access the dashboard.', 402);
        } else {
            ApiResponse::error('Your organization account is inactive or suspended.', 403);
        }
    }
    
    // Verify password
    if (!password_verify($password, $user['password_hash'])) {
        Logger::warning('Failed login attempt - invalid password', [
            'user_id' => $user['id'],
            'ip' => Security::getClientIp()
        ]);
        
        ApiResponse::error('Invalid credentials', 401);
    }
    
    // Login successful - start secure session
    Security::secureSession();
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['org_id'] = $user['org_id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['email'] = $email;
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['is_super_admin'] = (bool)$user['is_super_admin'];
    $_SESSION['last_activity'] = time();
    
    // Log successful login
    Logger::info('User logged in', [
        'user_id' => $user['id'],
        'email' => $email,
        'ip' => Security::getClientIp()
    ]);
    
    // Return success response (backward compatible format)
    // Frontend expects: { success: true, user: {...} }
    // New format: { success: true, data: { user: {...} } }
    // We'll return both for compatibility
    http_response_code(200);
    header('Content-Type: application/json');
    
    // Fetch org currency from settings
    $orgCurrency = 'USD';
    try {
        $orgStmt = $pdo->prepare("SELECT settings FROM organizations WHERE id = ?");
        $orgStmt->execute([$user['org_id']]);
        $orgRow = $orgStmt->fetch();
        if ($orgRow && $orgRow['settings']) {
            $orgSettings = json_decode($orgRow['settings'], true);
            $orgCurrency = $orgSettings['currency'] ?? 'USD';
        }
    } catch (Exception $e) { }

    $userData = [
        'id' => $user['id'],
        'user_id' => $user['id'],
        'org_id' => $user['org_id'],
        'org_name' => $user['org_name'],
        'email' => $email,
        'role' => $user['role'],
        'is_super_admin' => (bool)$user['is_super_admin'],
        'currency' => $orgCurrency
    ];
    
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'user' => $userData,  // For backward compatibility
        'data' => ['user' => $userData]  // New format
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Exception $e) {
    Logger::exception($e, ['endpoint' => 'login']);
    ApiResponse::error('Login failed', 500);
}
