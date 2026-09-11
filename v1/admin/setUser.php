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
$nickname = trim($data['nickname'] ?? '');
$realname = trim($data['realname'] ?? '');
$phone = trim($data['phone'] ?? '');
$email = trim($data['email'] ?? '');
$role = $data['role'] ?? '';
$campus = $data['campus'] ?? '';

if (!$id || $nickname === '') {
    echo json_encode(['success' => false, 'message' => 'ID 或昵称缺失']);
    exit;
}
if (!in_array($role, ['user', 'technician', 'admin'], true)) {
    echo json_encode(['success' => false, 'message' => '不支持的角色值']);
    exit;
}

// 变更前快照（供审计）
$stmt = $pdo->prepare("SELECT id, nickname, realname, phone, email, role, campus FROM fy_users WHERE id = ?");
$stmt->execute([$id]);
$before = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$before) {
    echo json_encode(['success' => false, 'message' => '用户不存在']);
    exit;
}

$stmt = $pdo->prepare("UPDATE fy_users SET nickname=?, realname=?, phone=?, email=?, role=?, campus=? WHERE id=?");
$success = $stmt->execute([$nickname, $realname, $phone, $email, $role, $campus, $id]);

fyAdminLog($pdo, $adminAccount, 'set_user', 'user', $id, [
    'before' => $before,
    'after' => ['nickname' => $nickname, 'realname' => $realname, 'phone' => $phone, 'email' => $email, 'role' => $role, 'campus' => $campus]
]);

echo json_encode(['success' => (bool)$success]);
