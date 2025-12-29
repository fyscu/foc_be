<?php
require_once 'config.php';

try {
    $pdo = getDBConnection();
    
    // 获取所有角色为technician的用户
    $sql = "SELECT id, nickname, phone FROM fy_users WHERE role = 'technician'";
    $stmt = $pdo->query($sql);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    sendResponse(true, $users);
} catch (Exception $e) {
    sendResponse(false, null, '获取技术员用户失败: ' . $e->getMessage());
}
?>