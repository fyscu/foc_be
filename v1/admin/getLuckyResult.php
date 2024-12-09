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

$activityId = $_GET['activity_id'] ?? null;
$count = intval($_GET['count'] ?? 1); // 默认抽取一个
$prize = $_GET['prize'] ?? "无";

if ($activityId && $count > 0) {
    if ($userinfo['is_lucky_admin']) {
        $stmt = $pdo->prepare("SELECT user_id, luckynum FROM fy_registrations WHERE activity_id = ?");
        $stmt->execute([$activityId]);
        $registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($registrations) {
            $luckyNumbers = array_map('intval', array_column($registrations, 'luckynum'));
            if (count($luckyNumbers) >= $count) {
                // 抽取 count 个随机索引
                $winningIndexes = (array)array_rand($luckyNumbers, $count);
                $winningNumbers = [];
                $winningUserIds = [];
            
                foreach ($winningIndexes as $index) {
                    $winningNumbers[] = $luckyNumbers[$index];
                    $winningUserIds[] = $registrations[$index]['user_id'];
                }
            
                $maxLuckyNumber = max($luckyNumbers);
            
                $selectStmt = $pdo->prepare("SELECT winnum, prize FROM fy_activities WHERE id = ?");
                $selectStmt->execute([$activityId]);
                $currentData = $selectStmt->fetch(PDO::FETCH_ASSOC);

                $winnumArray = !empty($currentData['winnum']) ? json_decode($currentData['winnum'], true) : [];
                $prizeArray = !empty($currentData['prize']) ? json_decode($currentData['prize'], true) : [];

                $winnumArray[] = $winningNumbers;
                $prizeArray[] = $prize;

                $updateStmt = $pdo->prepare("UPDATE fy_activities SET winnum = ?, prize = ? WHERE id = ?");
                $updateStmt->execute([json_encode($winnumArray), json_encode($prizeArray, JSON_UNESCAPED_UNICODE), $activityId]);
                
                $response = [
                    'success' => true,
                    'winning_nums' => $winningNumbers,
                    'winning_user_ids' => $winningUserIds,
                    'max_luckynum' => $maxLuckyNumber,
                    'luckyHistory' => json_encode($winnumArray),
                    'prizeHistory' => json_encode($prizeArray)
                ];
            } else {
                $response = [
                    'success' => false,
                    'message' => 'Not enough participants'
                ];
            }
        } else {
            $response = [
                'success' => false,
                'message' => 'Error'
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
        'message' => 'Missing parameters or invalid count'
    ];
}

echo json_encode($response);
?>