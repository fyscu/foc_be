<?php
require_once 'config.php';

try {
    $data = getPostData();
    
    // 验证必需字段
    validateRequired($data, ['order_id', 'action']);
    
    $pdo = getDBConnection();
    
    // 插入操作日志
    $sql = "
        INSERT INTO fyd_order_logs (
            order_id, action, old_status, new_status, 
            technician_id, notes, created_at
        ) VALUES (
            :order_id, :action, :old_status, :new_status,
            :technician_id, :notes, NOW()
        )
    ";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        ':order_id' => $data['order_id'],
        ':action' => $data['action'],
        ':old_status' => $data['old_status'] ?? null,
        ':new_status' => $data['new_status'] ?? null,
        ':technician_id' => $data['technician_id'] ?? null,
        ':notes' => $data['notes'] ?? ''
    ]);
    
    if ($result) {
        sendResponse(true, null, '操作日志记录成功');
    } else {
        sendResponse(false, null, '操作日志记录失败');
    }
    
} catch (Exception $e) {
    sendResponse(false, null, '记录操作日志失败: ' . $e->getMessage());
}
?>