<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
if (session_status() === PHP_SESSION_NONE) {
    session_name('admin');
    session_start();
}
require '../../../utils/xlsxreader.php';
require '../../../db.php';
$config = include('../../../config.php');

try {
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('文件上传失败');
    }

    $tmpName = $_FILES['file']['tmp_name'];
    $reader = new XlsxReader($tmpName);
    $data = $reader->getAssocData(1);
    $puredata = [];

    foreach ($data as &$row) {
        if (!isset($row['手机号']) || trim($row['手机号']) === '') {
            throw new Exception('表格中存在缺失手机号的行');
        }
        $stmt = $pdo->prepare("SELECT role FROM fy_users WHERE phone = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$row['手机号']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && $user['role'] === 'technician') {
            $row['role'] = '技术员';
            $row['role_class'] = 'text-success';
        } elseif ($user && $user['role'] === 'user') {
            $row['role'] = '用户';
            $row['role_class'] = 'text-primary';
            $puredata[] = $row;
        } else {
            $row['role'] = '';
            $row['role_class'] = 'text-muted';
            $puredata[] = [];
        }
    }
    
    $_SESSION['import_users'] = $puredata;
    echo json_encode([
        'success' => true,
        'users' => $data
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
