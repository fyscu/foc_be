<?php
declare(strict_types=1);

const FDX_VERSION = '0.1.0';

function fdx_load_config(): array
{
    $paths = [
        __DIR__ . '/../config.local.php',
        __DIR__ . '/../../../config.feidaxiu.php',
        __DIR__ . '/../deploy/config.example.php',
    ];

    foreach ($paths as $path) {
        if (is_file($path)) {
            $config = include $path;
            if (is_array($config)) {
                return $config;
            }
        }
    }

    return [];
}

function fdx_config(?string $key = null, mixed $default = null): mixed
{
    static $config = null;
    if ($config === null) {
        $config = fdx_load_config();
    }

    if ($key === null) {
        return $config;
    }

    $value = $config;
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }

    return $value;
}

function fdx_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $secure = (bool) fdx_config('security.cookie_secure', true);
    session_name((string) fdx_config('security.session_name', 'FDXSESSID'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/public/newrepair',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function fdx_cookie_options(int $expires = 0): array
{
    return [
        'expires' => $expires,
        'path' => '/public/newrepair',
        'domain' => '',
        'secure' => (bool) fdx_config('security.cookie_secure', true),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function fdx_auth_cookie_key(): string
{
    $key = (string) fdx_config('security.auth_cookie_key', '');
    if ($key !== '') {
        return $key;
    }

    $key = (string) fdx_config('security.encryption_key', '');
    if ($key !== '') {
        return $key;
    }

    $fallback = trim((string) fdx_config('wechat.secret', ''));
    if ($fallback !== '') {
        return hash('sha256', $fallback . '|' . (string) fdx_config('security.session_name', 'FDXSESSID'));
    }

    return '';
}

function fdx_technician_cookie_name(): string
{
    return (string) fdx_config('security.technician_cookie_name', 'FDXTECH');
}

function fdx_technician_cookie_ttl_seconds(): int
{
    return max(3600, (int) fdx_config('security.technician_cookie_ttl_seconds', 60 * 60 * 24 * 30));
}

function fdx_encrypt_with_key(string $plain, string $keyMaterial): ?string
{
    if ($plain === '' || $keyMaterial === '' || !function_exists('openssl_encrypt')) {
        return null;
    }

    $key = hash('sha256', $keyMaterial, true);
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false) {
        return null;
    }

    return base64_encode($iv . $tag . $cipher);
}

function fdx_decrypt_with_key(?string $encoded, string $keyMaterial): ?string
{
    if ($encoded === null || $encoded === '' || $keyMaterial === '' || !function_exists('openssl_decrypt')) {
        return null;
    }

    $raw = base64_decode($encoded, true);
    if ($raw === false || strlen($raw) <= 28) {
        return null;
    }

    $iv = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $cipher = substr($raw, 28);
    $key = hash('sha256', $keyMaterial, true);
    $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

    return $plain === false ? null : $plain;
}

function fdx_set_cookie_value(string $name, string $value, int $expires): void
{
    setcookie($name, $value, fdx_cookie_options($expires));
    $_COOKIE[$name] = $value;
}

function fdx_clear_cookie_value(string $name): void
{
    setcookie($name, '', fdx_cookie_options(time() - 3600));
    unset($_COOKIE[$name]);
}

function fdx_parse_technician_cookie(bool $clearInvalid = true): ?array
{
    $name = fdx_technician_cookie_name();
    $encoded = trim((string) ($_COOKIE[$name] ?? ''));
    if ($encoded === '') {
        return null;
    }

    $plain = fdx_decrypt_with_key($encoded, fdx_auth_cookie_key());
    if ($plain === null) {
        if ($clearInvalid) {
            fdx_clear_cookie_value($name);
        }
        return null;
    }

    $payload = json_decode($plain, true);
    if (!is_array($payload)) {
        if ($clearInvalid) {
            fdx_clear_cookie_value($name);
        }
        return null;
    }

    $technicianId = (int) ($payload['technician_id'] ?? 0);
    $appid = trim((string) ($payload['appid'] ?? ''));
    $openid = trim((string) ($payload['openid'] ?? ''));
    $expiresAt = (int) ($payload['expires_at'] ?? 0);
    if ($technicianId <= 0 || $appid === '' || $openid === '' || $expiresAt <= time()) {
        if ($clearInvalid) {
            fdx_clear_cookie_value($name);
        }
        return null;
    }

    return [
        'technician_id' => $technicianId,
        'appid' => $appid,
        'openid' => $openid,
        'expires_at' => $expiresAt,
    ];
}

function fdx_issue_technician_cookie(array $technician, array $wechat): void
{
    $technicianId = (int) ($technician['id'] ?? 0);
    $appid = trim((string) ($wechat['appid'] ?? ''));
    $openid = trim((string) ($wechat['openid'] ?? ''));
    $keyMaterial = fdx_auth_cookie_key();
    if ($technicianId <= 0 || $appid === '' || $openid === '' || $keyMaterial === '') {
        return;
    }

    $ttl = fdx_technician_cookie_ttl_seconds();
    $current = fdx_parse_technician_cookie(false);
    $refreshWindow = max(1800, min(86400, (int) floor($ttl / 4)));
    if ($current
        && (int) $current['technician_id'] === $technicianId
        && (string) $current['appid'] === $appid
        && (string) $current['openid'] === $openid
        && (int) $current['expires_at'] > time() + $refreshWindow
    ) {
        return;
    }

    $expiresAt = time() + $ttl;
    $token = fdx_encrypt_with_key(json_encode([
        'technician_id' => $technicianId,
        'appid' => $appid,
        'openid' => $openid,
        'issued_at' => time(),
        'expires_at' => $expiresAt,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $keyMaterial);
    if ($token === null) {
        return;
    }

    fdx_set_cookie_value(fdx_technician_cookie_name(), $token, $expiresAt);
}

function fdx_sync_technician_cookie_from_session(): void
{
    $technician = $_SESSION['technician'] ?? null;
    $wechat = $_SESSION['wechat'] ?? null;
    if (!is_array($technician) || !is_array($wechat)) {
        return;
    }

    fdx_issue_technician_cookie($technician, $wechat);
}

function fdx_restore_technician_session_from_cookie(): ?array
{
    $cookie = fdx_parse_technician_cookie();
    if ($cookie === null) {
        return null;
    }

    $stmt = fdx_db()->prepare("
        SELECT
            t.id,
            t.display_name,
            t.phone,
            t.campus,
            b.unionid
        FROM fdx_wechat_bindings b
        INNER JOIN fdx_technicians t ON t.id = b.technician_id
        WHERE b.technician_id = :technician_id
          AND b.appid = :appid
          AND b.openid = :openid
          AND b.status = 'active'
          AND t.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([
        ':technician_id' => $cookie['technician_id'],
        ':appid' => $cookie['appid'],
        ':openid' => $cookie['openid'],
    ]);
    $row = $stmt->fetch();
    if (!$row) {
        fdx_clear_cookie_value(fdx_technician_cookie_name());
        return null;
    }

    $_SESSION['wechat'] = [
        'appid' => $cookie['appid'],
        'openid' => $cookie['openid'],
        'unionid' => $row['unionid'] ?? null,
    ];
    $_SESSION['technician'] = [
        'id' => (int) $row['id'],
        'display_name' => $row['display_name'],
        'phone' => $row['phone'],
        'campus' => $row['campus'],
    ];

    fdx_db()->prepare("
        UPDATE fdx_wechat_bindings
        SET last_seen_at = NOW()
        WHERE technician_id = :technician_id
          AND appid = :appid
          AND openid = :openid
          AND status = 'active'
    ")->execute([
        ':technician_id' => $row['id'],
        ':appid' => $cookie['appid'],
        ':openid' => $cookie['openid'],
    ]);

    fdx_issue_technician_cookie($_SESSION['technician'], $_SESSION['wechat']);
    return $_SESSION['technician'];
}

function fdx_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = (string) fdx_config('db.host', '127.0.0.1');
    $dbname = (string) fdx_config('db.dbname', 'foc');
    $username = (string) fdx_config('db.username', 'foc');
    $password = (string) fdx_config('db.password', '');
    $charset = (string) fdx_config('db.charset', 'utf8mb4');

    $dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function fdx_json(bool $success, mixed $data = null, string $message = '', int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function fdx_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function fdx_require_method(string $method): void
{
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        fdx_json(false, null, '请求方法不正确', 405);
    }
}

function fdx_current_staff(): ?array
{
    fdx_start_session();
    return $_SESSION['staff'] ?? null;
}

function fdx_current_technician(): ?array
{
    fdx_start_session();
    $technician = $_SESSION['technician'] ?? null;
    if (is_array($technician)) {
        fdx_sync_technician_cookie_from_session();
        return $technician;
    }

    return fdx_restore_technician_session_from_cookie();
}

function fdx_require_staff(array $roles = []): array
{
    $staff = fdx_current_staff();
    if (!$staff) {
        fdx_json(false, null, '请先登录', 401);
    }

    if ($roles && !in_array($staff['role'] ?? '', $roles, true)) {
        fdx_json(false, null, '没有权限执行该操作', 403);
    }

    return $staff;
}

function fdx_require_technician(): array
{
    $technician = fdx_current_technician();
    if (!$technician) {
        fdx_json(false, null, '请先完成技术员微信绑定', 401);
    }

    return $technician;
}

function fdx_mask_phone(?string $phone): string
{
    $phone = preg_replace('/\D+/', '', (string) $phone);
    if (strlen($phone) < 7) {
        return $phone;
    }

    return substr($phone, 0, 3) . '****' . substr($phone, -4);
}

function fdx_random_token(int $bytes = 16): string
{
    return strtoupper(bin2hex(random_bytes($bytes)));
}

function fdx_token_hash(string $token): string
{
    return hash('sha256', strtoupper(trim($token)));
}

function fdx_encrypt_secret(?string $plain): ?string
{
    if ($plain === null || $plain === '') {
        return null;
    }

    $appKey = (string) fdx_config('security.encryption_key', '');
    if ($appKey === '') {
        throw new RuntimeException('未配置敏感字段加密密钥');
    }

    if (!function_exists('openssl_encrypt')) {
        throw new RuntimeException('服务器缺少 openssl 扩展，无法加密敏感字段');
    }

    $key = hash('sha256', $appKey, true);
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false) {
        throw new RuntimeException('敏感字段加密失败');
    }

    return base64_encode($iv . $tag . $cipher);
}

function fdx_decrypt_secret(?string $encoded): ?string
{
    if ($encoded === null || $encoded === '') {
        return null;
    }

    $appKey = (string) fdx_config('security.encryption_key', '');
    if ($appKey === '' || !function_exists('openssl_decrypt')) {
        return null;
    }

    $raw = base64_decode($encoded, true);
    if ($raw === false || strlen($raw) <= 28) {
        return null;
    }

    $iv = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $cipher = substr($raw, 28);
    $key = hash('sha256', $appKey, true);
    $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

    return $plain === false ? null : $plain;
}

function fdx_redirect(string $url): never
{
    header('Location: ' . $url, true, 302);
    exit;
}
