<?php
// 发起飞书授权：生成 state 防 CSRF，302 跳转到飞书授权页
// ?appid=1 时仅返回 app_id（飞书客户端内 JSAPI 免登需要）
$config = include('../../config.php');

if (empty($config['feishu']['app_id'])) {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['success' => false, 'message' => '飞书登录未配置']);
    exit;
}

if (isset($_GET['appid'])) {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['success' => true, 'app_id' => $config['feishu']['app_id']]);
    exit;
}

$state = bin2hex(random_bytes(16));
setcookie('fs_state', $state, [
    'expires' => time() + 600,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax',
]);

$url = 'https://accounts.feishu.cn/open-apis/authen/v1/authorize?' . http_build_query([
    'client_id' => $config['feishu']['app_id'],
    'redirect_uri' => $config['feishu']['redirect_uri'],
    'state' => $state,
]);

header('Location: ' . $url, true, 302);
exit;
