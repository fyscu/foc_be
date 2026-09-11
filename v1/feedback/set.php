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

$question_id = $data['qid'];
$question = getQuestionById($question_id);

$response = [];

if ($question) {
    $has_permission = false;

    if ($userinfo['is_admin']) {
        $has_permission = true;
    } elseif ($userinfo['id'] === $question['user_id']) {
        $has_permission = true;
    }

    if ($has_permission) {
        // 字段白名单：本人只能改 question/contact；admin 可改回复字段
        $userAllowedFields = ['question', 'contact'];
        $adminExtraFields = ['answer', 'status', 'answered_by'];
        $allowedFields = $userinfo['is_admin']
            ? array_merge($userAllowedFields, $adminExtraFields)
            : $userAllowedFields;

        $updateFields = [];
        $updateValues = [];
        $changedFields = [];

        foreach ($data as $key => $value) {
            if (empty($value) || $key === 'id') {
                continue;
            }
            if (!in_array($key, $allowedFields, true)) {
                continue;
            }
            if (!isset($question[$key])) {
                continue;
            }
            if ($question[$key] != $value) {
                $updateFields[] = "$key = :$key";
                $updateValues[":$key"] = $value;
                $changedFields[$key] = $value;
            }
        }

        if (count($updateFields) > 0) {
            $updateSql = "UPDATE fy_info SET " . implode(", ", $updateFields) . " WHERE id = :id";
            $updateValues[':id'] = $question_id;

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
        'message' => 'Question not found',
        'changedFields' => []
    ];
}

echo json_encode($response);
?>