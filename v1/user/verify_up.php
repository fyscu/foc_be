<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
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
include('../../utils/gets.php');
include('../../utils/token.php');
//include('../../utils/headercheck.php');

$json = file_get_contents('php://input');
$data = json_decode($json, true);
$phone = "+86".$data['phone'];
$rawphone = $data['phone'];
$openid = $data['openid'];
$toreguser = getUserByOpenid($data['openid']);

if (!$toreguser) {
    echo json_encode([
        'success' => false,
        'status' => 'user_not_exists'
    ]);
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM fy_sms_catcher WHERE address = ? AND body = ?');
$stmt->execute([$phone, $toreguser['verification_code']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    // 验证码正确，更新用户状态
    $stmt = $pdo->prepare('UPDATE fy_users SET status = ?, phone = ? WHERE openid = ?');
    $stmt->execute(['verified', $rawphone, $openid]);
    echo json_encode([
        'success' => true,
        'phone' => $phone,
        'status' => 'verified'
    ]);
    exit;
} else {
    echo json_encode([
        'success' => false,
        'status' => 'no_sms'
    ]);
    exit;
    // 验证码正确，更新用户状态
    // $stmt = $pdo->prepare('UPDATE fy_users SET status = ?, phone = ? WHERE openid = ?');
    // $stmt->execute(['verified', $rawphone, $openid]);
    // echo json_encode([
    //     'success' => true,
    //     'phone' => $phone,
    //     'status' => 'verified'
    // ]);
    // exit;
}
?>
