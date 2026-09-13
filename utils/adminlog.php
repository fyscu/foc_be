<?php
// 管理后台审计日志。写入 fy_admin_logs（迁移脚本见 deploy/20260611_fy_admin_logs.sql）。
// 任何异常（含表未创建）都吞掉，绝不影响主操作。

function fyAdminLog($pdo, $adminAccount, $action, $targetType = '', $targetId = '', $detail = null) {
    try {
        $ip = $_SERVER['HTTP_X_REAL_IP']
            ?? (isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]) : null)
            ?? $_SERVER['REMOTE_ADDR']
            ?? '';
        $stmt = $pdo->prepare("INSERT INTO fy_admin_logs (admin_username, admin_openid, admin_role, action, target_type, target_id, detail, ip, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $adminAccount['username'] ?? '',
            $adminAccount['openid'] ?? '',
            $adminAccount['role'] ?? '',
            $action,
            $targetType,
            (string)$targetId,
            $detail !== null ? json_encode($detail, JSON_UNESCAPED_UNICODE) : null,
            $ip,
            date('Y-m-d H:i:s')
        ]);
    } catch (Throwable $e) {
        // 日志失败不影响主流程
        error_log('[fyAdminLog] ' . $e->getMessage());
    }
}
