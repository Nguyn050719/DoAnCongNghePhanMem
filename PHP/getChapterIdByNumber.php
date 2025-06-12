<?php
// Bật báo cáo lỗi để dễ debug hơn
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'connect.php';

header('Content-Type: application/json; charset=utf-8');

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(array("success" => false, "message" => "Không thể kết nối đến cơ sở dữ liệu."));
    exit();
}

$comic_id = isset($_GET['comic_id']) ? htmlspecialchars($_GET['comic_id']) : null;
$chapter_number = isset($_GET['chapter_number']) ? intval($_GET['chapter_number']) : null;

if ($comic_id === null || empty($comic_id) || $chapter_number === null || $chapter_number <= 0) {
    http_response_code(400);
    echo json_encode(array("success" => false, "message" => "Thiếu comic_id hoặc chapter_number."));
    exit();
}

try {
    $query = "SELECT CHAPTER_ID FROM CHAPTER WHERE COMIC_ID = ? AND CHAPTER_NUMBER = ?";
    $stmt = $conn->prepare($query);

    if ($stmt === false) {
        error_log("Lỗi chuẩn bị truy vấn SQL getChapterIdByNumber: " . $conn->error);
        http_response_code(500);
        echo json_encode(array("success" => false, "message" => "Lỗi chuẩn bị truy vấn SQL."));
        exit();
    }

    $stmt->bind_param("si", $comic_id, $chapter_number); // 's' cho comic_id (string), 'i' cho chapter_number (integer)
    $stmt->execute();
    $result = $stmt->get_result();
    $chapter_data = $result->fetch_assoc();
    $stmt->close();

    if ($chapter_data) {
        http_response_code(200);
        echo json_encode(array("success" => true, "chapter_id" => $chapter_data['CHAPTER_ID']), JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(404);
        echo json_encode(array("success" => false, "message" => "Không tìm thấy chapter_id cho comic_id và chapter_number đã cho."));
    }

} catch (Exception $e) {
    error_log("Lỗi xử lý getChapterIdByNumber: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(array("success" => false, "message" => "Lỗi server nội bộ."));
}

$conn->close();
?>