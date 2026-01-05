<?php
session_start();

class AuthHelper {
    
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    public static function isAdmin() {
        return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
    }
    
    public static function requireLogin($redirect = 'login.php') {
        if (!self::isLoggedIn()) {
            header("Location: $redirect");
            exit();
        }
    }
    
    public static function requireAdmin($redirect = 'login.php') {
        if (!self::isLoggedIn() || !self::isAdmin()) {
            header("Location: $redirect");
            exit();
        }
    }
    
    public static function requireCustomer($redirect = 'login.php') {
        if (!self::isLoggedIn() || self::isAdmin()) {
            header("Location: $redirect");
            exit();
        }
    }
    
    public static function generateCSRFToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    public static function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    public static function logout() {
        session_destroy();
        header("Location: ../index.php");
        exit();
    }
}
?>