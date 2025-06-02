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

$selectSql = "SELECT id, openid, phone FROM fy_users WHERE phone <> '' AND status = :status AND regtime <= DATE_SUB(NOW(), INTERVAL 1 DAY)";
$selectStmt = $pdo->prepare($selectSql);
$selectStmt->execute([':status' => 'pending']);
$targets = $selectStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($targets)) {
    echo json_encode(['success' => true, 'deleted' => [], 'message' => 'No stale pending users']);
    exit;
}

$ids = array_column($targets, 'id');
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$deleteSql = "DELETE FROM fy_users WHERE id IN ($placeholders)";
$deleteStmt = $pdo->prepare($deleteSql);
$deleteStmt->execute($ids);

echo json_encode([
    'success'       => true,
    'deleted'       => $ids,
    'deleted_count' => $deleteStmt->rowCount()
]);
