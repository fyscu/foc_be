<?php
// login.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$config = include('../../config.php');
include('../../db.php');
include('../../utils/token.php');

$appid = $config['wechat']['app_id'];
$secret = $config['wechat']['app_secret'];
$tokensalt = $config['token']['salt'];

$code = $_POST['code'];

$url = "https://api.weixin.qq.com/sns/jscode2session?appid=$appid&secret=$secret&js_code=$code&grant_type=authorization_code";

$response = file_get_contents($url);
$responseData = json_decode($response, true);

if (isset($responseData['openid'])) {
    $stmt = $pdo->prepare('SELECT * FROM fy_users WHERE openid = ?');
    $stmt->execute([$responseData['openid']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        echo json_encode([
            'success' => true,
            'registered' => false,
            'openid' => $responseData['openid'],
            'access_token' => '',
            'uid' => '',
            'email' => '',
            'avatar' => '',
            'campus' => '',
            'phone' => '',
            'role' => '',
            'nickname' => ''
        ]);
    } else {
        $tokenData = generateToken($responseData['openid'], $tokensalt);
        $token = $tokenData['token'];    
        echo json_encode([
            'success' => true,
            'registered' => true,
            'openid' => $responseData['openid'],
            'access_token' => $token,
            'uid' => $user['id'],
            'email' => $user['email'],
            'avatar' => $user['avatar'],
            'campus' => $user['campus'],
            'phone' => $user['phone'],
            'role' => $user['role'],
            'nickname' => $user['nickname']
        ]);
    }
    
} else {
    echo json_encode([
        'success' => false,
        'error' => $responseData,
        'registered' => false
    ]);
}
?>
