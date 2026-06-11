<?php
/**
 * Standardized API Response Handler
 */

class ApiResponse {
    /**
     * Send success response
     */
    public static function success($data = null, $message = 'Success', $code = 200) {
        http_response_code($code);
        header('Content-Type: application/json');
        
        $response = [
            'success' => true,
            'message' => $message
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * Send error response
     */
    public static function error($message, $code = 400, $errors = null) {
        http_response_code($code);
        header('Content-Type: application/json');
        
        $response = [
            'success' => false,
            'error' => $message
        ];
        
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        
        // Log error
        if (class_exists('Logger')) {
            Logger::error($message, [
                'code' => $code,
                'errors' => $errors,
                'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
            ]);
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * Send paginated response
     */
    public static function paginated($data, $total, $limit, $offset, $message = 'Success') {
        http_response_code(200);
        header('Content-Type: application/json');
        
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => [
                'total' => (int)$total,
                'limit' => (int)$limit,
                'offset' => (int)$offset,
                'has_more' => ($offset + count($data)) < $total
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * Send validation error response
     */
    public static function validationError($errors, $message = 'Validation failed') {
        self::error($message, 422, $errors);
    }
}

// Global helper functions for backward compatibility
if (!function_exists('json_response')) {
    function json_response($code, $data, $message = null) {
        if ($message) {
            ApiResponse::success($data, $message, $code);
        } else {
            ApiResponse::success($data, 'Success', $code);
        }
    }
}

if (!function_exists('error_response')) {
    function error_response($code, $message) {
        ApiResponse::error($message, $code);
    }
}