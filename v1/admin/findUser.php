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

requireAdminRole(['super', 'active']);

$keyword = trim($_GET['keyword'] ?? '');
if ($keyword === '') {
    echo json_encode(['success' => false, 'message' => '请输入手机号或昵称']);
    exit;
}

// 与老 techtransfer/import_manual 同口径：手机号精确 或 昵称模糊，取最新一行
$stmt = $pdo->prepare("SELECT id, nickname, realname, phone, role, status, immed, available, wants, campus FROM fy_users WHERE phone = ? OR nickname LIKE ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$keyword, "%$keyword%"]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(['success' => false, 'message' => '未找到用户']);
    exit;
}

// 查该手机号最后一条短信验证码记录（快捷激活页用来判断 reg/rereg/imm）
$sms_type = null;
if (!empty($user['phone'])) {
    $stmt = $pdo->prepare("SELECT type FROM fy_sms_log WHERE phone = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$user['phone']]);
    $sms = $stmt->fetch(PDO::FETCH_ASSOC);
    $sms_type = $sms ? $sms['type'] : null;
}

$user['immed'] = (int)$user['immed'];

echo json_encode([
    'success' => true,
    'user' => $user,
    'sms_type' => $sms_type
], JSON_UNESCAPED_UNICODE);
