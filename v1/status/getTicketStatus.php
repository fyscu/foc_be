<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); 
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Max-Age: 86400");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0); // 提前结束响应，处理 OPTIONS 预检请求
}

if(isset($_GET['version'])){
    if($_GET['version'] === "1.1.9"){
        echo json_encode([
            "success" => true,
            "status" => 1
            
        ]);
        exit;
    }
}

echo json_encode([
    "success" => true,
    "status" => 0
]);
?>