<?php
// Bật báo cáo lỗi để dễ debug hơn
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Sử dụng file kết nối MySQLi
require_once 'connect.php'; // Đảm bảo đường dẫn đúng

// Kiểm tra xem kết nối có thành công không
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(array("success" => false, "message" => "Không thể kết nối đến cơ sở dữ liệu. Vui lòng kiểm tra cấu hình."));
    exit();
}

// Lấy chapter_id từ URL
$chapter_id = isset($_GET['chapter_id']) ? htmlspecialchars($_GET['chapter_id']) : null; // LẤY chapter_id

// Kiểm tra nếu thiếu tham số
if ($chapter_id === null || empty($chapter_id)) { // CHỈ KIỂM TRA chapter_id
    http_response_code(400); // Bad Request
    echo json_encode(array("success" => false, "message" => "Thiếu chapter_id hoặc định dạng không hợp lệ."));
    exit();
}

try {
    // Truy vấn SQL để lấy URL các trang của chapter
    // Đảm bảo tên bảng 'PAGE' và các cột 'CHAPTER_ID', 'PAGE_NUMBER', 'URL' khớp chính xác với DB
    $pages_query = "SELECT PAGE_NUMBER, URL FROM PAGE WHERE CHAPTER_ID = ? ORDER BY PAGE_NUMBER ASC";
    $pages_stmt = $conn->prepare($pages_query);

    if ($pages_stmt === false) {
        error_log("Lỗi chuẩn bị truy vấn SQL getChapterPages (get pages): " . $conn->error);
        http_response_code(500);
        echo json_encode(array("success" => false, "message" => "Lỗi chuẩn bị truy vấn SQL."));
        exit();
    }

    $pages_stmt->bind_param("s", $chapter_id); // 's' vì CHAPTER_ID có thể là chuỗi
    $pages_stmt->execute();
    $pages_result = $pages_stmt->get_result();
    $pages = $pages_result->fetch_all(MYSQLI_ASSOC);
    $pages_stmt->close();

    if ($pages) {
        http_response_code(200);
        echo json_encode(array("success" => true, "data" => $pages), JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(404); // Not Found
        echo json_encode(array("success" => false, "message" => "Không tìm thấy trang nào cho chapter_id này."));
    }

} catch (Exception $e) {
    error_log("Lỗi xử lý getChapterPages: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(array("success" => false, "message" => "Lỗi server nội bộ."));
}

$conn->close();
?>