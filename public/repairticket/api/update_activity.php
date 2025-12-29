<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'config.php';

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
    if (empty($input['id'])) {
        echo json_encode([
            'success' => false,
            'message' => '活动ID不能为空'
        ]);
        exit;
    }
    
    // 检查活动是否存在
    $stmt = $pdo->prepare("SELECT * FROM fyd_activities WHERE id = ?");
    $stmt->execute([$input['id']]);
    $existing_activity = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$existing_activity) {
        echo json_encode([
            'success' => false,
            'message' => '活动不存在'
        ]);
        exit;
    }
    
    // 构建更新字段
    $update_fields = [];
    $update_values = [];
    
    if (isset($input['name']) && $input['name'] !== $existing_activity['name']) {
        // 检查新名称是否已存在
        $stmt = $pdo->prepare("SELECT id FROM fyd_activities WHERE name = ? AND id != ?");
        $stmt->execute([$input['name'], $input['id']]);
        if ($stmt->fetch()) {
            echo json_encode([
                'success' => false,
                'message' => '活动名称已存在'
            ]);
            exit;
        }
        $update_fields[] = 'name = ?';
        $update_values[] = $input['name'];
    }
    
    if (isset($input['activity_date'])) {
        $update_fields[] = 'activity_date = ?';
        $update_values[] = $input['activity_date'];
    }
    
    if (isset($input['description'])) {
        $update_fields[] = 'description = ?';
        $update_values[] = $input['description'];
    }
    
    if (empty($update_fields)) {
        echo json_encode([
            'success' => false,
            'message' => '没有需要更新的字段'
        ]);
        exit;
    }
    
    // 添加更新时间
    $update_fields[] = 'updated_at = NOW()';
    $update_values[] = $input['id'];
    
    // 执行更新
    $sql = "UPDATE fyd_activities SET " . implode(', ', $update_fields) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute($update_values);
    
    if ($result) {
        // 获取更新后的活动信息
        $stmt = $pdo->prepare("SELECT * FROM fyd_activities WHERE id = ?");
        $stmt->execute([$input['id']]);
        $activity = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'message' => '活动更新成功',
            'data' => $activity
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => '活动更新失败'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => '服务器错误: ' . $e->getMessage()
    ]);
}
?>