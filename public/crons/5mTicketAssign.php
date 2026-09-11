<?php
/**
 * 5 分钟一次的工单自动派单 cron。
 *
 * 仅允许 CLI 调用（防止外部 HTTP 触发）。
 * 1Panel 计划任务配置（类型选「Shell 脚本」）：
 *   php /opt/1panel/apps/openresty/openresty/www/sites/focapi.feiyang.ac.cn/index/public/crons/5mTicketAssign.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "This script is CLI-only.\n";
    exit(1);
}

// 相对路径需以脚本目录为基准
chdir(__DIR__);

$config = include(__DIR__ . '/../../config.php');
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../utils/ticket_assign.php';

echo "[" . date('Y-m-d H:i:s') . "] 5mTicketAssign 开始\n";
$results = runTicketAssignment($pdo, $config, true);
$ok = count(array_filter($results, fn($r) => empty($r['skipped'])));
$skipped = count($results) - $ok;
echo "[" . date('Y-m-d H:i:s') . "] 5mTicketAssign 结束：分配 {$ok} 单，跳过 {$skipped} 单\n";
