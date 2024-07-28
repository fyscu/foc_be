
<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include('../../db.php');
include('../../utils/token.php');
include('../../utils/headercheck.php');

function createWorkOrder() {
    global $pdo;

    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    $uid = $data['uid'];
    $mpd = $data['purchase_date'];
    $up = $data['phone'];
    $dt = $data['device_type'];
    $cb = $data['brand'];
    $rd = $data['description'];
    $ri = $data['image'];
    $ft = $data['fault_type'];
    $qq = $data['qq'];
    $cp = $data['campus'];
    // 上面的这个变量解释应该还可以吧
    // 插入工单数据
    $stmt = $pdo->prepare("INSERT INTO fy_workorders (user_id, machine_purchase_date, user_phone, device_type, computer_brand, repair_description, repair_status, repair_image_url, fault_type, qq_number, campus) VALUES (?, ?, ?, ?, ?, ?, 'Pending', ?, ?, ?, ?)");
    $stmt->execute([$uid,$mpd,$up,$dt,$cb,$rd,$ri,$ft,$qq,$cp]);

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
}

// 处理POST请求
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    createWorkOrder();
}

?>