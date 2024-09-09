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
    $user_id = $data['openid'];

    $has_permission = false;

    if ($userinfo['is_admin']) {
        $has_permission = true;
    } elseif ($userinfo['openid'] === $user_id) {
        $has_permission = true;
    }

    if ($has_permission) {
        $user = getUserByOpenid($user_id);

        if ($user) {
            // 删除用户
            $deleteStmt = $pdo->prepare("DELETE FROM fy_users WHERE openid = :id");
            $deleteStmt->execute([':id' => $user_id]);

            $response = [
                'success' => true,
                'message' => '用户删除成功'
            ];
        } else {
            $response = [
                'success' => false,
                'message' => '用户未找到'
            ];
        }
    } else {
        $response = [
            'success' => false,
            'message' => 'Permission denied'
        ];
    }
} else {
    $response = [
        'success' => false,
        'message' => '未提供用户ID'
    ];
}

echo json_encode($response);
?>