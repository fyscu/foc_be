<?php
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
include('../../utils/token.php');
include('../../utils/headercheck.php');
include('../../utils/admincheck.php');
include('../../utils/adminlog.php');

requireAdminRole(['super']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '无效的请求方法']);
    exit;
}

$json = file_get_contents('php://input');
$data = json_decode($json, true) ?: [];

$name = trim($data['name'] ?? '');
if ($name === '' || !array_key_exists('data', $data)) {
    echo json_encode(['success' => false, 'message' => '参数缺失']);
    exit;
}
$newData = (string)$data['data'];

$stmt = $pdo->prepare("SELECT id, name, info, data FROM fy_confs WHERE name = ?");
$stmt->execute([$name]);
$cur = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$cur) {
    echo json_encode(['success' => false, 'message' => '未找到对应配置']);
    exit;
}

if ($cur['data'] === $newData) {
    echo json_encode(['success' => true, 'message' => '无修改']);
    exit;
}

// Global_Year 校验：必须 4 位数字
if ($name === 'Global_Year' && !preg_match('/^\d{4}$/', $newData)) {
    echo json_encode(['success' => false, 'message' => 'Global_Year 必须为 4 位年份数字']);
    exit;
}

$stmt = $pdo->prepare("UPDATE fy_confs SET data = ? WHERE name = ?");
$stmt->execute([$newData, $name]);

fyAdminLog($pdo, $adminAccount, 'set_config', 'conf', $name, [
    'info' => $cur['info'],
    'from' => mb_substr($cur['data'], 0, 200),
    'to' => mb_substr($newData, 0, 200),
]);

echo json_encode(['success' => true]);
