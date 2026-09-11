<?php
/**
 * 兜底修复：把"标记不可接单（available=0）但实际无活动单"的技术员恢复 available=1。
 *
 * 注意：新的分配算法（utils/ticket_assign.php）已不再读 fy_users.available 作为候选条件，
 *      仅按 当前 Repairing 数 < max_concurrent 决定。此 cron 保留是为了管理后台显示一致性，
 *      和兼容历史代码（give.php / set.php / complete.php 仍在写 available）。
 *
 * 仅允许 CLI 调用。
 * 1Panel 计划任务（Shell 脚本，每日执行）：
 *   php /opt/1panel/apps/openresty/openresty/www/sites/focapi.feiyang.ac.cn/index/public/crons/dailyTechStatusCheck.php
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
echo "[$ts] dailyTechStatusCheck 开始\n";

$stmt = $pdo->prepare("SELECT id, openid, nickname FROM fy_users WHERE role = :role AND available = :avail");
$stmt->execute([':role' => 'technician', ':avail' => 0]);
$techs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$checkStmt = $pdo->prepare("SELECT COUNT(*) FROM fy_workorders WHERE assigned_technician_id = :tech_id AND repair_status IN ('Repairing','TechConfirming','UserConfirming')");
$updStmt = $pdo->prepare("UPDATE fy_users SET available = 1 WHERE id = :tech_id");

$restored = [];
foreach ($techs as $tech) {
    $checkStmt->execute([':tech_id' => $tech['id']]);
    if ((int) $checkStmt->fetchColumn() === 0) {
        $updStmt->execute([':tech_id' => $tech['id']]);
        $restored[] = "{$tech['id']}({$tech['nickname']})";
    }
}

echo "[$ts] 恢复 " . count($restored) . " 名技术员 available=1：" . implode(', ', $restored) . "\n";
