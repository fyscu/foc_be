<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
$config = include('../../config.php');
include('../../db.php');
require '../../utils/email.php';
require '../../utils/sms.php';
include('../../utils/gets.php');
include('../../utils/token.php');
include('../../utils/headercheck.php'); //新逻辑下这里也需要Bearer验证了

$json = file_get_contents('php://input');
$data = json_decode($json, true);

// 检查 JSON 解析是否成功
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        'success' => false,
        'status' => 'invalid_json'
    ]);
    exit;
}

// 获取 JSON 数据
$phone = $data['phone'];
//$openid = $userinfo['openid']; （../../utils/headercheck.php已经给出了$openid变量，可以直接用）
$tokensalt = $config['token']['salt'];
$time = date("Y-m-d H:i:s");

// 检查用户是否已存在
$user = getUserByPhone($phone);

if (!$user) {
    // 用户不存在，更新用户手机号
    $stmt = $pdo->prepare('UPDATE fy_users SET phone = ? WHERE openid = ?');
    $stmt->execute([$phone, $openid]);

    // 生成新的token
    $tokenData = generateToken($openid, $tokensalt);
    $token = $tokenData['token'];
} else {
    // 用户存在，检查状态
    if ($user['status'] == 'verified') {
        echo json_encode([
            'success' => true,
            'status' => 'user_exists_verified'
        ]);
        exit;
    } else {
        // 用户存在但未验证，生成新的token
        $tokenData = generateToken($openid, $tokensalt);
        $token = $tokenData['token'];
    }
}

// 生成新的验证码
$verification_code = rand(100000, 999999);

// 更新验证码
$stmt = $pdo->prepare('UPDATE fy_users SET verification_code = ? WHERE phone = ?');
$stmt->execute([$verification_code, $phone]);

// 发送短信验证码
$sms = new Sms($config);
$templateKey = 'registration'; // 选择模板
$phoneNumber = $phone; // 接收短信的手机号
$templateParams = [$verification_code]; // 模板参数

$response = $sms->sendSms($templateKey, $phoneNumber, $templateParams);

if ($response) {
    echo json_encode([
        'success' => true,
        'status' => 'verification_code_sent',
        'access_token' => $token
    ]);
} else {
    echo json_encode([
        'success' => false,
        'status' => 'sms_failed'
    ]);
}
?>
