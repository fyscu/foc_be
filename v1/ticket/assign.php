<?php

include('../../db.php');
require '../../utils/email.php';
require '../../utils/sms.php';
include('../../utils/gets.php');

function assignWorkOrders() {
    $config = include('../../config.php');
    global $pdo;

    // 获取所有未分配的工单
    $stmt = $pdo->query("SELECT * FROM fy_workorders WHERE assigned_technician_id IS NULL");
    $workOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 获取所有空闲的技术员
    $stmt = $pdo->query("SELECT * FROM fy_users WHERE role = 'technician' AND available = 1");
    $technicians = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($workOrders as $workOrder) {
        if (empty($technicians)) {
            break;
        }

        // 做校区匹配
        $availableTechnicians = array_filter($technicians, function($technician) use ($workOrder) {
            return $technician['campus'] == $workOrder['campus'];
        });

        if (empty($availableTechnicians)) {
            continue;
        } 

        // 随机分配一个技术员
        $technician = $availableTechnicians[array_rand($availableTechnicians)];
        $assignedTime = date('Y-m-d H:i:s');

        // 更新工单信息并标记技术员不可用
        $stmt = $pdo->prepare("UPDATE fy_workorders SET assigned_technician_id = ?, assigned_time = ? WHERE id = ?");
        $stmt->execute([$technician['id'], $assignedTime, $workOrder['id']]);
        $stmt = $pdo->prepare("UPDATE fy_users SET available = 0 WHERE id = ?");
        $stmt->execute([$technician['id']]);

        // 从技术员列表中移除该技术员
        $technicians = array_filter($technicians, function($t) use ($technician) {
            return $t['id'] != $technician['id'];
        });

        // 获取用户信息
        $user = getUserById($workOrder['user_id']);

        // 发送短信和邮件通知
        $notification = new Email($config);
        $sms = new Sms($config);

        // 发送给技术员
        $templateKey = 'assign_to_technician'; // 选择模板
        $phoneNumber = $technician['phone']; // 接收短信的手机号
        $templateParams = [$workOrder['id']]; // 模板参数
        $sms->sendSms($templateKey, $phoneNumber, $templateParams);
        $notification->sendEmail($technician['email'], "新的报修工单", "您有一个新的报修工单，工单编号：{$workOrder['id']}。");

        // 发送给用户
        $templateKey = 'assign_to_user'; // 选择模板
        $phoneNumber = $user['phone']; // 接收短信的手机号
        $templateParams = [$workOrder['id']]; // 模板参数
        $response = $sms->sendSms($templateKey, $phoneNumber, $templateParams);
        $notification->sendEmail($user['email'], "报修工单已分配", "您的报修工单已分配给技术员，技术员编号：{$technician['id']}。");
        if($response){
            echo "工单 ".$workOrder['id']." 已分配给技术员 ".$technician['nickname'];
            echo "<br>";
        } else {
            echo "当前无符合条件的工单可供分配";
        }

    }
}

// 定时任务调用
assignWorkOrders();
?>
