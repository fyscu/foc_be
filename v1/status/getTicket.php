<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
$config = include('../../config.php');
include('../../db.php');
include('../../utils/token.php');
include('../../utils/headercheck.php'); //永远记得这里通过access_token给了$userinfo的全部数据
include('../../utils/gets.php');

function unauthorizedResponse() {
    $response = [
        "success" => false,
        "requesttype" => "",
        "data" => "权限不足"
    ];
    http_response_code(403);
    echo json_encode($response);
    exit;
}

// 获取请求参数
$workorderId = isset($_GET['orderid']) ? (int)$_GET['orderid'] : null;
$userId = isset($_GET['uid']) ? (int)$_GET['uid'] : null;
$technicianId = isset($_GET['tid']) ? (int)$_GET['tid'] : null;
$list = isset($_GET['list']) ? $_GET['list'] : null;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10; 
$offset = ($page - 1) * $limit;

$query = "SELECT * FROM fy_workorders WHERE 1=1";
$params = [];
$requestType = '';

// 构建查询和请求种类
if ($workorderId) {
    // 检查用户身份的条件
    $checkQuery = "SELECT user_id, assigned_technician_id FROM fy_workorders WHERE id = ?";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->execute([$workorderId]);
    $workorder = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$workorder || ($userinfo['is_admin'] == false && $workorder['user_id'] != $userinfo['id'] && $workorder['assigned_technician_id'] != $userinfo['id'])) {
        unauthorizedResponse();
    }

    $query .= " AND id = ?";
    $params[] = $workorderId;
    $requestType = 'by_workorder_id';

} elseif ($userId) {
    // 打印调试信息
    error_log("userinfo id: " . $userinfo['id'] . " userId: " . $userId);

    // 修正了类型比较
    if ($userinfo['is_admin'] == false && ((int)$userinfo['id'] !== $userId || $userinfo['role'] !== 'user')) {
        unauthorizedResponse();
    }
    if ($list === 'all') {
        $query .= " AND user_id = ?";
        $requestType = 'by_user_id_all';
    } else {
        $query .= " AND user_id = ? AND repair_status = 'Pending'";
        $requestType = 'by_user_id_pending';
    }
    $params[] = $userId;

} elseif ($technicianId) {
    if ($userinfo['is_admin'] == false && ((int)$userinfo['id'] !== $technicianId || $userinfo['role'] !== 'technician')) {
        unauthorizedResponse();
    }
    if ($list === 'all') {
        $query .= " AND assigned_technician_id = ?";
        $requestType = 'by_technician_id_all';
    } else {
        $query .= " AND assigned_technician_id = ? AND repair_status = 'Pending'";
        $requestType = 'by_technician_id_pending';
    }
    $params[] = $technicianId;

} else {
    if ($userinfo['is_admin'] == false) {
        unauthorizedResponse();
    }
    // 现在已经是获取全部工单了，但还是要根据 list 参数过滤一下结果
    if ($list === 'done') {
        $query .= " AND repair_status = 'Done'";
        $requestType = 'all_workorders_done';
    } elseif ($list === 'pending') {
        $query .= " AND repair_status = 'Pending'";
        $requestType = 'all_workorders_pending';
    } else {
        $requestType = 'all_workorders';
    }
    $query .= " LIMIT $limit OFFSET $offset";
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);

$workorders = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    "success" => true,
    "requesttype" => $requestType,
    "data" => $workorders ?: [],  //如果这人没工单就返回[]
    'page' => $page,
    'limit' => $limit
]);
?>