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
include('../../utils/gets.php');
include('../../utils/token.php');
include('../../utils/headercheck.php');

if($userinfo['is_admin']){
    echo json_encode(["success" => true, "type" => "super"]);
} elseif ($userinfo['is_lucky_admin']) {
    echo json_encode(["success" => true, "type" => "lucky"]);
} else {
    echo json_encode(["success" => false, "message" => "Permission denied"]);
}

?>
