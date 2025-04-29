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

$restored = [];

$stmt = $pdo->prepare("SELECT id, openid, nickname FROM fy_users WHERE role = :role AND available = :avail");
$stmt->execute([':role' => 'technician', ':avail' => 0]);
$techs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$checkStmt = $pdo->prepare("SELECT COUNT(*) FROM fy_workorders WHERE assigned_technician_id = :tech_id AND repair_status IN ('Repairing','TechConfirming','UserConfirming')");
$updStmt = $pdo->prepare("UPDATE fy_users SET available = :newavail WHERE id = :tech_id");

foreach ($techs as $tech) {
    $checkStmt->execute([':tech_id'=>$tech['id']]);
    if ((int)$checkStmt->fetchColumn() === 0) {
        $updStmt->execute([':newavail' => 1, ':tech_id' => $tech['id']]);
        $restored[] = ['id '=> $tech['id'], 'nickname' => $tech['nickname']];
    }
}
echo json_encode([
    'success' => true,
    'restored' => $restored
]);
