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

$phone = "+86".$data['phone'];
$openid = $data['openid'];
$tokensalt = $config['token']['salt'];
$user = getUserByPhone($data['phone']);
$newuser = getUserByOpenid($openid);

if ($user && $user['immed'] == '1') {
    echo json_encode([
        'success' => true,
        'status' => 'user_exists_verified'
    ]);
    exit;
} elseif ($user && $user['immed'] == '0') {
    $stmt = $pdo->prepare('SELECT * FROM fy_sms_catcher WHERE address = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$phone]);
    $smslog = $stmt->fetch(PDO::FETCH_ASSOC);
    // if (!$smslog) {
    //     echo json_encode([
    //         'success' => false,
    //         'status' => 'sms_not_found'
    //     ]);
    //     exit;
    // } else {
    //     echo json_encode([
    //         'success' => false,
    //         'status' => $newuser['verification_code'].'and'.$smslog['body']
    //     ]);
    //     exit;
    // }
    // 验证验证码是否正确
    if ($newuser['verification_code'] == $smslog['body']) {
        // 验证成功，更新用户数据并迁移 UID
        $stmt = $pdo->prepare('UPDATE fy_users SET openid = ?, immed = 1, phone = ?, email = ?, realname = ?, nickname = ?, avatar = ?, campus = ?, role = ?, status = "verified" WHERE id = ?');
        $stmt->execute([$openid,$user['phone'],$user['email'],$user['realname'],$user['nickname'],$user['avatar'],$user['campus'],$user['role'],$user['id']
        ]);

        // 删除原始的临时用户数据
        $stmt = $pdo->prepare('DELETE FROM fy_users WHERE openid = ? AND id != ?');
        $stmt->execute([$openid, $user['id']]);

        $tokenData = generateToken($openid, $tokensalt);
        $token = $tokenData['token'];
        echo json_encode([
            'success' => true,
            'status' => 'user_migrated',
            'access_token' => $token
        ]);
        exit;
    } else {
        echo json_encode([
            'success' => false,
            'status' => 'invalid_verification_code'
        ]);
        exit;
    }
} else {
    echo json_encode([
        'success' => false,
        'status' => 'user_not_found'
    ]);
    exit;
}
?>