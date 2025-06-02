<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); 
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Max-Age: 86400");
session_name('admin');
session_start();
if (empty($_SESSION['admin_logged_in'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '未登录']);
    exit;
}

require '../../../db.php';
$config = include('../../../config.php');

$id = $_POST['id'] ?? '';
$nickname = $_POST['nickname'] ?? '';
$realname = $_POST['realname'] ?? '';
$phone = $_POST['phone'] ?? '';
$email = $_POST['email'] ?? '';
$role = $_POST['role'] ?? '';
$campus = $_POST['campus'] ?? '';

if (!$id || !$nickname) {
    echo json_encode(['success' => false, 'message' => 'ID 或昵称缺失']);
    exit;
}

$stmt = $pdo->prepare("UPDATE fy_users SET nickname=?, realname=?, phone=?, email=?, role=?, campus=? WHERE id=?");
$success = $stmt->execute([$nickname, $realname, $phone, $email, $role, $campus, $id]);

echo json_encode(['success' => $success]);
