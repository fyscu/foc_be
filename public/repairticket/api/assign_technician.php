<?php
require_once 'config.php';

try {
    $data = getPostData();
    
    // 验证必需字段
    validateRequired($data, ['order_id', 'technician_id']);
    
    $pdo = getDBConnection();
    
    // 开始事务
    $pdo->beginTransaction();
    
    // 获取技术员信息
    $tech_sql = "SELECT name, phone FROM fyd_technicians WHERE id = :technician_id";
    $tech_stmt = $pdo->prepare($tech_sql);
    $tech_stmt->execute([':technician_id' => $data['technician_id']]);
    $technician = $tech_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$technician) {
        $pdo->rollBack();
        sendResponse(false, null, '技术员不存在');
    }
    
    // 更新订单状态和技术员
    $sql = "
        UPDATE fyd_orders 
        SET technician_id = :technician_id,
            technician1_name = :technician_name,
            technician1_phone = :technician_phone,
            status = 'processing', 
            updated_at = NOW() 
        WHERE id = :order_id
    ";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        ':technician_id' => $data['technician_id'],
        ':technician_name' => $technician['name'],
        ':technician_phone' => $technician['phone'],
        ':order_id' => $data['order_id']
    ]);
    
    if (!$result) {
        $pdo->rollBack();
        sendResponse(false, null, '分配技术员失败');
    }
    
    // 记录操作日志
    $log_sql = "
        INSERT INTO fyd_order_logs (order_id, action, new_status, technician_id, notes, created_at)
        VALUES (:order_id, 'assign_technician', 'processing', :technician_id, '分配技术员', NOW())
    ";
    $log_stmt = $pdo->prepare($log_sql);
    $log_stmt->execute([
        ':order_id' => $data['order_id'],
        ':technician_id' => $data['technician_id']
    ]);
    
    // 更新技术员当前订单数
    $update_tech_sql = "
        UPDATE fyd_technicians 
        SET current_orders = (
            SELECT COUNT(*) FROM fyd_orders 
            WHERE technician_id = :technician_id AND status = 'processing'
        )
        WHERE id = :technician_id
    ";
    $update_tech_stmt = $pdo->prepare($update_tech_sql);
    $update_tech_stmt->execute([':technician_id' => $data['technician_id']]);
    
    $pdo->commit();
    sendResponse(true, null, '技术员分配成功');
    
} catch (Exception $e) {
    $pdo->rollBack();
    sendResponse(false, null, '分配技术员失败: ' . $e->getMessage());
}
?>