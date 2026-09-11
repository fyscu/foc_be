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

requireAdminRole(['super']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '无效的请求方法']);
    exit;
}

$json = file_get_contents('php://input');
$data = json_decode($json, true) ?: [];
$id = (int)($data['id'] ?? 0);

if (!$id) {
    echo json_encode(['success' => false, 'message' => '参数缺失']);
    exit;
}

// 删除前整行快照（不含 token / 验证码）
$stmt = $pdo->prepare("SELECT id, openid, nickname, realname, phone, email, role, campus, status, immed, regtime, available, wants FROM fy_users WHERE id = ?");
$stmt->execute([$id]);
$snapshot = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$snapshot) {
    echo json_encode(['success' => false, 'message' => '用户不存在']);
    exit;
}

$stmt = $pdo->prepare("DELETE FROM fy_users WHERE id = ?");
$success = $stmt->execute([$id]);

fyAdminLog($pdo, $adminAccount, 'delete_user', 'user', $id, ['snapshot' => $snapshot]);

echo json_encode(['success' => (bool)$success]);
