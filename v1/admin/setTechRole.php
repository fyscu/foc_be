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

requireAdminRole(['super', 'active']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '无效的请求方法']);
    exit;
}

$json = file_get_contents('php://input');
$data = json_decode($json, true) ?: [];

$id = (int)($data['id'] ?? 0);
$toTech = (int)($data['toTech'] ?? 0);

if (!$id) {
    echo json_encode(['success' => false, 'message' => '参数错误']);
    exit;
}

$stmt = $pdo->prepare("SELECT id, nickname, phone, role FROM fy_users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    echo json_encode(['success' => false, 'message' => '用户不存在']);
    exit;
}

// 与老 techtransfer/import_manual.php 同口径
if ($toTech) {
    if ($user['role'] === 'technician') {
        echo json_encode(['success' => false, 'message' => $user['nickname'] . ' 已经是技术员']);
        exit;
    }
    $update = $pdo->prepare("UPDATE fy_users SET role = 'technician', available = 1, wants = 'a' WHERE id = ?");
    $update->execute([$id]);
    $newRole = 'technician';
} else {
    if ($user['role'] === 'user') {
        echo json_encode(['success' => false, 'message' => $user['nickname'] . ' 已经是用户']);
        exit;
    }
    $update = $pdo->prepare("UPDATE fy_users SET role = 'user', available = 5 WHERE id = ?");
    $update->execute([$id]);
    $newRole = 'user';
}

fyAdminLog($pdo, $adminAccount, 'set_tech_role', 'user', $id, [
    'from' => $user['role'],
    'to' => $newRole,
    'nickname' => $user['nickname']
]);

// 返回更新后的用户
$stmt = $pdo->prepare("SELECT id, nickname, realname, phone, role, available, wants FROM fy_users WHERE id = ?");
$stmt->execute([$id]);
$updated = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'message' => '更新成功', 'user' => $updated], JSON_UNESCAPED_UNICODE);
