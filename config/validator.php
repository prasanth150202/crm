<?php
/**
 * Input Validation Utilities
 */

require_once __DIR__ . '/security.php';

class Validator {
    /**
     * Validate and sanitize input data
     */
    public static function validate($data, $rules) {
        $errors = [];
        $sanitized = [];
        
        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            
            // Parse rule string (e.g., "required|email|max:255")
            $ruleParts = explode('|', $rule);
            
            foreach ($ruleParts as $rulePart) {
                $ruleName = $rulePart;
                $ruleValue = null;
                
                if (strpos($rulePart, ':') !== false) {
                    list($ruleName, $ruleValue) = explode(':', $rulePart, 2);
                }
                
                // Required check
                if ($ruleName === 'required') {
                    if ($value === null || trim($value) === '') {
                        $errors[$field] = "Field '$field' is required";
                        continue 2; // Skip to next field
                    }
                }
                
                // Skip other validations if field is empty and not required
                if (($value === null || trim($value) === '') && $ruleName !== 'required') {
                    continue;
                }
                
                // Email validation
                if ($ruleName === 'email') {
                    if (!Security::validateEmail($value)) {
                        $errors[$field] = "Field '$field' must be a valid email address";
                    } else {
                        $value = Security::sanitize($value, 'email');
                    }
                }
                
                // Integer validation
                if ($ruleName === 'integer') {
                    if (!is_numeric($value) || (int)$value != $value) {
                        $errors[$field] = "Field '$field' must be an integer";
                    } else {
                        $value = (int)$value;
                    }
                }
                
                // Float validation
                if ($ruleName === 'float') {
                    if (!is_numeric($value)) {
                        $errors[$field] = "Field '$field' must be a number";
                    } else {
                        $value = (float)$value;
                    }
                }
                
                // String length validation
                if ($ruleName === 'min') {
                    if (strlen($value) < (int)$ruleValue) {
                        $errors[$field] = "Field '$field' must be at least {$ruleValue} characters";
                    }
                }
                
                if ($ruleName === 'max') {
                    if (strlen($value) > (int)$ruleValue) {
                        $errors[$field] = "Field '$field' must not exceed {$ruleValue} characters";
                    }
                }
                
                // Numeric range validation
                if ($ruleName === 'min_value') {
                    if ((float)$value < (float)$ruleValue) {
                        $errors[$field] = "Field '$field' must be at least {$ruleValue}";
                    }
                }
                
                if ($ruleName === 'max_value') {
                    if ((float)$value > (float)$ruleValue) {
                        $errors[$field] = "Field '$field' must not exceed {$ruleValue}";
                    }
                }
                
                // In array validation
                if ($ruleName === 'in') {
                    $allowed = explode(',', $ruleValue);
                    if (!in_array($value, $allowed)) {
                        $errors[$field] = "Field '$field' must be one of: " . implode(', ', $allowed);
                    }
                }
                
                // URL validation
                if ($ruleName === 'url') {
                    if (!filter_var($value, FILTER_VALIDATE_URL)) {
                        $errors[$field] = "Field '$field' must be a valid URL";
                    } else {
                        $value = Security::sanitize($value, 'url');
                    }
                }
                
                // Phone validation (basic)
                if ($ruleName === 'phone') {
                    $cleaned = preg_replace('/[^0-9+()-]/', '', $value);
                    if (strlen($cleaned) < 10) {
                        $errors[$field] = "Field '$field' must be a valid phone number";
                    }
                }
            }
            
            // Sanitize string fields by default
            if (!isset($errors[$field]) && $value !== null) {
                if (is_string($value)) {
                    $value = Security::sanitize($value);
                }
            }
            
            $sanitized[$field] = $value;
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'data' => $sanitized
        ];
    }
    
    /**
     * Validate JSON input
     */
    public static function validateJson($jsonString, $rules) {
        $data = json_decode($jsonString, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'valid' => false,
                'errors' => ['json' => 'Invalid JSON format: ' . json_last_error_msg()],
                'data' => null
            ];
        }
        
        return self::validate($data, $rules);
    }
    
    /**
     * Validate lead data
     */
    public static function validateLead($data) {
        return self::validate($data, [
            'name' => 'required|max:255',
            'email' => 'email|max:255',
            'phone' => 'phone|max:50',
            'company' => 'max:255',
            'title' => 'max:255',
            'source' => 'in:Direct,Website,LinkedIn,Referral,Ads,Cold Call',
            'stage_id' => 'in:new,contacted,qualified,won,lost',
            'lead_value' => 'float|min_value:0'
        ]);
    }
    
    /**
     * Validate user data
     */
    public static function validateUser($data) {
        return self::validate($data, [
            'email' => 'required|email|max:255',
            'password' => 'required|min:6',
            'role' => 'in:super_admin,admin,manager,sales_rep,viewer',
            'full_name' => 'max:255'
        ]);
    }
}

