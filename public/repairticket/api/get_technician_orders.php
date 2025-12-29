<?php
require_once 'config.php';

try {
    $technicianId = $_GET['technician_id'] ?? null;
    
    if (!$technicianId) {
        sendResponse(false, null, '技术员ID不能为空');
    }
    
    $pdo = getDBConnection();
    
    // 检查技术员是否存在
    $check_stmt = $pdo->prepare("SELECT id, name FROM fyd_technicians WHERE id = :id");
    $check_stmt->execute([':id' => $technicianId]);
    $technician = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$technician) {
        sendResponse(false, null, '技术员不存在');
    }
    
    // 获取技术员的订单列表
    $stmt = $pdo->prepare("
        SELECT o.*, 
               a.name as activity_name,
               (SELECT created_at FROM fyd_order_logs 
                WHERE order_id = o.id AND action = 'technician_assigned' 
                ORDER BY created_at DESC LIMIT 1) as assigned_at
        FROM fyd_orders o
        LEFT JOIN fyd_activities a ON o.activity_id = a.id
        WHERE o.technician_id = :technician_id
        ORDER BY 
            CASE o.status 
                WHEN 'in_progress' THEN 1
                WHEN 'ready_for_pickup' THEN 2
                WHEN 'completed' THEN 3
                ELSE 4
            END,
            o.created_at DESC
    ");
    
    $stmt->execute([':technician_id' => $technicianId]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 格式化订单数据
    foreach ($orders as &$order) {
        $order['assigned_at'] = $order['assigned_at'] ? 
            date('Y-m-d H:i', strtotime($order['assigned_at'])) : '未知';
        $order['created_at'] = date('Y-m-d H:i', strtotime($order['created_at']));
        $order['updated_at'] = date('Y-m-d H:i', strtotime($order['updated_at']));
        
        // 截断问题描述
        if (strlen($order['problem_description']) > 50) {
            $order['problem_description'] = mb_substr($order['problem_description'], 0, 50) . '...';
        }
    }
    
    // 统计信息
    $stats = [
        'total_orders' => count($orders),
        'in_progress' => count(array_filter($orders, function($o) { return $o['status'] === 'in_progress'; })),
        'ready_for_pickup' => count(array_filter($orders, function($o) { return $o['status'] === 'ready_for_pickup'; })),
        'completed' => count(array_filter($orders, function($o) { return $o['status'] === 'completed'; }))
    ];
    
    sendResponse(true, [
        'technician' => $technician,
        'orders' => $orders,
        'stats' => $stats
    ], '获取技术员订单成功');
    
} catch (Exception $e) {
    error_log('获取技术员订单错误: ' . $e->getMessage());
    sendResponse(false, null, '服务器错误');
}
?>