<?php
header('Content-Type: text/plain; charset=utf-8');

// Kết nối CSDL qua connect.php
require 'connect.php'; // Đảm bảo đường dẫn đúng

$username = trim($_POST['username']);
$email = trim($_POST['email']);
$password = trim($_POST['password']);
$avatar = 'avatar.jpg';
$cover = 'anhbia.jpg';
$role = 'user';
$displayName = $username; // Tên hiển thị mặc định = username

// Kiểm tra hợp lệ
if (!preg_match("/^[a-zA-Z0-9]{6,20}$/", $username)) {
    echo "Tên đăng nhập không hợp lệ!";
    exit();
}

if (strlen($password) < 8 || strlen($password) > 30) {
    echo "Mật khẩu không hợp lệ!";
    exit();
}

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Sinh ID dạng US001
$getMaxID = $conn->query("SELECT MAX(CAST(SUBSTRING(USES_ID,3) AS UNSIGNED)) AS max_id FROM users");
$row = $getMaxID->fetch_assoc();
$nextID = $row['max_id'] ? $row['max_id'] + 1 : 1;
$userID = "US" . str_pad($nextID, 3, '0', STR_PAD_LEFT);

// Kiểm tra username/email đã tồn tại
$check = $conn->prepare("SELECT * FROM users WHERE USERNAME = ? OR EMAIL = ?");
$check->bind_param("ss", $username, $email);
$check->execute();
$result = $check->get_result();
if ($result->num_rows > 0) {
    echo "Tên đăng nhập hoặc email đã tồn tại!";
    exit();
}

// Thêm dữ liệu vào DB (đã thêm display_name)
$stmt = $conn->prepare("INSERT INTO users (USES_ID, USERNAME, PASSWORD, EMAIL, AVATAR, COVER_IMAGE, ROLE, DISPLAY_NAME) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssssss", $userID, $username, $hashedPassword, $email, $avatar, $cover, $role, $displayName);

if ($stmt->execute()) {
    echo "Đăng ký thành công";
} else {
    echo "Lỗi đăng ký: " . $stmt->error;
}

$conn->close();
?>
