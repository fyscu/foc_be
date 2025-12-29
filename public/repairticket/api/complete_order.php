<?php
require_once 'config.php';

// 检查请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '不支持的请求方法']);
    exit;
}

// 获取请求数据
$data = json_decode(file_get_contents('php://input'), true);

// 验证必要参数
if (!isset($data['id']) || !isset($data['pickup_time']) || !isset($data['customer_signature'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '缺少必要参数']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // 开始事务
    $pdo->beginTransaction();
    
    // 更新订单状态为已完成
    $stmt = $pdo->prepare("
        UPDATE fyd_orders 
        SET status = 'completed', 
            pickup_time = :pickup_time,
            customer_signature = :customer_signature,
            completion_time = NOW()
        WHERE id = :id AND status = 'ready'
    ");
    
    $stmt->execute([
        ':id' => $data['id'],
        ':pickup_time' => $data['pickup_time'],
        ':customer_signature' => $data['customer_signature']
    ]);
    
    // 检查是否成功更新
    if ($stmt->rowCount() === 0) {
        throw new Exception('订单不存在或状态不是待取机');
    }
    
    // 提交事务
    $pdo->commit();
    
    // 返回成功响应
    echo json_encode(['success' => true, 'message' => '订单已完成']);
    
} catch (Exception $e) {
    // 回滚事务
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    
    // 返回错误响应
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}