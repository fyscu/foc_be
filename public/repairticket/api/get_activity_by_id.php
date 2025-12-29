<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'config.php';
$pdo = getDBConnection();
try {
    // 获取活动ID
    $activity_id = $_GET['id'] ?? '';
    
    if (empty($activity_id)) {
        echo json_encode([
            'success' => false,
            'message' => '活动ID不能为空'
        ]);
        exit;
    }
    
    // 查询活动详情
    $stmt = $pdo->prepare("
        SELECT 
            a.*,
            COUNT(o.id) as order_count,
            COUNT(CASE WHEN o.status = 'completed' THEN 1 END) as completed_count,
            COUNT(CASE WHEN o.status = 'pending' THEN 1 END) as pending_count,
            COUNT(CASE WHEN o.status = 'processing' THEN 1 END) as processing_count,
            COUNT(CASE WHEN o.status = 'ready' THEN 1 END) as ready_count
        FROM fyd_activities a
        LEFT JOIN fyd_orders o ON a.id = o.activity_id
        WHERE a.id = ?
        GROUP BY a.id
    ");
    
    $stmt->execute([$activity_id]);
    $activity = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$activity) {
        echo json_encode([
            'success' => false,
            'message' => '活动不存在'
        ]);
        exit;
    }
    
    // 格式化数据
    $activity['order_count'] = (int)$activity['order_count'];
    $activity['completed_count'] = (int)$activity['completed_count'];
    $activity['pending_count'] = (int)$activity['pending_count'];
    $activity['processing_count'] = (int)$activity['processing_count'];
    $activity['ready_count'] = (int)$activity['ready_count'];
    
    echo json_encode([
        'success' => true,
        'data' => $activity
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => '服务器错误: ' . $e->getMessage()
    ]);
}
?>