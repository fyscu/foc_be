<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); 
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Max-Age: 86400");
session_name('admin');
session_start();
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

$keyword = $_GET['keyword'] ?? '';
$role = $_GET['role'] ?? '';
$campus = $_GET['campus'] ?? '';
$sort = $_GET['sort'] ?? 'id';
$order = strtolower($_GET['order'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';

$allowedSortFields = ['id', 'nickname', 'phone', 'email'];
if (!in_array($sort, $allowedSortFields)) $sort = 'id';

$where = [];
$params = [];

if ($keyword) {
    $where[] = "(id = ? OR nickname LIKE ? OR phone LIKE ? OR email LIKE ?)";
    $params[] = $keyword;
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
}
if ($role) {
    $where[] = "role = ?";
    $params[] = $role;
}
if ($campus) {
    $where[] = "campus = ?";
    $params[] = $campus;
}

$sql = "SELECT SQL_CALC_FOUND_ROWS id, nickname, realname, phone, email, role, campus, regtime FROM fy_users";
if ($where) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY `$sort` $order LIMIT $offset, $limit";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total = $pdo->query("SELECT FOUND_ROWS()")->fetchColumn();

echo json_encode(['total' => (int)$total, 'rows' => $rows]);
