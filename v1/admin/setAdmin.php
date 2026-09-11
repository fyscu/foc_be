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
$role = $data['role'] ?? null;          // 可选：改角色
$password = $data['password'] ?? null;  // 可选：重置密码

if (!$id || ($role === null && $password === null)) {
    echo json_encode(['success' => false, 'message' => '参数不完整']);
    exit;
}
if ($role !== null && !in_array($role, ['super', 'active', 'lucky'], true)) {
    echo json_encode(['success' => false, 'message' => '角色不合法']);
    exit;
}
if ($password !== null && mb_strlen($password) < 8) {
    echo json_encode(['success' => false, 'message' => '密码至少 8 位']);
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

    // 飞书引入的管理员由飞书用户组管理，此处只读
    if ($target['feishu_union_id'] !== null) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => '飞书引入的管理员由飞书用户组管理，不能在此修改']);
        exit;
    }

    // 降级超管前确认还有别的超管（含飞书超管）
    if ($role !== null && $target['role'] === 'super' && $role !== 'super') {
        $cnt = (int)$pdo->query("SELECT COUNT(*) FROM fy_admins WHERE role = 'super'")->fetchColumn();
        if ($cnt <= 1) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => '至少保留一个超级管理员']);
            exit;
        }
    }

    $changes = [];
    if ($role !== null && $role !== $target['role']) {
        $stmt = $pdo->prepare("UPDATE fy_admins SET role = ? WHERE id = ?");
        $stmt->execute([$role, $id]);
        $changes['role'] = ['from' => $target['role'], 'to' => $role];
    }
    if ($password !== null) {
        $stmt = $pdo->prepare("UPDATE fy_admins SET password = ? WHERE id = ?");
        $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
        // 重置密码同时吊销该管理员所有在线会话
        try {
            $stmt = $pdo->prepare("DELETE FROM fy_admin_tokens WHERE admin_id = ?");
            $stmt->execute([$id]);
        } catch (Throwable $e) {
            // fy_admin_tokens 表尚未创建，跳过吊销
        }
        $changes['password'] = 'reset';
    }

    $pdo->commit();

    if ($changes) {
        fyAdminLog($pdo, $adminAccount, 'set_admin', 'admin', $id, [
            'username' => $target['username'],
            'changes' => $changes
        ]);
    }

    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '数据库错误']);
}
