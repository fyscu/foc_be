<?php
require_once 'config.php';

try {
    $technicianId = $_GET['id'] ?? null;
    
    if (!$technicianId) {
        sendResponse(false, null, '技术员ID不能为空');
    }
    
    $pdo = getDBConnection();
    
    // 获取技术员详细信息
    $stmt = $pdo->prepare("
        SELECT t.*, 
               COUNT(DISTINCT o.id) as current_orders,
               COUNT(DISTINCT completed.id) as total_completed,
               AVG(CASE 
                   WHEN completed.completed_at IS NOT NULL AND completed.created_at IS NOT NULL 
                   THEN TIMESTAMPDIFF(HOUR, completed.created_at, completed.completed_at) 
                   ELSE NULL 
               END) as avg_completion_hours,
               MAX(o.updated_at) as last_active
        FROM fyd_technicians t
        LEFT JOIN fyd_orders o ON t.id = o.technician_id AND o.status IN ('in_progress', 'ready_for_pickup')
        LEFT JOIN fyd_orders completed ON t.id = completed.technician_id AND completed.status = 'completed'
        WHERE t.id = :id
        GROUP BY t.id
    ");
    
    $stmt->execute([':id' => $technicianId]);
    $technician = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$technician) {
        sendResponse(false, null, '技术员不存在');
    }
    
    // 格式化平均完成时间
    if ($technician['avg_completion_hours']) {
        $hours = round($technician['avg_completion_hours'], 1);
        $technician['avg_completion_time'] = $hours . ' 小时';
    } else {
        $technician['avg_completion_time'] = '暂无数据';
    }
    
    // 格式化时间
    $technician['created_at'] = $technician['created_at'] ? date('Y-m-d H:i', strtotime($technician['created_at'])) : '未知';
    $technician['last_active'] = $technician['last_active'] ? date('Y-m-d H:i', strtotime($technician['last_active'])) : '未知';
    
    sendResponse(true, $technician, '获取技术员信息成功');
    
} catch (Exception $e) {
    error_log('获取技术员信息错误: ' . $e->getMessage());
    sendResponse(false, null, '服务器错误');
}
?>