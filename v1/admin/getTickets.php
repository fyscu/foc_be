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

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = min(200, max(1, (int)($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;

$keyword = trim($_GET['keyword'] ?? '');
$sort = $_GET['sort'] ?? 'id';
$order = strtolower($_GET['order'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
$repair_status = $_GET['repair_status'] ?? '';
$campus = $_GET['campus'] ?? '';

$allowedSort = ['id', 'create_time', 'assigned_time', 'completion_time'];
if (!in_array($sort, $allowedSort, true)) $sort = 'id';

$where = [];
$params = [];

if ($repair_status !== '') {
    $where[] = 'w.repair_status = ?';
    $params[] = $repair_status;
}
if ($campus !== '') {
    $where[] = 'w.campus = ?';
    $params[] = $campus;
}
if ($keyword !== '') {
    $where[] = "(w.id = ? OR u.nickname LIKE ? OR t.nickname LIKE ? OR w.user_phone LIKE ?)";
    $params[] = $keyword;
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT
        w.id, w.user_phone, w.repair_status, w.campus,
        w.create_time, w.assigned_time, w.completion_time,
        w.device_type, w.computer_brand, w.model, w.fault_type, w.repair_description,
        w.archived, w.urgent, w.order_hash,
        u.nickname AS user_nick,
        u.id AS user_id,
        t.nickname AS tech_nick,
        t.id AS tech_id
    FROM fy_workorders w
    LEFT JOIN fy_users u ON w.user_id = u.id
    LEFT JOIN fy_users t ON w.assigned_technician_id = t.id
    $whereSql
    ORDER BY w.$sort $order
    LIMIT $offset, $limit
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as &$r) {
    $r['archived'] = (int)$r['archived'];
    $r['urgent'] = (int)$r['urgent'];
}
unset($r);

$countSql = "
    SELECT COUNT(*) FROM fy_workorders w
    LEFT JOIN fy_users u ON w.user_id = u.id
    LEFT JOIN fy_users t ON w.assigned_technician_id = t.id
    $whereSql
";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

echo json_encode([
    'success' => true,
    'total' => $total,
    'page' => $page,
    'limit' => $limit,
    'rows' => $rows
], JSON_UNESCAPED_UNICODE);
