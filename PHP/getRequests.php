<?php
require 'connect.php';
header('Content-Type: application/json; charset=utf-8');

$sql = "SELECT r.REQUEST_ID, r.REASON, r.CREATE_AT, u.USERNAME
        FROM requests r
        JOIN users u ON r.USERS_ID = u.USES_ID
        WHERE r.STATUS = 'pending'
        ORDER BY r.CREATE_AT DESC";

$result = $conn->query($sql);
$requests = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $requests[] = [
            'username' => $row['USERNAME'],
            'reason'   => $row['REASON'],
            'date'     => $row['CREATE_AT']
        ];
    }
}

echo json_encode($requests);
$conn->close();
?>
