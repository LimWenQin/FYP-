<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
?>

