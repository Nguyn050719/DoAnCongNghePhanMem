<?php
// Thông tin kết nối MySQL
$servername = "localhost";   // Hoặc 127.0.0.1
$username = "root";          // User mặc định của XAMPP
$password = "";              // Mật khẩu để trống nếu dùng XAMPP
$database = "webtruyen_1";     // Tên database bạn đã import SQL

// Kết nối MySQL
$conn = new mysqli($servername, $username, $password, $database);

// Kiểm tra kết nối
if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

// Thiết lập charset UTF-8 (nếu cần)
$conn->set_charset("utf8");
?>
