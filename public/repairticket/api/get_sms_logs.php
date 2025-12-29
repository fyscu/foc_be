<?php
require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '只支持GET请求']);
    exit;
}

try {
    $pdo = new PDO($dsn, $username, $password, $options);
    
    $orderId = $_GET['order_id'] ?? null;
    $limit = intval($_GET['limit'] ?? 50);
    $offset = intval($_GET['offset'] ?? 0);
    
    // 构建查询条件
    $whereClause = '';
    $params = [];
    
    if ($orderId) {
        $whereClause = 'WHERE sl.order_id = ?';
        $params[] = $orderId;
    }
    
    // 获取短信日志列表
    $sql = "
        SELECT 
            sl.*,
            o.order_number,
            o.customer_name,
            o.device_type
        FROM fyd_sms_logs sl
        LEFT JOIN fyd_orders o ON sl.order_id = o.id
        $whereClause
        ORDER BY sl.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
    
    // 获取总数
    $countSql = "
        SELECT COUNT(*) as total
        FROM fyd_sms_logs sl
        LEFT JOIN fyd_orders o ON sl.order_id = o.id
        $whereClause
    ";
    
    $countParams = $orderId ? [$orderId] : [];
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($countParams);
    $total = $countStmt->fetch()['total'];
    
    echo json_encode([
        'success' => true,
        'data' => $logs,
        'pagination' => [
            'total' => intval($total),
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < $total
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '获取短信日志失败: ' . $e->getMessage()
    ]);
}
?>