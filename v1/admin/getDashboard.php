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

$currentYear = date('Y');
$currentMonth = date('m');
$currentDate = date('Y-m-d');

// —— 工单数字汇总 ——
$stmt = $pdo->prepare("SELECT COUNT(*) AS count FROM fy_workorders WHERE DATE(create_time) = ?");
$stmt->execute([$currentDate]);
$thisDayTickets = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $pdo->prepare("SELECT COUNT(*) AS count FROM fy_workorders WHERE YEAR(create_time) = ? AND MONTH(create_time) = ?");
$stmt->execute([$currentYear, $currentMonth]);
$thisMonthTickets = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $pdo->prepare("SELECT COUNT(*) AS count FROM fy_workorders WHERE YEAR(create_time) = ?");
$stmt->execute([$currentYear]);
$thisYearTickets = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$totalTickets = (int)$pdo->query("SELECT COUNT(*) FROM fy_workorders")->fetchColumn();
$totalFeedback = (int)$pdo->query("SELECT COUNT(*) FROM fy_info")->fetchColumn();

/*
 * —— 校区归类口径 ——
 * 有效校区只有：江安 / 望江 / 华西 / 未迁移。
 *   未迁移 = campus 为空 且 immed = 0（老系统迁移过来但还没在新小程序登录的用户）
 *   其余（campus 为空且 immed=1、或 campus 是别的值）均视为测试数据，不纳入归类与总数。
 */
$CAMPUS = ['江安', '望江', '华西'];
$UNMIGRATED = '未迁移';

function fdCampusRows(array $raw, array $order) {
    $rows = [];
    foreach ($order as $c) {
        if (isset($raw[$c])) $rows[] = ['campus' => $c, 'count' => $raw[$c]];
    }
    return $rows;
}

// 在职技术员：immed=1，未迁移类别不存在，只看三个校区
$techRaw = [];
$totalTech = 0;
$stmt = $pdo->query("SELECT campus, COUNT(*) AS count FROM fy_users
    WHERE role = 'technician' AND immed = 1 AND wants != 'a' AND campus IN ('江安','望江','华西')
    GROUP BY campus");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $techRaw[$row['campus']] = (int)$row['count'];
    $totalTech += (int)$row['count'];
}

// 用户：三校区 + 未迁移
$userRaw = [];
$totalUser = 0;
$stmt = $pdo->query("SELECT grp, COUNT(*) AS count FROM (
        SELECT CASE
            WHEN campus IN ('江安','望江','华西') THEN campus
            WHEN (campus IS NULL OR campus = '') AND immed = 0 THEN '未迁移'
            ELSE NULL
        END AS grp
        FROM fy_users WHERE role = 'user'
    ) t WHERE grp IS NOT NULL GROUP BY grp");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $userRaw[$row['grp']] = (int)$row['count'];
    $totalUser += (int)$row['count'];
}

// 已激活用户：immed=1，只可能在三个校区
$activeRaw = [];
$totalActiveUser = 0;
$stmt = $pdo->query("SELECT campus, COUNT(*) AS count FROM fy_users
    WHERE role = 'user' AND immed = 1 AND campus IN ('江安','望江','华西')
    GROUP BY campus");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $activeRaw[$row['campus']] = (int)$row['count'];
    $totalActiveUser += (int)$row['count'];
}

// 工单：按三校区归类（其余校区值为测试/历史数据，不纳入）
$ticketRaw = [];
$stmt = $pdo->query("SELECT campus, COUNT(*) AS count FROM fy_workorders
    WHERE campus IN ('江安','望江','华西') GROUP BY campus");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $ticketRaw[$row['campus']] = (int)$row['count'];
}

echo json_encode([
    'success' => true,
    'data' => [
        'thisDayTickets' => (int)$thisDayTickets,
        'thisMonthTickets' => (int)$thisMonthTickets,
        'thisYearTickets' => (int)$thisYearTickets,
        'totalTickets' => $totalTickets,
        'totalTech' => $totalTech,
        'totalUser' => $totalUser,
        'totalActiveUser' => $totalActiveUser,
        'totalFeedback' => $totalFeedback,
        'techByCampus' => fdCampusRows($techRaw, $CAMPUS),
        'userByCampus' => fdCampusRows($userRaw, array_merge($CAMPUS, [$UNMIGRATED])),
        'activeUserByCampus' => fdCampusRows($activeRaw, $CAMPUS),
        'ticketByCampus' => fdCampusRows($ticketRaw, $CAMPUS)
    ]
], JSON_UNESCAPED_UNICODE);
