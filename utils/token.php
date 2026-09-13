<?php
// utils/token.php — 2026-09-10 补丁版
// 唯一改动：verifyToken() 中 fy_admin_tokens 回退未命中时，新增 fy_app_tokens 回退
// （App 端 30 天长效 token）。其余逻辑与线上原版逐字一致；部署前原版备份为 token.php.bak-20260910。

function generateToken($openid, $salt) {
    global $pdo;
    $timestamp = time();
    $expiry = $timestamp + 3600; 
    $tokenString = $openid . $salt . $timestamp;
    $token = hash('sha256', $tokenString);
    $stmt = $pdo->prepare("UPDATE fy_users SET access_token = ?, token_expiry = ? WHERE openid = ?");
    $stmt->execute([$token, date('Y-m-d H:i:s', $expiry), $openid]);
    return ['token' => $token, 'expiry' => $expiry];
}

// 管理后台多端 token：每次登录新增一行 fy_admin_tokens，互不顶号
// fy_admin_tokens 表未创建时回退到旧的单 token（$fallbackOpenid + $fallbackSalt 提供时）
function generateAdminToken($adminId, $fallbackOpenid = null, $fallbackSalt = null) {
    global $pdo;
    $token = hash('sha256', random_bytes(32));
    $expiry = time() + 12 * 3600;
    $ip = $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '';

    try {
        // 顺手清理该管理员的过期 token
        $stmt = $pdo->prepare("DELETE FROM fy_admin_tokens WHERE admin_id = ? AND expires_at < NOW()");
        $stmt->execute([$adminId]);

        $stmt = $pdo->prepare("INSERT INTO fy_admin_tokens (admin_id, token, expires_at, created_at, ip) VALUES (?, ?, ?, NOW(), ?)");
        $stmt->execute([$adminId, $token, date('Y-m-d H:i:s', $expiry), $ip]);
        return ['token' => $token, 'expiry' => $expiry];
    } catch (Throwable $e) {
        error_log('[generateAdminToken] ' . $e->getMessage());
        if ($fallbackOpenid !== null && $fallbackSalt !== null) {
            return generateToken($fallbackOpenid, $fallbackSalt);
        }
        throw $e;
    }
}

function verifyToken($token) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM fy_users WHERE access_token = ?");
    $stmt->execute([$token]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$userData) {
        // 回退：管理后台多端 token（fy_admin_tokens），小程序链路不受影响
        try {
            $stmt = $pdo->prepare("SELECT t.expires_at, a.openid, a.role AS admin_role FROM fy_admin_tokens t JOIN fy_admins a ON a.id = t.admin_id WHERE t.token = ?");
            $stmt->execute([$token]);
            $adminToken = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            // fy_admin_tokens 表尚未创建
            return "user_not_found";
        }

        if (!$adminToken) {
            // [2026-09-10 新增] 回退：App 端长效 token（fy_app_tokens），小程序/管理端链路不受影响
            try {
                $stmt = $pdo->prepare("SELECT t.user_id, t.expires_at FROM fy_app_tokens t WHERE t.token = ?");
                $stmt->execute([$token]);
                $appToken = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (Throwable $e) {
                // fy_app_tokens 表尚未创建
                return "user_not_found";
            }
            if (!$appToken) {
                return "user_not_found";
            }
            if (strtotime($appToken['expires_at']) <= time()) {
                return "token_expired";
            }
            $stmt = $pdo->prepare("SELECT * FROM fy_users WHERE id = ?");
            $stmt->execute([$appToken['user_id']]);
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$userData) {
                return "user_not_found";
            }
            // 管理员身份判定与其他分支保持一致
            $stmtAdmin = $pdo->prepare("SELECT role FROM fy_admins WHERE openid = ?");
            $stmtAdmin->execute([$userData['openid']]);
            $adminData = $stmtAdmin->fetch(PDO::FETCH_ASSOC);
            if ($adminData) {
                $role = $adminData['role'];
                $userData['is_admin'] = ($role === 'super');
                $userData['is_lucky_admin'] = ($role === 'lucky' || $role === 'super');
            } else {
                $userData['is_admin'] = false;
                $userData['is_lucky_admin'] = false;
            }
            return $userData;
        }
        if (strtotime($adminToken['expires_at']) <= time()) {
            return "token_expired";
        }

        $stmt = $pdo->prepare("SELECT * FROM fy_users WHERE openid = ?");
        $stmt->execute([$adminToken['openid']]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$userData) {
            return "user_not_found";
        }

        $role = $adminToken['admin_role'];
        $userData['is_admin'] = ($role === 'super');
        $userData['is_lucky_admin'] = ($role === 'lucky' || $role === 'super');
        return $userData;
    } elseif ($userData && strtotime($userData['token_expiry']) > time()) {
        // 检查是否为管理员并细分权限组
        $openid = $userData['openid'];
        $stmtAdmin = $pdo->prepare("SELECT role FROM fy_admins WHERE openid = ?");
        $stmtAdmin->execute([$openid]);
        $adminData = $stmtAdmin->fetch(PDO::FETCH_ASSOC);

        if ($adminData) {
            $role = $adminData['role'];
            $userData['is_admin'] = ($role === 'super');
            $userData['is_lucky_admin'] = ($role === 'lucky' || $role === 'super');           
        } else {
            $userData['is_admin'] = false;
            $userData['is_lucky_admin'] = false;           
        }

        return $userData;
    } elseif (strtotime($userData['token_expiry']) <= time()) {
        $status = "token_expired";
        return $status;
    }
}
