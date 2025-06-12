<?php
$conn = new mysqli("localhost", "root", "", "webtruyen");
$conn->set_charset("utf8");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['username'], $data['status'], $data['updateRole'])) {
        echo json_encode(["message" => "Thiếu dữ liệu cần thiết"]);
        exit;
    }

    $username = $conn->real_escape_string($data['username']);
    $status = $conn->real_escape_string($data['status']);
    $updateRole = $data['updateRole'];

    // Lấy USES_ID từ USERS
    $sqlGetUserId = "SELECT USES_ID FROM USERS WHERE USERNAME = '$username'";
    $result = $conn->query($sqlGetUserId);

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $userId = $row['USES_ID'];

        // Cập nhật trạng thái yêu cầu
        $sqlUpdateRequest = "UPDATE REQUESTS SET STATUS = '$status' WHERE USERS_ID = '$userId'";
        if ($conn->query($sqlUpdateRequest)) {
            // Nếu được duyệt thì cập nhật vai trò
            if ($updateRole) {
                $sqlUpdateRole = "UPDATE USERS SET ROLE = 'author' WHERE USES_ID = '$userId'";
                $conn->query($sqlUpdateRole);
            }

            echo json_encode(["message" => "Đã cập nhật trạng thái cho người dùng: $username"]);
        } else {
            echo json_encode(["message" => "Lỗi cập nhật REQUESTS: " . $conn->error]);
        }
    } else {
        echo json_encode(["message" => "Không tìm thấy người dùng $username"]);
    }
} else {
    echo json_encode(["message" => "Phương thức không hợp lệ"]);
}

$conn->close();
?>
