<?php
header('Content-Type: text/plain; charset=utf-8');
require 'connect.php';

$email = trim($_POST['email']);
if (empty($email)) {
    echo "Vui lòng nhập email.";
    exit();
}

$stmt = $conn->prepare("SELECT * FROM users WHERE EMAIL = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo "Email không tồn tại trong hệ thống!";
} else {
    $row = $result->fetch_assoc();
    echo "Mật khẩu cũ của bạn là: " . $row['PASSWORD'];
}

$conn->close();
?>
