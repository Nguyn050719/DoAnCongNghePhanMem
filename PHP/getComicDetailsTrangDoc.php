<?php
// Bật báo cáo lỗi để dễ debug hơn
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Sử dụng file kết nối MySQLi
require_once 'connect.php'; // Đảm bảo đường dẫn đúng đến connect.php

// Thiết lập header để trả về JSON
header('Content-Type: application/json; charset=utf-8');

// Kiểm tra xem kết nối có thành công không
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(array("success" => false, "message" => "Không thể kết nối đến cơ sở dữ liệu. Vui lòng kiểm tra cấu hình."));
    exit();
}

// Lấy comic_id từ URL
// Trong TrangDocTruyen.html, comic_id có thể được truyền qua URL query parameter.
// Ví dụ: TrangDocTruyen.html?comic_id=COMIC001
$comic_id = isset($_GET['comic_id']) ? htmlspecialchars($_GET['comic_id']) : null;

// Kiểm tra nếu thiếu tham số comic_id
if ($comic_id === null || empty($comic_id)) {
    http_response_code(400); // Bad Request
    echo json_encode(array("success" => false, "message" => "Thiếu comic_id hoặc định dạng không hợp lệ."));
    exit();
}

try {
    // Chuẩn bị truy vấn SQL để lấy tiêu đề truyện từ bảng COMIC
    // Đảm bảo tên bảng 'COMIC' và cột 'COMIC_ID', 'TITLE' khớp chính xác với DB của bạn
    $query = "SELECT COMIC_ID, TITLE FROM COMIC WHERE COMIC_ID = ?";
    $stmt = $conn->prepare($query);

    if ($stmt === false) {
        error_log("Lỗi chuẩn bị truy vấn SQL getComicDetails: " . $conn->error);
        http_response_code(500);
        echo json_encode(array("success" => false, "message" => "Lỗi chuẩn bị truy vấn SQL."));
        exit();
    }

    $stmt->bind_param("s", $comic_id); // 's' vì COMIC_ID thường là chuỗi (VARCHAR)
    $stmt->execute();
    $result = $stmt->get_result();
    $comic_data = $result->fetch_assoc();
    $stmt->close();

    if ($comic_data) {
        // Trả về tiêu đề truyện
        http_response_code(200);
        echo json_encode(array(
            "success" => true,
            "title" => $comic_data['TITLE']
        ), JSON_UNESCAPED_UNICODE); // Đảm bảo hiển thị tiếng Việt đúng
    } else {
        // Không tìm thấy truyện với comic_id đã cho
        http_response_code(404); // Not Found
        echo json_encode(array("success" => false, "message" => "Không tìm thấy truyện với ID: " . $comic_id));
    }

} catch (Exception $e) {
    // Ghi log lỗi và trả về lỗi server
    error_log("Lỗi trong getComicDetails.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(array("success" => false, "message" => "Đã xảy ra lỗi server nội bộ."));
}

$conn->close();
?>