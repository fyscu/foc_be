<?php
require_once 'config.php';

try {
    $data = getPostData();
    
    // 验证必需字段
    validateRequired($data, ['id', 'status']);
    
    $pdo = getDBConnection();
    
    // 验证状态值
    $valid_statuses = ['pending', 'processing', 'ready', 'completed'];
    if (!in_array($data['status'], $valid_statuses)) {
        sendResponse(false, null, '无效的状态值');
    }
    
    // 更新订单状态
    $sql = "UPDATE fyd_orders SET status = :status, updated_at = NOW() WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        ':status' => $data['status'],
        ':id' => $data['id']
    ]);
    
    if ($result) {
        // 记录状态变更日志
        $log_sql = "
            INSERT INTO fyd_order_logs (order_id, action, old_status, new_status, created_at)
            SELECT :order_id, 'status_change', 
                   (SELECT status FROM fyd_orders WHERE id = :order_id2), 
                   :new_status, NOW()
        ";
        $log_stmt = $pdo->prepare($log_sql);
        $log_stmt->execute([
            ':order_id' => $data['id'],
            ':order_id2' => $data['id'],
            ':new_status' => $data['status']
        ]);
        
        sendResponse(true, null, '状态更新成功');
    } else {
        sendResponse(false, null, '状态更新失败');
    }
    
} catch (Exception $e) {
    sendResponse(false, null, '更新状态失败: ' . $e->getMessage());
}
?>