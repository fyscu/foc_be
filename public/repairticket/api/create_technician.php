<?php
require_once 'config.php';

try {
    $data = getPostData();
    
    // 验证必需字段
    $required_fields = ['name'];
    validateRequired($data, $required_fields);
    
    $pdo = getDBConnection();
    
    // 检查技术员姓名是否已存在
    $check_stmt = $pdo->prepare("SELECT id FROM fyd_technicians WHERE name = :name");
    $check_stmt->execute([':name' => $data['name']]);
    
    if ($check_stmt->fetch()) {
        sendResponse(false, null, '技术员姓名已存在');
    }
    
    // 插入新技术员
    $stmt = $pdo->prepare("
        INSERT INTO fyd_technicians (name, phone, specialty, status, created_at) 
        VALUES (:name, :phone, :specialty, :status, NOW())
    ");
    
    $result = $stmt->execute([
        ':name' => $data['name'],
        ':phone' => $data['phone'] ?? null,
        ':specialty' => $data['specialty'] ?? '通用维修',
        ':status' => $data['status'] ?? 'offline'
    ]);
    
    if ($result) {
        $technicianId = $pdo->lastInsertId();
        
        // 获取新创建的技术员信息
        $get_stmt = $pdo->prepare("
            SELECT t.*, 
                   COUNT(o.id) as current_orders,
                   (SELECT COUNT(*) FROM fyd_orders WHERE technician_id = t.id AND status = 'completed') as total_completed
            FROM fyd_technicians t
            LEFT JOIN fyd_orders o ON t.id = o.technician_id AND o.status IN ('in_progress', 'ready_for_pickup')
            WHERE t.id = :id
            GROUP BY t.id
        ");
        $get_stmt->execute([':id' => $technicianId]);
        $technician = $get_stmt->fetch(PDO::FETCH_ASSOC);
        
        sendResponse(true, $technician, '技术员创建成功');
    } else {
        sendResponse(false, null, '创建技术员失败');
    }
    
} catch (Exception $e) {
    error_log('创建技术员错误: ' . $e->getMessage());
    sendResponse(false, null, '服务器错误');
}
?>