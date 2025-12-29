<?php
require_once 'config.php';

try {
    $pdo = getDBConnection();
    
    // 获取请求体
    $requestBody = file_get_contents('php://input');
    $data = json_decode($requestBody, true);
    
    if (!isset($data['users']) || !is_array($data['users'])) {
        sendResponse(false, null, '无效的请求数据');
        exit;
    }
    
    $users = $data['users'];
    $importedCount = 0;
    $errors = [];
    
    // 开始事务
    $pdo->beginTransaction();
    
    try {
        foreach ($users as $user) {
            // 检查技术员是否已存在
            $checkStmt = $pdo->prepare("SELECT id FROM fyd_technicians WHERE fy_userid = :user_id");
            $checkStmt->execute([':user_id' => $user['id']]);
            
            if ($checkStmt->rowCount() > 0) {
                $errors[] = "用户 {$user['nickname']} 已存在于技术员列表中";
                continue;
            }
            
            // 插入新技术员
            $insertStmt = $pdo->prepare("
                INSERT INTO fyd_technicians (name, phone, specialty, status, fy_userid)
                VALUES (:name, :phone, '通用维修', 'online', :user_id)
            ");
            
            $insertStmt->execute([
                ':name' => $user['nickname'],
                ':phone' => $user['phone'] ?? '',
                ':user_id' => $user['id']
            ]);
            
            $importedCount++;
        }
        
        // 提交事务
        $pdo->commit();
        
        sendResponse(true, [
            'imported_count' => $importedCount,
            'errors' => $errors
        ], "成功导入 {$importedCount} 名技术员");
        
    } catch (Exception $e) {
        // 回滚事务
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    sendResponse(false, null, '导入技术员失败: ' . $e->getMessage());
}
?>