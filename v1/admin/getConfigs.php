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

$stmt = $pdo->query("SELECT id, name, info, data FROM fy_confs ORDER BY id ASC");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 工单号现状：当前 AUTO_INCREMENT 与年份前缀
$tickets = ['next_id' => null, 'max_id' => null, 'current_prefix' => null];
try {
    $row = $pdo->query("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fy_workorders'")->fetch(PDO::FETCH_ASSOC);
    $tickets['next_id'] = $row ? (int)$row['AUTO_INCREMENT'] : null;
    $tickets['max_id'] = (int)$pdo->query("SELECT COALESCE(MAX(id), 0) FROM fy_workorders")->fetchColumn();
    if ($tickets['next_id']) {
        $tickets['current_prefix'] = (int)substr((string)$tickets['next_id'], 0, 4);
    }
} catch (Throwable $e) {
    // 拿不到 AUTO_INCREMENT 无关紧要
}

echo json_encode([
    'success' => true,
    'rows' => $rows,
    'tickets' => $tickets
], JSON_UNESCAPED_UNICODE);
