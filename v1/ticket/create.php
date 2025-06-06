<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); 
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Max-Age: 86400");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

include('../../db.php');
include('../../utils/token.php');
include('../../utils/headercheck.php');
include('../../utils/gets.php');

$user = $userinfo;

if ($user['role'] !== 'user') {
    echo json_encode([
        'success' => false,
        'message' => '仅用户可创建工单'
    ]);
    exit;
}

if ($user['available'] <= 0) {
    echo json_encode([
        'success' => false,
        'message' => '已达用户每周限额'
    ]);
    exit;
}

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if($data['image'] == ""){
    $data['image'] = "https://focapi.feiyang.ac.cn/v1/ticket/default.svg";
}
if($data['user_nick'] == ""){
    $data['user_nick'] = $user['nickname'];
}
if($data['model'] == ""){
    $data['model'] = "default";
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
$warranty = $data['warranty_status'];
$up = $data['phone'];
$dt = $data['device_type'];
$cb = $data['brand'];
$rd = $data['description'];
$ri = $data['image'];
$ft = $data['fault_type'];
$qq = $data['qq'];
$cp = $data['campus'];
$user_nick = $data['user_nick'];
$model = $data['model'];

$combinedString = $uid . $mpd . $up . $warranty . $dt . $cb . $rd . $ri . $ft . $qq . $cp . $user_nick . $model;
$orderhash = hash('sha256', $combinedString);

$tvcode = rand(100000, 999999);

$checkStmt = $pdo->prepare("SELECT id FROM fy_workorders WHERE order_hash = ?");
$checkStmt->execute([$orderhash]);

if ($checkStmt->rowCount() > 0) {
    echo json_encode([
        'success' => false,
        'message' => 'order_exists'
    ]);
    exit;
}

$stmt = $pdo->prepare("INSERT INTO fy_workorders (user_id, machine_purchase_date, user_phone, warranty_status, device_type, computer_brand, repair_description, repair_status, repair_image_url, fault_type, qq_number, campus, order_hash, transcode, user_nick, model) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->execute([$uid, $mpd, $up, $warranty, $dt, $cb, $rd, $ri, $ft, $qq, $cp, $orderhash, $tvcode, $user_nick, $model]);

$workOrderId = $pdo->lastInsertId();

if ($workOrderId) {
    // 更新用户可用配额
    $updateStmt = $pdo->prepare("UPDATE fy_users SET available = available - 1 WHERE id = ?");
    $updateStmt->execute([$uid]);

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