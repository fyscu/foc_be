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
require '../../utils/phone_change.php';

$json = file_get_contents('php://input');
$data = json_decode($json, true);

$newPhone = $data['phone']; // 从请求中获取的新手机号
$user = getUserByPhone($newPhone);
if ($user) {
    echo json_encode([
        'success' => false,
        'status' => 'phone_exists'
    ]);
    exit;
}    

$result = requestPhoneChange($userinfo, $newPhone);

if ($result['status'] === 'verification_sent') {
    echo json_encode([
        'success' => true,
        'status' => 'verification_code_sent'
    ]);
    exit;
} elseif ($result['status'] === 'same_phone') {
    echo json_encode([
        'success' => false,
        'status' => 'same_phone'
    ]);
    exit;
} elseif ($result['status'] === 'sms_failed') {
    echo json_encode([
        'success' => false,
        'status' => 'sms_failed'
    ]);
    exit;
}

?>