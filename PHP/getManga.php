<?php
include("connect.php");

header("Content-Type: application/json; charset=UTF-8");

$sql = "
  SELECT 
    c.*,
    COUNT(DISTINCT f.FOLLOW_ID) AS FOLLOW_COUNT,
    (SELECT COUNT(*) FROM CHAPTER WHERE CHAPTER.COMIC_ID = c.COMIC_ID) AS CHAPTER_COUNT,
    (SELECT MAX(CREATE_AT) FROM CHAPTER WHERE CHAPTER.COMIC_ID = c.COMIC_ID) AS LAST_UPDATE,
    GROUP_CONCAT(DISTINCT g.NAME SEPARATOR ', ') AS GENRE
  FROM COMIC c
  LEFT JOIN FOLLOW f ON c.COMIC_ID = f.COMIC_ID
  LEFT JOIN COMIC_GENRE cg ON c.COMIC_ID = cg.COM_COMIC_ID
  LEFT JOIN GENRE g ON cg.GEN_GENRE_ID = g.GENRE_ID
  GROUP BY c.COMIC_ID
  ORDER BY c.COMIC_ID DESC
";

$result = mysqli_query($conn, $sql);

if (!$result) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Query error", "error" => mysqli_error($conn)]);
    exit();
}

$comics = [];

while ($row = mysqli_fetch_assoc($result)) {
    if (empty($row['COMIC_ID'])) continue;

    $coverImage = $row['COVER_IMAGE'] ?? '';
    $coverImage = str_replace(['\\', '../', './'], ['/', '', ''], $coverImage);
    $coverImageUrl = ltrim($coverImage, '/');

    $comics[] = [
        'COMIC_ID' => $row['COMIC_ID'],
        'TITLE' => $row['TITLE'] ?? 'Không có tiêu đề',
        'DESCRIPTION' => $row['DESCRIPTION'] ?? '',
        'AUTHOR' => $row['AUTHOR'] ?? '',
        'STATUS' => $row['STATUS'] ?? '',
        'COVER_IMAGE' => $coverImageUrl,
        'VIEW' => isset($row['VIEW']) ? (int)$row['VIEW'] : 0,
        'CHAPTER_COUNT' => isset($row['CHAPTER_COUNT']) ? (int)$row['CHAPTER_COUNT'] : 0,
        'FOLLOW_COUNT' => isset($row['FOLLOW_COUNT']) ? (int)$row['FOLLOW_COUNT'] : 0,
        'LAST_UPDATE' => $row['LAST_UPDATE'] ?? null,
        'GENRE' => $row['GENRE'] ?? ''
    ];
}

echo json_encode($comics, JSON_UNESCAPED_UNICODE);
?>
