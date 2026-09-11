<?php
// 飞书客户端内 H5 免登：tt.requestAuthCode 的 code 进来
//   POST {"code":"...", "confirm":false} → 返回身份预览（姓名/角色），不签发 token
//   POST {"code":"...", "confirm":true}  → 签发后台 token（一键登录）
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
$code = $data['code'] ?? '';
$confirm = !empty($data['confirm']);
$ticket = $data['ticket'] ?? '';
$pickedRole = $data['role'] ?? '';

if ($confirm && $ticket !== '') {
    // 确认阶段：用预览时签发的 ticket，免去重复取 code / 调飞书
    $payload = fsParseTicket($config['token']['salt'], $ticket);
    if (!$payload) {
        echo json_encode(['success' => false, 'message' => '登录确认已过期，请重新进入']);
        exit;
    }
    $unionId = $payload['u'];
    $fsName = $payload['n'];
    $roles = $payload['r'];
} else {
    if ($code === '') {
        echo json_encode(['success' => false, 'message' => '缺少授权码']);
        exit;
    }

    $auth = fsExchangeJsapiCode($config['feishu'], $code);
    if (!$auth || empty($auth['union_id'])) {
        echo json_encode(['success' => false, 'message' => '飞书免登失败，请重试']);
        exit;
    }

    $unionId = $auth['union_id'];
    $fsName = $auth['name'] ?? '飞书用户';

    $resolved = fsResolveRoleByUnionId($config['feishu'], $unionId);
    if (isset($resolved['error'])) {
        echo json_encode(['success' => false, 'message' => $resolved['error']]);
        exit;
    }
    $roles = $resolved['roles'];
}

if (!$confirm) {
    // 预览：返回全部可用身份 + ticket（确认时回传，多身份时用户先选）
    echo json_encode([
        'success' => true,
        'preview' => true,
        'name' => $fsName,
        'roles' => $roles,
        'ticket' => fsMakeTicket($config['token']['salt'], $unionId, $fsName, $roles)
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 确认阶段：单身份默认取唯一项；多身份必须显式选择
if ($pickedRole === '' && count($roles) === 1) {
    $pickedRole = $roles[0];
}
if (!in_array($pickedRole, $roles, true)) {
    echo json_encode(['success' => false, 'message' => '请选择有效的管理员身份']);
    exit;
}
$role = $pickedRole;

try {
    $result = fsUpsertAdmin($pdo, $unionId, $fsName, $role);
} catch (Throwable $e) {
    error_log('[feishuJsapiLogin] ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '登录处理失败，请联系管理员']);
    exit;
}

$admin = $result['admin'];
$openid = $result['openid'];

$tokenData = generateAdminToken((int)$admin['id'], $openid, $config['token']['salt']);

fyAdminLog($pdo, ['username' => $admin['username'], 'openid' => $openid, 'role' => $role], 'feishu_jsapi_login', 'admin', $unionId, ['name' => $fsName]);

echo json_encode([
    'success' => true,
    'token' => $tokenData['token'],
    'role' => $role,
    'username' => $admin['username'],
    'nickname' => $fsName
], JSON_UNESCAPED_UNICODE);
