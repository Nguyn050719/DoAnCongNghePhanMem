<?php
require 'connect.php';
header('Content-Type: application/json; charset=utf-8');

// Đọc dữ liệu JSON từ client
$data = json_decode(file_get_contents("php://input"), true);

// Lấy dữ liệu từ JSON
$userID = trim($data['userID'] ?? '');
$reason = trim($data['reason'] ?? '');

// Kiểm tra dữ liệu đầu vào
if (!$userID || !$reason) {
  echo json_encode([
    'success' => false,
    'message' => 'Thiếu thông tin người dùng hoặc lý do.'
  ]);
  exit;
}

// === Tạo REQUEST_ID mới dạng RT001, RT002, ...
$result = $conn->query("SELECT REQUEST_ID FROM REQUESTS ORDER BY REQUEST_ID DESC LIMIT 1");
if ($result && $row = $result->fetch_assoc()) {
  $lastID = (int)substr($row['REQUEST_ID'], 2); // Bỏ "RT", lấy số
  $newID = "RT" . str_pad($lastID + 1, 3, "0", STR_PAD_LEFT);
} else {
  $newID = "RT001"; // Nếu bảng đang rỗng
}

// === Thêm yêu cầu mới vào bảng REQUESTS ===
// Lưu ý: Sử dụng đúng tên cột là USERS_ID và CREATE_AT theo cấu trúc bảng
$stmt = $conn->prepare("INSERT INTO REQUESTS (REQUEST_ID, USERS_ID, REASON, CREATE_AT, STATUS) VALUES (?, ?, ?, NOW(), 'pending')");
if (!$stmt) {
  echo json_encode([
    'success' => false,
    'message' => 'Lỗi chuẩn bị truy vấn.'
  ]);
  exit;
}

// Gán tham số và thực thi
$stmt->bind_param("sss", $newID, $userID, $reason);
$success = $stmt->execute();

// Phản hồi kết quả
if ($success) {
  echo json_encode([
    'success' => true,
    'message' => 'Gửi yêu cầu thành công! Hệ thống sẽ xử lý sớm.'
  ]);
} else {
  echo json_encode([
    'success' => false,
    'message' => 'Không thể gửi yêu cầu. Vui lòng thử lại.'
  ]);
}

$stmt->close();
$conn->close();
