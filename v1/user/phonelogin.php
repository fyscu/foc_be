<?php
// phonelogin.php — App 端手机号登录：校验验证码并签发 30 天长效 token（免鉴权）
// 2026-09-10 新增。token 写入独立表 fy_app_tokens（多端并存、互不顶号，不影响
// 小程序的 fy_users.access_token 一小时短 token 机制）；鉴权接入见 utils/token.php 回退逻辑。
// 防爆破：失败尝试记 fy_sms_log(type='applogin_fail')，10 分钟内同号失败 >=5 次锁定。
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

$json = file_get_contents('php://input');
$data = json_decode($json, true);
$phone = isset($data['phone']) ? trim($data['phone']) : '';
$code = isset($data['code']) ? trim($data['code']) : '';

if (!preg_match('/^1\d{10}$/', $phone) || !preg_match('/^\d{6}$/', $code)) {
    echo json_encode(['success' => false, 'status' => 'invalid_params', 'message' => '参数格式错误']);
    exit;
}

$user = getUserByPhone($phone);
if (!$user || $user['status'] !== 'verified' || $user['immed'] == '0') {
    echo json_encode(['success' => false, 'status' => 'user_not_found', 'message' => '该手机号不可用 App 登录，请先在小程序完成注册']);
    exit;
}

// 防爆破锁定：10 分钟内同手机号失败 >= 5 次
$stmt = $pdo->prepare("SELECT COUNT(*) FROM fy_sms_log WHERE phone = ? AND type = 'applogin_fail' AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
$stmt->execute([$phone]);
if ((int)$stmt->fetchColumn() >= 5) {
    echo json_encode(['success' => false, 'status' => 'locked', 'message' => '尝试次数过多，请 10 分钟后再试']);
    exit;
}

// 校验验证码 + 时效（10 分钟内必须有一次成功的 applogin 发码记录，防止陈旧验证码）
$stmt = $pdo->prepare("SELECT COUNT(*) FROM fy_sms_log WHERE phone = ? AND type = 'applogin' AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
$stmt->execute([$phone]);
$codeFresh = (int)$stmt->fetchColumn() > 0;

if (!$codeFresh || $user['verification_code'] === null || $user['verification_code'] !== $code) {
    // 记录一次失败尝试（时间同样用 MySQL NOW()，见 phonesend.php 时区说明）
    $stmt = $pdo->prepare("INSERT INTO fy_sms_log (openid, created_at, phone, type) VALUES (?, NOW(), ?, 'applogin_fail')");
    $stmt->execute([$user['openid'], $phone]);
    echo json_encode(['success' => false, 'status' => 'invalid_code', 'message' => '验证码错误或已过期']);
    exit;
}

// 验证码一次性：立即清空
$stmt = $pdo->prepare('UPDATE fy_users SET verification_code = NULL WHERE id = ?');
$stmt->execute([$user['id']]);

// 签发 30 天 App token（随机 256 位，与 fy_admin_tokens 同模式；不触碰 fy_users.access_token）
$token = hash('sha256', random_bytes(32));
$expiry = time() + 86400 * 30;
$ip = $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '';

try {
    // 顺手清理该用户的过期 token
    $stmt = $pdo->prepare("DELETE FROM fy_app_tokens WHERE user_id = ? AND expires_at < NOW()");
    $stmt->execute([$user['id']]);

    $stmt = $pdo->prepare("INSERT INTO fy_app_tokens (user_id, token, expires_at, created_at, ip) VALUES (?, ?, ?, NOW(), ?)");
    $stmt->execute([$user['id'], $token, date('Y-m-d H:i:s', $expiry), $ip]);
} catch (Throwable $e) {
    error_log('[phonelogin] ' . $e->getMessage());
    echo json_encode(['success' => false, 'status' => 'db_error', 'message' => '服务暂不可用，请稍后重试']);
    exit;
}

echo json_encode([
    'success' => true,
    'status' => 'login_ok',
    'access_token' => $token,
    'expires_in' => 86400 * 30,
    'uid' => (string)$user['id'],
]);
