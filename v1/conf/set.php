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
include('../../utils/gets.php');

$json = file_get_contents('php://input');
$data = json_decode($json, true);

$response = [];

if ($userinfo['is_admin']) {
    $has_permission = true;
} else {
    $has_permission = false;
}


if ($has_permission) {
    if (!isset($data['name']) || !isset($data['data'])) {
        $response = [
            'success' => false,
            'message' => 'Invalid input'
        ];
        echo json_encode($response);
        exit;
    }
$name = $data['name'];
$stmt = $pdo->prepare("SELECT * FROM fy_confs WHERE name = :name");
$stmt->execute([':name' => $name]);
$config = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$config) {
    $response = [
        'success' => false,
        'message' => '未找到对应配置'
    ];
    echo json_encode($response);
    exit;
}

$updateFields = [];
$updateValues = [];
$changedFields = [];

if ($config['data'] != $data['data']) {
    $updateFields[] = "data = :data";
    $updateValues[':data'] = $data['data'];
    $changedFields['data'] = $data['data'];
}

if (count($updateFields) > 0) {
    $updateSql = "UPDATE fy_confs SET " . implode(", ", $updateFields) . " WHERE name = :name";
    $updateValues[':name'] = $name;

    $stmt = $pdo->prepare($updateSql);
    $stmt->execute($updateValues);

    $response = [
        'success' => true,
        'changedFields' => $changedFields
    ];
} else {
    $response = [
        'success' => true,
        'message' => '无修改',
        'changedFields' => []
    ];
}
} else {
    $response = [
        'success' => false,
        'message' => 'Permission denied'
    ];
}
echo json_encode($response);
?>