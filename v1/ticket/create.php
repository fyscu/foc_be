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
include('../../utils/gets.php');

$user = $userinfo;

if ($user['role'] !== 'user') {
    echo json_encode([
        'success' => false,
        'message' => '仅用户可创建工单'
    ]);
    exit;
}

function rejectPausedRepairCreation() {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'status' => 'repair_paused',
        'message' => '当前暂停报修，请稍后再试',
    ]);
    exit;
}

function repairCreationIsEnabled(PDO $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT data FROM fy_confs WHERE name = ? LIMIT 2");
        $stmt->execute(['Global_Flag']);
        $values = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        return false;
    }

    return count($values) === 1 && trim((string) $values[0]) === '1';
}

if (!repairCreationIsEnabled($pdo)) {
    rejectPausedRepairCreation();
}

if ($user['available'] <= 0) {
    echo json_encode([
        'success' => false,
        'message' => '已达用户每周限额'
    ]);
    exit;
}

// 单用户未完结上限：含 Pending/Repairing/UserConfirming/TechConfirming（含加急）
$maxPerUser = (int) ($config['info']['max_pending_per_user'] ?? 1);
$stmt = $pdo->prepare("SELECT COUNT(*) FROM fy_workorders WHERE user_id = ? AND repair_status IN ('Pending','Repairing','UserConfirming','TechConfirming')");
$stmt->execute([$user['id']]);
if ((int) $stmt->fetchColumn() >= $maxPerUser) {
    echo json_encode([
        'success' => false,
        'status' => 'user_pending_limit',
        'message' => '您还有未完结的报修单，待处理完再提交新单哦～'
    ]);
    exit;
}

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!is_array($data)) {
    echo json_encode([
        'success' => false,
        'message' => '请求数据格式错误'
    ]);
    exit;
}

$campus = $data['campus'] ?? null;
if (is_string($campus)) {
    $campus = preg_replace('/^[\s\x{00A0}\x{3000}]+|[\s\x{00A0}\x{3000}]+$/u', '', $campus);
}

$allowedCampuses = ['江安', '望江', '华西', '线下'];
if (!is_string($campus) || !in_array($campus, $allowedCampuses, true)) {
    echo json_encode([
        'success' => false,
        'message' => '校区-campus无效'
    ]);
    exit;
}
$data['campus'] = $campus;

// 每校区 Pending 上限：只算普通 Pending（urgent=0），加急单不占名额
$maxGlobal = (int) ($config['info']['max_pending_global'] ?? 15);
$stmt = $pdo->prepare("SELECT COUNT(*) FROM fy_workorders WHERE campus = ? AND repair_status = 'Pending' AND urgent = 0");
$stmt->execute([$campus]);
if ((int) $stmt->fetchColumn() >= $maxGlobal) {
    echo json_encode([
        'success' => false,
        'status' => 'global_pending_limit',
        'message' => '当前报修排队较多，请稍后再试～'
    ]);
    exit;
}

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

// 这里就不给一个个注释了，可以参考前端和这里的英文释义
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
$duo = $data['DuoCampus'];
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

$stmt = $pdo->prepare("INSERT INTO fy_workorders (user_id, machine_purchase_date, user_phone, warranty_status, device_type, computer_brand, repair_description, repair_status, repair_image_url, fault_type, qq_number, campus, DuoCampus, order_hash, transcode, user_nick, model) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->execute([$uid, $mpd, $up, $warranty, $dt, $cb, $rd, $ri, $ft, $qq, $cp, $duo, $orderhash, $tvcode, $user_nick, $model]);

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
