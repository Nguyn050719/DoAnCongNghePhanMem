<?php
// Bật báo cáo lỗi để dễ debug hơn trong quá trình phát triển
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Sử dụng file kết nối MySQLi
require_once 'connect.php'; // Đảm bảo đường dẫn đúng đến connect.php
session_start(); // Bắt đầu session để sử dụng $_SESSION['uses_id'] và $_SESSION['role']

// Kiểm tra xem kết nối có thành công không
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(array("message" => "Không thể kết nối đến cơ sở dữ liệu. Vui lòng kiểm tra cấu hình."));
    exit();
}

// Thiết lập header để trả về JSON
header('Content-Type: application/json; charset=utf-8');

// Lấy phương thức HTTP request
$request_method = $_SERVER["REQUEST_METHOD"];

switch ($request_method) {
    case 'GET':
        $comic_id = isset($_GET['comicId']) ? htmlspecialchars($_GET['comicId']) : null;
        $sort_order = isset($_GET['sort']) ? htmlspecialchars($_GET['sort']) : 'newest';
        
        // KHÔNG lấy chapter_id từ URL nữa
        getComments($conn, $comic_id, $sort_order); 
        break;

    case 'POST':
        $data = json_decode(file_get_contents("php://input"));
        $comic_id = isset($data->comic_id) ? htmlspecialchars($data->comic_id) : null;
        // BỎ chapter_id khỏi dữ liệu nhận được
        $content = isset($data->content) ? htmlspecialchars($data->content) : null;
        addComment($conn, $comic_id, $content); // KHÔNG truyền chapter_id vào đây nữa
        break;

    case 'DELETE':
    $data = json_decode(file_get_contents("php://input"));

    // Đảm bảo comment_id được lấy
    $comment_id = isset($data->comment_id) ? htmlspecialchars($data->comment_id) : null;

    // KHAI BÁO VÀ GÁN GIÁ TRỊ CHO CÁC BIẾN NÀY TRƯỚC KHI SỬ DỤNG
    $current_user_id_from_client = isset($data->uses_id) ? htmlspecialchars($data->uses_id) : null;
    $current_user_role_from_client = isset($data->role) ? htmlspecialchars($data->role) : null;

    // Bây giờ thì gọi hàm deleteComment với đủ 4 đối số
    deleteComment($conn, $comment_id, $current_user_id_from_client, $current_user_role_from_client);
    break;        break;

    default:
        http_response_code(405); // Method Not Allowed
        echo json_encode(array("message" => "Phương thức không được hỗ trợ."));
        break;
}

// Hàm getComments - CHỈ LỌC THEO COMIC_ID
function getComments($conn, $comic_id, $sort_order) { 
    if ($comic_id === null || empty($comic_id)) { 
        http_response_code(400);
        echo json_encode(array("success" => false, "message" => "Thiếu comicId."));
        return;
    }

    $order_by_clause = "";
    if ($sort_order === 'oldest') {
        $order_by_clause = " ORDER BY COMMENT.CREATE_AT ASC";
    } else { // 'newest' hoặc các giá trị khác
        $order_by_clause = " ORDER BY COMMENT.CREATE_AT DESC";
    }

    try {
        // TRUY VẤN SQL CHỈ LỌC THEO COMIC_ID
        $query = "SELECT COMMENT.COMMENT_ID, COMMENT.USES_ID, USERS.USERNAME AS Author,
                                    CASE WHEN USERS.AVATAR IS NOT NULL AND USERS.AVATAR != '' THEN CONCAT('../Image/', USERS.AVATAR) ELSE '../Image/avatar.jpg' END AS AuthorAvatar,
                                    COMMENT.CONTENT, COMMENT.CREATE_AT
                             FROM COMMENT
                             JOIN USERS ON COMMENT.USES_ID = USERS.USES_ID
                             WHERE COMMENT.COMIC_ID = ? " . $order_by_clause; 
        $stmt = $conn->prepare($query);

        if ($stmt === false) {
            error_log("Lỗi chuẩn bị truy vấn SQL getComments: " . $conn->error);
            http_response_code(500);
            echo json_encode(array("success" => false, "message" => "Lỗi chuẩn bị truy vấn SQL."));
            return;
        }

        $stmt->bind_param("s", $comic_id); // CHỈ BIND MỘT THAM SỐ 's' (cho comic_id)
        $stmt->execute();
        $result = $stmt->get_result();
        $comments = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        echo json_encode(array("success" => true, "comments" => $comments), JSON_UNESCAPED_UNICODE);

    } catch (Exception $e) {
        error_log("Lỗi xử lý getComments: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(array("success" => false, "message" => "Lỗi server nội bộ."));
    }
}

// Hàm addComment - KHÔNG NHẬN chapter_id
function addComment($conn, $comic_id, $content) { 
    // Kiểm tra thông tin người dùng từ session
    $uses_id = isset($_SESSION['uses_id']) ? $_SESSION['uses_id'] : null;

    if ($uses_id === null) {
        http_response_code(401); // Unauthorized
        echo json_encode(array("success" => false, "message" => "Bạn cần đăng nhập để bình luận."));
        return;
    }

    // Chỉ kiểm tra comic_id và content
    if ($comic_id === null || empty($comic_id) || $content === null || empty($content)) {
        http_response_code(400); // Bad Request
        echo json_encode(array("success" => false, "message" => "Thiếu comic_id hoặc nội dung bình luận."));
        return;
    }

    try {
        // TRUY VẤN SQL INSERT ĐÃ LOẠI BỎ 'CHAPTER_ID'
        $query = "INSERT INTO COMMENT (COMIC_ID, USES_ID, CONTENT, CREATE_AT) VALUES (?, ?, ?, NOW())"; 
        $stmt = $conn->prepare($query);

        if ($stmt === false) {
            error_log("Lỗi chuẩn bị truy vấn SQL addComment: " . $conn->error);
            http_response_code(500);
            echo json_encode(array("success" => false, "message" => "Lỗi chuẩn bị truy vấn SQL."));
            return;
        }

        // bind_param ĐÃ THAY ĐỔI ĐỂ PHÙ HỢP: CHỈ CÓ comic_id, uses_id, content
        $stmt->bind_param("sss", $comic_id, $uses_id, $content); 
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            http_response_code(201); // Created
            echo json_encode(array("success" => true, "message" => "Bình luận của bạn đã được thêm."));
        } else {
            http_response_code(500);
            echo json_encode(array("success" => false, "message" => "Không thể thêm bình luận."));
        }
        $stmt->close();

    } catch (Exception $e) {
        error_log("Lỗi xử lý addComment: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(array("success" => false, "message" => "Lỗi server nội bộ."));
    }
}

function deleteComment($conn, $comment_id, $current_user_id, $current_user_role) { // Thêm 2 tham số mới
    // KHÔNG LẤY TỪ $_SESSION NỮA
    // $current_user_id = isset($_SESSION['uses_id']) ? $_SESSION['uses_id'] : null;
    // $current_user_role = isset($_SESSION['role']) ? $_SESSION['role'] : null;

    if ($current_user_id === null) {
        http_response_code(401); // Unauthorized
        echo json_encode(["message" => "Bạn cần đăng nhập để xóa bình luận."]);
        return;
    }

    if ($comment_id === null || empty($comment_id)) {
        http_response_code(400);
        echo json_encode(["message" => "Thiếu ID bình luận."]);
        return;
    }

    // Lấy thông tin bình luận để kiểm tra quyền
    $query_check = "SELECT USES_ID FROM COMMENT WHERE COMMENT_ID = ?";
    $stmt_check = $conn->prepare($query_check);
    if ($stmt_check === false) {
        error_log("Lỗi chuẩn bị truy vấn SQL kiểm tra quyền: " . $conn->error);
        http_response_code(500);
        echo json_encode(array("message" => "Lỗi chuẩn bị truy vấn SQL kiểm tra quyền."));
        return;
    }
    $stmt_check->bind_param("s", $comment_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $comment = $result_check->fetch_assoc();
    $stmt_check->close();

    if (!$comment) {
        http_response_code(404);
        echo json_encode(["message" => "Bình luận không tồn tại."]);
        return;
    }

    // Kiểm tra quyền:
    // 1. Nếu người dùng hiện tại có vai trò 'admin' HOẶC
    // 2. Nếu ID người dùng hiện tại khớp với USES_ID của bình luận
    if ($current_user_role === 'admin' || $current_user_id === $comment['USES_ID']) {
        $query = "DELETE FROM COMMENT WHERE COMMENT_ID = ?";
        $stmt = $conn->prepare($query);

        if ($stmt === false) {
            error_log("Lỗi chuẩn bị truy vấn SQL deleteComment (delete): " . $conn->error);
            http_response_code(500);
            echo json_encode(array("message" => "Lỗi chuẩn bị truy vấn SQL."));
            return;
        }
        $stmt->bind_param("s", $comment_id);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                http_response_code(200);
                echo json_encode(["message" => "Bình luận đã được xóa thành công."]);
            } else {
                http_response_code(404);
                echo json_encode(["message" => "Không tìm thấy bình luận để xóa."]);
            }
        } else {
            error_log("Lỗi thực thi truy vấn SQL deleteComment: " . $stmt->error);
            http_response_code(500);
            echo json_encode(["message" => "Lỗi khi xóa bình luận."]);
        }
        $stmt->close();
    } else {
        http_response_code(403); // Forbidden
        echo json_encode(["message" => "Bạn không có quyền xóa bình luận này."]);
    }
}
$conn->close();
?>