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

$userId = (int)($data['user_id'] ?? 0);
$username = trim($data['username'] ?? '');
$password = $data['password'] ?? '';
$role = $data['role'] ?? '';

if (!$userId || $username === '' || $password === '' || !in_array($role, ['super', 'active', 'lucky'], true)) {
    echo json_encode(['success' => false, 'message' => '参数不完整或角色不合法']);
    exit;
}
if (mb_strlen($password) < 8) {
    echo json_encode(['success' => false, 'message' => '密码至少 8 位']);
    exit;
}

$stmt = $pdo->prepare("SELECT id, openid, nickname FROM fy_users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user || empty($user['openid'])) {
    echo json_encode(['success' => false, 'message' => '用户不存在或没有有效的微信账号']);
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM fy_admins WHERE openid = ?");
$stmt->execute([$user['openid']]);
if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => '该用户已是管理员']);
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM fy_admins WHERE username = ?");
$stmt->execute([$username]);
if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => '用户名已被占用']);
    exit;
}

$stmt = $pdo->prepare("INSERT INTO fy_admins (role, username, password, openid) VALUES (?, ?, ?, ?)");
$stmt->execute([$role, $username, password_hash($password, PASSWORD_DEFAULT), $user['openid']]);
$newId = (int)$pdo->lastInsertId();

fyAdminLog($pdo, $adminAccount, 'create_admin', 'admin', $newId, [
    'username' => $username,
    'role' => $role,
    'user_id' => $userId,
    'nickname' => $user['nickname']
]);

echo json_encode(['success' => true, 'id' => $newId], JSON_UNESCAPED_UNICODE);
