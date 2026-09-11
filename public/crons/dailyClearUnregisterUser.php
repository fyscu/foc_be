<?php
/**
 * 清理过期未完成注册的 pending 用户（regtime 在 24h 前）。
 *
 * 仅允许 CLI 调用。
 * 1Panel 计划任务（Shell 脚本，每日执行）：
 *   php /opt/1panel/apps/openresty/openresty/www/sites/focapi.feiyang.ac.cn/index/public/crons/dailyClearUnregisterUser.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "This script is CLI-only.\n";
    exit(1);
}

chdir(__DIR__);

require_once __DIR__ . '/../../db.php';
$config = include __DIR__ . '/../../config.php';

$ts = date('Y-m-d H:i:s');
echo "[$ts] dailyClearUnregisterUser 开始\n";

$selectSql = "SELECT id, openid, phone FROM fy_users WHERE phone <> '' AND status = :status AND regtime <= DATE_SUB(NOW(), INTERVAL 1 DAY)";
$selectStmt = $pdo->prepare($selectSql);
$selectStmt->execute([':status' => 'pending']);
$targets = $selectStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($targets)) {
    echo "[$ts] 无需清理的 pending 用户\n";
    exit(0);
}

$ids = array_column($targets, 'id');
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$deleteSql = "DELETE FROM fy_users WHERE id IN ($placeholders)";
$deleteStmt = $pdo->prepare($deleteSql);
$deleteStmt->execute($ids);

echo "[$ts] 清理 {$deleteStmt->rowCount()} 个 pending 用户：" . implode(',', $ids) . "\n";
