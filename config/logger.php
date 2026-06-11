<?php
/**
 * Centralized Logging System
 */

class Logger {
    private static $logDir = null;
    private static $enabled = true;
    
    public static function init($logDir = null) {
        if ($logDir === null) {
            $logDir = dirname(__DIR__) . '/logs';
        }
        
        self::$logDir = $logDir;
        
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        self::$enabled = true;
    }
    
    /**
     * Log error message
     */
    public static function error($message, $context = []) {
        self::write('ERROR', $message, $context);
    }
    
    /**
     * Log warning message
     */
    public static function warning($message, $context = []) {
        self::write('WARNING', $message, $context);
    }
    
    /**
     * Log info message
     */
    public static function info($message, $context = []) {
        self::write('INFO', $message, $context);
    }
    
    /**
     * Log debug message
     */
    public static function debug($message, $context = []) {
        if (self::isDebugMode()) {
            self::write('DEBUG', $message, $context);
        }
    }
    
    /**
     * Write log entry
     */
    private static function write($level, $message, $context = []) {
        if (!self::$enabled) {
            return;
        }
        
        if (self::$logDir === null) {
            self::init();
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logFile = self::$logDir . '/app_' . date('Y-m-d') . '.log';
        
        $logEntry = [
            'timestamp' => $timestamp,
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown'
        ];
        
        $logLine = json_encode($logEntry) . PHP_EOL;
        
        @file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Check if debug mode is enabled
     */
    private static function isDebugMode() {
        return getenv('APP_DEBUG') === 'true' || getenv('APP_DEBUG') === '1';
    }
    
    /**
     * Log exception
     */
    public static function exception($exception, $context = []) {
        $message = $exception->getMessage();
        $context['exception'] = [
            'class' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ];
        
        self::error($message, $context);
    }
}

// Initialize logger
Logger::init();

