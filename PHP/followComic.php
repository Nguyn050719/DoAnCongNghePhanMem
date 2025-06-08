<?php
header('Content-Type: application/json');
include("connect.php");

$data = json_decode(file_get_contents("php://input"), true);
$usesId = $data["uses_id"];
$comicId = $data["comic_id"];

// Kiểm tra đã theo dõi chưa
$check = mysqli_query($conn, "SELECT * FROM FOLLOW WHERE USES_ID = '$usesId' AND COMIC_ID = '$comicId'");
if (mysqli_num_rows($check) > 0) {
  echo json_encode(["success" => false, "message" => "Bạn đã theo dõi truyện này rồi."]);
  exit;
}

// Tìm FOLLOW_ID lớn nhất
$sqlMax = "SELECT FOLLOW_ID FROM FOLLOW ORDER BY FOLLOW_ID DESC LIMIT 1";
$result = mysqli_query($conn, $sqlMax);

if (mysqli_num_rows($result) > 0) {
  $row = mysqli_fetch_assoc($result);
  $lastId = $row['FOLLOW_ID']; // VD: FL017
  $num = (int)substr($lastId, 2);
  $num++;
  $followId = 'FL' . str_pad($num, 3, '0', STR_PAD_LEFT); // FL018
} else {
  $followId = 'FL001'; // nếu chưa có dòng nào
}

// Thêm vào bảng FOLLOW
$sql = "INSERT INTO FOLLOW (FOLLOW_ID, USES_ID, COMIC_ID) VALUES ('$followId', '$usesId', '$comicId')";
if (mysqli_query($conn, $sql)) {
  echo json_encode(["success" => true]);
} else {
  echo json_encode(["success" => false, "message" => "Lỗi khi thêm theo dõi."]);
}
?>
