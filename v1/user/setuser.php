<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
$config = include('../../config.php');
include('../../db.php');
include('../../utils/token.php');
include('../../utils/headercheck.php');
include('../../utils/gets.php');

$json = file_get_contents('php://input');
$data = json_decode($json, true);

// 如果没有传入id，使用当前用户的openid
$target_openid = isset($data['id']) ? $data['id'] : $userinfo['openid'];
$target_user = getUserByOpenid($target_openid);

$response = [];

if ($target_user) {
    $has_permission = false;

    // 检查是否为管理员或本人
    if ($userinfo['is_admin']) {
        $has_permission = true;
    } elseif ($userinfo['openid'] === $target_openid) {
        $has_permission = true;
    }

    if ($has_permission) {
        $updateFields = [];
        $updateValues = [];
        $changedFields = [];

        foreach ($data as $key => $value) {
            if (!empty($value) && $key != 'id' && isset($target_user[$key])) {
                if ($target_user[$key] != $value) {
                    $updateFields[] = "$key = :$key";
                    $updateValues[":$key"] = $value;
                    $changedFields[$key] = $value;
                }
            }
        }

        if (count($updateFields) > 0) {
            $updateSql = "UPDATE fy_users SET " . implode(", ", $updateFields) . " WHERE openid = :id";
            $updateValues[':id'] = $target_openid;

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