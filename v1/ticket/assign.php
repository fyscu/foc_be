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
            // 逻辑1：同校区匹配（优先级最高，始终允许）
            $isSameCampus = ($technician['campus'] == $workOrder['campus']);

            // 逻辑2：跨校区匹配
            $ticketAllowsDuo = ($workOrder['DuoCampus'] ?? 0) == 1;
            $techWantsDuo = ($technician['canDuo'] ?? 0) == 1;
            $isCrossCampusMatch = $ticketAllowsDuo && $techWantsDuo;
            
            return $isSameCampus || $isCrossCampusMatch;
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

        if (empty($technicianWeights)) {
            echo "工单 ".$workOrder['id']." 没有合适的技术员可分配<br>";
            continue;
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
        $htmlBody = "
        <html>
        <head>
            <style>
            body {
                font-family: Arial, sans-serif;
                background-color:#f5f5f5;
                margin:0;
                padding:0;
            }
            .ticket {
                background:#fff;
                border:1px solid #ddd;
                border-radius:10px;
                max-width:600px;
                margin:20px auto;
                padding:20px;
                box-shadow:0 2px 6px rgba(0,0,0,0.1);
            }
            .ticket-header {
                border-bottom:1px dashed #ccc;
                padding-bottom:10px;
                margin-bottom:15px;
            }
            .ticket-header h2 {
                margin:0;
                color:#007bff;
            }
            .section {
                margin-bottom:15px;
            }
            .section h3 {
                margin:0 0 8px 0;
                font-size:16px;
                color:#555;
                border-left:4px solid #007bff;
                padding-left:6px;
            }
            .row {
                display:flex;
                justify-content:space-between;
                margin-bottom:6px;
                font-size:14px;
            }
            .label {
                font-weight:bold;
                width:130px;
                color:#333;
            }
            .ticket-image {
                text-align:center;
                margin-top:10px;
            }
            .ticket-image img {
                max-width:100%;
                border-radius:6px;
                border:1px solid #ccc;
            }
            .ticket-footer {
                margin-top:15px;
                font-size:12px;
                color:#888;
                border-top:1px dashed #ccc;
                padding-top:8px;
                text-align:center;
            }
            </style>
        </head>
        <body>
            <div class='ticket'>
            <div class='ticket-header'>
                <h2>工单分配通知</h2>
                <p>亲爱的技术员<strong>{$chosenTechnician['nickname']}</strong>：</p>
                <p>有一个新的报修工单分配到您，工单信息如下：</p>
            </div>
            
            <!-- 工单信息部分 -->
            <div class='section'>
                <h3>工单信息</h3>
                <div class='row'><span class='label'>用户昵称：</span><span>{$user['nickname']}</span></div>
                <div class='row'><span class='label'>用户电话：</span><span>{$workOrder['user_phone']}</span></div>
                <div class='row'><span class='label'>其他联系方式：</span><span>{$workOrder['qq_number']}</span></div>
            </div>
            
            <!-- 设备/故障信息部分 -->
            <div class='section'>
                <h3>设备/故障信息</h3>
                <div class='row'><span class='label'>设备类型：</span><span>{$workOrder['device_type']}</span></div>
                <div class='row'><span class='label'>型号：</span><span>{$workOrder['model']}</span></div>
                <div class='row'><span class='label'>保修状态：</span><span>{$workOrder['warranty_status']}</span></div>
                <div class='row'><span class='label'>品牌：</span><span>{$workOrder['computer_brand']}</span></div>
                <div class='row'><span class='label'>故障类型：</span><span>{$workOrder['fault_type']}</span></div>
                <div class='row'><span class='label'>问题描述：</span><span>{$workOrder['repair_description']}</span></div>
            </div>
            <p>其他详细工单信息请前往小程序查看～</p>
            <p>请不要忘记和机主详细沟通机器问题哦～～</p>
            <p>请尽快联系用户！飞扬感谢您的付出 ：）</p>
            <div class='ticket-footer'>
                本邮件由飞扬俱乐部自动发送，请勿直接回复。
            </div>
            </div>
        </body>
        </html>";
        $notification->sendEmail($chosenTechnician['email'],"新工单分配通知",$htmlBody);
        // $notification->sendEmail($chosenTechnician['email'], "新的报修工单", "亲爱的技术员{$chosenTechnician['nickname']}，您有一个新的报修工单，工单编号：{$workOrder['id']}。用户联系方式：{$workOrder['user_phone']}，请尽快联系用户！飞扬感谢您的付出 ：）");
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