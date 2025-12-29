<?php
require_once 'config.php';

try {
    $pdo = getDBConnection();
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
    
    // 获取查询参数
    $status = $_GET['status'] ?? '';
    $type = $_GET['type'] ?? '';
    $order_number = $_GET['order_number'] ?? '';
    $customer_name = $_GET['customer_name'] ?? '';
    $limit = $_GET['limit'] ?? 50;
    $offset = $_GET['offset'] ?? 0;
    
    // 构建查询条件
    $where_conditions = [];
    $params = [];
    
    if (!empty($status)) {
        $where_conditions[] = "o.status = :status";
        $params[':status'] = $status;
    }
    
    if (!empty($type)) {
        if ($type === 'current') {
            if ($currentActivityId) {
                $where_conditions[] = "o.activity_id = :activity_id";
                $params[':activity_id'] = $currentActivityId;
            }
        }
    }
    
    if (!empty($order_number)) {
        $where_conditions[] = "o.order_number LIKE :order_number";
        $params[':order_number'] = "%$order_number%";
    }
    
    if (!empty($customer_name)) {
        $where_conditions[] = "o.customer_name LIKE :customer_name";
        $params[':customer_name'] = "%$customer_name%";
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // 查询订单
    $sql = "
        SELECT 
            o.*,
            t.name as technician_name,
            a.activity_name
        FROM fyd_orders o
        LEFT JOIN fyd_technicians t ON o.technician_id = t.id
        LEFT JOIN fyd_activities a ON o.activity_id = a.id
        $where_clause
        ORDER BY o.created_at DESC
        LIMIT :limit OFFSET :offset
    ";
    
    $stmt = $pdo->prepare($sql);
    
    // 绑定参数
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $orders = $stmt->fetchAll();
    
    // 获取总数
    $count_sql = "SELECT COUNT(*) as total FROM fyd_orders o $where_clause";
    $count_stmt = $pdo->prepare($count_sql);
    foreach ($params as $key => $value) {
        $count_stmt->bindValue($key, $value);
    }
    $count_stmt->execute();
    $total = $count_stmt->fetch()['total'];
    
    sendResponse(true, [
        'orders' => $orders,
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset
    ]);
    
} catch (Exception $e) {
    sendResponse(false, null, '获取订单失败: ' . $e->getMessage());
}
?>