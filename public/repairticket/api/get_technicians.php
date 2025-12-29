<?php
require_once 'config.php';

try {
    $pdo = getDBConnection();
    
    // 获取技术员列表
    $sql = "
        SELECT 
            t.*,
            COUNT(o.id) as current_orders_count
        FROM fyd_technicians t
        LEFT JOIN fyd_orders o ON t.id = o.technician_id AND o.status = 'processing'
        GROUP BY t.id
        ORDER BY t.name
    ";
    
    $stmt = $pdo->query($sql);
    $technicians = $stmt->fetchAll();
    
    // 更新当前订单数
    foreach ($technicians as &$technician) {
        $update_sql = "UPDATE fyd_technicians SET current_orders = :count WHERE id = :id";
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->execute([
            ':count' => $technician['current_orders_count'],
            ':id' => $technician['id']
        ]);
        $technician['current_orders'] = $technician['current_orders_count'];
    }
    
    sendResponse(true, $technicians);
    
} catch (Exception $e) {
    sendResponse(false, null, '获取技术员列表失败: ' . $e->getMessage());
}
?>