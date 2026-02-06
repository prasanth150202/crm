<?php
/**
 * API Middleware Pipeline
 */

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/response.php';
require_once __DIR__ . '/logger.php';

class Middleware {
    /**
     * Apply common middleware stack
     */
    public static function apply($options = []) {
        // CORS
        $allowedOrigins = $options['cors_origins'] ?? null;
        Security::cors($allowedOrigins);
        
        // Rate limiting
        if (($options['rate_limit'] ?? true) !== false) {
            $identifier = Security::getClientIp();
            $maxRequests = $options['rate_limit_max'] ?? 100;
            $windowSeconds = $options['rate_limit_window'] ?? 60;
            Security::rateLimit($identifier, $maxRequests, $windowSeconds);
        }
        
        // Secure session
        if ($options['session'] ?? false) {
            Security::secureSession();
        }

        // CSRF Check (except for GET/OPTIONS and Login/Register endpoints)
        $method = $_SERVER['REQUEST_METHOD'];
        $scriptName = $_SERVER['SCRIPT_NAME'];
        $exempt = [
            '/api/auth/login.php', 
            '/api/auth/register.php',
            '/api/external/webhook.php' // Webhooks usually use signatures, not CSRF
        ];
        
        $isExempt = false;
        foreach ($exempt as $path) {
            if (strpos($scriptName, $path) !== false) {
                $isExempt = true;
                break;
            }
        }

        if (!$isExempt && in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            $headers = getallheaders();
            $token = $headers['X-CSRF-Token'] ?? ($_POST['csrf_token'] ?? null);
            
            if (!$token || !Security::verifyCsrfToken($token)) {
                ApiResponse::error('Invalid CSRF Token', 403);
            }
        }
        
        // Error handler
        if ($options['error_handler'] ?? true) {
            set_error_handler([self::class, 'errorHandler']);
            set_exception_handler([self::class, 'exceptionHandler']);
        }
    }
    
    /**
     * Error handler
     */
    public static function errorHandler($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        
        Logger::error("PHP Error: $message", [
            'severity' => $severity,
            'file' => $file,
            'line' => $line
        ]);
        
        if (getenv('APP_DEBUG') === 'true') {
            ApiResponse::error("Error: $message in $file:$line", 500);
        } else {
            ApiResponse::error('An error occurred', 500);
        }
        
        return true;
    }
    
    /**
     * Exception handler
     */
    public static function exceptionHandler($exception) {
        Logger::exception($exception);
        
        if (getenv('APP_DEBUG') === 'true') {
            ApiResponse::error(
                $exception->getMessage(),
                500,
                [
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine()
                ]
            );
        } else {
            ApiResponse::error('An error occurred', 500);
        }
    }
    
    /**
     * Require authentication
     */
    public static function requireAuth() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['user_id'])) {
            ApiResponse::error('Authentication required', 401);
        }

        // Validate Org Access (Split-Brain Protection)
        // Ensure the session org_id is actually valid for this user
        // We assume global $pdo is available or we need to access it. 
        // Middleware static methods usually don't have access to $pdo easily unless passed.
        // For this system, we will rely on session data being correct at login, 
        // BUT we should verify it if strict mode is requested.
        
        // For now, we trust the session to avoid DB performance hit on every request,
        // BUT we expect 'login.php' and 'switch_org.php' to have done the heavy lifting.
        
        return [
            'user_id' => $_SESSION['user_id'],
            'org_id' => $_SESSION['org_id'] ?? null,
            'role' => $_SESSION['role'] ?? null
        ];
    }
    
    /**
     * Require API key authentication
     */
    public static function requireApiKey($pdo) {
        $headers = getallheaders();
        $apiKey = null;
        
        if (isset($headers['Authorization'])) {
            $auth = $headers['Authorization'];
            if (strpos($auth, 'Bearer ') === 0) {
                $apiKey = substr($auth, 7);
            }
        }
        
        if (!$apiKey && isset($_GET['api_key'])) {
            $apiKey = $_GET['api_key'];
        }
        
        if (!$apiKey) {
            ApiResponse::error('Missing API key', 401);
        }
        
        try {
            $stmt = $pdo->prepare("SELECT id, name FROM organizations WHERE api_key = ?");
            $stmt->execute([$apiKey]);
            $org = $stmt->fetch();
            
            if (!$org) {
                ApiResponse::error('Invalid API key', 401);
            }
            
            return $org;
        } catch (Exception $e) {
            Logger::exception($e);
            ApiResponse::error('API key validation failed', 500);
        }
    }
}

