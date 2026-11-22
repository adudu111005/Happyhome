
<?php

$host = "localhost";
$user = "root";
$password = "";
$database = "happyhome";

// Kết nối MySQL
$conn = new mysqli($host, $user, $password, $database);

// Kiểm tra kết nối
if ($conn->connect_error) {
    die("Kết nối database thất bại: " . $conn->connect_error);
}

// Hỗ trợ tiếng Việt
$conn->set_charset("utf8mb4");

?>
<?php
echo password_hash("123456", PASSWORD_DEFAULT);
?>