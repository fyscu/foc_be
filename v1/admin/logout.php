<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Max-Age: 86400");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$config = include('../../config.php');
include('../../db.php');
include('../../utils/token.php');
include('../../utils/headercheck.php');
include('../../utils/admincheck.php');
include('../../utils/adminlog.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '无效的请求方法']);
    exit;
}

// 只吊销当前这一个 token：多端 token 删行；旧式 fy_users token 置空（不影响其他端登录态）
$stmt = $pdo->prepare("DELETE FROM fy_admin_tokens WHERE token = ?");
$stmt->execute([$token]);
if ($stmt->rowCount() === 0) {
    $stmt = $pdo->prepare("UPDATE fy_users SET access_token = NULL, token_expiry = NULL WHERE access_token = ?");
    $stmt->execute([$token]);
}

fyAdminLog($pdo, $adminAccount, 'logout');

echo json_encode(['success' => true, 'message' => '已退出登录']);
