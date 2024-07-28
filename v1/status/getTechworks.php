<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
$config = include('../../config.php');
include('../../db.php');
require '../../utils/email.php';
require '../../utils/sms.php';
include('../../utils/token.php');
include('../../utils/headercheck.php'); //永远记得这里通过access_token给了$userinfo的全部数据
include('../../utils/gets.php');

$tid = isset($_GET['tid']) ? intval($_GET['tid']) : null;

// 获取当前用户的ID
$current_user_id = $userinfo['id'];
//echo $current_user_id;
if ($userinfo['is_admin']) {
$sql = "
    SELECT assigned_technician_id, COUNT(*) as count
    FROM fy_workorders
    WHERE repair_status = 'done'
    GROUP BY assigned_technician_id
    ORDER BY count DESC
    LIMIT 1
";
$stmt = $pdo->query($sql);
$result = $stmt->fetch();
$max_id = $result['assigned_technician_id'];

// 用这个id在fy_users中取得对应的realname
$sql = "
    SELECT realname
    FROM fy_users
    WHERE id = ?
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$max_id]);
$max_realname = $stmt->fetchColumn();

// 为每个出现过的id计数，并同样查询他们的realname
$sql = "
    SELECT w.assigned_technician_id, COUNT(*) as count, u.realname
    FROM fy_workorders w
    JOIN fy_users u ON w.assigned_technician_id = u.id
    WHERE w.repair_status = 'done'
    GROUP BY w.assigned_technician_id
    ORDER BY count DESC
";
$stmt = $pdo->query($sql);
$technicians = $stmt->fetchAll();

$formatted_technicians = [];
foreach ($technicians as $technician) {
    $formatted_technicians[] = [
        'tid' => $technician['assigned_technician_id'],
        'count' => $technician['count'],
        'realname' => $technician['realname']
    ];
}

$response = [
    "success" => true,
    'max_id' => $max_id,
    'max_realname' => $max_realname,
    'technicians' => $formatted_technicians,
];


} elseif ($tid == $current_user_id) {
    // 如果请求者是技术员本人，只返回本人的信息
    $sql = "
        SELECT COUNT(*) as count
        FROM fy_workorders
        WHERE repair_status = 'done' AND assigned_technician_id = ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$current_user_id]);
    $count = $stmt->fetchColumn();

    $sql = "
        SELECT realname
        FROM fy_users
        WHERE id = ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$current_user_id]);
    $realname = $stmt->fetchColumn();

    $response = [
        "success" => true,
        'tid' => $current_user_id,
        'count' => $count,
        'realname' => $realname
    ];
} else {
    $response = [
        "success" => false,
        "requesttype" => "",
        "data" => "权限不足"
    ];
    http_response_code(403);
}

echo json_encode($response);
?>