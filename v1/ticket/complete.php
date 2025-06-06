<?php
// 完成工单代码
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
require '../../utils/email.php';
require '../../utils/sms.php';
include('../../utils/token.php');
include('../../utils/headercheck.php');
include('../../utils/gets.php');

$json = file_get_contents('php://input');
$data = json_decode($json, true);
$workOrderId = isset($data['order_id']) ? $data['order_id'] : null;

try {
    // 验证 Token 并获取用户信息
    $authinfo = getUserByaccesstoken($token);

    // 获取该工单对应的技术员和用户信息
    $stmt = $pdo->prepare("SELECT assigned_technician_id, user_id FROM fy_workorders WHERE id = ?");
    $stmt->execute([$workOrderId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        // 工单不存在
        echo json_encode(['success' => false, 'status' => 'ticket not found']);
        exit;
    }

    $technicianId = $row['assigned_technician_id'];
    $userId = $row['user_id'];

    // 检查是否是技术员或用户本人
    if ($authinfo['id'] !== $technicianId && $authinfo['id'] !== $userId) {
        echo json_encode(['success' => false, 'status' => 'Permission denied']);
        exit;
    }

    // 更新工单的维修状态和完成时间
    $stmt = $pdo->prepare("UPDATE fy_workorders SET repair_status = 'Done', completion_time = NOW() WHERE id = ?");
    $stmt->execute([$workOrderId]);

    // 更新技术员的最后维修时间（last_time）
    $stmt = $pdo->prepare("UPDATE fy_users SET available = 1, last_time = NOW() WHERE id = ?");
    $stmt->execute([$technicianId]);

    // 获取用户信息
    $user = getUserById($userId);

    // 发送通知
    $config = include('../../config.php');
    $notification = new Email($config);
    $sms = new Sms($config);
    $templateKey = 'completion'; // 选择模板
    $phoneNumber = $user['phone']; // 接收短信的手机号

    // 发送短信和邮件
    $sms->sendSms($templateKey, $phoneNumber, []);
    $notification->sendEmail($user['email'], "报修工单已完成", "您的报修工单 单号：$workOrderId 已由技术员维修完成，请及时取回");

    // 返回成功消息
    echo json_encode(['success' => true, 'status' => 'ticket completed']);
} catch (PDOException $e) {
    // 更新失败时返回错误消息
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['success' => false, 'status' => 'unknown_error']);
    exit();
}
?>