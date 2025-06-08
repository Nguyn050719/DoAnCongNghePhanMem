<?php
header('Content-Type: application/json');
require_once 'connect.php';

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Thiếu id']);
    exit;
}

$comicId = $_GET['id'];
$response = ['success' => false];

// Truy vấn thông tin truyện
$sqlComic = "
    SELECT 
        
        C.COMIC_ID,
        C.TITLE AS Title,
        C.DESCRIPTION AS Description,
        C.STATUS AS Status,
        C.COVER_IMAGE AS Image,
        C.VIEW AS Views,
        IFNULL(GROUP_CONCAT(GENRE.NAME SEPARATOR ', '), '') AS Genre,
        (SELECT COUNT(*) FROM FOLLOW WHERE COMIC_ID = ?) AS Follow
    FROM COMIC C
    LEFT JOIN COMIC_GENRE ON C.COMIC_ID = COMIC_GENRE.COM_COMIC_ID
    LEFT JOIN GENRE ON COMIC_GENRE.GEN_GENRE_ID = GENRE.GENRE_ID
    WHERE C.COMIC_ID = ?
    GROUP BY C.COMIC_ID
";

$stmt = $conn->prepare($sqlComic);
$stmt->bind_param("ss", $comicId, $comicId);
$stmt->execute();
$result = $stmt->get_result();

if ($comic = $result->fetch_assoc()) {
    $response['comic'] = $comic;

    // Truy vấn danh sách chương
    $sqlChapter = "
        SELECT 
            CHAPTER_ID,  
            TITLE AS ChapterTitle, 
            CREATE_AT AS UpdatedAt, 
            0 AS Views
        FROM CHAPTER 
        WHERE COMIC_ID = ? 
        ORDER BY CREATE_AT DESC
    ";

    $stmt2 = $conn->prepare($sqlChapter);
    $stmt2->bind_param("s", $comicId);
    $stmt2->execute();
    $result2 = $stmt2->get_result();

    $chapters = [];
    while ($row = $result2->fetch_assoc()) {
        $chapters[] = $row;
    }

    $response['chapters'] = $chapters;
    $response['success'] = true;
}

echo json_encode($response);
?>
