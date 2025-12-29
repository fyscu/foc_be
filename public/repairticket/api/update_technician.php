<?php
require_once 'config.php';

try {
    $data = getPostData();
    
    // 验证必需字段
    if (!isset($data['id']) || empty($data['id'])) {
        sendResponse(false, null, '技术员ID不能为空');
    }
    
    $pdo = getDBConnection();
    
    // 检查技术员是否存在
    $check_stmt = $pdo->prepare("SELECT id FROM fyd_technicians WHERE id = :id");
    $check_stmt->execute([':id' => $data['id']]);
    
    if (!$check_stmt->fetch()) {
        sendResponse(false, null, '技术员不存在');
    }
    
    // 如果更新姓名，检查是否与其他技术员重复
    if (isset($data['name'])) {
        $name_check = $pdo->prepare("SELECT id FROM fyd_technicians WHERE name = :name AND id != :id");
        $name_check->execute([':name' => $data['name'], ':id' => $data['id']]);
        
        if ($name_check->fetch()) {
            sendResponse(false, null, '技术员姓名已存在');
        }
    }
    
    // 构建更新SQL
    $updateFields = [];
    $params = [':id' => $data['id']];
    
    if (isset($data['name'])) {
        $updateFields[] = "name = :name";
        $params[':name'] = $data['name'];
    }
    
    if (isset($data['phone'])) {
        $updateFields[] = "phone = :phone";
        $params[':phone'] = $data['phone'];
    }
    
    if (isset($data['specialty'])) {
        $updateFields[] = "specialty = :specialty";
        $params[':specialty'] = $data['specialty'];
    }
    
    if (isset($data['status'])) {
        $updateFields[] = "status = :status";
        $params[':status'] = $data['status'];
    }
    
    if (empty($updateFields)) {
        sendResponse(false, null, '没有要更新的字段');
    }
    
    $updateFields[] = "updated_at = NOW()";
    
    $sql = "UPDATE fyd_technicians SET " . implode(', ', $updateFields) . " WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    
    $result = $stmt->execute($params);
    
    if ($result) {
        // 记录操作日志
        $log_stmt = $pdo->prepare("
            INSERT INTO fyd_order_logs (order_id, action, details, created_at) 
            VALUES (0, 'technician_updated', :details, NOW())
        ");
        $log_stmt->execute([
            ':details' => json_encode([
                'technician_id' => $data['id'],
                'updated_fields' => array_keys($data),
                'operator' => 'system'
            ])
        ]);
        
        // 获取更新后的技术员信息
        $get_stmt = $pdo->prepare("
            SELECT t.*, 
                   COUNT(o.id) as current_orders,
                   (SELECT COUNT(*) FROM fyd_orders WHERE technician_id = t.id AND status = 'completed') as total_completed
            FROM fyd_technicians t
            LEFT JOIN fyd_orders o ON t.id = o.technician_id AND o.status IN ('in_progress', 'ready_for_pickup')
            WHERE t.id = :id
            GROUP BY t.id
        ");
        $get_stmt->execute([':id' => $data['id']]);
        $technician = $get_stmt->fetch(PDO::FETCH_ASSOC);
        
        sendResponse(true, $technician, '技术员信息更新成功');
    } else {
        sendResponse(false, null, '更新技术员信息失败');
    }
    
} catch (Exception $e) {
    error_log('更新技术员信息错误: ' . $e->getMessage());
    sendResponse(false, null, '服务器错误');
}
?>