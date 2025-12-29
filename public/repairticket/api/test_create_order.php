<?php
require_once 'config.php';

try {
    $pdo = getDBConnection();
    
    echo "<h2>测试订单创建</h2>";
    
    // 检查当前活动
    $activity_stmt = $pdo->query("SELECT * FROM fyd_activities WHERE is_current = 1 LIMIT 1");
    $current_activity = $activity_stmt->fetch();
    
    echo "<h3>当前活动:</h3>";
    echo "<pre>" . print_r($current_activity, true) . "</pre>";
    
    if (!$current_activity) {
        echo "<p style='color: red;'>错误：没有设置当前活动！</p>";
        
        // 设置第一个活动为当前活动
        $pdo->exec("UPDATE fyd_activities SET is_current = 1 WHERE id = 1");
        echo "<p style='color: green;'>已自动设置第一个活动为当前活动</p>";
        
        $current_activity = $pdo->query("SELECT * FROM fyd_activities WHERE id = 1")->fetch();
        echo "<h3>更新后的当前活动:</h3>";
        echo "<pre>" . print_r($current_activity, true) . "</pre>";
    }
    
    // 测试创建一个订单
    $test_data = [
        'activity_id' => $current_activity['id'],
        'order_number' => '0001',
        'position' => 1,
        'customer_name' => '测试客户',
        'customer_phone' => '13800138000',
        'device_type' => '笔记本',
        'problem_description' => '测试故障描述',
        'status' => 'pending'
    ];
    
    $sql = "
        INSERT INTO fyd_orders (
            activity_id, order_number, position, customer_name, customer_phone, 
            device_type, problem_description, status, created_at
        ) VALUES (
            :activity_id, :order_number, :position, :customer_name, :customer_phone,
            :device_type, :problem_description, :status, NOW()
        )
    ";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute($test_data);
    
    if ($result) {
        $order_id = $pdo->lastInsertId();
        echo "<p style='color: green;'>测试订单创建成功！订单ID: $order_id</p>";
    } else {
        echo "<p style='color: red;'>测试订单创建失败！</p>";
        echo "<pre>" . print_r($stmt->errorInfo(), true) . "</pre>";
    }
    
    // 查询所有订单
    echo "<h3>所有订单:</h3>";
    $orders = $pdo->query("SELECT * FROM fyd_orders ORDER BY created_at DESC")->fetchAll();
    echo "<pre>" . print_r($orders, true) . "</pre>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>错误: " . $e->getMessage() . "</p>";
}
?>