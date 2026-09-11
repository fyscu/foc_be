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
include('../../utils/qiniu_url.php');
include('../../utils/headercheck.php');

$user = $userinfo;
$avatar = function_exists('generatePrivateLink') && !empty($user['avatar']) ? generatePrivateLink($user['avatar']) : '';

$data = [
    'uid' => (string)$user['id'],
    'id' => (string)$user['id'],
    'openid' => $user['openid'] ?? '',
    'phone' => $user['phone'] ?? '',
    'nickname' => $user['nickname'] ?? '飞扬同学',
    'avatar' => $avatar,
    'avatarUrl' => $avatar,
    'role' => $user['role'] ?? 'user',
    'campus' => $user['campus'] ?? '江安',
    'wants' => $user['wants'] ?? '',
    'canDuo' => (int)($user['canDuo'] ?? 1),
    // 2026-09-11 补：设置页需读取同时接单上限（setuser 白名单已支持本人修改，原响应漏了此字段）
    'max_concurrent' => (int)($user['max_concurrent'] ?? 1),
    'available' => (bool)($user['available'] ?? true),
    'email' => $user['email'] ?? '',
    'temp_email' => $user['temp_email'] ?? '',
    'isEmailValid' => ($user['email_status'] === 'verified'),
];

echo json_encode(array_merge([
    'success' => true,
    'data' => $data
], $data));
