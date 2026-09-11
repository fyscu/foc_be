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
$role = $_GET['role'] ?? '';
$campus = $_GET['campus'] ?? '';
$available = $_GET['available'] ?? '';
$sort = $_GET['sort'] ?? 'id';
$order = strtolower($_GET['order'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';

$allowedSortFields = ['id', 'nickname', 'phone', 'email', 'regtime'];
if (!in_array($sort, $allowedSortFields, true)) $sort = 'id';

$where = [];
$params = [];

if ($keyword !== '') {
    $where[] = "(id = ? OR nickname LIKE ? OR phone LIKE ? OR email LIKE ?)";
    $params[] = $keyword;
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
}
if ($role !== '' && in_array($role, ['user', 'technician', 'admin'], true)) {
    $where[] = "role = ?";
    $params[] = $role;
}
if ($campus !== '') {
    $where[] = "campus = ?";
    $params[] = $campus;
}
if ($available !== '') {
    $where[] = "available = ?";
    $params[] = (int)$available;
    $where[] = "role = 'technician'";
}

$whereSql = $where ? " WHERE " . implode(" AND ", $where) : "";

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM fy_users" . $whereSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

// 不返回 verification_code / access_token / openid 等敏感列
$sql = "SELECT id, nickname, realname, phone, email, role, campus, status, immed, regtime, available, wants, max_concurrent
        FROM fy_users" . $whereSql . " ORDER BY `$sort` $order LIMIT $offset, $limit";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'total' => $total,
    'page' => $page,
    'limit' => $limit,
    'rows' => $rows
], JSON_UNESCAPED_UNICODE);
