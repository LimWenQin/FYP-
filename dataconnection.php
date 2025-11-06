<?php
// dataconnection.php - Database Connection File

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "donation_system";

// Create connection
$conn = mysqli_connect($servername, $username, $password, $dbname);

// Check connection
if (!$conn) {
    // In production, log to file instead of showing to users
    error_log("Database connection failed: " . mysqli_connect_error());
    
    // Show user-friendly error message
    die("System maintenance, please try again later. Error code: DB_CONN_001");
}

// Set character set
mysqli_set_charset($conn, "utf8");

// Set timezone (optional)
date_default_timezone_set('Asia/Kuala_Lumpur');

?>