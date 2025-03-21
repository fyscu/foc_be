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

function regRepair() {
    global $pdo;
    global $userinfo;
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    $activity_id = $data['activity_id'];
    $user_id = $userinfo['id'];
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

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM fy_repair_registrations WHERE activity_id = ? AND user_id = ?");
    $stmt->execute([$activity_id, $user_id]);
    $userRegistered = $stmt->fetchColumn();

    if ($userRegistered > 0) {
        echo json_encode(['success' => false, 'message' => 'Registered']);
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
