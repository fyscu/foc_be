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
include('../../utils/gets.php');
require '../../utils/email.php';
require '../../utils/sms.php';

$json = file_get_contents('php://input');
$data = json_decode($json, true);

$order_id = $data['order_id'] ?? null;
$tvcode = $data['tvcode'] ?? null;
$order_hash = $data['order_hash'] ?? null;
$tid = isset($data['tid']) && !empty($data['tid']) ? $data['tid'] : $userinfo['id'];

$ticket = getTicketById($order_id);
$response = [];

if (!$userinfo['is_admin'] && $userinfo['role'] !== 'technician') {
    echo json_encode([
        'success' => false,
        'message' => 'Permission denied'
    ]);
    exit;
}

if (!$ticket) {
    echo json_encode([
        'success' => false,
        'message' => 'Ticket not found'
    ]);
    exit;
}

if ($order_hash) {
    // 优先看hash
    if ($ticket['order_hash'] !== $order_hash) {
        echo json_encode([
        'success' => false,
        'message' => 'Order hash mismatch'
        ]);
        exit;
    }
} elseif ($tvcode) {
    // 如果没有hash，则使用tvcode
    if ($ticket['transcode'] !== $tvcode) {
        echo json_encode([
        'success' => false,
        'message' => 'Transfer vcode mismatch'
        ]);
        exit;
    }
} else {
    // 如果两个都没传入，返回错误
    echo json_encode([
        'success' => false,
        'message' => 'Invalid code'
    ]);
    exit;
}

// 验证工单状态
if (in_array($ticket['repair_status'], ['Closed', 'Done', 'Canceled'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Order has closed'
    ]);
    exit;
}

$user = getUserById($ticket['user_id']);
$assigned_technician_id = $ticket['assigned_technician_id'];
$assigned_time = $ticket['assigned_time'];
$current_time = date('Y-m-d H:i:s');

$notification = new Email($config);
$sms = new Sms($config);
$newtvcode = rand(100000, 999999);

if (!empty($assigned_technician_id) && !empty($assigned_time)) {
    // 工单已被分配，转移工单给传入的技术员或当前技术员
    $updateSql = "UPDATE fy_workorders SET repair_status = 'Repairing', assigned_technician_id = :technician_id, assigned_time = :assigned_time, transcode = :newtvcode WHERE id = :id";
    $stmt = $pdo->prepare($updateSql);
    $stmt->execute([
        ':technician_id' => $tid,  
        ':assigned_time' => $current_time,
        ':newtvcode' => $newtvcode,
        ':id' => $order_id
    ]);
    
    $updateUserSql = "UPDATE fy_users SET available = 0 WHERE id = :technician_id";
    $stmt = $pdo->prepare($updateUserSql);
    $stmt->execute([':technician_id' => $tid]);

    // 检查原技术员是否还有其他Repairing状态的工单
    $checkSql = "SELECT COUNT(*) FROM fy_workorders WHERE assigned_technician_id = :technician_id AND repair_status = 'Repairing'";
    $stmt = $pdo->prepare($checkSql);
    $stmt->execute([':technician_id' => $assigned_technician_id]);
    $count = $stmt->fetchColumn();

    // 如果没有其他Repairing的工单，将原技术员设置为可用
    if ($count == 0) {
        $updateUserSql = "UPDATE fy_users SET available = 1 WHERE id = :technician_id";
        $stmt = $pdo->prepare($updateUserSql);
        $stmt->execute([':technician_id' => $assigned_technician_id]);
    }
    
    // 获取新技术员信息
    $newTechnician = getUserById($tid);

    // 发送给技术员
    $templateKey = 'assign_to_technician';
    $phoneNumber = $newTechnician['phone'];
    $templateParams = [$newTechnician['nickname'], $user['nickname'], $ticket['user_phone']];
    $sms->sendSms($templateKey, $phoneNumber, $templateParams);
    $notification->sendEmail($newTechnician['email'], "新的报修工单", "您有一个新的报修工单，工单编号：{$ticket['id']}。");

    // 发送给用户
    $templateKey = 'assign_to_user';
    $phoneNumber = $ticket['user_phone'];
    $templateParams = [$user['nickname'], $newTechnician['nickname'], $newTechnician['phone']];
    $response = $sms->sendSms($templateKey, $phoneNumber, $templateParams);
    $notification->sendEmail($user['email'], "报修工单已分配", "您的报修工单已分配给技术员，技术员编号：{$tid}。");

    // 记录转单
    $stmt = $pdo->prepare("INSERT INTO fy_transfer_record (time, type, fromuid, fromname, userid, username, tid, tname) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$current_time, 'transfer', $assigned_technician_id, getUserById($assigned_technician_id)['nickname'], $user['id'], $user['nickname'], $tid, $newTechnician['nickname']]);

    $response = [
        'success' => true,
        'message' => 'Order transferred successfully',
        'new_technician_id' => $tid,
        'new_assigned_time' => $current_time
    ];
} else {
    // 工单未分配，直接分配给传入的技术员或当前技术员
    $updateSql = "UPDATE fy_workorders SET repair_status = 'Repairing', assigned_technician_id = :technician_id, assigned_time = :assigned_time, transcode = :newtvcode WHERE id = :id";
    $stmt = $pdo->prepare($updateSql);
    $stmt->execute([
        ':technician_id' => $tid,  
        ':assigned_time' => $current_time,
        ':newtvcode' => $newtvcode,
        ':id' => $order_id
    ]);
    
    $updateUserSql = "UPDATE fy_users SET available = 0 WHERE id = :technician_id";
    $stmt = $pdo->prepare($updateUserSql);
    $stmt->execute([':technician_id' => $tid]);

    // 获取新技术员信息
    $newTechnician = getUserById($tid);

    // 发送给技术员
    $templateKey = 'assign_to_technician';
    $phoneNumber = $newTechnician['phone'];
    $templateParams = [$newTechnician['nickname'], $user['nickname'], $ticket['user_phone']];
    $sms->sendSms($templateKey, $phoneNumber, $templateParams);
    $notification->sendEmail($newTechnician['email'], "新的报修工单", "您有一个新的报修工单，工单编号：{$ticket['id']}。");

    // 发送给用户
    $templateKey = 'assign_to_user';
    $phoneNumber = $ticket['user_phone'];
    $templateParams = [$user['nickname'], $newTechnician['nickname'], $newTechnician['phone']];
    $response = $sms->sendSms($templateKey, $phoneNumber, $templateParams);
    $notification->sendEmail($user['email'], "报修工单已分配", "您的报修工单已分配给技术员，技术员编号：{$tid}。");

    // 记录分配
    $stmt = $pdo->prepare("INSERT INTO fy_transfer_record (time, type, fromuid, fromname, userid, username, tid, tname) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$current_time, 'assign', 100000, '系统', $user['id'], $user['nickname'], $tid, $newTechnician['nickname']]);

    $response = [
        'success' => true,
        'message' => 'Order assigned successfully',
        'technician_id' => $tid, 
        'assigned_time' => $current_time
    ];
}

echo json_encode($response);
?>