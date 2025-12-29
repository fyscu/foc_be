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
    
    // 获取当前活动的最大编号
    $max_stmt = $pdo->prepare("
        SELECT MAX(CAST(SUBSTRING(order_number, -4) AS UNSIGNED)) as max_number 
        FROM fyd_orders 
        WHERE activity_id = :activity_id
    ");
    $max_stmt->execute([':activity_id' => $activity_id]);
    $max_result = $max_stmt->fetch();
    $max_number = $max_result['max_number'] ?? 0;
    
    // 根据位置生成下一个编号
    if ($position == '1') {
        // 1号位：奇数编号
        $next_number = $max_number + 1;
        if ($next_number % 2 == 0) {
            $next_number++;
        }
    } else {
        // 4号位：偶数编号
        $next_number = $max_number + 1;
        if ($next_number % 2 == 1) {
            $next_number++;
        }
    }
    
    $order_number = str_pad($next_number, 4, '0', STR_PAD_LEFT);
    
    sendResponse(true, ['order_number' => $order_number]);
    
} catch (Exception $e) {
    sendResponse(false, null, '获取订单编号失败: ' . $e->getMessage());
}
?>