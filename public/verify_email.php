<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); 
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Max-Age: 86400");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0); // 提前结束响应，处理 OPTIONS 预检请求
}

$config = include('../config.php');
include('../db.php');

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        'success' => false,
        'status' => 'invalid_json'
    ]);
    exit;
}

$emailToken = $_GET['token'];

// 验证 email_token 是否匹配
$stmt = $pdo->prepare('SELECT openid, temp_email FROM fy_users WHERE email_token = ?');
$stmt->execute([$emailToken]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$userData) {
    echo json_encode([
        'success' => false,
        'status' => 'invalid_token'
    ]);
    exit;
}

// 验证通过，更新邮箱
$stmt = $pdo->prepare('UPDATE fy_users SET email = ?, temp_email = NULL, email_token = NULL WHERE openid = ?');
$stmt->execute([$userData['temp_email'], $userData['openid']]);

echo json_encode([
    'success' => true,
    'status' => 'email_verified'
]);
?>