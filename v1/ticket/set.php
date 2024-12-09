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
require '../../utils/sms.php';
include('../../utils/gets.php');

$json = file_get_contents('php://input');
$data = json_decode($json, true);

$ticket_id = $data['tid'];
$ticket = getTicketById($ticket_id);

$response = [];

if ($ticket) {
    $has_permission = false;

    // 权限检查
    if ($userinfo['is_admin']) {
        $has_permission = true;
    } elseif ($userinfo['id'] === $ticket['user_id']) {
        $has_permission = true;
    } elseif ($userinfo['role'] === 'technician' && $userinfo['id'] === $ticket['assigned_technician_id']) {
        $has_permission = true;
    }

    if ($has_permission) {
        $updateFields = [];
        $updateValues = [];
        $changedFields = [];

        foreach ($data as $key => $value) {
            if ($value !== null && $key != 'id' && array_key_exists($key, $ticket)) {
                if ($ticket[$key] != $value) {
                    $updateFields[] = "$key = :$key";
                    $updateValues[":$key"] = $value;
                    $changedFields[$key] = $value;
                }
            }
        }
        // 处理特殊情况：如果工单状态为 Canceled 或 Closed
        if (isset($data['repair_status']) && in_array($data['repair_status'], ['Canceled', 'Closed'])) {
            // 检查工单是否分配了技术员
            if (!empty($ticket['assigned_technician_id'])) {
                // 更新 fy_users 表中的技术员 available 状态为 1
                $technician_id = $ticket['assigned_technician_id'];
                $updateTechnicianSql = "UPDATE fy_users SET available = 1 WHERE id = :technician_id";
                $stmt = $pdo->prepare($updateTechnicianSql);
                $stmt->execute([':technician_id' => $technician_id]);
                // 找到这个技术员
                $stmttech = $pdo->prepare("SELECT phone FROM fy_users WHERE id = ?");
                $stmttech->execute([$technician_id]);
                $rowtechphone = $stmttech->fetch(PDO::FETCH_ASSOC);

                $sms = new Sms($config);
                // 发送给技术员
                $templateKey = 'beclosed';
                $phoneNumber = $rowtechphone['phone'];
                $templateParams = [];
                $response = $sms->sendSms($templateKey, $phoneNumber, $templateParams);
            }
            // 返还用户的配额（如果本周还满着就不用再返还了）
            $user_id = $ticket['user_id'];
            $updateUserQuotaSql = "UPDATE fy_users SET available = available + 1 WHERE id = :user_id AND role = 'user' AND available < 5";
            $stmtUser = $pdo->prepare($updateUserQuotaSql);
            $stmtUser->execute([':user_id' => $user_id]);
        }

        if (count($updateFields) > 0) {
            $updateSql = "UPDATE fy_workorders SET " . implode(", ", $updateFields) . " WHERE id = :id";
            $updateValues[':id'] = $ticket_id;

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
        'message' => 'Ticket not found',
        'changedFields' => []
    ];
}

echo json_encode($response);
?>