
<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include('../../db.php');
include('../../utils/token.php');
//include('../../utils/headercheck.php');
include('../../utils/gets.php');
$openid = "1";
$user = getUserByOpenid($openid);
    //global $pdo;

    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
        
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

    // $uid = $user['id'];
    $uid = 1;
    $mpd = $data['purchase_date'];
    $up = $data['phone'];
    $dt = $data['device_type'];
    $cb = $data['brand'];
    $rd = $data['description'];
    $ri = $data['image'];
    $ft = $data['fault_type'];
    $qq = $data['qq'];
    $cp = $data['campus'];
    
    $stmt = $pdo->prepare("INSERT INTO fy_workorders (user_id, machine_purchase_date, user_phone, device_type, computer_brand, repair_description, repair_status, repair_image_url, fault_type, qq_number, campus) VALUES (?, ?, ?, ?, ?, ?, 'Pending', ?, ?, ?, ?)");
    $stmt->execute([$uid, $mpd, $up, $dt, $cb, $rd, $ri, $ft, $qq, $cp]);

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