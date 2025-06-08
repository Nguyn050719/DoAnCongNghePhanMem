<?php
header('Content-Type: text/plain; charset=utf-8');

// Kết nối CSDL
require 'connect.php';
session_start();

$username = trim($_POST['username']);
$password = trim($_POST['password']);

// Kiểm tra tài khoản
$stmt = $conn->prepare("SELECT * FROM users WHERE USERNAME = ? OR EMAIL = ?");
$stmt->bind_param("ss", $username, $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo "Tên đăng nhập hoặc email không tồn tại!";
    exit();
}

$row = $result->fetch_assoc();

if (password_verify($password, $row['PASSWORD'])) {
    // Xử lý ảnh và thông tin mặc định nếu chưa có
    $avatar = (!empty($row['AVATAR'])) ? $row['AVATAR'] : '../Image/avatar.jpg';
    $cover = (!empty($row['COVER_IMAGE'])) ? $row['COVER_IMAGE'] : '../Image/anhbia.jpg';
    $bio = (!empty($row['BIO'])) ? $row['BIO'] : '';
    $displayName = (!empty($row['DISPLAY_NAME'])) ? $row['DISPLAY_NAME'] : $row['USERNAME'];
    $role = (!empty($row['ROLE'])) ? $row['ROLE'] : 'user'; // fallback mặc định là user

    $_SESSION['uses_id'] = $row['USES_ID'];

    // Trả về dữ liệu: Đăng nhập thành công!|USES_ID|USERNAME|AVATAR|COVER_IMAGE|BIO|DISPLAY_NAME|ROLE
    echo "Đăng nhập thành công!|" . 
        $row['USES_ID'] . "|" . 
        $row['USERNAME'] . "|" . 
        $avatar . "|" . 
        $cover . "|" . 
        $bio . "|" . 
        $displayName . "|" . 
        $role;
} else {
    echo "Sai mật khẩu!";
}

$conn->close();
?>
