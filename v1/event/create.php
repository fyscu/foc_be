<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include('../../db.php');
include('../../utils/token.php');
include('../../utils/headercheck.php');

function createEvent() {
    global $pdo;
    $name = $_POST['name'];
    $type = $_POST['type'];
    $description = $_POST['description'];
    $start_time = $_POST['start_time'];
    $signup_start_time = $_POST['signup_start_time'];
    $signup_end_time = $_POST['signup_end_time'];

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
