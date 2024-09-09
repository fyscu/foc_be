<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); 
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Max-Age: 86400");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0); // 提前结束响应，处理 OPTIONS 预检请求
}

include('../../db.php');
include('../../utils/token.php');
include('../../utils/headercheck.php');
include('../../utils/gets.php');

$user = $userinfo;

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if($data['image'] == ""){
    $data['image'] = "https://focapi.feiyang.ac.cn/v1/ticket/default.svg";
}

$requiredFields = [
    'purchase_date' => '购买日期',
    'phone' => '电话号码',
    'device_type' => '设备类型',
    'brand' => '品牌',
    'description' => '故障描述',
    'image' => '故障图片',
    'fault_type' => '故障类型',
    'qq' => 'QQ号码',
    'campus' => '校区'
];

$missingFields = [];

foreach ($requiredFields as $field => $chineseExplanation) {
    if (empty($data[$field])) {
        $missingFields[] = "{$chineseExplanation}-{$field}";
    }
}

if (!empty($missingFields)) {
    echo json_encode([
        'success' => false,
        'message' => '下列所需值缺失或为空：' . implode('、 ', $missingFields)
    ]);
    exit;
}

$uid = $user['id'];
$mpd = $data['purchase_date'];
$up = $data['phone'];
$dt = $data['device_type'];
$cb = $data['brand'];
$rd = $data['description'];
$ri = $data['image'];
$ft = $data['fault_type'];
$qq = $data['qq'];
$cp = $data['campus'];

// 生成订单hash
$combinedString = $uid . $mpd . $up . $dt . $cb . $rd . $ri . $ft . $qq . $cp;
$orderhash = hash('sha256', $combinedString);

// 检查该hash是否已存在
$checkStmt = $pdo->prepare("SELECT id FROM fy_workorders WHERE order_hash = ?");
$checkStmt->execute([$orderhash]);

if ($checkStmt->rowCount() > 0) {
    // 如果hash已存在，返回错误
    echo json_encode([
        'success' => false,
        'message' => 'order_exists'
    ]);
    exit;
}

// 如果ash不存在，插入新工单
$stmt = $pdo->prepare("INSERT INTO fy_workorders (user_id, machine_purchase_date, user_phone, device_type, computer_brand, repair_description, repair_status, repair_image_url, fault_type, qq_number, campus, order_hash) VALUES (?, ?, ?, ?, ?, ?, 'Pending', ?, ?, ?, ?, ?)");
$stmt->execute([$uid, $mpd, $up, $dt, $cb, $rd, $ri, $ft, $qq, $cp, $orderhash]);

// 获取新创建的工单ID
$workOrderId = $pdo->lastInsertId();

if($workOrderId){
    echo json_encode([
        'success' => true,
        'orderid' => $workOrderId
    ]);
} else {
    echo json_encode([
        'success' => false,
        'orderid' => ''
    ]);
}
?>