<?php
// 把 fy_workorders 的 AUTO_INCREMENT 跳到 <year>0000001
// 工单号格式：YYYY + 7 位序号（11 位 BIGINT）。年份切换的实质操作。
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

$year = (int)($data['year'] ?? 0);
if ($year < 2024 || $year > 2099) {
    echo json_encode(['success' => false, 'message' => '年份不合法（需在 2024–2099 之间）']);
    exit;
}

$newStart = $year * 10000000 + 1;          // 例：2026 → 20260000001
$nextEnd = ($year + 1) * 10000000;          // 该年最大可达
$maxId = (int)$pdo->query("SELECT COALESCE(MAX(id), 0) FROM fy_workorders")->fetchColumn();

// AUTO_INCREMENT 不能小于已有最大 id 否则下次插入冲突
if ($newStart <= $maxId) {
    echo json_encode([
        'success' => false,
        'message' => "新起点 $newStart 不大于当前最大工单号 $maxId，无法应用"
    ]);
    exit;
}

$row = $pdo->query("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fy_workorders'")->fetch(PDO::FETCH_ASSOC);
$oldNext = $row ? (int)$row['AUTO_INCREMENT'] : null;

// ALTER TABLE 不能用 prepared statements 绑定数值，但 $newStart 已严格校验为整数
$pdo->exec("ALTER TABLE fy_workorders AUTO_INCREMENT = $newStart");

$row = $pdo->query("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fy_workorders'")->fetch(PDO::FETCH_ASSOC);
$verifyNext = $row ? (int)$row['AUTO_INCREMENT'] : null;

fyAdminLog($pdo, $adminAccount, 'apply_ticket_year', 'workorders', (string)$year, [
    'old_next' => $oldNext,
    'new_next' => $verifyNext,
    'max_id_before' => $maxId
]);

echo json_encode([
    'success' => true,
    'year' => $year,
    'old_next_id' => $oldNext,
    'new_next_id' => $verifyNext
]);
