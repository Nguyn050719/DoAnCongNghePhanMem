<?php
header("Content-Type: application/json");
require_once "connect.php"; // Đảm bảo bạn đã tạo file kết nối CSDL

$comicId = $_GET['comic_id'] ?? '';
$chapterNumber = $_GET['chapter_number'] ?? '';

if (!$comicId || !$chapterNumber) {
  echo json_encode(["success" => false, "message" => "Thiếu tham số"]);
  exit;
}

// Lấy CHAPTER_ID từ COMIC_ID và CHAPTER_NUMBER
$stmt = $conn->prepare("SELECT CHAPTER_ID FROM Chapters WHERE COMIC_ID = ? AND CHAPTER_NUMBER = ?");
$stmt->bind_param("ss", $comicId, $chapterNumber);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  echo json_encode(["success" => false, "message" => "Không tìm thấy chương"]);
  exit;
}

$row = $result->fetch_assoc();
$chapterId = $row['CHAPTER_ID'];

// Lấy danh sách URL ảnh từ bảng Pages theo CHAPTER_ID
$stmt2 = $conn->prepare("SELECT URL FROM Pages WHERE CHAPTER_ID = ? ORDER BY PAGE_NUMBER ASC");
$stmt2->bind_param("s", $chapterId);
$stmt2->execute();
$result2 = $stmt2->get_result();

$images = [];
while ($row2 = $result2->fetch_assoc()) {
  $images[] = $row2['URL'];
}

if (count($images) > 0) {
  echo json_encode(["success" => true, "images" => $images]);
} else {
  echo json_encode(["success" => false, "message" => "Chương không có ảnh"]);
}
?>
