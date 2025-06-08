<?php
require_once 'connect.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['uses_id'])) {
    echo json_encode(['success' => false, 'message' => 'Bạn chưa đăng nhập!']);
    exit();
}

$uses_id = $_SESSION['uses_id'];
$displayName = trim($_POST['displayName']);
$bio = trim($_POST['bio']);
$avatarPath = null;
$coverPath = null;

$allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

// Lấy thông tin avatar và cover cũ từ database
$queryOld = $conn->prepare("SELECT AVATAR, COVER_IMAGE FROM USERS WHERE USES_ID = ?");
$queryOld->bind_param("s", $uses_id);
$queryOld->execute();
$resultOld = $queryOld->get_result();
$oldData = $resultOld->fetch_assoc();
$queryOld->close();

// Xử lý upload avatar
if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
    $avatarFolder = '../Image/avatar/';
    if (!file_exists($avatarFolder)) {
        mkdir($avatarFolder, 0777, true);
    }

    $avatarName = uniqid() . '_' . basename($_FILES['avatar']['name']);
    $avatarTarget = $avatarFolder . $avatarName;
    $fileType = strtolower(pathinfo($avatarTarget, PATHINFO_EXTENSION));

    if (in_array($fileType, $allowedTypes)) {
        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $avatarTarget)) {
            $avatarPath = 'avatar/' . $avatarName;

            // Xóa avatar cũ (nếu có) và không phải mặc định
            if (!empty($oldData['AVATAR']) && strpos($oldData['AVATAR'], 'default') === false) {
                $oldAvatarPath = '../Image/' . $oldData['AVATAR'];
                if (file_exists($oldAvatarPath)) {
                    unlink($oldAvatarPath);
                }
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Lỗi khi lưu avatar.']);
            exit();
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Chỉ chấp nhận file JPG, PNG, GIF, WEBP.']);
        exit();
    }
}

// Xử lý upload cover
if (isset($_FILES['cover']) && $_FILES['cover']['error'] == 0) {
    $coverFolder = '../Image/cover/';
    if (!file_exists($coverFolder)) {
        mkdir($coverFolder, 0777, true);
    }

    $coverName = uniqid() . '_' . basename($_FILES['cover']['name']);
    $coverTarget = $coverFolder . $coverName;
    $fileType = strtolower(pathinfo($coverTarget, PATHINFO_EXTENSION));

    if (in_array($fileType, $allowedTypes)) {
        if (move_uploaded_file($_FILES['cover']['tmp_name'], $coverTarget)) {
            $coverPath = 'cover/' . $coverName;

            // Xóa cover cũ (nếu có) và không phải mặc định
            if (!empty($oldData['COVER_IMAGE']) && strpos($oldData['COVER_IMAGE'], 'default') === false) {
                $oldCoverPath = '../Image/' . $oldData['COVER_IMAGE'];
                if (file_exists($oldCoverPath)) {
                    unlink($oldCoverPath);
                }
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Lỗi khi lưu ảnh bìa.']);
            exit();
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Chỉ chấp nhận file JPG, PNG, GIF, WEBP.']);
        exit();
    }
}

// Cập nhật SQL
$sql = "UPDATE USERS SET DISPLAY_NAME = ?, BIO = ?";
$params = [$displayName, $bio];
$types = "ss";

if ($avatarPath !== null) {
    $sql .= ", AVATAR = ?";
    $params[] = $avatarPath;
    $types .= "s";
}

if ($coverPath !== null) {
    $sql .= ", COVER_IMAGE = ?";
    $params[] = $coverPath;
    $types .= "s";
}

$sql .= " WHERE USES_ID = ?";
$params[] = $uses_id;
$types .= "s";

// Thực thi SQL
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    $query = $conn->prepare("SELECT DISPLAY_NAME, BIO, AVATAR, COVER_IMAGE FROM USERS WHERE USES_ID = ?");
    $query->bind_param("s", $uses_id);
    $query->execute();
    $result = $query->get_result();
    $data = $result->fetch_assoc();

    echo json_encode([
        'success' => true,
        'message' => 'Cập nhật thành công!',
        'displayName' => $data['DISPLAY_NAME'],
        'bio' => $data['BIO'],
        'avatar' => $data['AVATAR'] ? '../Image/' . $data['AVATAR'] : '../Image/default-avatar.png',
        'cover' => $data['COVER_IMAGE'] ? '../Image/' . $data['COVER_IMAGE'] : '../Image/default-cover.jpg'
    ], JSON_UNESCAPED_UNICODE);

    $query->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Lỗi SQL: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
