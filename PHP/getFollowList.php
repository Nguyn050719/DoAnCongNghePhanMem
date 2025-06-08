<?php
include "connect.php";
$uses_id = $_GET["uses_id"];

$sql = "SELECT F.FOLLOW_ID, C.COMIC_ID, C.TITLE, C.AUTHOR, C.STATUS, C.COVER_IMAGE
        FROM FOLLOW F
        JOIN COMIC C ON F.COMIC_ID = C.COMIC_ID
        WHERE F.USES_ID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $uses_id);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}
echo json_encode($data);
