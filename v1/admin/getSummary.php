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

if ($userinfo['is_admin']) {
    $currentYear = date('Y');
    $currentMonth = date('m');
    $currentDay = date('d');
    $currentDate = date('Y-m-d');

    $stmt = $pdo->prepare("SELECT COUNT(*) AS count FROM fy_workorders WHERE DATE(create_time) = ?");
    $stmt->execute([$currentDate]);
    $thisDayTickets = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    $stmt = $pdo->prepare("SELECT COUNT(*) AS count FROM fy_workorders WHERE YEAR(create_time) = ? AND MONTH(create_time) = ?");
    $stmt->execute([$currentYear,$currentMonth]);
    $thisMonthTickets = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    $stmt = $pdo->prepare("SELECT COUNT(*) AS count FROM fy_workorders WHERE YEAR(create_time) = ?");
    $stmt->execute([$currentYear]);
    $thisYearTickets = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    $stmt = $pdo->query("SELECT COUNT(*) AS count FROM fy_workorders");
    $totalTickets = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    $stmt = $pdo->query("SELECT COUNT(*) AS count FROM fy_users WHERE role = 'technician'");
    $totalTechnicians = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    $stmt = $pdo->query("SELECT COUNT(*) AS count FROM fy_users WHERE role = 'user'");
    $totalUsers = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    $stmt = $pdo->query("SELECT COUNT(*) AS count FROM fy_info");
    $totalFeedback = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    $recentTickets = [];
    for ($i = 5; $i >= 1; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $stmt = $pdo->prepare("SELECT COUNT(*) AS count FROM fy_workorders WHERE DATE(create_time) = ?");
        $stmt->execute([$date]);
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        $recentTickets[] = [
            "date" => $date,
            "count" => (int)$count
        ];
    }

    $response['success'] = true;
    $response['data'] = [
        "thisDayTickets" => (int)$thisDayTickets,
        "thisMonthTickets" => (int)$thisMonthTickets,
        "thisYearTickets" => (int)$thisYearTickets,
        "totalTickets" => (int)$totalTickets,
        "totalTech" => (int)$totalTechnicians,
        "totalUser" => (int)$totalUsers,
        "totalFeedback" => (int)$totalFeedback,
        "recentTickets" => $recentTickets
    ];
} else {
    $response['success'] = false;
    $response['message'] = "Permission Denied";
}

echo json_encode($response);
?>