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
$repair_status = $data['repair_status'] ?? '';

// 管理端只允许把"进行中"的工单改为 取消 / 关闭。
// 完成（Done）由用户与技术员在小程序内互相确认产生；Pending 取消建议走 ticketAction.php（含配额返还）。
$allowedTarget = ['Canceled', 'Closed'];
$allowedSource = ['Pending', 'Repairing', 'UserConfirming', 'TechConfirming'];

if (!$id || !in_array($repair_status, $allowedTarget, true)) {
    echo json_encode(['success' => false, 'message' => '参数不完整，状态只能改为 Canceled 或 Closed']);
    exit;
}

$stmt = $pdo->prepare("SELECT id, repair_status FROM fy_workorders WHERE id = ?");
$stmt->execute([$id]);
$before = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$before) {
    echo json_encode(['success' => false, 'message' => '工单不存在']);
    exit;
}
if (!in_array($before['repair_status'], $allowedSource, true)) {
    echo json_encode(['success' => false, 'message' => '仅进行中的工单（待分配 / 维修中 / 确认中）可执行取消或关闭']);
    exit;
}

$stmt = $pdo->prepare("UPDATE fy_workorders SET repair_status = ? WHERE id = ?");
$success = $stmt->execute([$repair_status, $id]);

fyAdminLog($pdo, $adminAccount, 'set_ticket', 'ticket', $id, [
    'from' => $before['repair_status'],
    'to' => $repair_status
]);

echo json_encode(['success' => (bool)$success]);
