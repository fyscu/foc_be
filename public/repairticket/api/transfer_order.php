<?php
require_once 'config.php';

try {
    $data = getPostData();
    
    // 验证必需字段
    validateRequired($data, ['order_id', 'to_technician_id']);
    
    $pdo = getDBConnection();
    
    // 开始事务
    $pdo->beginTransaction();
    
    // 获取订单当前信息
    $order_sql = "SELECT * FROM fyd_orders WHERE id = :order_id";
    $order_stmt = $pdo->prepare($order_sql);
    $order_stmt->execute([':order_id' => $data['order_id']]);
    $order = $order_stmt->fetch();
    
    if (!$order) {
        $pdo->rollBack();
        sendResponse(false, null, '订单不存在');
    }
    
    $from_technician_id = $data['from_technician_id'] ?? $order['technician_id'];
    $to_technician_id = $data['to_technician_id'];
    
    // 更新订单的技术员
    $update_sql = "
        UPDATE fyd_orders 
        SET technician_id = :to_technician_id, updated_at = NOW() 
        WHERE id = :order_id
    ";
    $update_stmt = $pdo->prepare($update_sql);
    $result = $update_stmt->execute([
        ':to_technician_id' => $to_technician_id,
        ':order_id' => $data['order_id']
    ]);
    
    if (!$result) {
        $pdo->rollBack();
        sendResponse(false, null, '转单失败');
    }
    
    // 记录转单日志
    $log_sql = "
        INSERT INTO fyd_order_logs (
            order_id, action, old_status, new_status, 
            technician_id, notes, created_at
        ) VALUES (
            :order_id, 'transfer_order', :status, :status,
            :to_technician_id, :notes, NOW()
        )
    ";
    $log_stmt = $pdo->prepare($log_sql);
    $log_stmt->execute([
        ':order_id' => $data['order_id'],
        ':status' => $order['status'],
        ':to_technician_id' => $to_technician_id,
        ':notes' => "订单从技术员 {$from_technician_id} 转移到技术员 {$to_technician_id}"
    ]);
    
    // 更新原技术员的订单数
    if ($from_technician_id) {
        $update_from_sql = "
            UPDATE fyd_technicians 
            SET current_orders = (
                SELECT COUNT(*) FROM fyd_orders 
                WHERE technician_id = :technician_id AND status = 'processing'
            )
            WHERE id = :technician_id
        ";
        $update_from_stmt = $pdo->prepare($update_from_sql);
        $update_from_stmt->execute([':technician_id' => $from_technician_id]);
    }
    
    // 更新新技术员的订单数
    $update_to_sql = "
        UPDATE fyd_technicians 
        SET current_orders = (
            SELECT COUNT(*) FROM fyd_orders 
            WHERE technician_id = :technician_id AND status = 'processing'
        )
        WHERE id = :technician_id
    ";
    $update_to_stmt = $pdo->prepare($update_to_sql);
    $update_to_stmt->execute([':technician_id' => $to_technician_id]);
    
    $pdo->commit();
    sendResponse(true, null, '转单成功');
    
} catch (Exception $e) {
    $pdo->rollBack();
    sendResponse(false, null, '转单失败: ' . $e->getMessage());
}
?>