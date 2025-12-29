<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
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
    // 获取POST数据
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode([
            'success' => false,
            'message' => '无效的请求数据'
        ]);
        exit;
    }
    
    $activity_id = $input['activity_id'] ?? null;
    
    // 开始事务
    $pdo->beginTransaction();
    
    try {
        // 首先将所有活动设为非当前活动
        $stmt = $pdo->prepare("UPDATE fyd_activities SET is_current = 0");
        $stmt->execute();
        
        if ($activity_id) {
            // 检查活动是否存在
            $stmt = $pdo->prepare("SELECT id FROM fyd_activities WHERE id = ?");
            $stmt->execute([$activity_id]);
            if (!$stmt->fetch()) {
                throw new Exception('活动不存在');
            }
            
            // 设置指定活动为当前活动
            $stmt = $pdo->prepare("UPDATE fyd_activities SET is_current = 1 WHERE id = ?");
            $stmt->execute([$activity_id]);
            
            $message = '当前活动设置成功';
        } else {
            $message = '已结束所有活动';
        }
        
        // 提交事务
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => $message
        ]);
        
    } catch (Exception $e) {
        // 回滚事务
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => '服务器错误: ' . $e->getMessage()
    ]);
}
?>