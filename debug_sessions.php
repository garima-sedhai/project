<?php
session_start();
echo "<h1>Session Debugger</h1>";

echo "<h3>Current Session Data:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h3>Session ID: " . session_id() . "</h3>";
echo "<h3>Current URL: " . $_SERVER['REQUEST_URI'] . "</h3>";

echo "<h3>Actions:</h3>";
echo "<ul>";
echo "<li><a href='admin/login.php'>Go to Admin Login</a></li>";
echo "<li><a href='customer/login.php'>Go to Customer Login</a></li>";
echo "<li><a href='clear_all.php'>Clear All Sessions</a></li>";
echo "<li><a href='index.php'>Go to Homepage</a></li>";
echo "</ul>";
?>