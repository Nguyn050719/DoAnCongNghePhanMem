<?php
include "connect.php";
$data = json_decode(file_get_contents("php://input"), true);
$follow_id = $data["follow_id"];

$stmt = $conn->prepare("DELETE FROM FOLLOW WHERE FOLLOW_ID = ?");
$stmt->bind_param("s", $follow_id);
if ($stmt->execute()) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false]);
}
