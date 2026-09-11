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
include('../../utils/adminlog.php');

requireAdminRole(['super']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '无效的请求方法']);
    exit;
}

$json = file_get_contents('php://input');
$data = json_decode($json, true) ?: [];
$id = (int)($data['id'] ?? 0);

if (!$id) {
    echo json_encode(['success' => false, 'message' => '参数缺失']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT * FROM fy_admins WHERE id = ? FOR UPDATE");
    $stmt->execute([$id]);
    $target = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$target) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => '管理员不存在']);
        exit;
    }

    if ($target['feishu_union_id'] !== null) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => '飞书引入的管理员由飞书用户组管理，不能在此移除']);
        exit;
    }

    if ($target['openid'] === $adminAccount['openid']) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => '不能移除自己']);
        exit;
    }

    if ($target['role'] === 'super') {
        $cnt = (int)$pdo->query("SELECT COUNT(*) FROM fy_admins WHERE role = 'super'")->fetchColumn();
        if ($cnt <= 1) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => '至少保留一个超级管理员']);
            exit;
        }
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM fy_admin_tokens WHERE admin_id = ?");
        $stmt->execute([$id]);
    } catch (Throwable $e) {
        // fy_admin_tokens 表尚未创建，跳过吊销
    }

    $stmt = $pdo->prepare("DELETE FROM fy_admins WHERE id = ?");
    $stmt->execute([$id]);

    $pdo->commit();

    fyAdminLog($pdo, $adminAccount, 'delete_admin', 'admin', $id, [
        'username' => $target['username'],
        'role' => $target['role']
    ]);

    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '数据库错误']);
}
