<?php
session_name('admin');
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['admin_logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => '未授权']);
    exit;
}

require '../../../db.php';
$config = include('../../../config.php');

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = min(200, (int)($_GET['limit'] ?? 10));
$offset = ($page - 1) * $limit;

$keyword = trim($_GET['keyword'] ?? '');
$sort = $_GET['sort'] ?? 'id';
$order = strtolower($_GET['order'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
$repair_status = $_GET['repair_status'] ?? '';
$campus = $_GET['campus'] ?? '';

// 白名单限制
$allowedSort = ['id', 'assigned_time', 'completion_time'];
if (!in_array($sort, $allowedSort)) $sort = 'id';

// 搜索条件：id、用户昵称、技术员昵称、手机号
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

if ($keyword) {
    $where[] = "(w.id = ? 
        OR u.nickname LIKE ? 
        OR t.nickname LIKE ?
        OR w.user_phone LIKE ?)";
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
        u.nickname AS user_nick,
        t.nickname AS tech_nick
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

// 总数统计
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
    'rows' => $rows
]);
