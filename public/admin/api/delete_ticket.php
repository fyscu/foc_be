<?php
session_name('admin');
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['admin_logged_in'])) {
  echo json_encode(['success' => false, 'message' => '未登录']);
  exit;
}

require '../../../db.php';
$config = include('../../../config.php');

$id = $_POST['id'] ?? '';
if (!$id) {
  echo json_encode(['success' => false, 'message' => '参数缺失']);
  exit;
}

$stmt = $pdo->prepare("DELETE FROM fy_workorders WHERE id = ?");
$success = $stmt->execute([$id]);

echo json_encode(['success' => $success]);
