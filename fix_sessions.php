<?php
// fix_sessions.php - Remove duplicate session_start() calls
echo "<h2>Fixing Session Issues</h2>";

$files_to_fix = [
    'login.php',
    'register.php',
    'admin/login.php',
    'admin/dashboard.php',
    'dashboard.php',
    'index.php'
];

foreach ($files_to_fix as $file) {
    $path = __DIR__ . '/' . $file;
    
    if (file_exists($path)) {
        $content = file_get_contents($path);
        
        // Remove session_start() if config.php is included
        if (strpos($content, "require_once 'includes/config.php'") !== false ||
            strpos($content, 'require_once "includes/config.php"') !== false ||
            strpos($content, "require_once('../includes/config.php')") !== false) {
            
            // Remove session_start() lines
            $new_content = preg_replace('/\s*session_start\(\);\s*/', '', $content);
            
            if ($content !== $new_content) {
                file_put_contents($path, $new_content);
                echo "<p style='color:green'>✅ Fixed: $file</p>";
            } else {
                echo "<p>✅ Already correct: $file</p>";
            }
        }
    } else {
        echo "<p style='color:orange'>⚠️ File not found: $file</p>";
    }
}

echo "<hr>";
echo "<p><strong>Test the fixes:</strong></p>";
echo "<ul>";
echo "<li><a href='admin/login.php'>Test Admin Login</a></li>";
echo "<li><a href='login.php'>Test User Login</a></li>";
echo "<li><a href='test_final.php'>Run Final Test</a></li>";
echo "</ul>";
?>