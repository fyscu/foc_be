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

requireAdminRole(['super', 'active']);

/*
 * 工单时间序列（按日新建数）。
 *   end  : 区间最后一天（含），YYYY-MM-DD，默认今天
 *   days : 往前取多少天，默认 30，上限 366 —— 前端按窗口懒加载，每次一个区间
 * 单条 GROUP BY 聚合查询，空缺日期补 0。
 */
$end = $_GET['end'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
    echo json_encode(['success' => false, 'message' => '日期格式应为 YYYY-MM-DD']);
    exit;
}
$days = (int)($_GET['days'] ?? 30);
if ($days < 1) $days = 1;
if ($days > 366) $days = 366;

$endTs = strtotime($end . ' 00:00:00');
$startTs = $endTs - ($days - 1) * 86400;
$start = date('Y-m-d', $startTs);

$stmt = $pdo->prepare("SELECT DATE(create_time) AS d, COUNT(*) AS c FROM fy_workorders
    WHERE create_time >= ? AND create_time < ?
    GROUP BY DATE(create_time)");
$stmt->execute([$start . ' 00:00:00', date('Y-m-d', $endTs + 86400) . ' 00:00:00']);

$byDate = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $byDate[$row['d']] = (int)$row['c'];
}

$rows = [];
for ($ts = $startTs; $ts <= $endTs; $ts += 86400) {
    $d = date('Y-m-d', $ts);
    $rows[] = ['date' => $d, 'count' => $byDate[$d] ?? 0];
}

echo json_encode([
    'success' => true,
    'start' => $start,
    'end' => $end,
    'rows' => $rows
], JSON_UNESCAPED_UNICODE);
