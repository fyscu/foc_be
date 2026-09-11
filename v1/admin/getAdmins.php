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

$sql = "SELECT a.id, a.username, a.role, a.openid, a.feishu_union_id, a.created_at,
               u.id AS user_id, u.nickname, u.phone
        FROM fy_admins a
        LEFT JOIN fy_users u ON BINARY u.openid = BINARY a.openid
        ORDER BY a.id ASC";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$superCount = 0;
foreach ($rows as &$r) {
    $r['is_feishu'] = $r['feishu_union_id'] !== null;
    $r['is_self'] = $r['openid'] === $adminAccount['openid'];
    if ($r['role'] === 'super') $superCount++;
    unset($r['openid'], $r['feishu_union_id']);
}
unset($r);

echo json_encode([
    'success' => true,
    'super_count' => $superCount,
    'rows' => $rows
], JSON_UNESCAPED_UNICODE);
