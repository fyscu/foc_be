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

$response = [];

// 获取用户的报名信息
$userId = $_GET['user_id'] ?? $userinfo['id'];
$activityId = $_GET['activity_id'] ?? null;

if ($userId && $activityId) {
    $stmt = $pdo->prepare("SELECT luckynum FROM fy_registrations WHERE user_id = ? AND activity_id = ?");
    $stmt->execute([$userId, $activityId]);
    $registration = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($registration) {
        $userLuckynum = $registration['luckynum'];

        $activityStmt = $pdo->prepare("SELECT winnum FROM fy_activities WHERE id = ?");
        $activityStmt->execute([$activityId]);
        $activity = $activityStmt->fetch(PDO::FETCH_ASSOC);

        if ($activity) {
            if (!empty($activity['isLucky']) && !empty($activity['winnum'])) {
                $winNumbers = json_decode($activity['winnum'], true);
                $isWinner = in_array($userLuckynum, $winNumbers);
            } else {
                $isWinner = false;
            }
        
            $response = [
                'success' => true,
                'luckynum' => $userLuckynum,
                'is_winner' => $isWinner
            ];
        } else {
            $response = [
                'success' => false,
                'message' => 'Activity not found'
            ];
        }
    } else {
        $response = [
            'success' => false,
            'message' => 'Registration not found'
        ];
    }
} else {
    $response = [
        'success' => false,
        'message' => 'Missing parameters'
    ];
}

echo json_encode($response);
?>