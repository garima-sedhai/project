<?php
session_start();

// Check if it's an AJAX request
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Clear all session variables
$_SESSION = array();

// Delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// EXTREME cache clearing headers
header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");

// Clear any output buffers
if (ob_get_level()) {
    ob_end_clean();
}

// Always return JSON for AJAX requests
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'Successfully logged out',
    'timestamp' => time(),
    'redirect' => 'login.php'
]);
exit();
?>