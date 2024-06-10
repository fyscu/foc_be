<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
$config = include('../../config.php');
include('../../db.php');
require '../../utils/email.php';
require '../../utils/sms.php';
include('../../utils/gets.php');
include('../../utils/token.php');
// 获取POST数据

$phone = $_POST['phone'];
$openid = $_POST['openid'];
$tokensalt = $config['token']['salt'];
$time = date("Y-m-d H:i:s");
// 检查用户是否已存在
$user = getUserByPhone($phone);

if (!$user) {
    // 用户不存在，插入用户数据，标记为待验证
    
    $stmt = $pdo->prepare('INSERT INTO fy_users (nickname, phone, openid, role, status, regtime) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute(['NULL', $phone, $openid, 'user', 'pending',$time]);
    $tokenData = generateToken($openid, $tokensalt);
    $token = $tokenData['token']; 
    // 生成验证码并发送
    $verification_code = rand(100000, 999999);
    $stmt = $pdo->prepare('UPDATE fy_users SET verification_code = ? WHERE phone = ?');
    $stmt->execute([$verification_code, $phone]);

    $sms = new Sms($config);
    $templateKey = 'registration'; // 选择模板
    $phoneNumber = $phone; // 接收短信的手机号
    $templateParams = [$verification_code]; // 模板参数

    $response = $sms->sendSms($templateKey, $phoneNumber, $templateParams);
    //echo json_encode(['status' => $response]);
    if($response){
        echo json_encode([
            'success' => true,
            'status' => 'verification_code_sent',
            'access_token' => $token
        ]);
    } 
}
else {
    echo json_encode([
        'success' => true,
        'status' => 'user_exists'
    ]);

}
?>
