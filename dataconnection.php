<?php

$connect=mysqli_connect("localhost","root","", "eshop");
//1.server name 2.username 3.password 4.database name

if($connect)
{
    echo "Connect sucessfully";
}

else 
    die("could not connect".mysqli_error());

?>