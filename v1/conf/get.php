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
include('../../utils/token.php');
include('../../utils/headercheck.php');

$response = [];

// 获取全部配置
$stmt = $pdo->query("SELECT name, info, data FROM fy_confs");
$configs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($configs) {
    $response = [
        'success' => true,
        'configs' => $configs
    ];
} else {
    $response = [
        'success' => false,
        'message' => 'No configurations found'
    ];
}

echo json_encode($response);
?>