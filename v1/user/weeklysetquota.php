<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); 
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Max-Age: 86400");

include('../../db.php');
$config = include('../../config.php');

$weeklyset = $config['info']['weeklyset'];
$stmt = $pdo->prepare("UPDATE fy_users SET available = $weeklyset WHERE role = 'user' AND available != $weeklyset");
$stmt->execute();

echo json_encode([
    'success' => true,
    'message' => '用户每周限额已重置为：' . $weeklyset
]);
?>