<?php
require_once 'config.php';

try {
    $pdo = getDBConnection();
    
    echo "<h2>数据库调试信息</h2>";
    
    // 检查活动表
    echo "<h3>活动表 (fyd_activities)</h3>";
    $activities = $pdo->query("SELECT * FROM fyd_activities")->fetchAll();
    echo "<pre>" . print_r($activities, true) . "</pre>";
    
    // 检查订单表
    echo "<h3>订单表 (fyd_orders)</h3>";
    $orders = $pdo->query("SELECT * FROM fyd_orders ORDER BY created_at DESC")->fetchAll();
    echo "<pre>" . print_r($orders, true) . "</pre>";
    
    // 检查技术员表
    echo "<h3>技术员表 (fyd_technicians)</h3>";
    $technicians = $pdo->query("SELECT * FROM fyd_technicians")->fetchAll();
    echo "<pre>" . print_r($technicians, true) . "</pre>";
    
    // 检查表结构
    echo "<h3>订单表结构</h3>";
    $structure = $pdo->query("DESCRIBE fyd_orders")->fetchAll();
    echo "<pre>" . print_r($structure, true) . "</pre>";
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage();
}
?>