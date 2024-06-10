<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include('../../db.php');
include('../../utils/token.php');
include('../../utils/headercheck.php');

function regRepair() {
    global $pdo;
    $activity_id = $_POST['activity_id'];
    $user_id = $_POST['uid'];
    $name = $_POST['name'];
    $gender = $_POST['gender'];
    $departments = implode(',', $_POST['departments']);
    $free_times = implode(',', $_POST['free_times']);

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
