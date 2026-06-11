<?php
/**
 * Security Middleware and Utilities
 */

class Security {
    /**
     * Configure CORS headers
     */
    public static function cors($allowedOrigins = null) {
        if ($allowedOrigins === null) {
            $allowedOrigins = getenv('CORS_ALLOWED_ORIGINS') ?: '*';
        }
        
        if ($allowedOrigins !== '*') {
            $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
            if (in_array($origin, explode(',', $allowedOrigins))) {
                header("Access-Control-Allow-Origin: $origin");
            }
        } else {
            header("Access-Control-Allow-Origin: *");
        }
        
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Max-Age: 86400");
        
        // Handle preflight
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }
    
    /**
     * Sanitize input string
     */
    public static function sanitize($input, $type = 'string') {
        if ($input === null) {
            return null;
        }
        
        switch ($type) {
            case 'email':
                return filter_var(trim($input), FILTER_SANITIZE_EMAIL);
            case 'int':
                return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
            case 'float':
                return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            case 'url':
                return filter_var(trim($input), FILTER_SANITIZE_URL);
            case 'string':
            default:
                return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
        }
    }
    
    /**
     * Validate email
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validate required fields
     */
    public static function validateRequired($data, $fields) {
        $errors = [];
        
        foreach ($fields as $field) {
            if (!isset($data[$field]) || trim($data[$field]) === '') {
                $errors[$field] = "Field '$field' is required";
            }
        }
        
        return empty($errors) ? true : $errors;
    }
    
    /**
     * Generate CSRF token
     */
    public static function generateCsrfToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Verify CSRF token
     */
    public static function verifyCsrfToken($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Rate limiting (File-based)
     */
    public static function rateLimit($identifier, $maxRequests = 60, $windowSeconds = 60) {
        $tempDir = sys_get_temp_dir();
        $key = md5($identifier . '_' . floor(time() / $windowSeconds));
        $file = $tempDir . '/rate_limit_' . $key;
        
        $current = 0;
        if (file_exists($file)) {
            $current = (int)file_get_contents($file);
        }
        
        if ($current >= $maxRequests) {
            http_response_code(429);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Rate limit exceeded. Please try again later.'
            ]);
            exit;
        }
        
        file_put_contents($file, $current + 1);
    }
    
    /**
     * Get client IP address
     */
    public static function getClientIp() {
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Secure session configuration
     */
    public static function secureSession() {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', 1);
            
            $host = $_SERVER['HTTP_HOST'] ?? '';
            $isLocal = ($host === 'localhost' || $host === '127.0.0.1' || strpos($host, '192.168.') === 0);
            
            $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
            
            // Disable secure flag on localhost even if HTTPS is detected (e.g. self-signed)
            // or if we are on plain HTTP
            ini_set('session.cookie_secure', ($isHttps && !$isLocal) ? 1 : 0);
            
            ini_set('session.use_strict_mode', 1);
            ini_set('session.cookie_samesite', 'Lax');
            session_start();
        }
    }
    
    /**
     * Validate password strength
     * @param string $password Password to validate
     * @return array Array of error messages (empty if valid)
     */
    public static function validatePassword(string $password): array {
        $errors = [];
        
        if (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters long";
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter";
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number";
        }
        
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = "Password must contain at least one special character";
        }
        
        return $errors;
    }
}


