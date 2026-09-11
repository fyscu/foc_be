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

requireAdminRole(['super', 'active']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '无效的请求方法']);
    exit;
}

$json = file_get_contents('php://input');
$data = json_decode($json, true) ?: [];

$action = $data['action'] ?? '';
$phone = trim($data['phone'] ?? '');

if ($phone === '' || !in_array($action, ['activate', 'migrate'], true)) {
    echo json_encode(['success' => false, 'message' => '参数不正确']);
    exit;
}

if ($action === 'activate') {
    // 移植 quickactive：激活 = pending → verified（pending_users.php 版本同时置 immed=0 表示走完旧流程，
    // 但 index.php 版本只改 status —— 这里取 index.php 口径，仅改 status，不动 immed）
    $stmt = $pdo->prepare("UPDATE fy_users SET status = 'verified' WHERE phone COLLATE utf8mb4_general_ci = ? AND status = 'pending'");
    $stmt->execute([$phone]);

    if ($stmt->rowCount() > 0) {
        fyAdminLog($pdo, $adminAccount, 'activate_user', 'user', $phone, ['action' => 'activate']);
        echo json_encode(['success' => true, 'message' => '用户成功激活']);
    } else {
        echo json_encode(['success' => false, 'message' => '该用户已激活或不存在，无需操作']);
    }
    exit;
}

// migrate：把新 openid 关联到旧手机号账户，删除冗余新记录（移植 quickactive 两处迁移逻辑，事务包裹）
$openid = trim($data['openid'] ?? '');

// 未显式传 openid 时，从短信日志找该手机号最后一条 imm 记录
if ($openid === '') {
    $stmt = $pdo->prepare("SELECT openid, type FROM fy_sms_log WHERE phone = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$phone]);
    $sms = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$sms || $sms['type'] !== 'imm') {
        echo json_encode(['success' => false, 'message' => '未找到该手机号的迁移（imm）验证码记录']);
        exit;
    }
    $openid = $sms['openid'];
}

try {
    $pdo->beginTransaction();

    // 旧账户：该手机号下 verified 且未迁移的记录
    $stmt = $pdo->prepare("SELECT id FROM fy_users WHERE phone COLLATE utf8mb4_general_ci = ? AND status = 'verified' AND immed = 0 LIMIT 1");
    $stmt->execute([$phone]);
    $target = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$target) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => '未找到可进行迁移的旧记录']);
        exit;
    }

    // 删除新 openid 自动创建的冗余记录（无手机号的那行）
    $stmt = $pdo->prepare("SELECT id FROM fy_users WHERE openid = ? AND (phone IS NULL OR phone = '') ORDER BY id DESC LIMIT 1");
    $stmt->execute([$openid]);
    $redundant = $stmt->fetchColumn();
    if ($redundant) {
        $del = $pdo->prepare("DELETE FROM fy_users WHERE id = ?");
        $del->execute([$redundant]);
    }

    // 把新 openid 写到旧账户上
    $upd = $pdo->prepare("UPDATE fy_users SET openid = ?, immed = 1 WHERE id = ?");
    $upd->execute([$openid, $target['id']]);

    $pdo->commit();

    fyAdminLog($pdo, $adminAccount, 'migrate_user', 'user', $phone, [
        'action' => 'migrate',
        'old_user_id' => (int)$target['id'],
        'deleted_redundant_id' => $redundant ? (int)$redundant : null
    ]);

    echo json_encode(['success' => true, 'message' => '用户迁移激活成功，冗余记录已删除']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '迁移失败：数据库错误']);
}
