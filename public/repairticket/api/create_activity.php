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
    
    // 验证必填字段
    $required_fields = ['name', 'activity_date'];
    foreach ($required_fields as $field) {
        if (empty($input[$field])) {
            echo json_encode([
                'success' => false,
                'message' => "字段 {$field} 不能为空"
            ]);
            exit;
        }
    }
    
    // 检查活动名称是否已存在
    $stmt = $pdo->prepare("SELECT id FROM fyd_activities WHERE activity_name = ?");
    $stmt->execute([$input['name']]);
    if ($stmt->fetch()) {
        echo json_encode([
            'success' => false,
            'message' => '活动名称已存在'
        ]);
        exit;
    }
    
    // 插入新活动
    $stmt = $pdo->prepare("
        INSERT INTO fyd_activities (activity_name, activity_date, description, is_current, created_at) 
        VALUES (?, ?, ?, 0, NOW())
    ");
    
    $result = $stmt->execute([
        $input['name'],
        $input['activity_date'],
        $input['description'] ?? ''
    ]);
    
    if ($result) {
        $activity_id = $pdo->lastInsertId();
        
        // 获取创建的活动信息
        $stmt = $pdo->prepare("SELECT * FROM fyd_activities WHERE id = ?");
        $stmt->execute([$activity_id]);
        $activity = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'message' => '活动创建成功',
            'data' => $activity
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => '活动创建失败'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => '服务器错误: ' . $e->getMessage()
    ]);
}
?>