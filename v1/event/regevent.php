<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); 
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Max-Age: 86400");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0); // 提前结束响应，处理 OPTIONS 预检请求
}

include('../../db.php');
include('../../utils/token.php');
include('../../utils/headercheck.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    $activity_id = $data['activity_id'];
    $user_id = $data['uid'];

    // 检查活动类型
    $stmt = $pdo->prepare("SELECT type FROM fy_activities WHERE id = ?");
    $stmt->execute([$activity_id]);
    $activity = $stmt->fetch();

    if ($activity['type'] == '大修') {
        echo json_encode(['success' => false, 'message' => '请使用大修活动报名接口']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM fy_registrations WHERE activity_id = ? AND user_id = ?");
    $stmt->execute([$activity_id, $user_id]);
    $userRegistered = $stmt->fetchColumn();

    if ($userRegistered > 0) {
        echo json_encode(['success' => false, 'message' => 'Registered']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT luckynum FROM fy_registrations WHERE activity_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$activity_id]);
    $lastreg = $stmt->fetch();
    $theluckynum = $lastreg !== false ? $lastreg['luckynum'] + 1 : 1;

    $stmt = $pdo->prepare("INSERT INTO fy_registrations (activity_id, user_id, luckynum) VALUES (?, ?, ?)");
    $stmt->execute([$activity_id, $user_id, $theluckynum]);

    echo json_encode(['success' => true]);
}
?>
