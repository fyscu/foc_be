<?php
$config = include('../../config.php');
include('../../db.php');
require '../../utils/email.php';
require '../../utils/sms.php';
include('../../utils/gets.php');
include('../../utils/subcribenotice.php');

$ifcool = $config['info']['ticketcooldown'];
$actioncode = $_GET['token'] ?? null;
if ($actioncode !== $config['info']['actioncode']) {
    echo json_encode([
        'success' => false,
        'message' => "Bad adtion code"
    ]);
    exit;
}

function assignWorkOrders() {
    $config = include('../../config.php');
    global $pdo;

    // 获取所有未分配的工单
    $stmt = $pdo->query("SELECT * FROM fy_workorders WHERE repair_status = 'Pending'");
    $workOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 获取所有空闲的技术员，并且过滤掉上次维修时间在一天内的技术员
    if($config['info']['ticketcooldown']){
        $cooldownDays = $config['info']['ticketcooldowndays'];
        $stmt = $pdo->query("SELECT * FROM fy_users WHERE role = 'technician' AND available = 1 AND immed = 1 AND (last_time IS NULL OR last_time <= NOW() - INTERVAL $cooldownDays DAY)");
    } else {
        $stmt = $pdo->query("SELECT * FROM fy_users WHERE role = 'technician' AND available = 1 AND immed = 1");
    }
       $technicians = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($workOrders as $workOrder) {
        if (empty($technicians)) {
            echo "当前无空闲技术员可分配<br>";
            continue;
        }

        // 校区匹配
        $availableTechnicians = array_filter($technicians, function($technician) use ($workOrder) {
            return $technician['campus'] == $workOrder['campus'];
        });

        if (empty($availableTechnicians)) {
            echo "工单 ".$workOrder['id']." 没有符合校区的技术员<br>";
            continue;
        }

        // 计算技术员接单权重
        $technicianWeights = [];
        foreach ($availableTechnicians as $technician) {
            switch ($technician['wants']) {
                case 'a':
                    $weight = 0;
                    break;
                case 'b':
                    $weight = 25;
                    break;
                case 'c':
                    $weight = 50;
                    break;
                case 'd':
                    $weight = 75;
                    break;
                case 'e':
                    $weight = 100;
                    break;
                default:
                    $weight = 0;
            }

            if ($weight > 0) {
                $technicianWeights[] = ['technician' => $technician, 'weight' => $weight];
            }
        }

        $totalWeight = array_sum(array_column($technicianWeights, 'weight'));
        $random = mt_rand(1, $totalWeight);

        $chosenTechnician = null;
        foreach ($technicianWeights as $entry) {
            $random -= $entry['weight'];
            if ($random <= 0) {
                $chosenTechnician = $entry['technician'];
                break;
            }
        }

        // 分配工单
        $assignedTime = date('Y-m-d H:i:s');

        // 更新工单信息并标记技术员不可用
        $stmt = $pdo->prepare("UPDATE fy_workorders SET assigned_technician_id = ?, assigned_time = ?, repair_status = ? WHERE id = ?");
        $stmt->execute([$chosenTechnician['id'], $assignedTime, "Repairing", $workOrder['id']]);

        // 将技术员的 available 设置为 0
        $stmt = $pdo->prepare("UPDATE fy_users SET available = 0 WHERE id = ?");
        $stmt->execute([$chosenTechnician['id']]);

        // 记录分配日志并输出调试信息
        echo "工单 ".$workOrder['id']." 已分配给技术员 ".$chosenTechnician['nickname']."<br>";

        // 获取用户信息
        $user = getUserById($workOrder['user_id']);

        // 发送短信和邮件通知
        $notification = new Email($config);
        $sms = new Sms($config);
        $wechat = new SubscribeNotifier($config['wechat']['app_id'], $config['wechat']['app_secret']);

        // 发送给技术员
        $templateKey = 'assign_to_technician';
        $phoneNumber = $chosenTechnician['phone'];
        $templateParams = ['tech' => $chosenTechnician['nickname'], 'mate' => $user['nickname'], 'maten' => $workOrder['user_phone']];
        $response = $sms->sendSms($templateKey, $phoneNumber, $templateParams);
        $notification->sendEmail($chosenTechnician['email'], "新的报修工单", "亲爱的技术员{$chosenTechnician['nickname']}，您有一个新的报修工单，工单编号：{$workOrder['id']}。用户联系方式：{$workOrder['user_phone']}，请尽快联系用户！飞扬感谢您的付出 ：）");
        $wechat->send($chosenTechnician['openid'], 'KMe-rYXD_Js_X3oE9_t6qMoa6DMm07Dfzeq94bsMvxg', 'pages/homePage/ticketDetail/index?id='.$workOrder['id'].'&role=technician',
        ['character_string1' => $workOrder['id'],
        'short_thing2' => $user['nickname'], 
        'thing4' => $workOrder['fault_type'],
        'time6' => $workOrder['create_time'],
        'thing11' => '联系方式：'.$workOrder['qq_number']]);

        // 发送给用户
        $templateKey = 'assign_to_user';
        $phoneNumber = $user['phone'];
        $templateParams = ['mate' => $user['nickname'], 'tech' => $chosenTechnician['nickname'], 'techn' => $chosenTechnician['phone']];
        $response = $sms->sendSms($templateKey, $phoneNumber, $templateParams);
        $notification->sendEmail($user['email'], "报修工单已分配", "您的报修工单已分配给技术员，技术员昵称：{$chosenTechnician['nickname']}。技术员联系方式：{$chosenTechnician['phone']}。由于技术员均为在校学生，消息回复与通知可能不及时，请您谅解！");
        $wechat->send($user['openid'], 'FGhVRnNp7C4580nyAXMOqSvSZCNG36cd6nEInS_RVCs', 'pages/homePage/ticketDetail/index?id='.$workOrder['id'].'&role=user',
        ['thing2' => $workOrder['fault_type'],
        'phone_number5' => $chosenTechnician['phone'], 
        'thing10' => '工单号：'.$workOrder['id']]);

        if ($response) {
            $stmt = $pdo->prepare("INSERT INTO fy_transfer_record (ticketid, time, type, fromuid, fromname, userid, username, tid, tname) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$workOrder['id'], $assignedTime, 'assign', 100000, '系统', $user['id'], $user['nickname'], $chosenTechnician['id'], $chosenTechnician['nickname']]);
            echo "通知已发送给技术员 ".$chosenTechnician['nickname']." 和用户 ".$user['nickname']."<br>";
        } else {
            echo "通知发送失败<br>";
        }

        // 从技术员列表中移除已分配的技术员
        $technicians = array_filter($technicians, function($t) use ($chosenTechnician) {
            return $t['id'] != $chosenTechnician['id'];
        });
    }
}

// 定时任务调用
assignWorkOrders();
?>