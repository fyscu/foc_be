<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
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
if (!isset($data['order_id']) || !isset($data['diagnosis']) || !isset($data['solution']) || 
    !isset($data['technician_signature']) || !isset($data['technician1_time'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '缺少必要参数']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // 开始事务
    $pdo->beginTransaction();
    
    // 更新订单状态为待取机，并保存维修记录
    $stmt = $pdo->prepare("
        UPDATE fyd_orders 
        SET status = 'ready', 
            diagnosis = :diagnosis,
            solution = :solution,
            technician_signature = :technician_signature,
            technician1_time = :technician1_time
        WHERE id = :id AND status = 'processing'
    ");
    
    $stmt->execute([
        ':id' => $data['order_id'],
        ':diagnosis' => $data['diagnosis'],
        ':solution' => $data['solution'],
        ':technician_signature' => $data['technician_signature'],
        ':technician1_time' => $data['technician1_time']
    ]);
    
    // 检查是否成功更新
    if ($stmt->rowCount() === 0) {
        throw new Exception('订单不存在或状态不是维修中');
    }
    
    // 提交事务
    $pdo->commit();
    
    // 返回成功响应
    echo json_encode(['success' => true, 'message' => '维修记录已更新，订单状态已更改为待取机']);
    
} catch (Exception $e) {
    // 回滚事务
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    
    // 返回错误响应
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}