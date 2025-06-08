<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'connect.php'; // Đảm bảo đường dẫn đúng

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(array("status" => "error", "message" => "Không thể kết nối đến cơ sở dữ liệu."));
    exit();
}

header('Content-Type: application/json; charset=utf-8');

// Lấy user_id từ tham số GET
$userId = isset($_GET['user_id']) ? $_GET['user_id'] : null;

if ($userId === null || empty($userId)) {
    http_response_code(400);
    echo json_encode(array("status" => "error", "message" => "Thiếu user_id."));
    exit();
}

try {
    // Truy vấn để lấy lịch sử đọc của người dùng
    // JOIN với bảng COMIC để lấy TITLE và COVER_IMAGE
    // JOIN với bảng CHAPTER để lấy CHAPTER_NUMBER và CHAPTER_TITLE
$query = "SELECT
                h.uses_id,
                h.comic_id,
                h.chapter_id,
                h.read_at,
                c.TITLE AS COMIC_TITLE,
                c.COVER_IMAGE,
                ch.CHAPTER_NUMBER,
                ch.TITLE AS CHAPTER_TITLE
              FROM
                history h
              JOIN
                COMIC c ON h.comic_id = c.COMIC_ID
              JOIN
                CHAPTER ch ON h.chapter_id = ch.CHAPTER_ID
              WHERE
                h.uses_id = ?
              ORDER BY
                h.read_at DESC"; // Sắp xếp theo thời gian đọc gần nhất
    $stmt = $conn->prepare($query);

    if ($stmt === false) {
        error_log("Lỗi chuẩn bị truy vấn SQL getHistory: " . $conn->error);
        http_response_code(500);
        echo json_encode(array("status" => "error", "message" => "Lỗi chuẩn bị truy vấn SQL."));
        exit();
    }

    // Bind tham số user_id (giả định là số nguyên)
    // Nếu USES_ID của bạn là chuỗi, hãy thay đổi "i" thành "s"
    $stmt->bind_param("i", $userId); // "i" for integer

    $stmt->execute();
    $result = $stmt->get_result();
    $history = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $conn->close();

    if ($history) {
        echo json_encode($history, JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([]); // Trả về mảng rỗng nếu không có lịch sử
    }

} catch (Exception $e) {
    error_log("Lỗi khi lấy lịch sử đọc: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(array("status" => "error", "message" => "Đã xảy ra lỗi khi lấy lịch sử đọc."));
}
?>