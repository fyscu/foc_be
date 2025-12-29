<?php
require_once 'config.php';

try {
    $data = getPostData();
    
    // 验证必需字段
    if (!isset($data['technician_id']) || !isset($data['status'])) {
        sendResponse(false, null, '技术员ID和状态不能为空');
    }
    
    $validStatuses = ['online', 'busy', 'offline'];
    if (!in_array($data['status'], $validStatuses)) {
        sendResponse(false, null, '无效的状态值');
    }
    
    $pdo = getDBConnection();
    
    // 检查技术员是否存在
    $check_stmt = $pdo->prepare("SELECT id, name FROM fyd_technicians WHERE id = :id");
    $check_stmt->execute([':id' => $data['technician_id']]);
    $technician = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$technician) {
        sendResponse(false, null, '技术员不存在');
    }
    
    // 更新技术员状态
    $stmt = $pdo->prepare("
        UPDATE fyd_technicians 
        SET status = :status, updated_at = NOW() 
        WHERE id = :id
    ");
    
    $result = $stmt->execute([
        ':status' => $data['status'],
        ':id' => $data['technician_id']
    ]);
    
    if ($result) {
        
        sendResponse(true, [
            'technician_id' => $data['technician_id'],
            'new_status' => $data['status']
        ], '技术员状态更新成功');
    } else {
        sendResponse(false, null, '更新技术员状态失败');
    }
    
} catch (Exception $e) {
    error_log('更新技术员状态错误: ' . $e->getMessage());
    sendResponse(false, null, '服务器错误');
}
?>