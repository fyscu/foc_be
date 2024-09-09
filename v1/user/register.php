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
require '../../utils/sms.php';
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

$phone = $data['phone'];
$tokensalt = $config['token']['salt'];
$time = date("Y-m-d H:i:s");

// 检查用户是否已存在
$user = getUserByPhone($phone);

if (!$user) {
    // 用户不存在，更新当前用户的手机号和注册时间
    $verification_code = rand(100000, 999999);
    $stmt = $pdo->prepare('UPDATE fy_users SET phone = ?, regtime = ?, verification_code = ? WHERE openid = ?');
    $stmt->execute([$phone, $time, $verification_code, $openid]);

    // 生成新的 token
    $tokenData = generateToken($openid, $tokensalt);
    $token = $tokenData['token'];

    // 发送短信验证码
    $sms = new Sms($config);
    $templateKey = 'registration'; 
    $phoneNumber = $phone; 
    $templateParams = [$verification_code];
    $response = $sms->sendSms($templateKey, $phoneNumber, $templateParams);

    if ($response) {
        echo json_encode([
            'success' => true,
            'status' => 'verification_code_sent',
            'access_token' => $token
        ]);
        exit;
    } else {
        echo json_encode([
            'success' => false,
            'status' => 'sms_failed'
        ]);
        exit;
    }
    
} else {
    if ($user['immed'] == '0') {
        // 需要迁移的用户，生成并发送验证码
        $tokenData = generateToken($openid, $tokensalt);
        $token = $tokenData['token'];
        $verification_code = rand(100000, 999999);
        $stmt = $pdo->prepare('UPDATE fy_users SET verification_code = ? WHERE phone = ?');
        $stmt->execute([$verification_code, $phone]);

        $sms = new Sms($config);
        $templateKey = 'migration'; 
        $phoneNumber = $phone; 
        $templateParams = [$verification_code];
        $response = $sms->sendSms($templateKey, $phoneNumber, $templateParams);

        if ($response) {
            echo json_encode([
                'success' => true,
                'status' => 'user_need_migration',
                'access_token' => $token
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'status' => 'sms_failed'
            ]);
        }
    } else {
        // 用户已存在并且已验证
        if ($user['status'] == 'verified') {
            echo json_encode([
                'success' => true,
                'status' => 'user_exists_verified'
            ]);
            exit;
        } else {
            // 用户存在但未验证，生成新的 token 和验证码
            $tokenData = generateToken($openid, $tokensalt);
            $token = $tokenData['token'];

            $verification_code = rand(100000, 999999);
            $stmt = $pdo->prepare('UPDATE fy_users SET verification_code = ? WHERE phone = ?');
            $stmt->execute([$verification_code, $phone]);

            $sms = new Sms($config);
            $templateKey = 'registration'; 
            $phoneNumber = $phone; 
            $templateParams = [$verification_code];
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
        }
    }
}
?>