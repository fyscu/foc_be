<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); 
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Max-Age: 86400");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0); // 提前结束响应，处理 OPTIONS 预检请求
}

$config = include('../../config.php');
include('../../db.php');
require '../../utils/email.php';
include('../../utils/gets.php');
include('../../utils/token.php');
include('../../utils/headercheck.php'); 

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        'success' => false,
        'status' => 'invalid_json'
    ]);
    exit;
}

$email = $data['email'];
$tokensalt = $config['token']['salt'];
$appdomain = $config['info']['appdomain'];
$time = date("Y-m-d H:i:s");

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'success' => false,
        'status' => 'invalid_email_format'
    ]);
    exit;
}

// 检查是否与当前邮箱相同
if($email == $userinfo['email']){
    echo json_encode([
        'success' => false,
        'status' => 'same_email'
    ]);
    exit;
}

// 检查邮箱是否已经被其他用户使用
$stmt = $pdo->prepare('SELECT id FROM fy_users WHERE email = ?');
$stmt->execute([$email]);
if ($stmt->rowCount() > 0) {
    echo json_encode([
        'success' => false,
        'status' => 'email_already_in_use'
    ]);
    exit;
}
// 生成邮箱验证 token
// $token = generateToken($userinfo['openid'], $tokensalt)['token'];
$emailToken = hash('sha256', $email . $tokensalt . time());

// 将邮箱暂存到缓冲区，等待验证通过
$stmt = $pdo->prepare('UPDATE fy_users SET temp_email = ?, email_status = ? WHERE openid = ?');
$stmt->execute([$email, $emailToken, $openid]);

// 发送验证邮件
$emailSender = new Email($config);
$verificationLink = $appdomain."/public/verify_email?token=$emailToken";
$subject = "请验证您的邮箱";
$body = "请点击以下链接验证您的邮箱：<a href='$verificationLink'>$verificationLink</a>";
$sent = $emailSender->sendEmail($email, $subject, $body);

if ($sent) {
    echo json_encode([
        'success' => true,
        'status' => 'verification_email_sent'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'status' => 'email_send_failed'
    ]);
}
?>