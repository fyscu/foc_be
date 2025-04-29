<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
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
require '../../utils/email.php';
require '../../utils/sms.php';

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
            if ($user['role'] == "technician"){
                // 若为技术员，则先检查其未完工单并尝试处理
                $atid = $user['id'];
                $stmt = $pdo->prepare("SELECT * FROM fy_workorders WHERE assigned_technician_id = ? AND repair_status = 'Repairing'");
                $stmt->execute([$atid]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC); // 获取所有未完成工单
                
                if (!empty($rows)) {
                    foreach ($rows as $row) {
                        $ticket_id = $row['id'];
                        $updateSql = "UPDATE fy_workorders SET assigned_technician_id = NULL, assigned_time = NULL, repair_status = 'Pending' WHERE id = :id";
                        $updateStmt = $pdo->prepare($updateSql);
                        $updateStmt->execute([':id' => $ticket_id]);

                        // 发送重新分配工单的短信通知
                        $sms = new Sms($config);
                        $templateKey = 'reassign'; 
                        $phoneNumber = $row['user_phone']; // 这里应该是工单相关的用户的手机号
                        $templateParams = [];
                        $sms->sendSms($templateKey, $phoneNumber, $templateParams);
                    }
                }
            }
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