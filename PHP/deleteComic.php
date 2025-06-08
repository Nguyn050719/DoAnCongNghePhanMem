<?php
require_once 'connect.php';
header('Content-Type: application/json; charset=utf-8');

// Đọc JSON từ yêu cầu
$data = json_decode(file_get_contents("php://input"), true);
$comicId = $data['comic_id'] ?? null;

if (!$comicId) {
    echo json_encode(['success' => false, 'message' => 'Thiếu mã truyện']);
    exit();
}

// Xóa truyện
$stmt = $conn->prepare("DELETE FROM COMIC WHERE COMIC_ID = ?");
$stmt->bind_param("s", $comicId);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Không thể xóa truyện']);
}

$stmt->close();
$conn->close();
?>
