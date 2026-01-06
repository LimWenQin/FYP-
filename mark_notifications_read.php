<?php
include 'dataconnection.php'; // 你的数据库连接

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sql = "UPDATE admin_notifications SET Is_Read = 1 WHERE Is_Read = 0";
    if (mysqli_query($conn, $sql)) {
        echo "Success";
    } else {
        echo "Error";
    }
}
?>