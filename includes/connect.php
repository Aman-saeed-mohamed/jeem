<?php
$conn = mysqli_connect("localhost", "root", "", "jeem_mall");
if (!$conn) {
    die("Connection Failed: " . mysqli_connect_error());
}
mysqli_set_charset($conn, "utf8mb4");
?>
