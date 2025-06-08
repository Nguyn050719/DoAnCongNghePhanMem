<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Replace with your actual database connection details
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "webtruyen_1";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Connection failed: " . $conn->connect_error]);
    exit();
}

header('Content-Type: application/json; charset=utf-8');

// Get data from POST request
// Sử dụng isset và kiểm tra rỗng để an toàn hơn
$userId = isset($_POST['user_id']) ? $_POST['user_id'] : null;
$comicId = isset($_POST['comic_id']) ? $_POST['comic_id'] : null;
$chapterId = isset($_POST['chapter_id']) ? $_POST['chapter_id'] : null;

// Kiểm tra dữ liệu đầu vào
if ($userId === null || $comicId === null || $chapterId === null) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing user_id, comic_id, or chapter_id."]);
    exit();
}

// Prepare and execute SQL statement to insert or update history
// This assumes you have a table named 'reading_history' with columns:
// user_id, comic_id, chapter_id, read_at (timestamp)
// and a unique constraint on (user_id, comic_id) if you only want to store the latest chapter read for a comic
$sql = "INSERT INTO history (uses_id, comic_id, chapter_id, read_at)
        VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE chapter_id = VALUES(chapter_id), read_at = NOW()";

$stmt = $conn->prepare($sql);

// **** CHÚ Ý: Thay đổi "iii" thành loại dữ liệu tương ứng với cột của bạn ****
// Nếu USES_ID là INT, COMIC_ID là VARCHAR, CHAPTER_ID là VARCHAR thì dùng "iss"
// Nếu cả 3 đều là VARCHAR thì dùng "sss"
// Dựa trên các file PHP khác của bạn, có vẻ COMIC_ID và CHAPTER_ID là VARCHAR.
// Giả định USES_ID là INT, COMIC_ID là VARCHAR, CHAPTER_ID là VARCHAR
$stmt->bind_param("iss", $userId, $comicId, $chapterId); // "i" for integer, "s" for string

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Reading history saved successfully."]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Error saving reading history: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>