<?php
// 多身份选择：浏览器 OAuth 流的第二步
//   POST {"ticket":"...", "role":"super|active|lucky"} → 校验 ticket 与角色合法性 → 签发 token
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
include('../../utils/feishu.php');
include('../../utils/adminlog.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '无效的请求方法']);
    exit;
}
if (empty($config['feishu']['app_id'])) {
    echo json_encode(['success' => false, 'message' => '飞书登录未配置']);
    exit;
}

$json = file_get_contents('php://input');
$data = json_decode($json, true) ?: [];
$ticket = $data['ticket'] ?? '';
$role = $data['role'] ?? '';

if ($ticket === '' || !in_array($role, ['super', 'active', 'lucky'], true)) {
    echo json_encode(['success' => false, 'message' => '参数不完整']);
    exit;
}

$payload = fsParseTicket($config['token']['salt'], $ticket);
if (!$payload) {
    echo json_encode(['success' => false, 'message' => '选择已过期，请重新登录']);
    exit;
}
if (!in_array($role, $payload['r'], true)) {
    echo json_encode(['success' => false, 'message' => '所选身份不在您的可用范围内']);
    exit;
}

$unionId = $payload['u'];
$fsName = $payload['n'];

try {
    $result = fsUpsertAdmin($pdo, $unionId, $fsName, $role);
} catch (Throwable $e) {
    error_log('[feishuSelectRole] ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '登录处理失败，请联系管理员']);
    exit;
}

$admin = $result['admin'];
$openid = $result['openid'];

$tokenData = generateAdminToken((int)$admin['id'], $openid, $config['token']['salt']);

fyAdminLog($pdo, ['username' => $admin['username'], 'openid' => $openid, 'role' => $role], 'feishu_login', 'admin', $unionId, ['name' => $fsName, 'selected_role' => $role]);

echo json_encode([
    'success' => true,
    'token' => $tokenData['token'],
    'role' => $role,
    'username' => $admin['username'],
    'nickname' => $fsName
], JSON_UNESCAPED_UNICODE);
