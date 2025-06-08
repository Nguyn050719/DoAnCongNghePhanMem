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

// Lấy comic_id và chapter_number từ URL
$comic_id = isset($_GET['comic_id']) ? htmlspecialchars($_GET['comic_id']) : null;
$chapter_number = isset($_GET['chapter_number']) ? intval($_GET['chapter_number']) : null;

// Kiểm tra nếu thiếu tham số
if ($comic_id === null || empty($comic_id) || $chapter_number === null || $chapter_number <= 0) {
    http_response_code(400); // Bad Request
    echo json_encode(array("success" => false, "message" => "Thiếu comic_id hoặc chapter_number hoặc định dạng không hợp lệ."));
    exit();
}

try {
    // Đầu tiên, lấy CHAPTER_ID từ COMIC_ID và CHAPTER_NUMBER
    // Đảm bảo tên bảng 'CHAPTER' và các cột 'COMIC_ID', 'CHAPTER_NUMBER', 'CHAPTER_ID' khớp chính xác với DB
    $chapter_query = "SELECT CHAPTER_ID FROM CHAPTER WHERE COMIC_ID = ? AND CHAPTER_NUMBER = ?";
    $chapter_stmt = $conn->prepare($chapter_query);

    if ($chapter_stmt === false) {
        error_log("Lỗi chuẩn bị truy vấn SQL getChapterPages (get chapter ID): " . $conn->error);
        http_response_code(500);
        echo json_encode(array("success" => false, "message" => "Lỗi chuẩn bị truy vấn SQL."));
        exit();
    }

    $chapter_stmt->bind_param("si", $comic_id, $chapter_number); // 's' cho COMIC_ID, 'i' cho CHAPTER_NUMBER
    $chapter_stmt->execute();
    $chapter_result = $chapter_stmt->get_result();
    $chapter_data = $chapter_result->fetch_assoc();
    $chapter_stmt->close();

    if (!$chapter_data) {
        http_response_code(404);
        echo json_encode(array("success" => false, "message" => "Không tìm thấy chapter này cho comic đã cho."));
        exit();
    }

    $chapter_id = $chapter_data['CHAPTER_ID'];

    // Sau đó, lấy các trang (pages) dựa trên CHAPTER_ID
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

    // Thêm đường dẫn cơ sở cho URL ảnh
    // foreach ($pages as &$page) {
    //     $page['URL'] = '../Image/' . $page['URL'];
    // }

    if ($pages) {
        http_response_code(200);
        echo json_encode(array("success" => true, "data" => $pages), JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(404); // Not Found
        echo json_encode(array("success" => false, "message" => "Không tìm thấy trang nào cho chapter này."));
    }

} catch (Exception $e) {
    error_log("Lỗi cơ sở dữ liệu khi lấy trang chapter: " . $e->getMessage());
    http_response_code(500); // Internal Server Error
    echo json_encode(array("success" => false, "message" => "Đã xảy ra lỗi server khi lấy các trang chapter."));
}
?>