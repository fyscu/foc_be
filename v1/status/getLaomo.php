<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); 
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Max-Age: 86400");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0); // 处理 OPTIONS 预检请求
}

$config = include('../../config.php');
include('../../db.php');
include('../../utils/token.php');
include('../../utils/headercheck.php'); // 通过 access_token 验证用户身份
include('../../utils/gets.php');

// 获取请求参数：limit 和 campus
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 3;
$campus = isset($_GET['campus']) ? trim($_GET['campus']) : null;

// SQL 查询基础部分，增加完成工单的时间字段
$sql = "
    SELECT w.assigned_technician_id, COUNT(*) as count, u.realname, u.nickname, MAX(w.completion_time) as last_completion_time, u.campus
    FROM fy_workorders w
    JOIN fy_users u ON w.assigned_technician_id = u.id
    WHERE w.repair_status = 'done' AND u.role = 'technician'
";

// 为了增加磨子桥榜单，特地处理campus，顺便加了aka
$campus_conditions = [];
if ($campus) {
    switch (strtolower($campus)) {
        case '磨子桥':
        case 'm':
            $campus_conditions[] = "u.campus = '望江'";
            $campus_conditions[] = "u.campus = '华西'";
            break;
        case '望江':
        case 'w':
            $campus_conditions[] = "u.campus = '望江'";
            break;
        case '江安':
        case 'j':
            $campus_conditions[] = "u.campus = '江安'";
            break;
        case '华西':
        case 'h':
            $campus_conditions[] = "u.campus = '华西'";
            break;
    }

    if (!empty($campus_conditions)) {
        $sql .= " AND (" . implode(' OR ', $campus_conditions) . ")";
    }
}

// 按技术员完成工单数量分组，工单完成时间用来解决并列问题
$sql .= " 
    GROUP BY w.assigned_technician_id, u.realname, u.nickname
    ORDER BY count DESC, last_completion_time ASC
    LIMIT :limit
";

$stmt = $pdo->prepare($sql);

// 绑定参数
$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();
$top_technicians = $stmt->fetchAll();

// 格式化返回数据，包含 rank 字段
$formatted_technicians = [];
$rank = 1;
foreach ($top_technicians as $technician) {
    $formatted_technicians[] = [
        'rank' => $rank,
        'tid' => $technician['assigned_technician_id'],
        'count' => $technician['count'],
        'realname' => $technician['realname'],
        'nickname' => $technician['nickname'],
        'last_time' => $technician['last_completion_time'],
        'campus' => $technician['campus']
    ];
    $rank++;
}

$response = [
    "success" => true,
    'top_technicians' => $formatted_technicians,
];

echo json_encode($response);
?>
