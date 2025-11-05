<?php

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "donation_system"; 

// make the connection
$conn = mysqli_connect($servername, $username, $password, $dbname);

// check the connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8");

// echo "Connect successfully";
?>