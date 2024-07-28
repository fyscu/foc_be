<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include('../../db.php');
include('../../utils/token.php');
include('../../utils/headercheck.php');

function regRepair() {
    global $pdo;
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    $activity_id = $data['activity_id'];
    $user_id = $data['uid'];
    $name = $data['name'];
    $gender = $data['gender'];
    $departments = implode(',', $data['departments']);
    $free_times = implode(',', $data['free_times']);

    // 检查活动类型
    $stmt = $pdo->prepare("SELECT type FROM fy_activities WHERE id = ?");
    $stmt->execute([$activity_id]);
    $activity = $stmt->fetch();

    if ($activity['type'] != '大修') {
        echo json_encode(['success' => false, 'message' => '此活动不是大修活动']);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO fy_repair_registrations (activity_id, user_id, name, gender, departments, free_times) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$activity_id, $user_id, $name, $gender, $departments, $free_times]);

    echo json_encode(['success' => true]);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    regRepair();
}
?>
