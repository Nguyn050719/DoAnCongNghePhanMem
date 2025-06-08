<?php

// Kết nối CSDL
require_once "connect.php";


// Lấy dữ liệu từ form
$title = trim($_POST['title']);
$description = trim($_POST['description']);
$author = trim($_POST['author']);
$status = 'Tiếp tục'; // Cố định
$genres = isset($_POST['genre']) ? $_POST['genre'] : '';
$uses_id = trim($_POST['uses_id'] ?? ''); // Thêm dòng này để lấy USES_ID

if (!$uses_id) {
    echo json_encode(['success' => false, 'message' => 'Thiếu user ID.']);
    exit;
}

// Kiểm tra tên truyện đã tồn tại chưa
$stmtCheckTitle = $conn->prepare("SELECT COMIC_ID FROM COMIC WHERE LOWER(TITLE) = LOWER(?)");
$stmtCheckTitle->bind_param("s", $title);
$stmtCheckTitle->execute();
$stmtCheckTitle->store_result();
if ($stmtCheckTitle->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Tên truyện đã tồn tại!']);
    exit;
}
$stmtCheckTitle->close();

// Tạo COMIC_ID tự tăng (CM001, CM002...)
$result = $conn->query("SELECT COMIC_ID FROM COMIC ORDER BY COMIC_ID DESC LIMIT 1");
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $last_id = intval(substr($row['COMIC_ID'], 2));
    $comic_id = 'CM' . str_pad($last_id + 1, 3, '0', STR_PAD_LEFT);
} else {
    $comic_id = 'CM001';
}

// Xử lý ảnh bìa
$folder = "../Image/bia/";
if (!file_exists($folder)) {
    mkdir($folder, 0777, true);
}

$cover_path = "";
if (isset($_FILES['cover']) && $_FILES['cover']['error'] == 0) {
    $file_name = uniqid() . "_" . basename($_FILES["cover"]["name"]);
    $target_file = $folder . $file_name;

    if (move_uploaded_file($_FILES["cover"]["tmp_name"], $target_file)) {
        $cover_path = '../Image/bia/' . $file_name;
    } else {
        echo json_encode(['success' => false, 'message' => 'Lỗi tải ảnh bìa.']);
        exit;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Chưa chọn ảnh bìa.']);
    exit;
}

// Lưu vào bảng COMIC (thêm USES_ID)
$stmt = $conn->prepare("INSERT INTO COMIC (COMIC_ID, TITLE, DESCRIPTION, AUTHOR, STATUS, COVER_IMAGE, USES_ID) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("sssssss", $comic_id, $title, $description, $author, $status, $cover_path, $uses_id);

if ($stmt->execute()) {
    // Xử lý thể loại
    $genreList = array_map('trim', explode(',', $genres));
    foreach ($genreList as $genreName) {
        if ($genreName == '') continue;

        $genreName = ucwords(mb_strtolower($genreName, 'UTF-8'));

        // Kiểm tra thể loại đã tồn tại chưa
        $stmtCheck = $conn->prepare("SELECT GENRE_ID FROM GENRE WHERE LOWER(NAME) = LOWER(?)");
        $stmtCheck->bind_param("s", $genreName);
        $stmtCheck->execute();
        $resultCheck = $stmtCheck->get_result();

        if ($resultCheck->num_rows > 0) {
            $genreRow = $resultCheck->fetch_assoc();
            $genre_id = $genreRow['GENRE_ID'];
        } else {
            // Tạo GENRE_ID mới
            $resultLast = $conn->query("SELECT GENRE_ID FROM GENRE ORDER BY GENRE_ID DESC LIMIT 1");
            if ($resultLast->num_rows > 0) {
                $rowLast = $resultLast->fetch_assoc();
                $lastGenreNum = intval(substr($rowLast['GENRE_ID'], 2));
                $genre_id = 'GE' . str_pad($lastGenreNum + 1, 3, '0', STR_PAD_LEFT);
            } else {
                $genre_id = 'GE001';
            }

            $stmtInsert = $conn->prepare("INSERT INTO GENRE (GENRE_ID, NAME) VALUES (?, ?)");
            $stmtInsert->bind_param("ss", $genre_id, $genreName);
            $stmtInsert->execute();
            $stmtInsert->close();
        }
        $stmtCheck->close();

        // Gán vào bảng COMIC_GENRE
        $stmtCG = $conn->prepare("INSERT INTO COMIC_GENRE (COM_COMIC_ID, GEN_GENRE_ID) VALUES (?, ?)");
        $stmtCG->bind_param("ss", $comic_id, $genre_id);
        $stmtCG->execute();
        $stmtCG->close();
    }

    echo json_encode(['success' => true, 'message' => 'Đăng truyện thành công!', 'comic_id' => $comic_id]);
} else {
    echo json_encode(['success' => false, 'message' => 'Lỗi lưu truyện.']);
}

$stmt->close();
$conn->close();
?>
