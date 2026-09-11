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

$start = $_GET['start'] ?? '';
$end = $_GET['end'] ?? '';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
    echo json_encode(['success' => false, 'message' => '日期格式应为 YYYY-MM-DD']);
    exit;
}

// 统计口径与老 public/statistics/index.php 一致：Done 工单按 create_time 范围、按技术员分组
$sql = "
    SELECT
        u.id AS technician_id,
        u.nickname,
        u.phone,
        COUNT(w.id) AS order_count
    FROM fy_workorders w
    INNER JOIN fy_users u ON w.assigned_technician_id = u.id
    WHERE w.repair_status = 'Done'
    AND w.create_time BETWEEN :start AND :end
    GROUP BY w.assigned_technician_id
    ORDER BY order_count DESC, technician_id ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':start' => $start . ' 00:00:00',
    ':end' => $end . ' 23:59:59',
]);
$raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 跳跃排名（1,1,3 式）
$results = [];
$lastCount = null;
$rank = 0;
$actualRank = 0;
foreach ($raw as $row) {
    $actualRank++;
    if ($lastCount !== $row['order_count']) {
        $rank = $actualRank;
    }
    $results[] = [
        'id' => (int)$row['technician_id'],
        'nickname' => $row['nickname'],
        'phone' => $row['phone'],
        'order_count' => (int)$row['order_count'],
        'rank' => $rank,
    ];
    $lastCount = $row['order_count'];
}

echo json_encode([
    'success' => true,
    'start' => $start,
    'end' => $end,
    'rows' => $results
], JSON_UNESCAPED_UNICODE);
