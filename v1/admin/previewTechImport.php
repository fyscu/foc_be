<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Max-Age: 86400");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$config = include('../../config.php');
include('../../db.php');
include('../../utils/token.php');
include('../../utils/headercheck.php');
include('../../utils/admincheck.php');

requireAdminRole(['super', 'active']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '无效的请求方法']);
    exit;
}

try {
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('文件上传失败');
    }

    require('../../utils/xlsxreader.php');

    $reader = new XlsxReader($_FILES['file']['tmp_name']);
    $data = $reader->getAssocData(1); // 表头行：姓名 / 手机号

    $rows = [];
    foreach ($data as $row) {
        $phone = trim((string)($row['手机号'] ?? ''));
        if ($phone === '') {
            throw new Exception('表格中存在缺失手机号的行');
        }
        $name = trim((string)($row['姓名'] ?? ''));

        $stmt = $pdo->prepare("SELECT role, nickname FROM fy_users WHERE phone = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$phone]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $rows[] = [
            'name' => $name,
            'phone' => $phone,
            'nickname' => $user ? $user['nickname'] : null,
            'current_role' => $user ? $user['role'] : null,   // technician / user / admin / null(未注册)
            'importable' => $user && $user['role'] === 'user'
        ];
    }

    // 无状态：预览结果直接返回前端，提交时由 commitTechImport.php 带手机号列表重查
    echo json_encode(['success' => true, 'rows' => $rows], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
