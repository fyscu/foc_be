<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
if (session_status() === PHP_SESSION_NONE) {
    session_name('admin');
    session_start();
}
require '../../../db.php';
$config = include('../../../config.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$input = $_POST;
$response = [];

function importTechnicianByPhone($pdo, $phone) {
    $stmt = $pdo->prepare("SELECT id, nickname, role, available FROM fy_users WHERE phone = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$phone]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        return "<br> 未找到手机号为 $phone 的用户";
    }

    if ($user['role'] === 'technician') {
        return "<br> $user[nickname] 已经是技术员";
    }

    $update = $pdo->prepare("UPDATE fy_users SET role = 'technician', available = 1, wants = 'a' WHERE id = ?");
    $update->execute([$user['id']]);

    return "<br> $user[nickname] 导入成功";
}

if (isset($input['phone'])) {
    echo importTechnicianByPhone($pdo, $input['phone']);
    exit;
}

if (isset($input['all']) && $input['all'] == '1') {

    if (!isset($_SESSION['import_users']) || !is_array($_SESSION['import_users'])) {
        exit('未找到可导入的数据，请重新上传');
    }

    $results = [];
    foreach ($_SESSION['import_users'] as $row) {
        if (isset($row['手机号'])) {
            $results[] = importTechnicianByPhone($pdo, $row['手机号']);
        }
    }
    echo implode("\n", $results);
    exit;
}

exit('参数错误');
