
<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include('../../db.php');
include('../../utils/token.php');
include('../../utils/headercheck.php');

function createWorkOrder() {
    global $pdo;
    
    $uid = $_POST['uid'];
    $mpd = $_POST['purchase_date'];
    $up = $_POST['phone'];
    $dt = $_POST['device_type'];
    $cb = $_POST['brand'];
    $rd = $_POST['description'];
    $ri = $_POST['image'];
    $ft = $_POST['fault_type'];
    $qq = $_POST['qq'];
    $cp = $_POST['campus'];

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