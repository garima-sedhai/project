<?php
session_start();
session_destroy();
echo "All sessions cleared! <a href='../index.php'>Go to Home</a>";
?>