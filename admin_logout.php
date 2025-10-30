<?php
// admin_logout.php - Logout Script
session_start();
session_destroy();

// Clear all cookies
setcookie('admin_email', '', time() - 3600, "/");
setcookie('admin_remember', '', time() - 3600, "/");

header("Location: admin_login.php");
exit();
?>