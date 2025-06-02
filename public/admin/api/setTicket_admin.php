<?php
session_name('admin');
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['admin_logged_in'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '未登录']);
    exit;
}

require '../../../db.php';
$config = include('../../../config.php');

$id = $_POST['id'] ?? '';
$repair_status = $_POST['repair_status'] ?? '';

if (!$id || !$repair_status) {
    echo json_encode(['success' => false, 'message' => '参数不完整']);
    exit;
}

$allowedFields = ['repair_status'];
$setClause = [];
$params = [];

if (in_array('repair_status', $allowedFields) && $repair_status) {
    $setClause[] = "repair_status = ?";
    $params[] = $repair_status;
}

if (empty($setClause)) {
    echo json_encode(['success' => false, 'message' => '未指定修改字段']);
    exit;
}

$params[] = $id;

$sql = "UPDATE fy_workorders SET " . implode(',', $setClause) . " WHERE id = ?";
$stmt = $pdo->prepare($sql);
$success = $stmt->execute($params);

echo json_encode(['success' => $success]);
