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
require '../../utils/email.php';
require '../../utils/sms.php';
include('../../utils/token.php');
include('../../utils/headercheck.php'); // 永远记得这里通过access_token给了$userinfo的全部数据
include('../../utils/gets.php');

$tid = isset($_GET['tid']) ? intval($_GET['tid']) : null;

// 获取当前用户的ID
$current_user_id = $userinfo['id'];

if ($userinfo['is_admin']) {
    // 允许排序的字段
    $allowedFields = ['realname', 'nickname', 'count']; // 允许按这些字段排序
    $sortField = isset($_GET['sort_field']) ? $_GET['sort_field'] : 'count'; // 默认按 count 排序
    $sortOrder = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'desc'; // 默认倒序

    // 验证排序字段是否合法
    if (!in_array($sortField, $allowedFields)) {
        $sortField = 'count'; // 不合法时默认按工单数量排序
    }

    // 验证排序顺序是否合法
    if (!in_array(strtolower($sortOrder), ['asc', 'desc'])) {
        $sortOrder = 'desc'; // 默认倒序
    }

    // 查询完成工单最多的技术员
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

    // 用这个 id 在 fy_users 中取得对应的 realname 和 nickname
    $sql = "
        SELECT realname, nickname
        FROM fy_users
        WHERE id = ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$max_id]);
    $max_user = $stmt->fetch();

    // 为每个技术员计数，并查询他们的 realname 和 nickname
    $sql = "
        SELECT w.assigned_technician_id, COUNT(*) as count, u.realname, u.nickname
        FROM fy_workorders w
        JOIN fy_users u ON w.assigned_technician_id = u.id
        WHERE w.repair_status = 'done'
        GROUP BY w.assigned_technician_id
        ORDER BY $sortField $sortOrder
    ";
    $stmt = $pdo->query($sql);
    $technicians = $stmt->fetchAll();

    $formatted_technicians = [];
    foreach ($technicians as $technician) {
        $formatted_technicians[] = [
            'tid' => $technician['assigned_technician_id'],
            'count' => $technician['count'],
            'realname' => $technician['realname'],
            'nickname' => $technician['nickname']
        ];
    }

    $response = [
        "success" => true,
        'max_id' => $max_id,
        'max_realname' => $max_user['realname'],
        'max_nickname' => $max_user['nickname'],
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