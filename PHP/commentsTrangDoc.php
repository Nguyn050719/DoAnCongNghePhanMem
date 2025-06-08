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

// Lấy phương thức HTTP request
$request_method = $_SERVER["REQUEST_METHOD"];

switch ($request_method) {
    case 'GET':
        $comic_id = isset($_GET['comicId']) ? htmlspecialchars($_GET['comicId']) : null;
        $sort_order = isset($_GET['sort']) ? htmlspecialchars($_GET['sort']) : 'newest';
        getComments($conn, $comic_id, $sort_order);
        break;

    case 'POST':
        createComment($conn);
        break;

    case 'DELETE':
        deleteComment($conn);
        break;

    default:
        http_response_code(405);
        echo json_encode(array("message" => "Phương thức không được phép."));
        exit();
}

function getComments($conn, $comic_id, $sort_order) {
    header('Content-Type: application/json; charset=utf-8');

    if ($comic_id === null || empty($comic_id)) {
        http_response_code(400);
        echo json_encode(["message" => "Thiếu Comic ID."]);
        return;
    }

    $order_by = '';
    switch ($sort_order) {
        case 'oldest':
            $order_by = 'ORDER BY C.CREATE_AT ASC';
            break;
        case 'featured':
            $order_by = 'ORDER BY C.FEATURED DESC, C.CREATE_AT DESC';
            break;
        case 'newest':
        default:
            $order_by = 'ORDER BY C.CREATE_AT DESC';
            break;
    }

    // Lấy thêm USES_ID của tác giả comment để client có thể kiểm tra quyền xóa
    $query = "SELECT C.COMMENT_ID, C.CONTENT, C.CREATE_AT, C.USES_ID, U.USERNAME AS Author, U.AVATAR AS AuthorAvatar FROM COMMENT C JOIN USERS U ON C.USES_ID = U.USES_ID WHERE C.COMIC_ID = ? " . $order_by;
    $stmt = $conn->prepare($query);

    if ($stmt === false) {
        error_log("Lỗi chuẩn bị truy vấn SQL getComments: " . $conn->error);
        http_response_code(500);
        echo json_encode(array("message" => "Lỗi chuẩn bị truy vấn SQL."));
        return;
    }

    $stmt->bind_param("s", $comic_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $comments = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Thêm đường dẫn đầy đủ cho avatar
// Thêm đường dẫn đầy đủ cho avatar - SỬA PHẦN NÀY
    foreach ($comments as &$comment) {
        if (!empty($comment['AuthorAvatar'])) {
            // Kiểm tra xem đường dẫn avatar đã là URL tuyệt đối (http/https) chưa
            if (strpos($comment['AuthorAvatar'], 'http://') === 0 || strpos($comment['AuthorAvatar'], 'https://') === 0) {
                // Nếu đã là URL tuyệt đối, giữ nguyên
                // Không làm gì, đường dẫn đã đúng
            } else {
                // Nếu không phải URL tuyệt đối, thêm tiền tố ../Image/
                $comment['AuthorAvatar'] = '../Image/' . $comment['AuthorAvatar'];
            }
        } else {
            $comment['AuthorAvatar'] = '../Image/avatar.jpg'; // Avatar mặc định
        }
    }

    http_response_code(200);
    echo json_encode($comments, JSON_UNESCAPED_UNICODE);}

function createComment($conn) {
    header('Content-Type: application/json; charset=utf-8');

    // Kiểm tra session user_id thay vì JWT
    if (!isset($_SESSION['uses_id'])) {
        http_response_code(401); // Unauthorized
        echo json_encode(["message" => "Bạn cần đăng nhập để bình luận."]);
        return;
    }

    $user_id = $_SESSION['uses_id']; // Lấy user_id từ session
    $data = json_decode(file_get_contents("php://input"), true);

    $content = isset($data['content']) ? trim($data['content']) : '';
    $comic_id = isset($data['comic_id']) ? htmlspecialchars($data['comic_id']) : null;

    if (empty($content) || $comic_id === null || empty($comic_id)) {
        http_response_code(400); // Bad Request
        echo json_encode(["message" => "Nội dung bình luận hoặc Comic ID không được để trống."]);
        return;
    }

    // Sinh COMMENT_ID mới
    $getMaxID = $conn->query("SELECT MAX(CAST(SUBSTRING(COMMENT_ID,3) AS UNSIGNED)) AS max_id FROM COMMENT");
    if ($getMaxID === false) {
        error_log("Lỗi truy vấn lấy max COMMENT_ID: " . $conn->error);
        http_response_code(500);
        echo json_encode(["message" => "Lỗi hệ thống khi tạo ID bình luận."]);
        return;
    }
    $row = $getMaxID->fetch_assoc();
    $nextID = $row['max_id'] ? $row['max_id'] + 1 : 1;
    $comment_id = "CM" . str_pad($nextID, 3, '0', STR_PAD_LEFT);

    $create_at = date('Y-m-d H:i:s');

    $query = "INSERT INTO COMMENT (COMMENT_ID, COMIC_ID, USES_ID, CONTENT, CREATE_AT) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);

    if ($stmt === false) {
        error_log("Lỗi chuẩn bị truy vấn SQL createComment: " . $conn->error);
        http_response_code(500);
        echo json_encode(array("message" => "Lỗi chuẩn bị truy vấn SQL."));
        return;
    }

    $stmt->bind_param("sssss", $comment_id, $comic_id, $user_id, $content, $create_at);

    if ($stmt->execute()) {
        http_response_code(201); // Created
        echo json_encode(["message" => "Bình luận đã được thêm thành công."]);
    } else {
        error_log("Lỗi khi thêm bình luận vào DB: " . $stmt->error);
        http_response_code(500);
        echo json_encode(["message" => "Không thể thêm bình luận."]);
    }
    $stmt->close();
}

function deleteComment($conn) {
    header('Content-Type: application/json; charset=utf-8');

    // Kiểm tra session user_id thay vì JWT
    if (!isset($_SESSION['uses_id'])) {
        http_response_code(401); // Unauthorized
        echo json_encode(["message" => "Bạn cần đăng nhập để xóa bình luận."]);
        return;
    }

    $current_user_id = $_SESSION['uses_id']; // Lấy user_id từ session
    $current_user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'user'; // Lấy role từ session

    $data = json_decode(file_get_contents("php://input"), true);
    $comment_id = isset($data['comment_id']) ? htmlspecialchars($data['comment_id']) : null;

    if ($comment_id === null || empty($comment_id)) {
        http_response_code(400); // Bad Request
        echo json_encode(["message" => "Thiếu ID bình luận."]);
        return;
    }

    // Lấy thông tin bình luận để kiểm tra quyền
    $query_check = "SELECT USES_ID FROM COMMENT WHERE COMMENT_ID = ?";
    $stmt_check = $conn->prepare($query_check);
    if ($stmt_check === false) {
        error_log("Lỗi chuẩn bị truy vấn SQL check comment owner: " . $conn->error);
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

    // Kiểm tra quyền: admin hoặc chủ sở hữu bình luận
    if ($current_user_role !== 'admin' && $current_user_id !== $comment['USES_ID']) {
        http_response_code(403);
        echo json_encode(["message" => "Bạn không có quyền xóa bình luận này."]);
        return;
    }

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
        http_response_code(200);
        echo json_encode(["message" => "Bình luận đã được xóa thành công."]);
    } else {
        error_log("Lỗi khi xóa bình luận từ DB: " . $stmt->error);
        http_response_code(500);
        echo json_encode(["message" => "Không thể xóa bình luận."]);
    }
    $stmt->close();
}
?>