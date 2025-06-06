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

$user = getUserByPhone($phone);
$tokenData = generateToken($openid, $tokensalt);
$token = $tokenData['token'];
if (!$user) {
    echo json_encode([
        'success' => true,
        'status' => 'no_imm',
        'access_token' => $token
    ]);
    exit;   
} else {
    if ($user['immed'] == '0') {
        echo json_encode([
            'success' => true,
            'status' => 'imm',
            'access_token' => $token
        ]);
        exit;
    }
    echo json_encode([
        'success' => true,
        'status' => 'user_exists_verified'
    ]);
    exit;
}
?>