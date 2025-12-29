<?php
require_once 'config.php';

try {
    $pdo = getDBConnection();
    
    // 获取当前活动的订单统计
    $sql = "
        SELECT 
            status,
            COUNT(*) as count
        FROM fyd_orders o
        LEFT JOIN fyd_activities a ON o.activity_id = a.id
        WHERE a.is_current = 1
        GROUP BY status
    ";
    
    $stmt = $pdo->query($sql);
    $results = $stmt->fetchAll();
    $sql = "
        SELECT 
            COUNT(*)
        FROM fyd_orders o
        LEFT JOIN fyd_activities a ON o.activity_id = a.id
        WHERE a.is_current = 1
    ";
    
    $stmt = $pdo->query($sql);
    $totalresults = $stmt->fetchColumn();
    $query = "SELECT id, activity_name, activity_date 
          FROM fyd_activities 
          WHERE is_current = 1 
          LIMIT 1";

    $stmt = $pdo->query($query);
    $currentActivity = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($currentActivity) {
        $currentActivityId   = $currentActivity['id'];
        $currentActivityName = $currentActivity['activity_name'];
        $currentActivityDate = $currentActivity['activity_date'];
    } else {
        $currentActivityId   = null;
        $currentActivityName = null;
        $currentActivityDate = null;
    }
    
    // 初始化统计数据
    $stats = [
        'total' => 0,
        'pending' => 0,
        'in_progress' => 0,
        'ready_for_pickup' => 0,
        'completed' => 0,
        'online_technicians' => 0,
        'currentActivity' => $currentActivityName,
        'activityDate' => $currentActivityDate
    ];
    
    // 填充实际数据
    foreach ($results as $row) {
        // 状态名称映射
        $status = $row['status'];
        if ($status == 'processing') {
            $status = 'in_progress';
        } else if ($status == 'ready') {
            $status = 'ready_for_pickup';
        }
        
        $stats[$status] = (int)$row['count'];
    }
    $stats['total'] = (int)$totalresults;
    $stats['online_technicians'] = 255;
    sendResponse(true, $stats);
    
} catch (Exception $e) {
    sendResponse(false, null, '获取统计数据失败: ' . $e->getMessage());
}
?>