<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 处理预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'config.php';

try {
    // 只允许POST请求
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('只允许POST请求');
    }

    // 获取POST数据
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('无效的JSON数据');
    }

    // 验证必需参数
    if (empty($input['order_id'])) {
        throw new Exception('订单ID不能为空');
    }

    $orderId = $input['order_id'];

    // 连接数据库
    $pdo = getDBConnection();

    // 检查订单是否存在
    $checkStmt = $pdo->prepare("SELECT id, order_number, customer_name, customer_phone FROM fyd_orders WHERE id = ?");
    $checkStmt->execute([$orderId]);
    $order = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception('订单不存在');
    }

    // 更新订单的短信发送状态
    $updateStmt = $pdo->prepare("
        UPDATE fyd_orders 
        SET sms_sent = 1, sms_sent_at = NOW() 
        WHERE id = ?
    ");
    
    $result = $updateStmt->execute([$orderId]);

    if (!$result) {
        throw new Exception('更新短信发送状态失败');
    }

    // 记录短信发送日志
    $logStmt = $pdo->prepare("
        INSERT INTO fyd_sms_logs (order_id, phone, content, status, sent_at, created_at) 
        VALUES (?, ?, ?, 'sent', NOW(), NOW())
    ");
    
    $smsContent = "【飞扬俱乐部】您好{$order['customer_name']}，您的设备（订单号：{$order['order_number']}）已维修完成，请及时到现场取回。如有疑问请联系我们。";
    
    $logStmt->execute([
        $orderId,
        $order['customer_phone'],
        $smsContent
    ]);

    // 返回成功响应
    echo json_encode([
        'success' => true,
        'message' => '短信发送状态已更新',
        'data' => [
            'order_id' => $orderId,
            'order_number' => $order['order_number'],
            'customer_name' => $order['customer_name'],
            'sms_sent' => true,
            'updated_at' => date('Y-m-d H:i:s')
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // 返回错误响应
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>