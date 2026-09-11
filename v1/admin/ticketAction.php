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
$action = $data['action'] ?? '';

if (!$id || !in_array($action, ['cancel', 'restore', 'archive', 'unarchive'], true)) {
    echo json_encode(['success' => false, 'message' => '参数不正确']);
    exit;
}

$weeklyset = (int)($config['info']['weeklyset'] ?? 5);
$adminName = $adminAccount['username'];
$now = date('Y-m-d H:i:s');

// 以下事务逻辑逐行移植自老 public/admin/api/cancel_or_restore.php
try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT id, user_id, repair_status, archived, restored_from FROM fy_workorders WHERE id = ? FOR UPDATE");
    $stmt->execute([$id]);
    $t = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$t) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => '工单不存在']);
        exit;
    }

    if ($action === 'cancel') {
        // 仅 Pending（非存档）可手动 cancel
        if ($t['repair_status'] !== 'Pending' || (int)$t['archived'] === 1) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => '仅普通 Pending 可取消（当前 ' . $t['repair_status'] . '）']);
            exit;
        }
        $stmt = $pdo->prepare("UPDATE fy_workorders SET repair_status = 'Canceled' WHERE id = ?");
        $stmt->execute([$id]);
        $stmt = $pdo->prepare("UPDATE fy_users SET available = LEAST(available + 1, ?) WHERE id = ? AND role = 'user'");
        $stmt->execute([$weeklyset, $t['user_id']]);
        $newStatus = 'Canceled';
        $logType = 'admin_cancel';

    } elseif ($action === 'restore') {
        // 把"普通 Canceled"恢复回 Pending
        if ($t['repair_status'] !== 'Canceled' || (int)$t['archived'] === 1) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => '仅普通 Canceled 可恢复（当前 ' . $t['repair_status'] . '/archived=' . $t['archived'] . '）']);
            exit;
        }
        $stmt = $pdo->prepare("UPDATE fy_workorders SET repair_status = 'Pending' WHERE id = ?");
        $stmt->execute([$id]);
        $stmt = $pdo->prepare("UPDATE fy_users SET available = GREATEST(available - 1, 0) WHERE id = ? AND role = 'user'");
        $stmt->execute([$t['user_id']]);
        $newStatus = 'Pending';
        $logType = 'admin_restore';

    } elseif ($action === 'archive') {
        // 把 Pending 标为存档（status=Canceled, archived=1）+ 返还配额
        if ($t['repair_status'] !== 'Pending' || (int)$t['archived'] === 1) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => '仅普通 Pending 可存档']);
            exit;
        }
        $stmt = $pdo->prepare("UPDATE fy_workorders SET repair_status = 'Canceled', archived = 1, archived_at = ? WHERE id = ?");
        $stmt->execute([$now, $id]);
        $stmt = $pdo->prepare("UPDATE fy_users SET available = LEAST(available + 1, ?) WHERE id = ? AND role = 'user'");
        $stmt->execute([$weeklyset, $t['user_id']]);
        $newStatus = 'Archived';
        $logType = 'admin_archive';

    } else { // unarchive
        // 取消存档：把存档单恢复回 Pending 队列（如果原本被恢复过则禁止）
        if ((int)$t['archived'] !== 1) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => '该单不是存档单']);
            exit;
        }
        if (!empty($t['restored_from'])) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => '该存档单已被用户恢复为加急单，请直接处理对应加急单']);
            exit;
        }
        $stmt = $pdo->prepare("UPDATE fy_workorders SET repair_status = 'Pending', archived = 0, archived_at = NULL WHERE id = ?");
        $stmt->execute([$id]);
        $stmt = $pdo->prepare("UPDATE fy_users SET available = GREATEST(available - 1, 0) WHERE id = ? AND role = 'user'");
        $stmt->execute([$t['user_id']]);
        $newStatus = 'Pending';
        $logType = 'admin_unarchive';
    }

    $log = $pdo->prepare("INSERT INTO fy_transfer_record (ticketid, time, type, fromuid, fromname, userid, username, tid, tname) VALUES (?, ?, ?, 100000, ?, ?, '', 0, '')");
    $log->execute([$id, $now, $logType, $adminName, $t['user_id']]);

    $pdo->commit();

    fyAdminLog($pdo, $adminAccount, $logType, 'ticket', $id, [
        'from_status' => $t['repair_status'],
        'new_status' => $newStatus,
        'user_id' => (int)$t['user_id']
    ]);

    echo json_encode(['success' => true, 'id' => $id, 'new_status' => $newStatus]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '数据库错误']);
}
