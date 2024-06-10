<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include('../../db.php');
include('../../utils/token.php');
include('../../utils/headercheck.php');

function regEvent() {
    global $pdo;
    $activity_id = $_POST['activity_id'];
    $user_id = $_POST['uid'];

    // 检查活动类型
    $stmt = $pdo->prepare("SELECT type FROM fy_activities WHERE id = ?");
    $stmt->execute([$activity_id]);
    $activity = $stmt->fetch();

    if ($activity['type'] == '大修') {
        echo json_encode(['success' => false, 'message' => '请使用大修活动报名接口']);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO fy_registrations (activity_id, user_id) VALUES (?, ?)");
    $stmt->execute([$activity_id, $user_id]);

    echo json_encode(['success' => true]);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    regEvent();
}
?>
