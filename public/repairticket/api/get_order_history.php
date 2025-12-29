<?php
require_once 'config.php';

try {
    $order_id = $_GET['order_id'] ?? '';
    
    if (empty($order_id)) {
        sendResponse(false, null, '请提供订单ID');
    }
    
    $pdo = getDBConnection();
    
    // 查询订单操作历史
    $sql = "
        SELECT 
            l.*,
            t.name as technician_name
        FROM fyd_order_logs l
        LEFT JOIN fyd_technicians t ON l.technician_id = t.id
        WHERE l.order_id = :order_id
        ORDER BY l.created_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':order_id' => $order_id]);
    $history = $stmt->fetchAll();
    
    sendResponse(true, $history);
    
} catch (Exception $e) {
    sendResponse(false, null, '获取订单历史失败: ' . $e->getMessage());
}
?>