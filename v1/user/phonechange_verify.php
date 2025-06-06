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

$inputCode = $data['vcode'];
$result = verifyPhoneChange($userinfo, $inputCode);

if ($result['status'] === 'phone_updated') {
    echo json_encode(['status' => 'success', 'message' => 'phone_updated']);
} elseif ($result['status'] === 'verification_failed') {
    echo json_encode(['status' => 'error', 'message' => 'bad_code']);
}
?>