<?php
class Security {
    
    // Sanitize input data
    public static function sanitize($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitize'], $data);
        }
        
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        return $data;
    }
    
    // Validate email
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    // Validate phone number (Nepali format)
    public static function validatePhone($phone) {
        return preg_match('/^[0-9]{10}$/', $phone) === 1;
    }
    
    // Generate CSRF token
    public static function generateCSRFToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    // Validate CSRF token
    public static function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    // Rate limiting
    public static function checkRateLimit($key, $max_attempts = 5, $time_window = 900) { // 15 minutes
        $current_time = time();
        $attempts_key = "rate_limit_{$key}";
        
        if (!isset($_SESSION[$attempts_key])) {
            $_SESSION[$attempts_key] = [
                'attempts' => 1,
                'first_attempt' => $current_time
            ];
            return true;
        }
        
        $rate_data = $_SESSION[$attempts_key];
        
        // Reset if time window has passed
        if ($current_time - $rate_data['first_attempt'] > $time_window) {
            $_SESSION[$attempts_key] = [
                'attempts' => 1,
                'first_attempt' => $current_time
            ];
            return true;
        }
        
        // Check if max attempts exceeded
        if ($rate_data['attempts'] >= $max_attempts) {
            return false;
        }
        
        // Increment attempts
        $rate_data['attempts']++;
        $_SESSION[$attempts_key] = $rate_data;
        
        return true;
    }
    
    // Password strength validation
    public static function validatePasswordStrength($password) {
        $errors = [];
        
        if (strlen($password) < 6) {
            $errors[] = "Password must be at least 6 characters long";
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
        
        return $errors;
    }
    
    // Generate secure random string
    public static function generateRandomString($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }
    
    // Log security events
    public static function logSecurityEvent($event, $user_id = null, $details = []) {
        $log_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'user_id' => $user_id,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'details' => json_encode($details)
        ];
        
        // In production, you would write this to a secure log file or database
        error_log("SECURITY: " . json_encode($log_entry));
    }
}
?>