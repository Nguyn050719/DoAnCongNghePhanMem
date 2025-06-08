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
    // Chuẩn bị truy vấn SQL để lấy thông tin truyện và chapter
    // Đảm bảo tên bảng 'COMIC', 'CHAPTER', và các cột khớp chính xác với DB
$query = "SELECT CO.TITLE AS ComicTitle, CH.TITLE AS ChapterTitle, CH.CHAPTER_ID, CH.CHAPTER_NUMBER, CO.AUTHOR, CO.DESCRIPTION, CO.STATUS FROM COMIC CO JOIN CHAPTER CH ON CO.COMIC_ID = CH.COMIC_ID WHERE CO.COMIC_ID = ? AND CH.CHAPTER_NUMBER = ?";    $stmt = $conn->prepare($query);

    if ($stmt === false) {
        error_log("Lỗi chuẩn bị truy vấn SQL getComicChapterInfo: " . $conn->error);
        http_response_code(500);
        echo json_encode(array("success" => false, "message" => "Lỗi chuẩn bị truy vấn SQL."));
        exit();
    }

    $stmt->bind_param("si", $comic_id, $chapter_number); // 's' cho COMIC_ID, 'i' cho CHAPTER_NUMBER
    $stmt->execute();
    $result = $stmt->get_result();
    $info = $result->fetch_assoc();
    $stmt->close();

    if ($info) {
        // Lấy tổng số chapter
        $query_total_chapters = "SELECT MAX(CHAPTER_NUMBER) AS total_chapters FROM CHAPTER WHERE COMIC_ID = ?";
        $stmt_total_chapters = $conn->prepare($query_total_chapters);

        if ($stmt_total_chapters === false) {
            error_log("Lỗi chuẩn bị truy vấn SQL total chapters: " . $conn->error);
            http_response_code(500);
            echo json_encode(array("success" => false, "message" => "Lỗi chuẩn bị truy vấn SQL."));
            exit();
        }

        $stmt_total_chapters->bind_param("s", $comic_id);
        $stmt_total_chapters->execute();
        $result_total_chapters = $stmt_total_chapters->get_result();
        $total_chapters_data = $result_total_chapters->fetch_assoc();
        $stmt_total_chapters->close();

        $total_chapters = $total_chapters_data ? intval($total_chapters_data['total_chapters']) : 0;
        $info['total_chapters'] = $total_chapters; // Thêm total_chapters vào mảng $info

        http_response_code(200);
        echo json_encode(array("success" => true, "data" => $info), JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(404); // Not Found
        echo json_encode(array("success" => false, "message" => "Không tìm thấy thông tin truyện hoặc chapter."));
    }

} catch (Exception $e) {
    error_log("Lỗi cơ sở dữ liệu khi lấy thông tin truyện/chapter: " . $e->getMessage());
    http_response_code(500); // Internal Server Error
    echo json_encode(array("success" => false, "message" => "Đã xảy ra lỗi server khi lấy thông tin truyện."));
}
?>