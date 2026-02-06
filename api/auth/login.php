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
    'rate_limit_max' => 10, // Stricter for login
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
    // Get user from database
    $stmt = $pdo->prepare("
        SELECT u.id, u.org_id, u.password_hash, u.role, u.is_super_admin, 
               u.full_name, o.name as org_name, u.is_active
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
    
    // Check if user is active
    if (isset($user['is_active']) && !$user['is_active']) {
        ApiResponse::error('Account is inactive', 403);
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
    
    $userData = [
        'id' => $user['id'],
        'org_id' => $user['org_id'],
        'org_name' => $user['org_name'],
        'email' => $email,
        'role' => $user['role'],
        'is_super_admin' => (bool)$user['is_super_admin']
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
