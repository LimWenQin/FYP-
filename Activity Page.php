<?php
$conn = new mysqli("localhost", "root", "", "donation_system");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "SELECT ID, Date, Details, Picture, Status, Get_Amount FROM activity ORDER BY Date DESC";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        echo "<div>";
        echo "<h3>" . $row["Details"] . "</h3>";
        echo "<p>Date: " . $row["Date"] . "</p>";
        echo "<p>Status: " . $row["Status"] . "</p>";
        echo "<img src='uploads/" . $row["Picture"] . "' width='200'>";
        echo "</div>";
    }
} else {
    echo "No activities found.";
}

$conn->close();
?>
