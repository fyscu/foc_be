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
$limit = min(200, max(1, (int)($_GET['limit'] ?? 50)));
$offset = ($page - 1) * $limit;

$action = trim($_GET['action'] ?? '');

$where = [];
$params = [];
if ($action !== '') {
    $where[] = "action = ?";
    $params[] = $action;
}
$whereSql = $where ? " WHERE " . implode(" AND ", $where) : "";

try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM fy_admin_logs" . $whereSql);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT id, admin_username, admin_role, action, target_type, target_id, detail, ip, created_at FROM fy_admin_logs" . $whereSql . " ORDER BY id DESC LIMIT $offset, $limit");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'rows' => $rows
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    // fy_admin_logs 表可能尚未创建
    echo json_encode(['success' => false, 'message' => '审计日志表不可用，请确认已执行 deploy/20260611_fy_admin_logs.sql']);
}
