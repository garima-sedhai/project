<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear all session variables
$_SESSION = array();

// Delete session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Clear any cached data
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Always return JSON for AJAX requests
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'Successfully logged out',
    'timestamp' => time()
]);
exit();
?>