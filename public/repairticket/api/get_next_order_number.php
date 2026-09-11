<?php
require_once 'config.php';

try {
    $position = $_GET['position'] ?? '';
    
    if (empty($position)) {
        sendResponse(false, null, '请指定录入位置');
    }
    
    $pdo = getDBConnection();
    
    // 获取当前活动ID
    $activity_stmt = $pdo->query("SELECT id FROM fyd_activities WHERE is_current = 1 LIMIT 1");
    $current_activity = $activity_stmt->fetch();
    
    if (!$current_activity) {
        sendResponse(false, null, '请先设置当前活动');
    }
    
    $activity_id = $current_activity['id'];
    
    // 按位置(单号/双号)独立查询各自最大编号，避免互相干扰跳号
    if ($position == '1') {
        // 1号位：奇数编号，只查 position=1 的最大号
        $max_stmt = $pdo->prepare("
            SELECT MAX(CAST(order_number AS UNSIGNED)) as max_number 
            FROM fyd_orders 
            WHERE activity_id = :activity_id AND position = 1
        ");
        $max_stmt->execute([':activity_id' => $activity_id]);
        $max_result = $max_stmt->fetch();
        $max_number = $max_result['max_number'] ?? 0;
        
        if ($max_number == 0) {
            $next_number = 1; // 第一个奇数
        } else {
            $next_number = $max_number + 2; // 奇数步进2
        }
    } else {
        // 2号位(双号)：偶数编号，只查 position=2 的最大号
        $max_stmt = $pdo->prepare("
            SELECT MAX(CAST(order_number AS UNSIGNED)) as max_number 
            FROM fyd_orders 
            WHERE activity_id = :activity_id AND position = 2
        ");
        $max_stmt->execute([':activity_id' => $activity_id]);
        $max_result = $max_stmt->fetch();
        $max_number = $max_result['max_number'] ?? 0;
        
        if ($max_number == 0) {
            $next_number = 2; // 第一个偶数
        } else {
            $next_number = $max_number + 2; // 偶数步进2
        }
    }
    
    $order_number = str_pad($next_number, 4, '0', STR_PAD_LEFT);
    
    sendResponse(true, ['order_number' => $order_number]);
    
} catch (Exception $e) {
    sendResponse(false, null, '获取订单编号失败: ' . $e->getMessage());
}
?>