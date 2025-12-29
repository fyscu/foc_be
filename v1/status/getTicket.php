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
include('../../utils/headercheck.php'); //永远记得这里通过access_token给了$userinfo的全部数据
include('../../utils/gets.php');
include('../../utils/qrcode.php');
include('../../utils/qiniu_url.php');

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
$campus = isset($_GET['campus']) ? $_GET['campus'] : null;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 1000; 
$offset = ($page - 1) * $limit;

// 新增排序参数
$sortBy = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'id';  // 默认为 id
$order = isset($_GET['order']) && strtolower($_GET['order']) === 'desc' ? 'DESC' : 'DESC';  // 默认降序

$query = "SELECT * FROM fy_workorders WHERE 1=1";
$params = [];
$requestType = '';

// 构建查询和请求种类
if ($workorderId) {
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
    if ($userinfo['is_admin'] == false && ((int)$userinfo['id'] !== $userId || $userinfo['role'] !== 'user')) {
        unauthorizedResponse();
    }
    if ($list === 'pending') {
        $query .= " AND user_id = ? AND repair_status = 'Pending'";
        $requestType = 'by_user_id_pending';
    } else {       
        $query .= " AND user_id = ?";
        $requestType = 'by_user_id_all';
    }
    $params[] = $userId;

} elseif ($campus) {
    if ($userinfo['is_admin'] == false && ((int)$userinfo['id'] !== $userId || $userinfo['role'] !== 'user')) {
        unauthorizedResponse();
    }
    $query .= " AND campus = ?";
    $requestType = 'by_campus';
    $params[] = $campus;

} elseif ($technicianId) {
    if ($userinfo['is_admin'] == false && ((int)$userinfo['id'] !== $technicianId || $userinfo['role'] !== 'technician')) {
        unauthorizedResponse();
    }
    if ($list === 'all') {
        $query .= " AND assigned_technician_id = ? AND repair_status = 'Repairing'";
        $requestType = 'by_technician_id_pending';
    } else {
        $query .= " AND assigned_technician_id = ?";
        $requestType = 'by_technician_id_all';
    }
    $params[] = $technicianId;

} else {
    if ($userinfo['is_admin'] == false) {
        unauthorizedResponse();
    }
    if ($list === 'done') {
        $query .= " AND repair_status = 'Done'";
        $requestType = 'all_workorders_done';
    } elseif ($list === 'pending') {
        $query .= " AND repair_status = 'Pending'";
        $requestType = 'all_workorders_pending';
    } else {
        $requestType = 'all_workorders';
    }
}

// 应用排序
$query .= " ORDER BY $sortBy $order LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($query);
$stmt->execute($params);

$workorders = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($workorders as &$workorder) {
    $workorderId = $workorder['id']; 
    $workorderHash = $workorder['order_hash'];
    if (isset($workorder['assigned_technician_id']) && $workorder['assigned_technician_id'] !== '' && !is_null($userId)) {
        $techQuery = "SELECT nickname,phone FROM fy_users WHERE id = ?";
        $techStmt = $pdo->prepare($techQuery);
        $techStmt->execute([$workorder['assigned_technician_id']]);
        $technicianInfo = $techStmt->fetch(PDO::FETCH_ASSOC);
        $workorder['assigned_technician_phone'] = $technicianInfo['phone'];
        $workorder['assigned_technician_nickname'] = $technicianInfo['nickname'];
    }
    $workorder['assigned_technician_id'] = $workorder['assigned_technician_nickname'] . ' - ' . $workorder['assigned_technician_phone'];
    if ($workorder['repair_image_url'] != 'https://focapp.feiyang.ac.cn/public/ticketdefault.svg') $workorder['repair_image_url'] = generatePrivateLink($workorder['repair_image_url']);
    $workorder['complete_image_url'] = generatePrivateLink($workorder['complete_image_url']);

    if ($workorder['repair_status'] == "Closed" || $workorder['repair_status'] == "Done" || $workorder['repair_status'] == "Canceled"){
        $workorder['qrcode_url'] = "";
    } else {
        $qrcodeData = "[give];$workorderId;$workorderHash";
        $qrcode64 = generateQrCodeBase64($qrcodeData);
        $workorder['qrcode_url'] = $qrcode64;
    }
}

echo json_encode([
    "success" => true,
    "requesttype" => $requestType,
    "data" => $workorders ?: [],  //如果这人没工单就返回[]
    'page' => $page,
    'limit' => $limit
]);
?>