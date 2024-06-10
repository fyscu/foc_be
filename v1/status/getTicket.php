<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
$config = include('../../config.php');
include('../../db.php');
require '../../utils/email.php';
require '../../utils/sms.php';
include('../../utils/token.php');
include('../../utils/headercheck.php');
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
// 这里是有一个从../../utils/token带来的参数$userinfo变量，他通过at获取了发起该请求的用户的所有信息，就不用再进行数据库io了
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
    $query .= " AND id = ?";
    $params[] = $workorderId;
    $requestType = 'by_workorder_id';
} elseif ($userId) {
    if ($userinfo['role'] !== 'admin' && ($userinfo['id'] !== $userId || $userinfo['role'] !== 'user')) {
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
    if ($userinfo['role'] !== 'admin' && ($userinfo['id'] !== $technicianId || $userinfo['role'] !== 'technician')) {
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
    if ($userinfo['role'] !== 'admin') {
        unauthorizedResponse();
    }
    // 现在已经是获取全部工单了，但还是要根据 list 参数过滤一下结果
    if ($list === 'done') {
        $query .= " AND repair_status = 'Done'";
        $query .= " LIMIT $limit OFFSET $offset";
        $requestType = 'all_workorders_done';
    } elseif ($list === 'pending') {
        $query .= " AND repair_status = 'Pending'";
        $query .= " LIMIT $limit OFFSET $offset";
        $requestType = 'all_workorders_pending';
    } else {
        $query .= " LIMIT $limit OFFSET $offset";
        $requestType = 'all_workorders';
    }
    
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);

$workorders = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    "success" => true,
    "requesttype" => $requestType,
    "data" => $workorders,
    'page' => $page,
    'limit' => $limit
]);  
?>
