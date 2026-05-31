<?php
// Generates a bcrypt hash — run once, paste result into jeem_mall.sql
$password = "Admin1234";
echo password_hash($password, PASSWORD_DEFAULT);
?>
