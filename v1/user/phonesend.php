<?php
// phonesend.php — App 端手机号登录：发送短信验证码（免鉴权）
// 2026-09-10 新增，不修改任何现有接口；仅服务 status=verified 且已绑定手机号的存量用户。
// 频控与验证码有效期均基于现有 fy_sms_log 表，无表结构改动。
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
include('../../utils/gets.php');
require '../../utils/sms.php';

$json = file_get_contents('php://input');
$data = json_decode($json, true);
$phone = isset($data['phone']) ? trim($data['phone']) : '';

if (!preg_match('/^1\d{10}$/', $phone)) {
    echo json_encode(['success' => false, 'status' => 'invalid_phone', 'message' => '请输入正确的11位手机号码']);
    exit;
}

$user = getUserByPhone($phone);

if (!$user) {
    echo json_encode([
        'success' => false,
        'status' => 'user_not_found',
        'message' => '该手机号尚未注册，请先在微信小程序「云上飞扬」完成注册并绑定手机号',
    ]);
    exit;
}

if ($user['immed'] == '0') {
    echo json_encode([
        'success' => false,
        'status' => 'user_need_migration',
        'message' => '该账号有老系统数据待迁移，请先在微信小程序中完成一次登录迁移后再使用 App',
    ]);
    exit;
}

if ($user['status'] !== 'verified') {
    echo json_encode([
        'success' => false,
        'status' => 'user_not_verified',
        'message' => '该账号尚未完成验证，请先在微信小程序中完成注册验证',
    ]);
    exit;
}

// 频控 1：同手机号 60 秒冷却
$stmt = $pdo->prepare("SELECT COUNT(*) FROM fy_sms_log WHERE phone = ? AND type = 'applogin' AND created_at > DATE_SUB(NOW(), INTERVAL 60 SECOND)");
$stmt->execute([$phone]);
if ((int)$stmt->fetchColumn() > 0) {
    echo json_encode(['success' => false, 'status' => 'too_frequent', 'message' => '发送太频繁，请 60 秒后再试']);
    exit;
}

// 频控 2：同手机号每小时最多 5 条
$stmt = $pdo->prepare("SELECT COUNT(*) FROM fy_sms_log WHERE phone = ? AND type = 'applogin' AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
$stmt->execute([$phone]);
if ((int)$stmt->fetchColumn() >= 5) {
    echo json_encode(['success' => false, 'status' => 'hourly_limit', 'message' => '发送次数过多，请 1 小时后再试']);
    exit;
}

$verification_code = (string)rand(100000, 999999);

// 时间统一用 MySQL NOW() 写入：PHP 容器为 UTC 而 MySQL 为 +08:00，
// 若用 date() 写入会与频控/时效查询里的 NOW() 错位 8 小时（2026-09-10 联调实测）
$stmt = $pdo->prepare("INSERT INTO fy_sms_log (openid, created_at, phone, type) VALUES (?, NOW(), ?, 'applogin')");
$stmt->execute([$user['openid'], $phone]);

$stmt = $pdo->prepare('UPDATE fy_users SET verification_code = ? WHERE id = ?');
$stmt->execute([$verification_code, $user['id']]);

// 复用阿里云 'verification' 模板（与 register.php 同款），验证码有效期 10 分钟
$sms = new Sms($config);
$response = $sms->sendSms('verification', $phone, ['code' => $verification_code, 'min' => '10']);

if (is_array($response) && !isset($response['error'])) {
    echo json_encode(['success' => true, 'status' => 'code_sent']);
} else {
    echo json_encode(['success' => false, 'status' => 'sms_failed', 'message' => '短信发送失败，请稍后重试']);
}
