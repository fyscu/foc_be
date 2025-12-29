<?php
require_once 'config.php';

try {
    $pdo = getDBConnection();
    
    // 获取活动列表
    $sql = "
        SELECT 
            a.*,
            COUNT(o.id) as order_count,
            COUNT(CASE WHEN o.status = 'completed' THEN 1 END) as completed_count
        FROM fyd_activities a
        LEFT JOIN fyd_orders o ON a.id = o.activity_id
        GROUP BY a.id
        ORDER BY a.created_at DESC
    ";
    
    $stmt = $pdo->query($sql);
    $activities = $stmt->fetchAll();
    
    sendResponse(true, $activities);
    
} catch (Exception $e) {
    sendResponse(false, null, '获取活动列表失败: ' . $e->getMessage());
}
?>