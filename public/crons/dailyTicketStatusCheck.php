<?php
/**
 * 自动 Done：TechConfirming/UserConfirming 状态超过 7 天的工单强制设为 Done。
 *
 * 仅允许 CLI 调用。
 * 1Panel 计划任务（Shell 脚本，每日执行）：
 *   php /opt/1panel/apps/openresty/openresty/www/sites/focapi.feiyang.ac.cn/index/public/crons/dailyTicketStatusCheck.php
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
echo "[$ts] dailyTicketStatusCheck 开始\n";

$stmt = $pdo->prepare("SELECT id, order_hash, repair_status, assigned_time FROM fy_workorders WHERE repair_status IN ('TechConfirming','UserConfirming')");
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$now = time();
$updStmt = $pdo->prepare("UPDATE fy_workorders SET repair_status = 'Done', completion_time = NOW() WHERE id = :id");

$autoDone = [];
foreach ($orders as $o) {
    if (empty($o['assigned_time'])) continue;
    if ($now - strtotime($o['assigned_time']) > 7 * 24 * 3600) {
        $updStmt->execute([':id' => $o['id']]);
        $autoDone[] = "{$o['id']}({$o['repair_status']})";
    }
}

echo "[$ts] 自动 Done " . count($autoDone) . " 单：" . implode(', ', $autoDone) . "\n";
