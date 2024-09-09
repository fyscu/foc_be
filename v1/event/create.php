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

function createEvent() {
    global $pdo;
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    $name = $data['name'];
    $type = $data['type'];
    $description = $data['description'];
    $start_time = $data['start_time'];
    $signup_start_time = $data['signup_start_time'];
    $signup_end_time = $data['signup_end_time'];

    $stmt = $pdo->prepare("INSERT INTO fy_activities (name, type, description, start_time, signup_start_time, signup_end_time) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$name, $type, $description, $start_time, $signup_start_time, $signup_end_time]);

    $eventId = $pdo->lastInsertId();

    if($eventId){
        echo json_encode([
            'success' => true,
            'eventid' => $eventId
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'eventid' => ''
        ]);
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    createEvent();
}
?>
