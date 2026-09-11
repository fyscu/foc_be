<?php
/**
 * 每周重置用户报修配额（fy_users.available = weeklyset）。
 *
 * 仅允许 CLI 调用。
 *
 * 1Panel 计划任务（Shell 脚本，每周执行）：
 *   php /opt/1panel/apps/openresty/openresty/www/sites/focapi.feiyang.ac.cn/index/public/crons/weeklyUserQuotaReset.php
 *
 * 单用户重置（可选）：
 *   php weeklyUserQuotaReset.php --openid=oXxxxxxxxxxxx
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "This script is CLI-only.\n";
    exit(1);
}

chdir(__DIR__);

require_once __DIR__ . '/../../db.php';
$config = include __DIR__ . '/../../config.php';

$weeklyset = (int) ($config['info']['weeklyset'] ?? 5);

// 解析 CLI argv：支持 --openid=xxx
$openid = null;
foreach ($argv as $arg) {
    if (preg_match('/^--openid=(.+)$/', $arg, $m)) {
        $openid = $m[1];
    }
}

$ts = date('Y-m-d H:i:s');
echo "[$ts] weeklyUserQuotaReset 开始（weeklyset=$weeklyset）\n";

if ($openid) {
    $stmt = $pdo->prepare("UPDATE fy_users SET available = :weeklyset WHERE openid = :openid AND role = 'user' AND available != :weeklyset");
    $stmt->execute([':weeklyset' => $weeklyset, ':openid' => $openid]);
    echo "[$ts] 单用户 openid=$openid 重置完成，affected={$stmt->rowCount()}\n";
} else {
    $stmt = $pdo->prepare("UPDATE fy_users SET available = :weeklyset WHERE role = 'user' AND available != :weeklyset");
    $stmt->execute([':weeklyset' => $weeklyset]);
    echo "[$ts] 全用户重置完成，affected={$stmt->rowCount()}\n";
}
