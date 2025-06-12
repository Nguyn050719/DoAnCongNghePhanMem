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

// Thiết lập header để trả về JSON
header('Content-Type: application/json; charset=utf-8');

// Lấy comic_id và chapter_id từ URL
$comic_id = isset($_GET['comic_id']) ? htmlspecialchars($_GET['comic_id']) : null;
$chapter_id = isset($_GET['chapter_id']) ? htmlspecialchars($_GET['chapter_id']) : null;

// Kiểm tra nếu thiếu tham số
if ($comic_id === null || empty($comic_id) || $chapter_id === null || empty($chapter_id)) {
    http_response_code(400); // Bad Request
    echo json_encode(array("success" => false, "message" => "Thiếu comic_id hoặc chapter_id hoặc định dạng không hợp lệ."));
    exit();
}

try {
    // 1. Lấy thông tin truyện và chapter hiện tại
    $query = "SELECT C.TITLE AS comic_title, CH.CHAPTER_NUMBER, CH.TITLE AS chapter_title, CH.CHAPTER_ID FROM COMIC C JOIN CHAPTER CH ON C.COMIC_ID = CH.COMIC_ID WHERE C.COMIC_ID = ? AND CH.CHAPTER_ID = ?";
    $stmt = $conn->prepare($query);

    if ($stmt === false) {
        error_log("Lỗi chuẩn bị truy vấn SQL lấy thông tin chapter hiện tại: " . $conn->error);
        http_response_code(500);
        echo json_encode(array("success" => false, "message" => "Lỗi chuẩn bị truy vấn SQL."));
        exit();
    }

    $stmt->bind_param("ss", $comic_id, $chapter_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $chapter_info = $result->fetch_assoc();
    $stmt->close();

    if ($chapter_info) {
        // 2. Lấy tổng số chapter
        $query_total_chapters = "SELECT COUNT(*) AS total_chapters FROM CHAPTER WHERE COMIC_ID = ?";
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
        $chapter_info['total_chapters'] = $total_chapters;

        // 3. Lấy danh sách tất cả CHAPTER_ID của truyện, sắp xếp theo CHAPTER_NUMBER
        $query_chapter_ids = "SELECT CHAPTER_ID FROM CHAPTER WHERE COMIC_ID = ? ORDER BY CHAPTER_NUMBER ASC";
        $stmt_chapter_ids = $conn->prepare($query_chapter_ids);

        if ($stmt_chapter_ids === false) {
            error_log("Lỗi chuẩn bị truy vấn SQL lấy danh sách chapter IDs: " . $conn->error);
            http_response_code(500);
            echo json_encode(array("success" => false, "message" => "Lỗi chuẩn bị truy vấn SQL."));
            exit();
        }

        $stmt_chapter_ids->bind_param("s", $comic_id);
        $stmt_chapter_ids->execute();
        $result_chapter_ids = $stmt_chapter_ids->get_result();
        $chapter_ids_list = [];
        while ($row = $result_chapter_ids->fetch_assoc()) {
            $chapter_ids_list[] = $row['CHAPTER_ID'];
        }
        $stmt_chapter_ids->close();

        $chapter_info['chapter_ids_sorted'] = $chapter_ids_list;

        http_response_code(200);
        echo json_encode(array("success" => true, "data" => $chapter_info), JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(404); // Not Found
        echo json_encode(array("success" => false, "message" => "Không tìm thấy thông tin truyện hoặc chapter với chapter_id đã cho."));
    }

} catch (Exception $e) {
    error_log("Lỗi xử lý getComicChapterInfo: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(array("success" => false, "message" => "Lỗi server nội bộ."));
}

$conn->close();
?>