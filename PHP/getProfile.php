<?php
require_once 'connect.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['uses_id'])) {
    echo json_encode(['success' => false, 'message' => 'Bạn chưa đăng nhập!']);
    exit();
}

$uses_id = $_SESSION['uses_id'];

$query = $conn->prepare("SELECT USERNAME, USES_ID, `ROLE`, DISPLAY_NAME, BIO, AVATAR, COVER_IMAGE FROM USERS WHERE USES_ID = ?");
$query->bind_param("s", $uses_id);
$query->execute();
$result = $query->get_result();
$data = $result->fetch_assoc();

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy người dùng!']);
    exit();
}

echo json_encode([
    'success' => true,
    'usesId' => $data['USES_ID'],
    'username' => $data['USERNAME'],
    'role' => $data['ROLE'],
    'displayName' => $data['DISPLAY_NAME'],
    'bio' => $data['BIO'],
    'avatar' => $data['AVATAR'] ? '../Image/' . $data['AVATAR'] : '../Image/avatar.jpg',
    'cover' => $data['COVER_IMAGE'] ? '../Image/' . $data['COVER_IMAGE'] : '../Image/anhbia.jpg'
], JSON_UNESCAPED_UNICODE);

$query->close();
$conn->close();
?>
