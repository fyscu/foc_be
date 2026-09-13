<?php
/**
 * 从一张存档订单恢复为加急订单（urgent=1）。
 *
 * 入参（POST JSON）：
 *   { "archive_id": <存档单 id> }
 *
 * 校验：
 *   - 鉴权：登录用户必须是该存档单的本人
 *   - 状态：archived=1 AND repair_status='Canceled'
 *   - 防重复：原存档单的 restored_from IS NULL（首次恢复后会反向写入新单 id）
 *
 * 副作用：
 *   - 创建一张新工单：复用原单字段，repair_status='Pending', urgent=1, urgent_at=NOW(), restored_from=原id
 *   - 原存档单的 restored_from 反写新单 id（用作"已被恢复"的锁）
 *   - 用户 fy_users.available -= 1（最低 0）
 *   - max_pending_per_user 限制仍生效（防止滥用）
 */

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
include('../../utils/gets.php');

$user = $userinfo;

if ($user['role'] !== 'user') {
    echo json_encode(['success' => false, 'message' => '仅用户可恢复存档订单']);
    exit;
}

function rejectPausedRepairCreation() {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'status' => 'repair_paused',
        'message' => '当前暂停报修，请稍后再试',
    ]);
    exit;
}

function repairCreationIsEnabled(PDO $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT data FROM fy_confs WHERE name = ? LIMIT 2");
        $stmt->execute(['Global_Flag']);
        $values = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        return false;
    }

    return count($values) === 1 && trim((string) $values[0]) === '1';
}

function archiveTicketId($value) {
    if (!is_int($value) && !is_string($value)) {
        return null;
    }

    $id = (string) $value;
    if (!preg_match('/^[0-9]+$/D', $id)) {
        return null;
    }

    $id = ltrim($id, '0');
    if ($id === '' || strlen($id) > 19
        || (strlen($id) === 19 && strcmp($id, '9223372036854775807') > 0)) {
        return null;
    }

    return $id;
}

if (!repairCreationIsEnabled($pdo)) {
    rejectPausedRepairCreation();
}

$json = file_get_contents('php://input');
$data = json_decode($json, true);
$archiveId = is_array($data) && array_key_exists('archive_id', $data)
    ? archiveTicketId($data['archive_id'])
    : null;

if ($archiveId === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'status' => 'invalid_params', 'message' => '缺少 archive_id']);
    exit;
}

// 单用户未完结上限（含加急）
$maxPerUser = (int) ($config['info']['max_pending_per_user'] ?? 1);
$stmt = $pdo->prepare("SELECT COUNT(*) FROM fy_workorders WHERE user_id = ? AND repair_status IN ('Pending','Repairing','UserConfirming','TechConfirming')");
$stmt->execute([$user['id']]);
if ((int) $stmt->fetchColumn() >= $maxPerUser) {
    echo json_encode([
        'success' => false,
        'status' => 'user_pending_limit',
        'message' => '您还有未完结的报修单，待处理完再恢复存档哦～'
    ]);
    exit;
}

try {
    $pdo->beginTransaction();

    // 锁定存档单
    $stmt = $pdo->prepare("SELECT * FROM fy_workorders WHERE id = ? FOR UPDATE");
    $stmt->execute([$archiveId]);
    $archive = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$archive) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => '存档订单不存在']);
        exit;
    }
    if ((int) $archive['user_id'] !== (int) $user['id']) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => '无权操作他人订单']);
        exit;
    }
    if ((int) $archive['archived'] !== 1 || $archive['repair_status'] !== 'Canceled') {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => '此订单不是存档订单']);
        exit;
    }
    if (!empty($archive['restored_from'])) {
        // restored_from 字段在存档单上被用作"已恢复"锁（反向指向新单 id）
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => '该存档订单已恢复过，请直接前往新工单查看']);
        exit;
    }

    // 复用字段创建新工单
    $now = date('Y-m-d H:i:s');
    $tvcode = random_int(100000, 999999);
    $combined = $archive['user_id'] . $archive['machine_purchase_date'] . $archive['user_phone']
        . $archive['warranty_status'] . $archive['device_type'] . $archive['computer_brand']
        . $archive['repair_description'] . $archive['repair_image_url'] . $archive['fault_type']
        . $archive['qq_number'] . $archive['campus'] . $archive['user_nick'] . $archive['model']
        . '|urgent|' . $now;
    $orderhash = hash('sha256', $combined);

    $insert = $pdo->prepare("
        INSERT INTO fy_workorders (
            user_id, machine_purchase_date, user_phone, warranty_status, device_type,
            computer_brand, repair_description, repair_status, repair_image_url, fault_type,
            qq_number, campus, DuoCampus, order_hash, transcode, user_nick, model,
            urgent, urgent_at, restored_from
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)
    ");
    $insert->execute([
        $archive['user_id'], $archive['machine_purchase_date'], $archive['user_phone'],
        $archive['warranty_status'], $archive['device_type'], $archive['computer_brand'],
        $archive['repair_description'], $archive['repair_image_url'], $archive['fault_type'],
        $archive['qq_number'], $archive['campus'], $archive['DuoCampus'], $orderhash,
        $tvcode, $archive['user_nick'], $archive['model'],
        $now, $archive['id'],
    ]);
    $newId = (string) $pdo->lastInsertId();

    // 反向标记存档单，作为"已恢复"锁
    $stmt = $pdo->prepare("UPDATE fy_workorders SET restored_from = ? WHERE id = ?");
    $stmt->execute([$newId, $archiveId]);

    // 扣用户配额（不允许小于 0）
    $stmt = $pdo->prepare("UPDATE fy_users SET available = GREATEST(available - 1, 0) WHERE id = ? AND role = 'user'");
    $stmt->execute([$user['id']]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'orderid' => (string) $newId,
        'urgent' => true,
        'message' => '已恢复为加急订单',
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[restore_archive] ' . get_class($e) . ': ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '服务器错误']);
}
