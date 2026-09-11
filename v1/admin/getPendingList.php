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

requireAdminRole(['super']);

/**
 * 视图（与老 public/admin/api/pending_list.php 同构）：
 *   - Pending  : repair_status='Pending'  AND archived=0  （正常队列，urgent 优先 + FIFO）
 *   - Canceled : repair_status='Canceled' AND archived=0  （admin 主动取消的）
 *   - Archived : archived=1                               （存档订单）
 */
$view = $_GET['view'] ?? 'Pending';

switch ($view) {
    case 'Pending':
        $where = "w.repair_status = 'Pending' AND w.archived = 0";
        $order = 'w.urgent DESC, w.urgent_at ASC, w.create_time ASC, w.id ASC';
        break;
    case 'Canceled':
        $where = "w.repair_status = 'Canceled' AND w.archived = 0";
        $order = 'w.id DESC';
        break;
    case 'Archived':
        $where = "w.archived = 1";
        $order = 'w.archived_at DESC, w.id DESC';
        break;
    default:
        echo json_encode(['success' => false, 'message' => '不支持的视图']);
        exit;
}

$sql = "
    SELECT w.id, w.user_id, w.user_phone, w.user_nick, w.campus, w.device_type,
           w.computer_brand, w.fault_type, w.repair_description, w.repair_status,
           w.create_time, w.archived, w.archived_at, w.urgent, w.urgent_at, w.restored_from,
           u.nickname AS user_nickname, u.available AS user_available
    FROM fy_workorders w
    LEFT JOIN fy_users u ON u.id = w.user_id
    WHERE $where
    ORDER BY $order
    LIMIT 500
";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as &$r) {
    $r['archived'] = (int)$r['archived'];
    $r['urgent'] = (int)$r['urgent'];
    $r['restored_from'] = $r['restored_from'] !== null ? (int)$r['restored_from'] : null;
}
unset($r);

echo json_encode([
    'success' => true,
    'view' => $view,
    'count' => count($rows),
    'rows' => $rows
], JSON_UNESCAPED_UNICODE);
