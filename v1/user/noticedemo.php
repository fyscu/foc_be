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
    exit(0);
}

$config = include('../../config.php');
include('../../db.php');
include('../../utils/subcribenotice.php');

$appid = $config['wechat']['app_id'];
$secret = $config['wechat']['app_secret'];

$json = file_get_contents('php://input');
$data = json_decode($json, true);

$openid = $data['openid'] ?? 'ogh0a5DsJIzo4CMJweQYAUaFr9Co';
$template_id = $data['template_id'] ?? '6DFh_1LVIGVqrIadLMNm1uaEOxw8J1Gn0SSZD7UAdL0';
$page = $data['page'] ?? 'pages/homePage/ticketDetail/index?id=20240001259&role=technician';
$msg_data = $data['msg_data'] ?? ['thing1' => '机器维修', 'phrase2' => '维修中', 'date3' => '2025-5-12 15:00', 'thing27' => '初音过去']; // 形如 ['thing1' => '内容', 'time2' => '时间']

if (!$openid || !$template_id || empty($msg_data)) {
    echo json_encode(['success' => false, 'message' => '缺少必要参数']);
    exit;
}

$notifier = new SubscribeNotifier($appid, $secret);
$result = $notifier->send($openid, $template_id, $page, $msg_data);
echo json_encode($result);