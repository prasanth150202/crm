<?php
ini_set('display_errors', 0);
/**
 * Environment Configuration Loader
 * Supports .env file or environment variables
 */

class Env {
    private static $loaded = false;
    private static $cache = [];
    
    /**
     * Load environment variables from .env file
     */
    public static function load($path = null) {
        if (self::$loaded) {
            return;
        }

        if ($path === null) {
            $path = __DIR__ . '/../.env';
            if (!file_exists($path)) {
                $path = __DIR__ . '/.env';
            }
        }

        if (file_exists($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) {
                    continue; // Skip comments
                }
                
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);
                
                // Remove quotes if present
                if (preg_match('/^"(.*)"$/', $value, $matches)) {
                    $value = $matches[1];
                } elseif (preg_match("/^'(.*)'$/", $value, $matches)) {
                    $value = $matches[1];
                }
                
                // Check if variable is not set OR is empty
                $currentValue = getenv($name);
                if ($currentValue === false || $currentValue === '') {
                    putenv("$name=$value");
                    $_ENV[$name] = $value;
                    $_SERVER[$name] = $value;
                }
            }
        }
        
        self::$loaded = true;
    }
    
    /**
     * Get environment variable with optional default
     */
    public static function get($key, $default = null) {
        self::load();
        
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }
        
        $value = getenv($key);
        if ($value === false) {
            $value = $default;
        }
        
        self::$cache[$key] = $value;
        return $value;
    }
    
    /**
     * Get boolean environment variable
     */
    public static function getBool($key, $default = false) {
        $value = self::get($key, $default);
        if (is_bool($value)) {
            return $value;
        }
        return in_array(strtolower($value), ['true', '1', 'yes', 'on']);
    }
    
    /**
     * Get integer environment variable
     */
    public static function getInt($key, $default = 0) {
        return (int) self::get($key, $default);
    }
}

// Auto-load on include
Env::load();

// Production Error Handling settings
if (Env::get('APP_ENV') === 'production') {
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    error_reporting(E_ALL);
}

