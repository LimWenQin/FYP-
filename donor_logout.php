<?php
session_start();


session_unset();
session_destroy();

$_SESSION['logged_in'] = false;


header("Location: donor_login.php");
exit();
?>

