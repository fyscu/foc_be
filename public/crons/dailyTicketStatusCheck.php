<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Max-Age: 86400");

include('../../db.php');
$config = include('../../config.php');

$actioncode = $_GET['token'] ?? null;
if ($actioncode !== $config['info']['actioncode']) {
    echo json_encode([
        'success' => false,
        'message' => "Bad adtion code"
    ]);
    exit;
}

$autoDone = [];

$stmt = $pdo->prepare("SELECT id, order_hash, repair_status, assigned_time FROM fy_workorders WHERE repair_status = :tc OR repair_status = :uc");
$stmt->execute([ ':tc' => 'TechConfirming', ':uc' => 'UserConfirming' ]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$now = time();
$updStmt = $pdo->prepare("UPDATE fy_workorders SET repair_status = :newstatus, completion_time = NOW() WHERE id = :id");

foreach ($orders as $o) {
    if (empty($o['assigned_time'])) continue;
    if ($now - strtotime($o['assigned_time']) > 7 * 24 * 3600) {
        $updStmt->execute([ ':newstatus' => 'Done', ':id' => $o['id'] ]);
        $autoDone[] = [ 'id' => $o['id'], 'old_status' => $o['repair_status'] ];
    }
}

echo json_encode([
    'success' => true,
    'auto_done' => $autoDone
]);
