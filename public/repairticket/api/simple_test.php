<?php
require_once 'config.php';

echo "<h2>简单测试 - 订单创建和查询</h2>";

try {
    $pdo = getDBConnection();
    
    // 1. 检查并设置当前活动
    echo "<h3>1. 检查当前活动</h3>";
    $activity = $pdo->query("SELECT * FROM fyd_activities WHERE is_current = 1")->fetch();
    
    if (!$activity) {
        echo "<p style='color: orange;'>没有当前活动，正在设置...</p>";
        $pdo->exec("UPDATE fyd_activities SET is_current = 1 WHERE id = 1");
        $activity = $pdo->query("SELECT * FROM fyd_activities WHERE id = 1")->fetch();
    }
    
    echo "<p style='color: green;'>当前活动: {$activity['activity_name']} (ID: {$activity['id']})</p>";
    
    // 2. 创建测试订单
    echo "<h3>2. 创建测试订单</h3>";
    $test_order = [
        'activity_id' => $activity['id'],
        'order_number' => '9999', // 使用特殊编号避免冲突
        'position' => 1,
        'customer_name' => '测试用户' . date('His'),
        'customer_phone' => '13800138000',
        'device_type' => '测试设备',
        'problem_description' => '测试问题描述',
        'status' => 'pending'
    ];
    
    $sql = "INSERT INTO fyd_orders (activity_id, order_number, position, customer_name, customer_phone, device_type, problem_description, status, created_at) 
            VALUES (:activity_id, :order_number, :position, :customer_name, :customer_phone, :device_type, :problem_description, :status, NOW())";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute($test_order);
    
    if ($result) {
        $order_id = $pdo->lastInsertId();
        echo "<p style='color: green;'>✅ 测试订单创建成功！订单ID: $order_id</p>";
    } else {
        echo "<p style='color: red;'>❌ 测试订单创建失败！</p>";
        print_r($stmt->errorInfo());
    }
    
    // 3. 查询所有订单
    echo "<h3>3. 查询所有订单</h3>";
    $orders = $pdo->query("SELECT id, order_number, customer_name, device_type, status, created_at FROM fyd_orders ORDER BY created_at DESC")->fetchAll();
    
    echo "<p>订单总数: " . count($orders) . "</p>";
    
    if (count($orders) > 0) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>订单号</th><th>客户</th><th>设备</th><th>状态</th><th>创建时间</th></tr>";
        foreach ($orders as $order) {
            echo "<tr>";
            echo "<td>{$order['id']}</td>";
            echo "<td>{$order['order_number']}</td>";
            echo "<td>{$order['customer_name']}</td>";
            echo "<td>{$order['device_type']}</td>";
            echo "<td>{$order['status']}</td>";
            echo "<td>{$order['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: red;'>❌ 没有找到任何订单！</p>";
    }
    
    // 4. 测试API查询
    echo "<h3>4. 测试API查询逻辑</h3>";
    $api_sql = "
        SELECT 
            o.*,
            t.name as technician_name,
            a.activity_name
        FROM fyd_orders o
        LEFT JOIN fyd_technicians t ON o.technician_id = t.id
        LEFT JOIN fyd_activities a ON o.activity_id = a.id
        ORDER BY o.created_at DESC
        LIMIT 10
    ";
    
    $api_orders = $pdo->query($api_sql)->fetchAll();
    echo "<p>API查询结果数量: " . count($api_orders) . "</p>";
    
    if (count($api_orders) > 0) {
        echo "<p style='color: green;'>✅ API查询正常</p>";
        echo "<pre>" . print_r($api_orders[0], true) . "</pre>";
    } else {
        echo "<p style='color: red;'>❌ API查询返回空结果</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>错误: " . $e->getMessage() . "</p>";
}
?>