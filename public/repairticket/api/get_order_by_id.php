<?php
require_once 'config.php';

try {
    $order_id = $_GET['id'] ?? '';
    
    if (empty($order_id)) {
        sendResponse(false, null, '请提供订单ID');
    }
    
    $pdo = getDBConnection();
    
    // 查询订单详情
    $sql = "
        SELECT 
            o.*,
            t.name as technician_name,
            a.activity_name
        FROM fyd_orders o
        LEFT JOIN fyd_technicians t ON o.technician_id = t.id
        LEFT JOIN fyd_activities a ON o.activity_id = a.id
        WHERE o.id = :order_id
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':order_id' => $order_id]);
    $order = $stmt->fetch();
    
    if (!$order) {
        sendResponse(false, null, '订单不存在');
    }
    
    sendResponse(true, $order);
    
} catch (Exception $e) {
    sendResponse(false, null, '获取订单详情失败: ' . $e->getMessage());
}
?>