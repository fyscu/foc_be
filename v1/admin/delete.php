<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); 
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Max-Age: 86400");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0); // 提前结束响应，处理 OPTIONS 预检请求
}

$config = include('../../config.php');
include('../../db.php');
include('../../utils/token.php');
include('../../utils/headercheck.php');
include('../../utils/gets.php');

$json = file_get_contents('php://input');
$data = json_decode($json, true);

$response = [];

if (isset($data['openid'])) {
    $admin_openid = $data['openid'];

    $user = getUserByOpenid($admin_openid);

    if ($user) {
        if ($userinfo['is_admin']) {
            // 更新用户身份
            $updateStmt = $pdo->prepare("UPDATE fy_users SET role = 'user' WHERE openid = :openid");
            $updateStmt->execute([':openid' => $admin_openid]);

            // 删除管理员记录
            $deleteStmt = $pdo->prepare("DELETE FROM fy_admins WHERE openid = :openid");
            $deleteStmt->execute([':openid' => $admin_openid]);

            $response = [
                'success' => true,
                'message' => '管理员删除成功'
            ];
        } else {
            $response = [
                'success' => false,
                'message' => '权限不足'
            ];
        }
    } else {
        $response = [
            'success' => false,
            'message' => '用户未找到'
        ];
    }
} else {
    $response = [
        'success' => false,
        'message' => '未提供管理员的openid'
    ];
}

echo json_encode($response);
?>