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
$id = (int)($data['id'] ?? 0);

if (!$id) {
    echo json_encode(['success' => false, 'message' => '参数缺失']);
    exit;
}

// 删除前整行快照
$stmt = $pdo->prepare("SELECT * FROM fy_workorders WHERE id = ?");
$stmt->execute([$id]);
$snapshot = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$snapshot) {
    echo json_encode(['success' => false, 'message' => '工单不存在']);
    exit;
}

$stmt = $pdo->prepare("DELETE FROM fy_workorders WHERE id = ?");
$success = $stmt->execute([$id]);

fyAdminLog($pdo, $adminAccount, 'delete_ticket', 'ticket', $id, ['snapshot' => $snapshot]);

echo json_encode(['success' => (bool)$success]);
