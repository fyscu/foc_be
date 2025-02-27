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

if (isset($data['avatar'])) {
    $avatar = $data['avatar'];
    $clean_avatar = explode('?', $avatar)[0];
    $data['avatar'] = $clean_avatar;
} // 对私有化逻辑产生的图片上传 bug 修改

$response = [];
$current_user = $userinfo;

$is_admin = $userinfo['is_admin'] ?? false;

// 如果用户没有管理员权限，并且传入了 id，但传入的 id 与当前用户的 id 不一致，则忽略传入的 id
if (!$is_admin && isset($data['id']) && $data['id'] != $current_user['id']) {
    unset($data['id']); // 忽略！
}

if (isset($data['id'])) {
    // 管理员通过 ID 修改用户信息
    $target_uid = $data['id'];
    $target_user = getUserById($target_uid);
} else {
    // 用户修改自己的信息，直接使用当前用户信息
    $target_user = $current_user;
}

if ($target_user) {
    $has_permission = false;

    // 检查是否为管理员或本人
    if ($is_admin) {
        $has_permission = true;
    } elseif ($userinfo['id'] === $target_user['id']) {
        $has_permission = true;
    }

    if ($has_permission) {
        $updateFields = [];
        $updateValues = [];
        $changedFields = [];

        foreach ($data as $key => $value) {
            if ($value !== null && $key != 'id' && array_key_exists($key, $target_user)) {
                if ($target_user[$key] != $value) {
                    $updateFields[] = "$key = :$key";
                    $updateValues[":$key"] = $value;
                    $changedFields[$key] = $value;
                }
            }
        }

        if (isset($data['available']) && $is_admin && $target_user['openid']) {
            $weeklyset = $data['available'] ?? $config['info']['weeklyset'];
            $data['available'] = $weeklyset;
            $updateFields[] = "available = :available";
            $updateValues[':available'] = $weeklyset;
            $changedFields['available'] = $weeklyset;
        } // 手动设置每周限额

        if (count($updateFields) > 0) {
            $updateSql = "UPDATE fy_users SET " . implode(", ", $updateFields) . " WHERE id = :id";
            $updateValues[':id'] = $target_user['id'];

            $stmt = $pdo->prepare($updateSql);
            $stmt->execute($updateValues);

            $response = [
                'success' => true,
                'changedFields' => $changedFields
            ];
        } else {
            $response = [
                'success' => true,
                'changedFields' => []
            ];
        }
    } else {
        $response = [
            'success' => false,
            'message' => 'Permission denied',
            'changedFields' => []
        ];
    }
} else {
    $response = [
        'success' => false,
        'message' => 'User not found',
        'changedFields' => []
    ];
}

echo json_encode($response);
?>