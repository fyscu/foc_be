<?php
// 飞书授权回调（浏览器 OAuth 流）：code → 用户信息 → 用户组 → 角色 → 签发后台 token
$config = include('../../config.php');
include('../../db.php');
include('../../utils/token.php');
include('../../utils/feishu.php');
include('../../utils/adminlog.php');

$adminUrl = $config['feishu']['admin_url'] ?? '/public/admin/';

function fsFail($msg) {
    global $adminUrl;
    header('Location: ' . $adminUrl . '#/login?fs_error=' . urlencode($msg), true, 302);
    exit;
}

if (empty($config['feishu']['app_id'])) fsFail('飞书登录未配置');

// state 校验
$state = $_GET['state'] ?? '';
$cookieState = $_COOKIE['fs_state'] ?? '';
setcookie('fs_state', '', ['expires' => time() - 3600, 'path' => '/']);
if ($state === '' || $cookieState === '' || !hash_equals($cookieState, $state)) {
    fsFail('授权状态校验失败，请重新登录');
}

if (isset($_GET['error'])) fsFail('已取消授权');
$code = $_GET['code'] ?? '';
if ($code === '') fsFail('缺少授权码');

$userToken = fsExchangeUserToken($config['feishu'], $code);
if (!$userToken) fsFail('飞书授权失败，请重试');

$info = fsGetUserInfo($userToken);
if (!$info || empty($info['union_id'])) fsFail('获取飞书用户信息失败');

$unionId = $info['union_id'];
$fsName = $info['name'] ?? '飞书用户';

$resolved = fsResolveRoleByUnionId($config['feishu'], $unionId);
if (isset($resolved['error'])) fsFail($resolved['error']);
$roles = $resolved['roles'];

// 多个可用身份：签发选择 ticket，跳回前端选择页（选定后由 feishuSelectRole.php 换 token）
if (count($roles) > 1) {
    $ticket = fsMakeTicket($config['token']['salt'], $unionId, $fsName, $roles);
    $payload = base64_encode(rawurlencode(json_encode([
        'ticket' => $ticket,
        'name' => $fsName,
        'roles' => $roles,
    ], JSON_UNESCAPED_UNICODE)));
    header('Location: ' . $adminUrl . '#/login?fs_select=' . urlencode($payload), true, 302);
    exit;
}

$role = $roles[0];

try {
    $result = fsUpsertAdmin($pdo, $unionId, $fsName, $role);
} catch (Throwable $e) {
    error_log('[feishuCallback] ' . $e->getMessage());
    fsFail('登录处理失败，请联系管理员');
}

$admin = $result['admin'];
$openid = $result['openid'];

$tokenData = generateAdminToken((int)$admin['id'], $openid, $config['token']['salt']);

fyAdminLog($pdo, ['username' => $admin['username'], 'openid' => $openid, 'role' => $role], 'feishu_login', 'admin', $unionId, ['name' => $fsName]);

// base64 前先 rawurlencode，前端 atob 后 decodeURIComponent 还原 UTF-8（直接 atob 中文会乱码）
$payload = base64_encode(rawurlencode(json_encode([
    'token' => $tokenData['token'],
    'role' => $role,
    'username' => $admin['username'],
    'nickname' => $fsName,
], JSON_UNESCAPED_UNICODE)));

header('Location: ' . $adminUrl . '#/login?fs_auth=' . urlencode($payload), true, 302);
exit;
