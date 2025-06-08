<?php
require_once 'connect.php';
header('Content-Type: application/json');

// Lấy dữ liệu từ form
$comic_id = $_POST['comic_id'] ?? '';
$title = $_POST['title'] ?? '';
$number = $_POST['chapter_number'] ?? 0;

// Kiểm tra dữ liệu bắt buộc
if (!$comic_id || !$title) {
    echo json_encode(['success' => false, 'message' => 'Thiếu thông tin bắt buộc']);
    exit;
}

// ======= TẠO CHAPTER_ID MỚI DẠNG CR001 =======
$query = "SELECT CHAPTER_ID FROM CHAPTER WHERE CHAPTER_ID LIKE 'CR%' ORDER BY CHAPTER_ID DESC LIMIT 1";
$result = mysqli_query($conn, $query);

if ($row = mysqli_fetch_assoc($result)) {
    $lastId = $row['CHAPTER_ID'];
    $num = intval(substr($lastId, 2)) + 1;
} else {
    $num = 1;
}
$chapter_id = 'CR' . str_pad($num, 3, '0', STR_PAD_LEFT);

// ======= Ghi vào bảng CHAPTER =======
$stmt = $conn->prepare("INSERT INTO CHAPTER (CHAPTER_ID, COMIC_ID, TITLE, CHAPTER_NUMBER, CREATE_AT) VALUES (?, ?, ?, ?, NOW())");
$stmt->bind_param("sssi", $chapter_id, $comic_id, $title, $number);
if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Lỗi khi lưu CHAPTER: ' . $stmt->error]);
    exit;
}
$stmt->close();

// ======= Xử lý upload các trang (pages[]) =======
if (!isset($_FILES['pages'])) {
    echo json_encode(['success' => false, 'message' => 'Chưa chọn ảnh trang']);
    exit;
}

$uploadDir = "../Image/pages/";
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$files = $_FILES['pages'];
$success = true;

for ($i = 0; $i < count($files['name']); $i++) {
    $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9.]/', '_', $files['name'][$i]);
    $targetPath = $uploadDir . $filename;

    if (!move_uploaded_file($files['tmp_name'][$i], $targetPath)) {
        $success = false;
        continue;
    }

    // Tạo PAGE_ID và PAGE_NUMBER
    $pageQuery = "SELECT PAGE_ID FROM PAGE WHERE PAGE_ID LIKE 'PE%' ORDER BY PAGE_ID DESC LIMIT 1";
    $pageResult = mysqli_query($conn, $pageQuery);

    if ($row = mysqli_fetch_assoc($pageResult)) {
    $lastPageId = $row['PAGE_ID'];
    $nextNumber = intval(substr($lastPageId, 2)) + 1;
    } else {
    $nextNumber = 1;
    }
    $page_id = 'PE' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    $page_number = $i + 1;
    $url = substr($targetPath, 3); // bỏ "../"

    $stmt2 = $conn->prepare("INSERT INTO PAGE (PAGE_ID, CHAPTER_ID, URL, PAGE_NUMBER) VALUES (?, ?, ?, ?)");
    $stmt2->bind_param("sssi", $page_id, $chapter_id, $url, $page_number);
    $success = $success && $stmt2->execute();
    $stmt2->close();
}

if ($success) {
    echo json_encode(['success' => true, 'message' => 'Thêm chapter và trang thành công']);
} else {
    echo json_encode(['success' => false, 'message' => 'Thêm chapter thành công nhưng có ảnh trang bị lỗi']);
}
?>
