<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/wechat.php';
require_once __DIR__ . '/../app/domain.php';

fdx_start_session();

$route = $_GET['route'] ?? $_POST['route'] ?? '';

try {
    match ($route) {
        'auth.me' => fdx_route_auth_me(),
        'staff.login' => fdx_route_staff_login(),
        'staff.shift_login' => fdx_route_staff_shift_login(),
        'staff.logout' => fdx_route_staff_logout(),
        'wechat.start' => fdx_route_wechat_start(),
        'wechat.callback' => fdx_route_wechat_callback(),
        'technician.bind' => fdx_route_technician_bind(),
        'technician.bind_phone' => fdx_route_technician_bind_phone(),
        'technician.orders' => fdx_route_technician_orders(),
        'activities.list' => fdx_route_activities_list(),
        'activities.create' => fdx_route_activities_create(),
        'activities.set_current' => fdx_route_activities_set_current(),
        'activities.clear_current' => fdx_route_activities_clear_current(),
        'shift_codes.list' => fdx_route_shift_codes_list(),
        'shift_codes.create' => fdx_route_shift_codes_create(),
        'shift_codes.disable' => fdx_route_shift_codes_disable(),
        'technicians.list' => fdx_route_technicians_list(),
        'technicians.create' => fdx_route_technicians_create(),
        'technicians.import_fy_users' => fdx_route_technicians_import_fy_users(),
        'technicians.bind_token' => fdx_route_technicians_bind_token(),
        'orders.customer_lookup' => fdx_route_orders_customer_lookup(),
        'orders.list' => fdx_route_orders_list(),
        'orders.detail' => fdx_route_orders_detail(),
        'orders.export' => fdx_route_orders_export(),
        'orders.next_number' => fdx_route_orders_next_number(),
        'orders.create' => fdx_route_orders_create(),
        'orders.update' => fdx_route_orders_update(),
        'orders.cancel' => fdx_route_orders_cancel(),
        'orders.assign' => fdx_route_orders_assign(),
        'orders.claim_link' => fdx_route_orders_claim_link(),
        'orders.claim' => fdx_route_orders_claim(),
        'orders.start_repair' => fdx_route_orders_start_repair(),
        'orders.submit_repair' => fdx_route_orders_submit_repair(),
        'orders.staff_complete_repair' => fdx_route_orders_staff_complete_repair(),
        'orders.sms_notice' => fdx_route_orders_sms_notice(),
        'orders.sms_confirm' => fdx_route_orders_sms_confirm(),
        'orders.pickup' => fdx_route_orders_pickup(),
        'dashboard.stats' => fdx_route_dashboard_stats(),
        'health' => fdx_json(true, ['version' => FDX_VERSION], '飞大修 API 正常'),
        default => fdx_json(false, null, '未知接口', 404),
    };
} catch (Throwable $e) {
    error_log('[feidaxiu] ' . $e->getMessage());
    $status = $e instanceof RuntimeException ? 422 : 500;
    fdx_json(false, null, $e->getMessage(), $status);
}

function fdx_route_auth_me(): never
{
    fdx_json(true, [
        'staff' => fdx_current_staff(),
        'technician' => fdx_current_technician(),
        'wechat' => $_SESSION['wechat'] ?? null,
    ]);
}

function fdx_staff_actor_id(array $staff): ?int
{
    $id = $staff['id'] ?? null;
    if (!is_numeric($id) || (int) $id <= 0) {
        return null;
    }
    return (int) $id;
}

function fdx_public_base_url(): string
{
    $callbackBase = (string) fdx_config('wechat.oauth_callback_base', '');
    if ($callbackBase !== '') {
        return rtrim(dirname(dirname($callbackBase)), '/');
    }

    $host = $_SERVER['HTTP_HOST'] ?? 'focapp.feiyang.ac.cn';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return "{$scheme}://{$host}/public/newrepair";
}

function fdx_tech_landing_url(?string $claim = null): string
{
    $params = [];
    if ($claim !== null && trim($claim) !== '') {
        $params['claim'] = $claim;
    }
    $params['wx'] = '1';

    return fdx_public_base_url() . '/index.html#/tech?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

function fdx_wechat_entry_url(?string $claim = null): string
{
    return fdx_public_base_url() . '/api/index.php?route=wechat.start&next=' . rawurlencode(fdx_tech_landing_url($claim));
}

function fdx_is_wechat_browser(): bool
{
    return stripos((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 'MicroMessenger') !== false;
}

function fdx_wechat_open_bridge_url(string $next): string
{
    $entry = fdx_public_base_url() . '/api/index.php?route=wechat.start&next=' . rawurlencode($next);
    $params = [
        'entry' => $entry,
        'next' => $next,
    ];

    return fdx_public_base_url() . '/index.html#/wechat-open?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

function fdx_route_staff_login(): never
{
    fdx_require_method('POST');
    $input = fdx_input();
    $username = trim((string) ($input['username'] ?? ''));
    $password = (string) ($input['password'] ?? '');
    if ($username === '' || $password === '') {
        fdx_json(false, null, '请输入账号和密码', 422);
    }

    $stmt = fdx_db()->prepare("
        SELECT id, username, display_name, password_hash, role, status
        FROM fdx_staff_users
        WHERE username = :username
        LIMIT 1
    ");
    $stmt->execute([':username' => $username]);
    $staff = $stmt->fetch();

    if (!$staff || $staff['status'] !== 'active' || !password_verify($password, $staff['password_hash'])) {
        fdx_json(false, null, '账号或密码错误', 401);
    }

    $_SESSION['staff'] = [
        'id' => (int) $staff['id'],
        'username' => $staff['username'],
        'display_name' => $staff['display_name'],
        'role' => $staff['role'],
    ];

    fdx_db()->prepare("UPDATE fdx_staff_users SET last_login_at = NOW() WHERE id = :id")
        ->execute([':id' => $staff['id']]);

    fdx_json(true, $_SESSION['staff'], '登录成功');
}

function fdx_route_staff_shift_login(): never
{
    fdx_require_method('POST');
    $input = fdx_input();
    $code = trim((string) ($input['code'] ?? ''));

    if ($code === '') {
        fdx_json(false, null, '请输入值班码', 422);
    }

    $pdo = fdx_db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            SELECT sc.*, a.name AS activity_name
            FROM fdx_shift_codes sc
            LEFT JOIN fdx_activities a ON a.id = sc.activity_id
            WHERE sc.code_hash = :code_hash
              AND sc.status = 'active'
              AND (sc.expires_at IS NULL OR sc.expires_at > NOW())
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([':code_hash' => fdx_token_hash($code)]);
        $shiftCode = $stmt->fetch();

        if (!$shiftCode) {
            $pdo->rollBack();
            fdx_json(false, null, '值班码无效或已过期', 401);
        }

        $pdo->prepare("
            UPDATE fdx_shift_codes
            SET use_count = use_count + 1, last_used_at = NOW()
            WHERE id = :id
        ")->execute([':id' => $shiftCode['id']]);

        $pdo->commit();

        $_SESSION['staff'] = [
            'id' => null,
            'username' => 'shift:' . $shiftCode['id'],
            'display_name' => $shiftCode['label'],
            'role' => 'duty',
            'role_label' => '现场值班',
            'auth_type' => 'shift_code',
            'shift_code_id' => (int) $shiftCode['id'],
            'activity_id' => $shiftCode['activity_id'] ? (int) $shiftCode['activity_id'] : null,
            'activity_name' => $shiftCode['activity_name'] ?? null,
        ];

        fdx_json(true, $_SESSION['staff'], '值班登录成功');
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function fdx_route_staff_logout(): never
{
    fdx_require_method('POST');
    unset($_SESSION['staff']);
    fdx_json(true, null, '已退出');
}

function fdx_route_wechat_start(): never
{
    fdx_require_method('GET');

    $next = $_GET['next'] ?? fdx_tech_landing_url();
    $callbackBase = (string) fdx_config('wechat.oauth_callback_base', '');
    $canonicalHost = parse_url($callbackBase, PHP_URL_HOST);
    $currentHost = $_SERVER['HTTP_HOST'] ?? '';
    if ($callbackBase !== '' && $canonicalHost && strcasecmp((string) $currentHost, (string) $canonicalHost) !== 0) {
        fdx_redirect($callbackBase . '?route=wechat.start&next=' . rawurlencode((string) $next));
    }

    if (($_GET['force_oauth'] ?? '') !== '1' && !fdx_is_wechat_browser()) {
        fdx_redirect(fdx_wechat_open_bridge_url((string) $next));
    }

    $state = fdx_random_token(12);
    $_SESSION['wechat_oauth_state'] = $state;
    $_SESSION['wechat_oauth_next'] = $next;

    fdx_redirect(fdx_wechat_oauth_url($state));
}

function fdx_route_wechat_callback(): never
{
    fdx_require_method('GET');

    $state = $_GET['state'] ?? '';
    $code = $_GET['code'] ?? '';
    if (!$state || !$code || !hash_equals((string) ($_SESSION['wechat_oauth_state'] ?? ''), (string) $state)) {
        fdx_redirect(fdx_public_base_url() . '/index.html#/tech?error=oauth_state');
    }

    $oauth = fdx_wechat_fetch_openid((string) $code);
    $appid = (string) fdx_config('wechat.appid', '');
    $openid = (string) $oauth['openid'];

    $_SESSION['wechat'] = [
        'appid' => $appid,
        'openid' => $openid,
        'unionid' => $oauth['unionid'] ?? null,
    ];

    $technician = fdx_find_bound_technician($appid, $openid);
    if ($technician) {
        $_SESSION['technician'] = [
            'id' => (int) $technician['id'],
            'display_name' => $technician['display_name'],
            'phone' => $technician['phone'],
            'campus' => $technician['campus'],
        ];
        fdx_issue_technician_cookie($_SESSION['technician'], $_SESSION['wechat']);

        fdx_db()->prepare("
            UPDATE fdx_wechat_bindings
            SET last_seen_at = NOW()
            WHERE appid = :appid AND openid = :openid AND status = 'active'
        ")->execute([
            ':appid' => $appid,
            ':openid' => $openid,
        ]);
    }

    $next = $_SESSION['wechat_oauth_next'] ?? fdx_tech_landing_url();
    unset($_SESSION['wechat_oauth_state'], $_SESSION['wechat_oauth_next']);
    fdx_redirect((string) $next);
}

function fdx_route_technician_bind(): never
{
    fdx_require_method('POST');

    $wechat = $_SESSION['wechat'] ?? null;
    if (!$wechat || empty($wechat['openid'])) {
        fdx_json(false, null, '请先从微信服务号进入飞大修', 401);
    }

    $input = fdx_input();
    $token = (string) ($input['token'] ?? '');
    if (trim($token) === '') {
        fdx_json(false, null, '请输入绑定码', 422);
    }

    $pdo = fdx_db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            SELECT bt.id, bt.technician_id, t.display_name, t.phone, t.campus
            FROM fdx_bind_tokens bt
            INNER JOIN fdx_technicians t ON t.id = bt.technician_id
            WHERE bt.token_hash = :token_hash
              AND bt.status = 'unused'
              AND bt.expires_at > NOW()
              AND t.status = 'active'
            FOR UPDATE
        ");
        $stmt->execute([':token_hash' => fdx_token_hash($token)]);
        $binding = $stmt->fetch();

        if (!$binding) {
            $pdo->rollBack();
            fdx_json(false, null, '绑定码无效或已过期', 422);
        }

        $appid = (string) $wechat['appid'];
        $openid = (string) $wechat['openid'];

        $pdo->prepare("
            INSERT INTO fdx_wechat_bindings (
                technician_id, appid, openid, unionid,
                bind_method, status, bound_at, last_seen_at
            )
            VALUES (
                :technician_id, :appid, :openid, :unionid,
                'bind_token', 'active', NOW(), NOW()
            )
            ON DUPLICATE KEY UPDATE
                technician_id = VALUES(technician_id),
                unionid = VALUES(unionid),
                bind_method = 'bind_token',
                status = 'active',
                revoked_at = NULL,
                last_seen_at = NOW()
        ")->execute([
            ':technician_id' => $binding['technician_id'],
            ':appid' => $appid,
            ':openid' => $openid,
            ':unionid' => $wechat['unionid'] ?? null,
        ]);

        $pdo->prepare("
            UPDATE fdx_bind_tokens
            SET status = 'used', used_at = NOW(), used_openid = :openid
            WHERE id = :id
        ")->execute([
            ':id' => $binding['id'],
            ':openid' => $openid,
        ]);

        $pdo->commit();

        $_SESSION['technician'] = [
            'id' => (int) $binding['technician_id'],
            'display_name' => $binding['display_name'],
            'phone' => $binding['phone'],
            'campus' => $binding['campus'],
        ];
        fdx_issue_technician_cookie($_SESSION['technician'], $_SESSION['wechat']);

        fdx_json(true, $_SESSION['technician'], '绑定成功');
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function fdx_route_technician_bind_phone(): never
{
    fdx_require_method('POST');

    $wechat = $_SESSION['wechat'] ?? null;
    if (!$wechat || empty($wechat['openid'])) {
        fdx_json(false, null, '请先从微信服务号进入飞大修', 401);
    }

    $input = fdx_input();
    $phone = preg_replace('/\D+/', '', (string) ($input['phone'] ?? ''));
    $name = trim((string) ($input['display_name'] ?? ''));
    if (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
        fdx_json(false, null, '请输入正确的手机号', 422);
    }

    $pdo = fdx_db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            SELECT id, display_name, phone, campus
            FROM fdx_technicians
            WHERE phone = :phone AND status = 'active'
            ORDER BY id
            FOR UPDATE
        ");
        $stmt->execute([':phone' => $phone]);
        $matches = $stmt->fetchAll();

        if (!$matches) {
            $pdo->rollBack();
            fdx_json(false, null, '未找到该手机号对应的技术员，请先在后台从 fy_users 导入', 404);
        }

        if (count($matches) > 1 && $name !== '') {
            $matches = array_values(array_filter($matches, fn ($row) => $row['display_name'] === $name));
        }

        if (count($matches) !== 1) {
            $pdo->rollBack();
            fdx_json(false, null, '该手机号匹配到多名技术员，请补充姓名或联系管理员处理', 409);
        }

        $technician = $matches[0];
        $appid = (string) $wechat['appid'];
        $openid = (string) $wechat['openid'];

        $existingForOpenid = $pdo->prepare("
            SELECT technician_id
            FROM fdx_wechat_bindings
            WHERE appid = :appid AND openid = :openid AND status = 'active'
            LIMIT 1
        ");
        $existingForOpenid->execute([
            ':appid' => $appid,
            ':openid' => $openid,
        ]);
        $boundTechnicianId = $existingForOpenid->fetchColumn();
        if ($boundTechnicianId && (int) $boundTechnicianId !== (int) $technician['id']) {
            $pdo->rollBack();
            fdx_json(false, null, '当前微信已绑定其他技术员，请联系管理员解绑', 409);
        }

        $existingForTechnician = $pdo->prepare("
            SELECT openid
            FROM fdx_wechat_bindings
            WHERE appid = :appid AND technician_id = :technician_id AND status = 'active'
            LIMIT 1
        ");
        $existingForTechnician->execute([
            ':appid' => $appid,
            ':technician_id' => $technician['id'],
        ]);
        $boundOpenid = $existingForTechnician->fetchColumn();
        if ($boundOpenid && $boundOpenid !== $openid) {
            $pdo->rollBack();
            fdx_json(false, null, '该技术员已绑定其他微信，请联系管理员解绑', 409);
        }

        $pdo->prepare("
            INSERT INTO fdx_wechat_bindings (
                technician_id, appid, openid, unionid,
                bind_method, verified_phone, status, bound_at, last_seen_at
            ) VALUES (
                :technician_id, :appid, :openid, :unionid,
                'phone', :verified_phone, 'active', NOW(), NOW()
            )
            ON DUPLICATE KEY UPDATE
                technician_id = VALUES(technician_id),
                unionid = VALUES(unionid),
                bind_method = 'phone',
                verified_phone = VALUES(verified_phone),
                status = 'active',
                revoked_at = NULL,
                last_seen_at = NOW()
        ")->execute([
            ':technician_id' => $technician['id'],
            ':appid' => $appid,
            ':openid' => $openid,
            ':unionid' => $wechat['unionid'] ?? null,
            ':verified_phone' => $phone,
        ]);

        $pdo->commit();

        $_SESSION['technician'] = [
            'id' => (int) $technician['id'],
            'display_name' => $technician['display_name'],
            'phone' => $technician['phone'],
            'campus' => $technician['campus'],
        ];
        fdx_issue_technician_cookie($_SESSION['technician'], $_SESSION['wechat']);

        fdx_json(true, $_SESSION['technician'], '手机号验证通过，绑定成功');
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function fdx_route_technician_orders(): never
{
    fdx_require_method('GET');
    $technician = fdx_require_technician();
    $status = $_GET['status'] ?? '';

    $where = "a.technician_id = :technician_id";
    $params = [':technician_id' => $technician['id']];
    if ($status !== '') {
        $where .= " AND o.status = :status";
        $params[':status'] = $status;
    } else {
        $where .= " AND (a.status = 'active' OR o.status IN ('ready_for_pickup', 'notified', 'completed'))";
    }

    $stmt = fdx_db()->prepare("
        SELECT
            o.id,
            o.order_no,
            o.station_no,
            o.status,
            o.customer_name,
            o.customer_phone,
            o.customer_phone_mask,
            o.backup_phone,
            o.customer_qq,
            o.gender,
            o.college,
            o.dormitory,
            o.student_id,
            o.device_type,
            o.device_model,
            o.boot_password_cipher,
            o.accessories,
            o.existing_damage,
            o.confirmed_out_of_warranty,
            o.service_types,
            o.allow_reinstall,
            o.allow_format,
            o.important_data,
            o.problem_description,
            o.repair_notes,
            o.notes,
            o.created_at,
            o.updated_at,
            act.name AS activity_name,
            act.activity_date,
            act.location AS activity_location
        FROM fdx_order_assignments a
        INNER JOIN fdx_orders o ON o.id = a.order_id
        INNER JOIN fdx_activities act ON act.id = o.activity_id
        WHERE {$where}
        ORDER BY FIELD(o.status, 'repairing','assigned','ready_for_pickup','notified','waiting_assignment','completed','cancelled'),
                 o.updated_at DESC
        LIMIT 100
    ");
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
    foreach ($orders as &$order) {
        $order['boot_password'] = fdx_decrypt_secret($order['boot_password_cipher'] ?? null);
        unset($order['boot_password_cipher']);
    }
    unset($order);

    fdx_json(true, ['orders' => $orders]);
}

function fdx_route_activities_list(): never
{
    fdx_require_staff(['admin', 'duty', 'intake', 'dispatcher', 'service']);
    $stmt = fdx_db()->query("
        SELECT a.*,
               COUNT(o.id) AS order_count,
               SUM(o.status = 'completed') AS completed_count
        FROM fdx_activities a
        LEFT JOIN fdx_orders o ON o.activity_id = a.id
        GROUP BY a.id
        ORDER BY a.activity_date DESC, a.id DESC
    ");
    fdx_json(true, ['activities' => $stmt->fetchAll()]);
}

function fdx_route_activities_create(): never
{
    fdx_require_method('POST');
    fdx_require_staff(['admin']);
    $input = fdx_input();
    $name = trim((string) ($input['name'] ?? ''));
    $activityDate = trim((string) ($input['activity_date'] ?? ''));
    if ($name === '' || $activityDate === '') {
        fdx_json(false, null, '活动名称和日期不能为空', 422);
    }

    $stmt = fdx_db()->prepare("
        INSERT INTO fdx_activities (name, location, activity_date, status, notes)
        VALUES (:name, :location, :activity_date, :status, :notes)
    ");
    $stmt->execute([
        ':name' => $name,
        ':location' => $input['location'] ?? null,
        ':activity_date' => $activityDate,
        ':status' => $input['status'] ?? 'planning',
        ':notes' => $input['notes'] ?? null,
    ]);
    fdx_json(true, ['id' => (int) fdx_db()->lastInsertId()], '活动已创建');
}

function fdx_route_activities_set_current(): never
{
    fdx_require_method('POST');
    fdx_require_staff(['admin']);
    $input = fdx_input();
    $activityId = (int) ($input['activity_id'] ?? 0);
    if ($activityId <= 0) {
        fdx_json(false, null, '活动 ID 不正确', 422);
    }

    $pdo = fdx_db();
    $pdo->beginTransaction();
    try {
        $pdo->exec("UPDATE fdx_activities SET is_current = 0");
        $stmt = $pdo->prepare("UPDATE fdx_activities SET is_current = 1, status = 'active' WHERE id = :id");
        $stmt->execute([':id' => $activityId]);
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('活动不存在');
        }
        $pdo->commit();
        fdx_json(true, null, '当前活动已设置');
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function fdx_route_activities_clear_current(): never
{
    fdx_require_method('POST');
    fdx_require_staff(['admin']);

    fdx_db()->exec("UPDATE fdx_activities SET is_current = 0 WHERE is_current = 1");
    fdx_json(true, null, '已取消当前大修');
}

function fdx_route_shift_codes_list(): never
{
    fdx_require_staff(['admin']);
    $stmt = fdx_db()->query("
        SELECT sc.id, sc.label, sc.role, sc.code_plain, sc.status, sc.expires_at,
               sc.use_count, sc.last_used_at, sc.created_at,
               a.name AS activity_name
        FROM fdx_shift_codes sc
        LEFT JOIN fdx_activities a ON a.id = sc.activity_id
        ORDER BY sc.status, sc.created_at DESC, sc.id DESC
        LIMIT 200
    ");
    fdx_json(true, ['shift_codes' => $stmt->fetchAll()]);
}

function fdx_route_shift_codes_create(): never
{
    fdx_require_method('POST');
    $staff = fdx_require_staff(['admin']);
    $input = fdx_input();
    $label = trim((string) ($input['label'] ?? ''));
    $code = trim((string) ($input['code'] ?? ''));
    $expiresAt = trim((string) ($input['expires_at'] ?? ''));

    if ($code === '') {
        fdx_json(false, null, '值班码不能为空', 422);
    }
    if (strlen($code) < 4) {
        fdx_json(false, null, '值班码至少 4 位', 422);
    }

    $pdo = fdx_db();
    $activity = fdx_active_activity($pdo);
    if (!$activity) {
        fdx_json(false, null, '请先设置当前活动', 422);
    }

    if ($label === '') {
        $label = $activity['name'] . '值班码';
    }

    $expiresAt = $expiresAt !== '' ? str_replace('T', ' ', $expiresAt) : null;
    $pdo->beginTransaction();
    try {
        $pdo->prepare("
            UPDATE fdx_shift_codes
            SET status = 'disabled'
            WHERE activity_id = :activity_id AND status = 'active'
        ")->execute([':activity_id' => $activity['id']]);

        $stmt = $pdo->prepare("
            INSERT INTO fdx_shift_codes (label, role, code_hash, code_plain, activity_id, expires_at, status, created_by)
            VALUES (:label, 'viewer', :code_hash, :code_plain, :activity_id, :expires_at, 'active', :created_by)
            ON DUPLICATE KEY UPDATE
                label = VALUES(label),
                role = 'viewer',
                code_plain = VALUES(code_plain),
                activity_id = VALUES(activity_id),
                expires_at = VALUES(expires_at),
                status = 'active',
                created_by = VALUES(created_by),
                updated_at = NOW()
        ");
        $stmt->execute([
            ':label' => $label,
            ':code_hash' => fdx_token_hash($code),
            ':code_plain' => $code,
            ':activity_id' => $activity['id'],
            ':expires_at' => $expiresAt,
            ':created_by' => fdx_staff_actor_id($staff),
        ]);
        $id = (int) $pdo->lastInsertId();
        if ($id === 0) {
            $stmt = $pdo->prepare("SELECT id FROM fdx_shift_codes WHERE code_hash = :code_hash LIMIT 1");
            $stmt->execute([':code_hash' => fdx_token_hash($code)]);
            $id = (int) $stmt->fetchColumn();
        }
        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        if ($e->getCode() === '23000') {
            fdx_json(false, null, '这个值班码已存在，请换一个', 409);
        }
        throw $e;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    fdx_json(true, ['id' => $id], '值班码已保存');
}

function fdx_route_shift_codes_disable(): never
{
    fdx_require_method('POST');
    fdx_require_staff(['admin']);
    $input = fdx_input();
    $id = (int) ($input['id'] ?? 0);
    if ($id <= 0) {
        fdx_json(false, null, '值班码 ID 不正确', 422);
    }

    $stmt = fdx_db()->prepare("
        UPDATE fdx_shift_codes
        SET status = 'disabled'
        WHERE id = :id
    ");
    $stmt->execute([':id' => $id]);
    fdx_json(true, null, '值班码已禁用');
}

function fdx_route_technicians_list(): never
{
    fdx_require_staff(['admin', 'duty', 'dispatcher', 'viewer']);
    $stmt = fdx_db()->query("
        SELECT t.*,
               COUNT(a.id) AS active_orders,
               MAX(b.bound_at) AS bound_at
        FROM fdx_technicians t
        LEFT JOIN fdx_order_assignments a ON a.technician_id = t.id AND a.status = 'active'
        LEFT JOIN fdx_wechat_bindings b ON b.technician_id = t.id AND b.status = 'active'
        GROUP BY t.id
        ORDER BY t.status, t.display_name
    ");
    fdx_json(true, ['technicians' => $stmt->fetchAll()]);
}

function fdx_route_technicians_create(): never
{
    fdx_require_method('POST');
    $staff = fdx_require_staff(['admin']);
    $input = fdx_input();
    $name = trim((string) ($input['display_name'] ?? ''));
    if ($name === '') {
        fdx_json(false, null, '技术员姓名不能为空', 422);
    }

    $phone = fdx_text_value($input, 'phone');
    if ($phone !== null) {
        $phone = preg_replace('/\D+/', '', $phone);
        if (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
            fdx_json(false, null, '请输入正确的手机号', 422);
        }
    }
    $campus = fdx_text_value($input, 'campus');
    $skillTags = isset($input['skill_tags']) ? json_encode($input['skill_tags'], JSON_UNESCAPED_UNICODE) : null;

    $pdo = fdx_db();
    $pdo->beginTransaction();
    try {
        $existingId = 0;
        if ($phone !== null) {
            $stmt = $pdo->prepare("SELECT id FROM fdx_technicians WHERE phone = :phone ORDER BY status = 'active' DESC, id DESC LIMIT 1");
            $stmt->execute([':phone' => $phone]);
            $existingId = (int) $stmt->fetchColumn();
        }

        if ($existingId > 0) {
            $stmt = $pdo->prepare("
                UPDATE fdx_technicians
                SET display_name = :display_name,
                    campus = :campus,
                    skill_tags = :skill_tags,
                    status = 'active',
                    created_by = COALESCE(created_by, :created_by)
                WHERE id = :id
            ");
            $stmt->execute([
                ':display_name' => $name,
                ':campus' => $campus,
                ':skill_tags' => $skillTags,
                ':created_by' => fdx_staff_actor_id($staff),
                ':id' => $existingId,
            ]);
            $pdo->commit();
            fdx_json(true, ['id' => $existingId], '技术员已更新');
        }

        $stmt = $pdo->prepare("
            INSERT INTO fdx_technicians (display_name, phone, campus, skill_tags, status, created_by)
            VALUES (:display_name, :phone, :campus, :skill_tags, 'active', :created_by)
        ");
        $stmt->execute([
            ':display_name' => $name,
            ':phone' => $phone,
            ':campus' => $campus,
            ':skill_tags' => $skillTags,
            ':created_by' => fdx_staff_actor_id($staff),
        ]);
        $id = (int) $pdo->lastInsertId();
        $pdo->commit();
        fdx_json(true, ['id' => $id], '技术员已创建');
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function fdx_route_technicians_import_fy_users(): never
{
    fdx_require_method('POST');
    $staff = fdx_require_staff(['admin']);

    $pdo = fdx_db();
    $users = $pdo->query("
        SELECT id, nickname, realname, phone, campus
        FROM fy_users
        WHERE role = 'technician'
          AND phone IS NOT NULL
          AND phone <> ''
        ORDER BY id
    ")->fetchAll();

    $imported = 0;
    $skipped = 0;
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO fdx_technicians (
                source_table, source_user_id, display_name, phone, campus, status, created_by
            ) VALUES (
                'fy_users', :source_user_id, :display_name, :phone, :campus, 'active', :created_by
            )
            ON DUPLICATE KEY UPDATE
                display_name = VALUES(display_name),
                phone = VALUES(phone),
                campus = VALUES(campus),
                status = 'active',
                updated_at = NOW()
        ");

        foreach ($users as $user) {
            $phone = preg_replace('/\D+/', '', (string) ($user['phone'] ?? ''));
            $name = trim((string) ($user['realname'] ?? ''));
            if ($name === '') {
                $name = trim((string) ($user['nickname'] ?? ''));
            }
            if (!preg_match('/^1[3-9]\d{9}$/', $phone) || $name === '') {
                $skipped++;
                continue;
            }

            $stmt->execute([
                ':source_user_id' => $user['id'],
                ':display_name' => $name,
                ':phone' => $phone,
                ':campus' => $user['campus'] ?? null,
                ':created_by' => fdx_staff_actor_id($staff),
            ]);
            $imported++;
        }

        $pdo->commit();
        fdx_json(true, [
            'imported_count' => $imported,
            'skipped_count' => $skipped,
        ], '技术员已从 fy_users 只读导入');
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function fdx_route_technicians_bind_token(): never
{
    fdx_require_method('POST');
    $staff = fdx_require_staff(['admin']);
    $input = fdx_input();
    $technicianId = (int) ($input['technician_id'] ?? 0);
    if ($technicianId <= 0) {
        fdx_json(false, null, '技术员 ID 不正确', 422);
    }

    $token = (string) random_int(100000, 999999);
    $ttl = (int) fdx_config('security.bind_token_ttl_minutes', 30);
    $expiresAt = (new DateTimeImmutable('now'))->modify("+{$ttl} minutes")->format('Y-m-d H:i:s');
    $stmt = fdx_db()->prepare("
        INSERT INTO fdx_bind_tokens (technician_id, token_hash, status, created_by, expires_at)
        SELECT id, :token_hash, 'unused', :created_by, :expires_at
        FROM fdx_technicians
        WHERE id = :technician_id AND status = 'active'
    ");
    $stmt->execute([
        ':token_hash' => fdx_token_hash($token),
        ':created_by' => fdx_staff_actor_id($staff),
        ':expires_at' => $expiresAt,
        ':technician_id' => $technicianId,
    ]);

    if ($stmt->rowCount() === 0) {
        fdx_json(false, null, '技术员不存在或已停用', 404);
    }

    fdx_json(true, [
        'token' => $token,
        'expires_in_minutes' => $ttl,
    ], '绑定码已生成，请只展示给本人');
}

function fdx_route_orders_list(): never
{
    $staff = fdx_require_staff(['admin', 'duty', 'intake', 'dispatcher', 'service', 'viewer']);
    $status = trim((string) ($_GET['status'] ?? ''));
    $activityId = (int) ($_GET['activity_id'] ?? 0);
    $query = trim((string) ($_GET['q'] ?? ''));
    $currentOnly = ($_GET['current'] ?? '1') !== '0';
    $limit = min(500, max(1, (int) ($_GET['limit'] ?? 100)));

    $where = [];
    $params = [];

    if ($activityId > 0) {
        $where[] = 'o.activity_id = :activity_id';
        $params[':activity_id'] = $activityId;
        $currentOnly = false;
    }

    if ($status !== '') {
        $where[] = 'o.status = :status';
        $params[':status'] = $status;
    }

    if ($query !== '') {
        $where[] = "(
            o.order_no LIKE :q
            OR o.customer_name LIKE :q
            OR o.customer_phone LIKE :q
            OR o.customer_phone_mask LIKE :q
            OR o.backup_phone LIKE :q
            OR o.device_model LIKE :q
            OR o.problem_description LIKE :q
            OR tech.display_name LIKE :q
        )";
        $params[':q'] = '%' . $query . '%';
    }

    if ($currentOnly) {
        $where[] = 'act.is_current = 1';
    }

    $sql = fdx_order_select_sql();
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY o.created_at DESC LIMIT ' . $limit;

    $stmt = fdx_db()->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
    foreach ($orders as &$order) {
        unset($order['boot_password_cipher']);
    }
    unset($order);
    fdx_json(true, ['orders' => $orders]);
}

function fdx_route_orders_customer_lookup(): never
{
    fdx_require_method('POST');
    $input = fdx_input();
    $phone = preg_replace('/\D+/', '', (string) ($input['phone'] ?? ''));
    if (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
        fdx_json(false, null, '请输入正确的手机号', 422);
    }

    $stmt = fdx_db()->prepare("
        SELECT
            o.id,
            o.order_no,
            o.status,
            o.customer_phone_mask,
            o.device_type,
            o.device_model,
            o.problem_description,
            o.created_at,
            o.updated_at,
            o.completed_at,
            act.name AS activity_name,
            act.activity_date,
            act.location AS activity_location,
            tech.display_name AS technician_name
        FROM fdx_orders o
        INNER JOIN fdx_activities act ON act.id = o.activity_id
        LEFT JOIN fdx_order_assignments ta ON ta.order_id = o.id AND ta.status = 'active'
        LEFT JOIN fdx_technicians tech ON tech.id = ta.technician_id
        WHERE o.customer_phone = :phone
        ORDER BY o.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([':phone' => $phone]);
    $orders = $stmt->fetchAll();

    fdx_json(true, [
        'phone_mask' => fdx_mask_phone($phone),
        'orders' => $orders,
    ], $orders ? '查询成功' : '未查询到该手机号的订单');
}

function fdx_route_orders_detail(): never
{
    fdx_require_method('GET');
    fdx_require_staff(['admin', 'duty', 'intake', 'dispatcher', 'service']);

    $orderId = (int) ($_GET['id'] ?? $_GET['order_id'] ?? 0);
    if ($orderId <= 0) {
        fdx_json(false, null, '订单 ID 不正确', 422);
    }

    try {
        fdx_json(true, fdx_order_detail_payload($orderId));
    } catch (RuntimeException $e) {
        fdx_json(false, null, $e->getMessage(), 404);
    }
}

function fdx_order_detail_payload(int $orderId): array
{
    $sql = fdx_order_select_sql() . ' WHERE o.id = :id LIMIT 1';
    $stmt = fdx_db()->prepare($sql);
    $stmt->execute([':id' => $orderId]);
    $order = $stmt->fetch();
    if (!$order) {
        throw new RuntimeException('订单不存在');
    }

    $order['boot_password'] = fdx_decrypt_secret($order['boot_password_cipher'] ?? null);
    unset($order['boot_password_cipher']);

    $assignments = fdx_db()->prepare("
        SELECT a.*, t.display_name AS technician_name, t.phone AS technician_phone
        FROM fdx_order_assignments a
        INNER JOIN fdx_technicians t ON t.id = a.technician_id
        WHERE a.order_id = :order_id
        ORDER BY a.assigned_at DESC, a.id DESC
    ");
    $assignments->execute([':order_id' => $orderId]);

    $repairs = fdx_db()->prepare("
        SELECT r.*, t.display_name AS technician_name, t.phone AS technician_phone
        FROM fdx_repair_records r
        INNER JOIN fdx_technicians t ON t.id = r.technician_id
        WHERE r.order_id = :order_id
        ORDER BY r.submitted_at DESC, r.id DESC
    ");
    $repairs->execute([':order_id' => $orderId]);

    $pickups = fdx_db()->prepare("
        SELECT p.*, s.display_name AS handler_name
        FROM fdx_pickup_records p
        LEFT JOIN fdx_staff_users s ON s.id = p.handled_by
        WHERE p.order_id = :order_id
        ORDER BY p.pickup_time DESC, p.id DESC
    ");
    $pickups->execute([':order_id' => $orderId]);

    $events = fdx_db()->prepare("
        SELECT *
        FROM fdx_order_events
        WHERE order_id = :order_id
        ORDER BY created_at DESC, id DESC
        LIMIT 200
    ");
    $events->execute([':order_id' => $orderId]);

    $smsLogs = fdx_db()->prepare("
        SELECT *
        FROM fdx_sms_logs
        WHERE order_id = :order_id
        ORDER BY created_at DESC, id DESC
        LIMIT 100
    ");
    $smsLogs->execute([':order_id' => $orderId]);

    return [
        'order' => $order,
        'assignments' => $assignments->fetchAll(),
        'repair_records' => $repairs->fetchAll(),
        'pickup_records' => $pickups->fetchAll(),
        'events' => $events->fetchAll(),
        'sms_logs' => $smsLogs->fetchAll(),
    ];
}

function fdx_route_orders_export(): never
{
    fdx_require_method('GET');
    fdx_require_staff(['admin']);

    $orderId = (int) ($_GET['order_id'] ?? $_GET['id'] ?? 0);
    $orderIds = $orderId > 0 ? [$orderId] : fdx_export_order_ids_from_query();
    if (!$orderIds) {
        fdx_json(false, null, '没有可导出的工单', 404);
    }

    $payloads = [];
    foreach ($orderIds as $id) {
        $payloads[] = fdx_order_detail_payload((int) $id);
    }

    if (count($payloads) === 1) {
        $filename = fdx_order_export_filename($payloads[0]['order'], 'html');
        fdx_send_html_download($filename, fdx_render_orders_export([$payloads[0]]));
    }

    fdx_send_zip_download('飞大修工单批量导出-' . date('Ymd-His') . '.zip', fdx_export_zip_entries($payloads));
}

function fdx_export_order_ids_from_query(): array
{
    $status = trim((string) ($_GET['status'] ?? ''));
    $activityId = (int) ($_GET['activity_id'] ?? 0);
    $query = trim((string) ($_GET['q'] ?? ''));
    $currentOnly = ($_GET['current'] ?? '1') !== '0';
    $limit = min(500, max(1, (int) ($_GET['limit'] ?? 500)));

    $where = [];
    $params = [];

    if ($activityId > 0) {
        $where[] = 'o.activity_id = :activity_id';
        $params[':activity_id'] = $activityId;
        $currentOnly = false;
    }
    if ($status !== '') {
        $where[] = 'o.status = :status';
        $params[':status'] = $status;
    }
    if ($query !== '') {
        $where[] = "(
            o.order_no LIKE :q
            OR o.customer_name LIKE :q
            OR o.customer_phone LIKE :q
            OR o.customer_phone_mask LIKE :q
            OR o.backup_phone LIKE :q
            OR o.device_model LIKE :q
            OR o.problem_description LIKE :q
            OR tech.display_name LIKE :q
        )";
        $params[':q'] = '%' . $query . '%';
    }
    if ($currentOnly) {
        $where[] = 'act.is_current = 1';
    }

    $sql = fdx_order_select_sql();
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY o.created_at ASC LIMIT ' . $limit;

    $stmt = fdx_db()->prepare($sql);
    $stmt->execute($params);
    return array_map(static fn (array $row): int => (int) $row['id'], $stmt->fetchAll());
}

function fdx_send_html_download(string $filename, string $html): never
{
    $fallback = 'feidaxiu-export.html';
    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $fallback . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
    header('Content-Length: ' . strlen($html));
    echo $html;
    exit;
}

function fdx_send_zip_download(string $filename, array $entries): never
{
    if (!class_exists('ZipArchive')) {
        fdx_json(false, null, '服务器未启用 ZIP 导出组件', 500);
    }
    $tmp = tempnam(sys_get_temp_dir(), 'fdx-export-');
    if ($tmp === false) {
        fdx_json(false, null, '无法创建导出文件', 500);
    }
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
        @unlink($tmp);
        fdx_json(false, null, '无法生成导出压缩包', 500);
    }
    foreach ($entries as $entry) {
        $zip->addFromString($entry['filename'], $entry['content']);
    }
    $zip->close();

    $fallback = 'feidaxiu-export.zip';
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $fallback . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
    header('Content-Length: ' . filesize($tmp));
    readfile($tmp);
    @unlink($tmp);
    exit;
}

function fdx_export_zip_entries(array $payloads): array
{
    $entries = [];
    $used = [];
    foreach ($payloads as $payload) {
        $filename = fdx_order_export_filename($payload['order'], 'html');
        $base = preg_replace('/\.html$/u', '', $filename);
        $counter = 2;
        while (isset($used[$filename])) {
            $filename = "{$base}-{$counter}.html";
            $counter++;
        }
        $used[$filename] = true;
        $entries[] = [
            'filename' => $filename,
            'content' => fdx_render_orders_export([$payload]),
        ];
    }
    return $entries;
}

function fdx_order_export_filename(array $order, string $extension): string
{
    $activityName = fdx_filename_part($order['activity_name'] ?? '未命名大修');
    $orderNo = fdx_filename_part($order['order_no'] ?? '未编号');
    $status = fdx_filename_part(fdx_status_label((string) ($order['status'] ?? '')));
    return "{$activityName}-{$orderNo}-{$status}.{$extension}";
}

function fdx_filename_part(mixed $value): string
{
    $text = trim((string) ($value ?? ''));
    if ($text === '') {
        $text = '未填';
    }
    $text = preg_replace('/[\/\\\\:*?"<>|\r\n\t]+/u', '_', $text) ?? '未填';
    $text = trim($text, " ._-");
    if ($text === '') {
        $text = '未填';
    }
    return function_exists('mb_substr') ? mb_substr($text, 0, 80, 'UTF-8') : substr($text, 0, 80);
}

function fdx_h(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function fdx_export_multiline(mixed $value): string
{
    $text = trim((string) ($value ?? ''));
    return $text === '' ? '未填' : nl2br(fdx_h($text));
}

function fdx_export_bool(mixed $value): string
{
    if ($value === null || $value === '') {
        return '未指定';
    }
    return (int) $value === 1 ? '是' : '否';
}

function fdx_status_label(string $status): string
{
    return [
        'waiting_assignment' => '待技术员接机',
        'assigned' => '已分配',
        'repairing' => '维修中',
        'ready_for_pickup' => '待通知',
        'notified' => '待取机',
        'completed' => '已完成',
        'cancelled' => '已取消',
    ][$status] ?? '未知';
}

function fdx_assignment_status_label(string $status): string
{
    return [
        'active' => '当前分配',
        'transferred' => '已转单',
        'closed' => '已关闭',
        'cancelled' => '已取消',
    ][$status] ?? '未知';
}

function fdx_repair_result_label(string $result): string
{
    return [
        'fixed' => '已修复',
        'partially_fixed' => '部分修复',
        'unfixed' => '未修复',
        'needs_followup' => '需后续处理',
    ][$result] ?? '未记录';
}

function fdx_sms_status_label(string $status): string
{
    return [
        'manual_sent' => '已手动发送',
        'sent' => '已发送',
        'pending' => '待发送',
        'failed' => '发送失败',
        'generated' => '已生成',
    ][$status] ?? '未记录';
}

function fdx_export_service_types(mixed $value): string
{
    if (is_array($value)) {
        return $value ? implode('、', array_map('strval', $value)) : '未指定';
    }
    $text = trim((string) ($value ?? ''));
    if ($text === '') {
        return '未指定';
    }
    $decoded = json_decode($text, true);
    if (is_array($decoded)) {
        return $decoded ? implode('、', array_map('strval', $decoded)) : '未指定';
    }
    return $text;
}

function fdx_export_signature_html(?string $signature, string $alt): string
{
    $signature = trim((string) $signature);
    if ($signature === '') {
        return '<div class="empty-signature">未签</div>';
    }
    if (!preg_match('#^data:image/(png|jpe?g|webp);base64,#i', $signature)) {
        return '<div class="empty-signature">签名格式不可预览</div>';
    }
    return '<img class="signature-img" src="' . fdx_h($signature) . '" alt="' . fdx_h($alt) . '">';
}

function fdx_first_signed_record(array $records, string $field): ?array
{
    foreach ($records as $record) {
        if (trim((string) ($record[$field] ?? '')) !== '') {
            return $record;
        }
    }
    return null;
}

function fdx_render_orders_export(array $payloads): string
{
    $title = count($payloads) === 1
        ? '飞大修工单'
        : '飞大修工单批量导出';
    $html = '<!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><title>' . fdx_h($title) . '</title>';
    $html .= '<style>
      *{box-sizing:border-box}body{margin:0;background:#eef2f7;color:#172033;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","Microsoft YaHei",sans-serif}.export-shell{padding:24px}.work-order{width:min(980px,100%);margin:0 auto 24px;padding:28px;border:1px solid #d9e0ea;border-radius:10px;background:#fff;box-shadow:0 14px 36px rgba(23,32,51,.08)}.order-head{display:flex;justify-content:space-between;gap:18px;border-bottom:2px solid #172033;padding-bottom:16px;margin-bottom:18px}.order-head h1{margin:0;font-size:28px}.order-head p{margin:6px 0 0;color:#5d6a7e;font-weight:700}.status{display:inline-flex;align-items:center;height:32px;border-radius:999px;padding:0 12px;background:#edf7fb;color:#176b87;font-weight:800}.section{margin-top:18px}.section h2{margin:0 0 10px;font-size:18px}.info-table,.record-table{width:100%;border-collapse:collapse}.info-table th,.info-table td,.record-table th,.record-table td{border:1px solid #d9e0ea;padding:9px 10px;vertical-align:top;text-align:left;line-height:1.55}.info-table th{width:150px;background:#f6f8fb;color:#5d6a7e}.record-table th{background:#f6f8fb;color:#5d6a7e}.signature-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.signature-box{min-height:150px;border:1px solid #d9e0ea;border-radius:8px;padding:12px;background:#fbfcfe}.signature-box strong{display:block;margin-bottom:8px}.signature-img{display:block;max-width:100%;max-height:180px;object-fit:contain;background:#fff}.empty-signature{display:grid;min-height:112px;place-items:center;color:#5d6a7e;border:1px dashed #cbd5e1;border-radius:8px;background:#fff}.muted{color:#5d6a7e}.page-break{page-break-after:always}@media print{body{background:#fff}.export-shell{padding:0}.work-order{width:100%;margin:0;padding:18mm;border:0;border-radius:0;box-shadow:none;page-break-after:always}.work-order:last-child{page-break-after:auto}.signature-grid{grid-template-columns:1fr 1fr}}@media(max-width:720px){.export-shell{padding:12px}.work-order{padding:16px}.order-head,.signature-grid{display:grid;grid-template-columns:1fr}.info-table th{width:110px}}
    </style></head><body><main class="export-shell">';

    foreach ($payloads as $payload) {
        $html .= fdx_render_export_order($payload);
    }

    return $html . '</main></body></html>';
}

function fdx_render_export_order(array $payload): string
{
    $order = $payload['order'];
    $repairs = $payload['repair_records'];
    $pickups = $payload['pickup_records'];
    $assignments = $payload['assignments'];
    $smsLogs = $payload['sms_logs'];
    $activity = implode(' · ', array_filter([
        $order['activity_name'] ?? '',
        $order['activity_date'] ?? '',
        $order['activity_location'] ?? '',
    ])) ?: '未填';
    $techSignature = fdx_first_signed_record($repairs, 'technician_signature');
    $customerSignature = fdx_first_signed_record($pickups, 'customer_signature');

    $fields = [
        '活动' => $activity,
        '编号' => $order['order_no'] ?? '',
        '状态' => fdx_status_label((string) ($order['status'] ?? '')),
        '录入位置' => (($order['station_no'] ?? '') !== '' ? ($order['station_no'] . '号位') : '未填'),
        '机主' => trim((string) (($order['customer_name'] ?? '') . ' ' . ($order['customer_phone'] ?? ''))) ?: '未填',
        '备用电话' => $order['backup_phone'] ?? '未填',
        'QQ' => $order['customer_qq'] ?? '未填',
        '性别' => $order['gender'] ?? '未填',
        '学院' => $order['college'] ?? '未填',
        '宿舍' => $order['dormitory'] ?? '未填',
        '学号' => $order['student_id'] ?? '未填',
        '设备' => trim((string) (($order['device_type'] ?? '') . ' ' . ($order['device_model'] ?? ''))) ?: '未填',
        '开机密码' => $order['boot_password'] ?? '未填',
        '外带附件' => $order['accessories'] ?? '未填',
        '外观及硬件损坏' => $order['existing_damage'] ?? '未填',
        '服务' => fdx_export_service_types($order['service_types'] ?? null),
        '确认过保' => ((int) ($order['confirmed_out_of_warranty'] ?? 0) === 1 ? '已确认' : '未确认'),
        '允许重装系统' => fdx_export_bool($order['allow_reinstall'] ?? null),
        '允许清空硬盘' => fdx_export_bool($order['allow_format'] ?? null),
        '重要数据' => $order['important_data'] ?? '未填',
        '故障描述' => $order['problem_description'] ?? '未填',
        '维修要求' => $order['repair_notes'] ?? '未填',
        '备注' => $order['notes'] ?? '未填',
        '创建时间' => $order['created_at'] ?? '',
        '更新时间' => $order['updated_at'] ?? '',
    ];

    $html = '<section class="work-order"><div class="order-head"><div><h1>飞大修工单 ' . fdx_h($order['order_no'] ?? '') . '</h1><p>' . fdx_h($activity) . '</p></div><span class="status">' . fdx_h(fdx_status_label((string) ($order['status'] ?? ''))) . '</span></div>';
    $html .= '<section class="section"><h2>订单信息</h2><table class="info-table"><tbody>';
    foreach ($fields as $label => $value) {
        $html .= '<tr><th>' . fdx_h($label) . '</th><td>' . fdx_export_multiline($value) . '</td></tr>';
    }
    $html .= '</tbody></table></section>';

    $html .= '<section class="section"><h2>双方签名</h2><div class="signature-grid"><div class="signature-box"><strong>技术员签名</strong>' . fdx_export_signature_html($techSignature['technician_signature'] ?? null, '技术员签名') . '<p class="muted">' . fdx_h($techSignature ? (($techSignature['technician_name'] ?? '') . ' · ' . ($techSignature['submitted_at'] ?? '')) : '暂无维修签名') . '</p></div><div class="signature-box"><strong>机主取机签名</strong>' . fdx_export_signature_html($customerSignature['customer_signature'] ?? null, '机主取机签名') . '<p class="muted">' . fdx_h($customerSignature ? ($customerSignature['pickup_time'] ?? '') : '暂无取机签名') . '</p></div></div></section>';
    $html .= fdx_render_export_assignments($assignments);
    $html .= fdx_render_export_repairs($repairs);
    $html .= fdx_render_export_pickups($pickups);
    $html .= fdx_render_export_sms($smsLogs);

    return $html . '</section>';
}

function fdx_render_export_assignments(array $assignments): string
{
    if (!$assignments) {
        return '<section class="section"><h2>技术员分配</h2><p class="muted">暂无记录</p></section>';
    }
    $html = '<section class="section"><h2>技术员分配</h2><table class="record-table"><thead><tr><th>技术员</th><th>手机号</th><th>状态</th><th>时间</th><th>备注</th></tr></thead><tbody>';
    foreach ($assignments as $row) {
        $html .= '<tr><td>' . fdx_h($row['technician_name'] ?? '') . '</td><td>' . fdx_h($row['technician_phone'] ?? '') . '</td><td>' . fdx_h(fdx_assignment_status_label((string) ($row['status'] ?? ''))) . '</td><td>' . fdx_h($row['assigned_at'] ?? '') . '</td><td>' . fdx_export_multiline($row['notes'] ?? '') . '</td></tr>';
    }
    return $html . '</tbody></table></section>';
}

function fdx_render_export_repairs(array $repairs): string
{
    if (!$repairs) {
        return '<section class="section"><h2>维修记录</h2><p class="muted">暂无记录</p></section>';
    }
    $html = '<section class="section"><h2>维修记录</h2><table class="record-table"><thead><tr><th>技术员</th><th>结果</th><th>提交时间</th><th>故障诊断</th><th>解决方案</th><th>备注</th></tr></thead><tbody>';
    foreach ($repairs as $row) {
        $html .= '<tr><td>' . fdx_h($row['technician_name'] ?? '') . '</td><td>' . fdx_h(fdx_repair_result_label((string) ($row['result'] ?? ''))) . '</td><td>' . fdx_h($row['submitted_at'] ?? '') . '</td><td>' . fdx_export_multiline($row['diagnosis'] ?? '') . '</td><td>' . fdx_export_multiline($row['solution'] ?? '') . '</td><td>' . fdx_export_multiline($row['notes'] ?? '') . '</td></tr>';
    }
    return $html . '</tbody></table></section>';
}

function fdx_render_export_pickups(array $pickups): string
{
    if (!$pickups) {
        return '<section class="section"><h2>取机记录</h2><p class="muted">暂无记录</p></section>';
    }
    $html = '<section class="section"><h2>取机记录</h2><table class="record-table"><thead><tr><th>取机时间</th><th>经办人</th><th>备注</th></tr></thead><tbody>';
    foreach ($pickups as $row) {
        $html .= '<tr><td>' . fdx_h($row['pickup_time'] ?? '') . '</td><td>' . fdx_h($row['handler_name'] ?? '系统/旧系统') . '</td><td>' . fdx_export_multiline($row['notes'] ?? '') . '</td></tr>';
    }
    return $html . '</tbody></table></section>';
}

function fdx_render_export_sms(array $smsLogs): string
{
    if (!$smsLogs) {
        return '<section class="section"><h2>短信记录</h2><p class="muted">暂无记录</p></section>';
    }
    $html = '<section class="section"><h2>短信记录</h2><table class="record-table"><thead><tr><th>状态</th><th>手机号</th><th>时间</th><th>内容</th></tr></thead><tbody>';
    foreach ($smsLogs as $row) {
        $html .= '<tr><td>' . fdx_h(fdx_sms_status_label((string) ($row['status'] ?? ''))) . '</td><td>' . fdx_h($row['phone_mask'] ?? '') . '</td><td>' . fdx_h($row['sent_at'] ?? $row['created_at'] ?? '') . '</td><td>' . fdx_export_multiline($row['content'] ?? '') . '</td></tr>';
    }
    return $html . '</tbody></table></section>';
}

function fdx_route_orders_next_number(): never
{
    fdx_require_method('GET');
    fdx_require_staff(['admin', 'duty', 'intake', 'dispatcher', 'service', 'viewer']);

    $stationNo = fdx_station_from_input($_GET['station_no'] ?? $_GET['position'] ?? null);
    $pdo = fdx_db();
    $activity = fdx_active_activity($pdo);
    if (!$activity) {
        fdx_json(false, null, '请先设置当前活动', 422);
    }

    $stmt = $pdo->prepare("
        SELECT next_number
        FROM fdx_order_sequences
        WHERE activity_id = :activity_id AND station_no = :station_no
        LIMIT 1
    ");
    $stmt->execute([
        ':activity_id' => $activity['id'],
        ':station_no' => $stationNo,
    ]);

    $nextNumber = $stmt->fetchColumn();
    if ($nextNumber === false) {
        $nextNumber = $stationNo === '1' ? 1 : 2;
    }

    $orderNo = str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    fdx_json(true, [
        'order_no' => $orderNo,
        'order_number' => $orderNo,
        'station_no' => $stationNo,
        'position' => $stationNo,
    ]);
}

function fdx_input_value(array $input, string ...$keys): mixed
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $input)) {
            return $input[$key];
        }
    }
    return null;
}

function fdx_text_value(array $input, string ...$keys): ?string
{
    $value = fdx_input_value($input, ...$keys);
    if ($value === null || is_array($value)) {
        return null;
    }

    $text = trim((string) $value);
    return $text === '' ? null : $text;
}

function fdx_intake_token(array $input): ?string
{
    $token = fdx_text_value($input, 'intake_token', 'submission_token', 'request_id');
    if ($token === null) {
        return null;
    }
    if (strlen($token) > 80 || !preg_match('/^[A-Za-z0-9._:-]{8,80}$/', $token)) {
        fdx_json(false, null, '提交标识不正确，请刷新后重试', 422);
    }
    return $token;
}

function fdx_find_order_by_intake_token(PDO $pdo, int $activityId, string $token): ?array
{
    $stmt = $pdo->prepare("
        SELECT id, order_no
        FROM fdx_orders
        WHERE activity_id = :activity_id AND intake_token = :intake_token
        LIMIT 1
    ");
    $stmt->execute([
        ':activity_id' => $activityId,
        ':intake_token' => $token,
    ]);
    $order = $stmt->fetch();
    return $order ?: null;
}

function fdx_normalize_service_types(array $input): ?array
{
    $raw = fdx_input_value($input, 'service_type', 'service_type[]', 'service_types');
    if ($raw === null || $raw === '') {
        return null;
    }

    if (!is_array($raw)) {
        $raw = preg_split('/\s*,\s*/u', (string) $raw) ?: [];
    }

    $allowed = [
        '硬件初级检修',
        '拆机清洁',
        '数据恢复',
        '软件、驱动安装',
        '病毒清除、系统修复',
        '系统优化',
    ];
    $serviceTypes = [];

    foreach ($raw as $value) {
        $serviceType = trim((string) $value);
        if ($serviceType === '') {
            continue;
        }
        if (!in_array($serviceType, $allowed, true)) {
            fdx_json(false, null, "服务类型不正确：{$serviceType}", 422);
        }
        if (!in_array($serviceType, $serviceTypes, true)) {
            $serviceTypes[] = $serviceType;
        }
    }

    return $serviceTypes ?: null;
}

function fdx_route_orders_create(): never
{
    fdx_require_method('POST');
    $staff = fdx_require_staff(['admin', 'duty', 'intake']);
    $input = fdx_input();

    $required = [
        '录入位置' => fdx_text_value($input, 'station_no', 'position'),
        '姓名' => fdx_text_value($input, 'customer_name'),
        '手机号' => fdx_text_value($input, 'customer_phone'),
        '设备类型' => fdx_text_value($input, 'device_type'),
        '故障描述' => fdx_text_value($input, 'problem_description'),
    ];
    foreach ($required as $label => $value) {
        if ($value === null) {
            fdx_json(false, null, "请填写{$label}", 422);
        }
    }

    $customerPhone = preg_replace('/\D+/', '', (string) $required['手机号']);
    if (!preg_match('/^1[3-9]\d{9}$/', $customerPhone)) {
        fdx_json(false, null, '请输入正确的手机号', 422);
    }

    $backupPhone = fdx_text_value($input, 'backup_phone');
    if ($backupPhone !== null) {
        $backupPhone = preg_replace('/\D+/', '', $backupPhone);
        if (!preg_match('/^1[3-9]\d{9}$/', $backupPhone)) {
            fdx_json(false, null, '请输入正确的备用联系人手机号', 422);
        }
    }

    $confirmedOutOfWarranty = fdx_json_bool(
        fdx_input_value($input, 'confirmed_out_of_warranty', 'out_of_warranty_confirmed', 'warranty_confirmed')
    );
    if ($confirmedOutOfWarranty !== 1) {
        fdx_json(false, null, '请确认设备已过保', 422);
    }

    $stationNo = fdx_station_from_input($required['录入位置']);
    $serviceTypes = fdx_normalize_service_types($input);
    $notes = fdx_text_value($input, 'notes', 'intake_notes');
    $intakeToken = fdx_intake_token($input);
    $pdo = fdx_db();
    $activityId = 0;
    $pdo->beginTransaction();
    try {
        $activity = fdx_active_activity($pdo);
        if (!$activity) {
            throw new RuntimeException('请先设置当前活动');
        }
        $activityId = (int) $activity['id'];
        if ($intakeToken !== null) {
            $existing = fdx_find_order_by_intake_token($pdo, $activityId, $intakeToken);
            if ($existing) {
                $pdo->commit();
                fdx_json(true, [
                    'id' => (int) $existing['id'],
                    'order_no' => $existing['order_no'],
                    'order_number' => $existing['order_no'],
                    'duplicate' => true,
                ], '订单已创建，请勿重复提交');
            }
        }
        $orderNo = fdx_next_order_no($pdo, $activityId, $stationNo);

        $stmt = $pdo->prepare("
            INSERT INTO fdx_orders (
                activity_id, order_no, station_no, status,
                customer_name, customer_phone, customer_phone_mask, backup_phone, customer_qq,
                gender, college, dormitory, student_id,
                device_type, device_model, boot_password_cipher,
                accessories, existing_damage, confirmed_out_of_warranty, service_types,
                allow_reinstall, allow_format, important_data,
                problem_description, repair_notes, notes, intake_notes, intake_token, created_by
            ) VALUES (
                :activity_id, :order_no, :station_no, 'waiting_assignment',
                :customer_name, :customer_phone, :customer_phone_mask, :backup_phone, :customer_qq,
                :gender, :college, :dormitory, :student_id,
                :device_type, :device_model, :boot_password_cipher,
                :accessories, :existing_damage, :confirmed_out_of_warranty, :service_types,
                :allow_reinstall, :allow_format, :important_data,
                :problem_description, :repair_notes, :notes, :intake_notes, :intake_token, :created_by
            )
        ");
        $stmt->execute([
            ':activity_id' => $activityId,
            ':order_no' => $orderNo,
            ':station_no' => $stationNo,
            ':customer_name' => $required['姓名'],
            ':customer_phone' => $customerPhone,
            ':customer_phone_mask' => fdx_mask_phone($customerPhone),
            ':backup_phone' => $backupPhone,
            ':customer_qq' => fdx_text_value($input, 'customer_qq'),
            ':gender' => fdx_text_value($input, 'gender'),
            ':college' => fdx_text_value($input, 'college'),
            ':dormitory' => fdx_text_value($input, 'dormitory'),
            ':student_id' => fdx_text_value($input, 'student_id'),
            ':device_type' => $required['设备类型'],
            ':device_model' => fdx_text_value($input, 'device_model'),
            ':boot_password_cipher' => fdx_encrypt_secret(fdx_text_value($input, 'login_password', 'boot_password')),
            ':accessories' => fdx_text_value($input, 'accessories'),
            ':existing_damage' => fdx_text_value($input, 'existing_damage'),
            ':confirmed_out_of_warranty' => 1,
            ':service_types' => $serviceTypes ? json_encode($serviceTypes, JSON_UNESCAPED_UNICODE) : null,
            ':allow_reinstall' => fdx_json_bool(fdx_input_value($input, 'allow_system_reinstall', 'allow_reinstall')),
            ':allow_format' => fdx_json_bool(fdx_input_value($input, 'allow_disk_format', 'allow_format')),
            ':important_data' => fdx_text_value($input, 'important_data'),
            ':problem_description' => $required['故障描述'],
            ':repair_notes' => fdx_text_value($input, 'repair_notes'),
            ':notes' => $notes,
            ':intake_notes' => fdx_text_value($input, 'intake_notes') ?? $notes,
            ':intake_token' => $intakeToken,
            ':created_by' => fdx_staff_actor_id($staff),
        ]);

        $orderId = (int) $pdo->lastInsertId();
        fdx_event($orderId, 'staff', fdx_staff_actor_id($staff), 'order_created', null, 'waiting_assignment', [
            'station_no' => $stationNo,
            'confirmed_out_of_warranty' => true,
        ]);
        $pdo->commit();
        fdx_json(true, ['id' => $orderId, 'order_no' => $orderNo, 'order_number' => $orderNo], '订单已创建');
    } catch (PDOException $e) {
        $pdo->rollBack();
        if ($e->getCode() === '23000' && $intakeToken !== null && $activityId > 0) {
            $existing = fdx_find_order_by_intake_token($pdo, $activityId, $intakeToken);
            if ($existing) {
                fdx_json(true, [
                    'id' => (int) $existing['id'],
                    'order_no' => $existing['order_no'],
                    'order_number' => $existing['order_no'],
                    'duplicate' => true,
                ], '订单已创建，请勿重复提交');
            }
        }
        throw $e;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function fdx_route_orders_update(): never
{
    fdx_require_method('POST');
    $staff = fdx_require_staff(['admin']);
    $input = fdx_input();
    $orderId = (int) ($input['order_id'] ?? $input['id'] ?? 0);
    if ($orderId <= 0) {
        fdx_json(false, null, '订单 ID 不正确', 422);
    }

    $required = [
        '录入位置' => fdx_text_value($input, 'station_no', 'position'),
        '姓名' => fdx_text_value($input, 'customer_name'),
        '手机号' => fdx_text_value($input, 'customer_phone'),
        '设备类型' => fdx_text_value($input, 'device_type'),
        '故障描述' => fdx_text_value($input, 'problem_description'),
    ];
    foreach ($required as $label => $value) {
        if ($value === null) {
            fdx_json(false, null, "请填写{$label}", 422);
        }
    }

    $customerPhone = preg_replace('/\D+/', '', (string) $required['手机号']);
    if (!preg_match('/^1[3-9]\d{9}$/', $customerPhone)) {
        fdx_json(false, null, '请输入正确的手机号', 422);
    }

    $backupPhone = fdx_text_value($input, 'backup_phone');
    if ($backupPhone !== null) {
        $backupPhone = preg_replace('/\D+/', '', $backupPhone);
        if (!preg_match('/^1[3-9]\d{9}$/', $backupPhone)) {
            fdx_json(false, null, '请输入正确的备用联系人手机号', 422);
        }
    }

    $confirmedOutOfWarranty = fdx_json_bool(
        fdx_input_value($input, 'confirmed_out_of_warranty', 'out_of_warranty_confirmed', 'warranty_confirmed')
    );
    if ($confirmedOutOfWarranty !== 1) {
        fdx_json(false, null, '请确认设备已过保', 422);
    }

    $stationNo = fdx_station_from_input($required['录入位置']);
    $serviceTypes = fdx_normalize_service_types($input);
    $notes = fdx_text_value($input, 'notes', 'intake_notes');
    $pdo = fdx_db();
    $pdo->beginTransaction();
    try {
        $order = fdx_find_order_for_update($pdo, $orderId);
        $stmt = $pdo->prepare("
            UPDATE fdx_orders
            SET station_no = :station_no,
                customer_name = :customer_name,
                customer_phone = :customer_phone,
                customer_phone_mask = :customer_phone_mask,
                backup_phone = :backup_phone,
                customer_qq = :customer_qq,
                gender = :gender,
                college = :college,
                dormitory = :dormitory,
                student_id = :student_id,
                device_type = :device_type,
                device_model = :device_model,
                boot_password_cipher = :boot_password_cipher,
                accessories = :accessories,
                existing_damage = :existing_damage,
                confirmed_out_of_warranty = :confirmed_out_of_warranty,
                service_types = :service_types,
                allow_reinstall = :allow_reinstall,
                allow_format = :allow_format,
                important_data = :important_data,
                problem_description = :problem_description,
                repair_notes = :repair_notes,
                notes = :notes,
                intake_notes = :intake_notes
            WHERE id = :id
        ");
        $stmt->execute([
            ':station_no' => $stationNo,
            ':customer_name' => $required['姓名'],
            ':customer_phone' => $customerPhone,
            ':customer_phone_mask' => fdx_mask_phone($customerPhone),
            ':backup_phone' => $backupPhone,
            ':customer_qq' => fdx_text_value($input, 'customer_qq'),
            ':gender' => fdx_text_value($input, 'gender'),
            ':college' => fdx_text_value($input, 'college'),
            ':dormitory' => fdx_text_value($input, 'dormitory'),
            ':student_id' => fdx_text_value($input, 'student_id'),
            ':device_type' => $required['设备类型'],
            ':device_model' => fdx_text_value($input, 'device_model'),
            ':boot_password_cipher' => fdx_encrypt_secret(fdx_text_value($input, 'login_password', 'boot_password')),
            ':accessories' => fdx_text_value($input, 'accessories'),
            ':existing_damage' => fdx_text_value($input, 'existing_damage'),
            ':confirmed_out_of_warranty' => 1,
            ':service_types' => $serviceTypes ? json_encode($serviceTypes, JSON_UNESCAPED_UNICODE) : null,
            ':allow_reinstall' => fdx_json_bool(fdx_input_value($input, 'allow_system_reinstall', 'allow_reinstall')),
            ':allow_format' => fdx_json_bool(fdx_input_value($input, 'allow_disk_format', 'allow_format')),
            ':important_data' => fdx_text_value($input, 'important_data'),
            ':problem_description' => $required['故障描述'],
            ':repair_notes' => fdx_text_value($input, 'repair_notes'),
            ':notes' => $notes,
            ':intake_notes' => fdx_text_value($input, 'intake_notes') ?? $notes,
            ':id' => $orderId,
        ]);
        fdx_event($orderId, 'staff', fdx_staff_actor_id($staff), 'order_updated', $order['status'], $order['status']);
        $pdo->commit();
        fdx_json(true, null, '订单已保存');
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function fdx_route_orders_cancel(): never
{
    fdx_require_method('POST');
    $staff = fdx_require_staff(['admin']);
    $input = fdx_input();
    $orderId = (int) ($input['order_id'] ?? $input['id'] ?? 0);
    if ($orderId <= 0) {
        fdx_json(false, null, '订单 ID 不正确', 422);
    }
    $reason = fdx_text_value($input, 'reason', 'notes');

    $pdo = fdx_db();
    $pdo->beginTransaction();
    try {
        $order = fdx_find_order_for_update($pdo, $orderId);
        if ($order['status'] === 'completed') {
            throw new RuntimeException('已完成订单不用取消');
        }
        if ($order['status'] === 'cancelled') {
            $pdo->commit();
            fdx_json(true, null, '订单已取消');
        }

        $pdo->prepare("
            UPDATE fdx_order_assignments
            SET status = 'closed', released_at = COALESCE(released_at, NOW())
            WHERE order_id = :order_id AND status = 'active'
        ")->execute([':order_id' => $orderId]);
        $pdo->prepare("
            UPDATE fdx_orders
            SET status = 'cancelled', cancelled_at = NOW(), completed_at = NULL
            WHERE id = :id
        ")->execute([':id' => $orderId]);
        fdx_event($orderId, 'staff', fdx_staff_actor_id($staff), 'order_cancelled', $order['status'], 'cancelled', [
            'reason' => $reason,
        ]);
        $pdo->commit();
        fdx_json(true, null, '订单已取消');
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function fdx_route_orders_assign(): never
{
    fdx_require_method('POST');
    $staff = fdx_require_staff(['admin', 'duty', 'dispatcher']);
    $input = fdx_input();
    $orderId = (int) ($input['order_id'] ?? 0);
    $technicianId = (int) ($input['technician_id'] ?? 0);
    if ($orderId <= 0 || $technicianId <= 0) {
        fdx_json(false, null, '订单和技术员不能为空', 422);
    }

    $pdo = fdx_db();
    $pdo->beginTransaction();
    try {
        $order = fdx_find_order_for_update($pdo, $orderId);
        if (in_array($order['status'], ['completed', 'cancelled', 'ready_for_pickup', 'notified'], true)) {
            throw new RuntimeException('当前状态不能分配或转单');
        }

        $tech = $pdo->prepare("SELECT id FROM fdx_technicians WHERE id = :id AND status = 'active'");
        $tech->execute([':id' => $technicianId]);
        if (!$tech->fetch()) {
            throw new RuntimeException('技术员不存在或已停用');
        }

        $pdo->prepare("
            UPDATE fdx_order_assignments
            SET status = 'transferred', released_at = NOW()
            WHERE order_id = :order_id AND status = 'active'
        ")->execute([':order_id' => $orderId]);

        $pdo->prepare("
            INSERT INTO fdx_order_assignments (order_id, technician_id, assigned_by, status, notes)
            VALUES (:order_id, :technician_id, :assigned_by, 'active', :notes)
        ")->execute([
            ':order_id' => $orderId,
            ':technician_id' => $technicianId,
            ':assigned_by' => fdx_staff_actor_id($staff),
            ':notes' => $input['notes'] ?? null,
        ]);

        $toStatus = 'assigned';
        $pdo->prepare("UPDATE fdx_orders SET status = :status WHERE id = :id")->execute([
            ':status' => $toStatus,
            ':id' => $orderId,
        ]);

        fdx_event($orderId, 'staff', fdx_staff_actor_id($staff), 'order_assigned', $order['status'], $toStatus, [
            'technician_id' => $technicianId,
        ]);
        $pdo->commit();
        fdx_json(true, null, '分配成功');
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function fdx_route_orders_claim_link(): never
{
    fdx_require_method('POST');
    $staff = fdx_require_staff(['admin', 'duty', 'dispatcher']);
    $input = fdx_input();
    $orderId = (int) ($input['order_id'] ?? 0);
    if ($orderId <= 0) {
        fdx_json(false, null, '订单 ID 不正确', 422);
    }

    $pdo = fdx_db();
    $pdo->beginTransaction();
    try {
        $order = fdx_find_order_for_update($pdo, $orderId);
        if ($order['status'] !== 'waiting_assignment') {
            throw new RuntimeException('只有待技术员接机订单可以生成扫码接机二维码');
        }

        $pdo->prepare("
            UPDATE fdx_signed_links
            SET status = 'expired'
            WHERE order_id = :order_id AND purpose = 'repair' AND status = 'active'
        ")->execute([':order_id' => $orderId]);

        $token = fdx_random_token(24);
        $expiresAt = (new DateTimeImmutable('now'))->modify('+10 minutes')->format('Y-m-d H:i:s');
        $pdo->prepare("
            INSERT INTO fdx_signed_links (order_id, purpose, token_hash, status, expires_at, created_by)
            VALUES (:order_id, 'repair', :token_hash, 'active', :expires_at, :created_by)
        ")->execute([
            ':order_id' => $orderId,
            ':token_hash' => fdx_token_hash($token),
            ':expires_at' => $expiresAt,
            ':created_by' => fdx_staff_actor_id($staff),
        ]);

        $pdo->commit();
        fdx_json(true, [
            'url' => fdx_wechat_entry_url($token),
            'expires_at' => $expiresAt,
        ], '二维码已生成');
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function fdx_route_orders_claim(): never
{
    fdx_require_method('POST');
    $technician = fdx_require_technician();
    $input = fdx_input();
    $token = trim((string) ($input['token'] ?? ''));
    if ($token === '') {
        fdx_json(false, null, '领取码不能为空', 422);
    }

    $pdo = fdx_db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            SELECT sl.*, o.status AS order_status
            FROM fdx_signed_links sl
            INNER JOIN fdx_orders o ON o.id = sl.order_id
            WHERE sl.token_hash = :token_hash AND sl.purpose = 'repair'
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([':token_hash' => fdx_token_hash($token)]);
        $link = $stmt->fetch();
        if (!$link || $link['status'] !== 'active' || $link['expires_at'] <= date('Y-m-d H:i:s')) {
            $pdo->rollBack();
            fdx_json(false, null, '领取二维码无效或已过期', 422);
        }

        $orderId = (int) $link['order_id'];
        $order = fdx_find_order_for_update($pdo, $orderId);
        if ($order['status'] !== 'waiting_assignment') {
            throw new RuntimeException('这台机器已接机或不在待技术员接机状态');
        }

        $pdo->prepare("
            UPDATE fdx_order_assignments
            SET status = 'transferred', released_at = NOW()
            WHERE order_id = :order_id AND status = 'active'
        ")->execute([':order_id' => $orderId]);

        $pdo->prepare("
            INSERT INTO fdx_order_assignments (order_id, technician_id, assigned_by, status, accepted_at, notes)
            VALUES (:order_id, :technician_id, NULL, 'active', NOW(), '扫码领取')
        ")->execute([
            ':order_id' => $orderId,
            ':technician_id' => $technician['id'],
        ]);

        $pdo->prepare("UPDATE fdx_orders SET status = 'assigned' WHERE id = :id")
            ->execute([':id' => $orderId]);
        $pdo->prepare("
            UPDATE fdx_signed_links
            SET status = 'used', used_at = NOW()
            WHERE id = :id
        ")->execute([':id' => $link['id']]);
        $pdo->prepare("
            UPDATE fdx_signed_links
            SET status = 'expired'
            WHERE order_id = :order_id AND purpose = 'repair' AND status = 'active'
        ")->execute([':order_id' => $orderId]);

        fdx_event($orderId, 'technician', (int) $technician['id'], 'order_claimed', 'waiting_assignment', 'assigned');
        $pdo->commit();
        fdx_json(true, ['order_id' => $orderId], '领取成功');
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function fdx_route_orders_start_repair(): never
{
    fdx_require_method('POST');
    $technician = fdx_require_technician();
    $input = fdx_input();
    $orderId = (int) ($input['order_id'] ?? 0);

    $pdo = fdx_db();
    $pdo->beginTransaction();
    try {
        $order = fdx_find_order_for_update($pdo, $orderId);
        if ($order['status'] !== 'assigned') {
            throw new RuntimeException('只有已分配订单可以开始维修');
        }
        if (!fdx_technician_owns_order($pdo, (int) $technician['id'], $orderId)) {
            throw new RuntimeException('这不是分配给你的订单');
        }

        $pdo->prepare("UPDATE fdx_orders SET status = 'repairing' WHERE id = :id")
            ->execute([':id' => $orderId]);
        $pdo->prepare("
            UPDATE fdx_order_assignments
            SET accepted_at = COALESCE(accepted_at, NOW())
            WHERE order_id = :order_id AND technician_id = :technician_id AND status = 'active'
        ")->execute([
            ':order_id' => $orderId,
            ':technician_id' => $technician['id'],
        ]);
        fdx_event($orderId, 'technician', (int) $technician['id'], 'repair_started', 'assigned', 'repairing');
        $pdo->commit();
        fdx_json(true, null, '已开始维修');
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function fdx_route_orders_submit_repair(): never
{
    fdx_require_method('POST');
    $technician = fdx_require_technician();
    $input = fdx_input();
    $orderId = (int) ($input['order_id'] ?? 0);
    $diagnosis = trim((string) ($input['diagnosis'] ?? ''));
    $solution = trim((string) ($input['solution'] ?? ''));
    $signature = trim((string) ($input['technician_signature'] ?? ''));
    if ($orderId <= 0 || $diagnosis === '' || $solution === '' || $signature === '') {
        fdx_json(false, null, '订单、故障诊断、解决方案和技术员签字不能为空', 422);
    }

    $pdo = fdx_db();
    $pdo->beginTransaction();
    try {
        $order = fdx_find_order_for_update($pdo, $orderId);
        if ($order['status'] !== 'repairing') {
            throw new RuntimeException('请先开始维修，再提交维修记录');
        }
        if (!fdx_technician_owns_order($pdo, (int) $technician['id'], $orderId)) {
            throw new RuntimeException('这不是分配给你的订单');
        }

        $pdo->prepare("
            INSERT INTO fdx_repair_records (
                order_id, technician_id, diagnosis, solution, result, notes, technician_signature
            ) VALUES (
                :order_id, :technician_id, :diagnosis, :solution, :result, :notes, :technician_signature
            )
        ")->execute([
            ':order_id' => $orderId,
            ':technician_id' => $technician['id'],
            ':diagnosis' => $diagnosis,
            ':solution' => $solution,
            ':result' => $input['result'] ?? 'fixed',
            ':notes' => $input['notes'] ?? null,
            ':technician_signature' => $signature,
        ]);

        $pdo->prepare("UPDATE fdx_orders SET status = 'ready_for_pickup' WHERE id = :id")
            ->execute([':id' => $orderId]);
        fdx_event($orderId, 'technician', (int) $technician['id'], 'repair_submitted', $order['status'], 'ready_for_pickup');
        $pdo->commit();
        fdx_json(true, null, '维修记录已提交，订单进入待通知');
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function fdx_route_orders_staff_complete_repair(): never
{
    fdx_require_method('POST');
    $staff = fdx_require_staff(['admin', 'duty', 'service']);
    $input = fdx_input();
    $orderId = (int) ($input['order_id'] ?? 0);
    $diagnosis = trim((string) ($input['diagnosis'] ?? ''));
    $solution = trim((string) ($input['solution'] ?? ''));
    $signature = trim((string) ($input['technician_signature'] ?? ''));
    if ($orderId <= 0 || $diagnosis === '' || $solution === '' || $signature === '') {
        fdx_json(false, null, '订单、故障诊断、解决方案和技术员签字不能为空', 422);
    }

    $pdo = fdx_db();
    $pdo->beginTransaction();
    try {
        $order = fdx_find_order_for_update($pdo, $orderId);
        if ($order['status'] !== 'repairing') {
            throw new RuntimeException('只有维修中的订单可以登记修完');
        }

        $assignment = $pdo->prepare("
            SELECT technician_id
            FROM fdx_order_assignments
            WHERE order_id = :order_id AND status = 'active'
            ORDER BY id DESC
            LIMIT 1
        ");
        $assignment->execute([':order_id' => $orderId]);
        $technicianId = (int) $assignment->fetchColumn();
        if ($technicianId <= 0) {
            throw new RuntimeException('这台机器还没有当前技术员，请先到 6 号位分配');
        }

        $pdo->prepare("
            INSERT INTO fdx_repair_records (
                order_id, technician_id, diagnosis, solution, result, notes, technician_signature
            ) VALUES (
                :order_id, :technician_id, :diagnosis, :solution, :result, :notes, :technician_signature
            )
        ")->execute([
            ':order_id' => $orderId,
            ':technician_id' => $technicianId,
            ':diagnosis' => $diagnosis,
            ':solution' => $solution,
            ':result' => $input['result'] ?? 'fixed',
            ':notes' => $input['notes'] ?? null,
            ':technician_signature' => $signature,
        ]);

        $pdo->prepare("UPDATE fdx_orders SET status = 'ready_for_pickup' WHERE id = :id")
            ->execute([':id' => $orderId]);
        fdx_event($orderId, 'staff', fdx_staff_actor_id($staff), 'repair_submitted_by_service', $order['status'], 'ready_for_pickup', [
            'technician_id' => $technicianId,
        ]);
        $pdo->commit();
        fdx_json(true, null, '已登记修完，订单进入待通知');
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function fdx_pickup_notice_payload(array $order): array
{
    $phone = preg_replace('/\D+/', '', (string) ($order['customer_phone'] ?? ''));
    if ($phone === '') {
        throw new RuntimeException('机主手机号为空，无法生成短信通知');
    }

    $content = "【飞扬俱乐部】您好{$order['customer_name']}，您的设备（订单号：{$order['order_no']}）已维修完成，请及时到现场取回。如有疑问请联系我们。";
    $messageBase = rtrim(dirname(fdx_public_base_url()), '/') . '/message/repairdone';
    $url = $messageBase . '?phone=' . rawurlencode($phone) . '&message=' . rawurlencode($content);

    return [
        'phone' => $phone,
        'phone_mask' => fdx_mask_phone($phone),
        'content' => $content,
        'url' => $url,
    ];
}

function fdx_route_orders_sms_notice(): never
{
    fdx_require_method('POST');
    fdx_require_staff(['admin', 'duty', 'service']);
    $input = fdx_input();
    $orderId = (int) ($input['order_id'] ?? 0);
    if ($orderId <= 0) {
        fdx_json(false, null, '订单 ID 不正确', 422);
    }

    $stmt = fdx_db()->prepare("SELECT * FROM fdx_orders WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $orderId]);
    $order = $stmt->fetch();
    if (!$order) {
        throw new RuntimeException('订单不存在');
    }
    if (!in_array($order['status'], ['ready_for_pickup', 'notified'], true)) {
        throw new RuntimeException('只有待通知或待取机订单可以生成取机通知');
    }
    $payload = fdx_pickup_notice_payload($order);
    fdx_json(true, [
        'order_id' => $orderId,
        'url' => $payload['url'],
        'content' => $payload['content'],
        'phone_mask' => $payload['phone_mask'],
    ], '取机通知已生成');
}

function fdx_route_orders_sms_confirm(): never
{
    fdx_require_method('POST');
    $staff = fdx_require_staff(['admin', 'duty', 'service']);
    $input = fdx_input();
    $orderId = (int) ($input['order_id'] ?? 0);
    if ($orderId <= 0) {
        fdx_json(false, null, '订单 ID 不正确', 422);
    }

    $pdo = fdx_db();
    $pdo->beginTransaction();
    try {
        $order = fdx_find_order_for_update($pdo, $orderId);
        if ($order['status'] === 'notified') {
            $pdo->commit();
            fdx_json(true, null, '订单已处于待取机');
        }
        if ($order['status'] !== 'ready_for_pickup') {
            throw new RuntimeException('只有待通知订单可以确认已发送');
        }

        $payload = fdx_pickup_notice_payload($order);
        $stmt = $pdo->prepare("
            INSERT INTO fdx_sms_logs (order_id, phone_mask, content, status, sent_by, sent_at, provider_response)
            VALUES (:order_id, :phone_mask, :content, 'manual_sent', :sent_by, NOW(), :provider_response)
        ");
        $stmt->execute([
            ':order_id' => $orderId,
            ':phone_mask' => $payload['phone_mask'],
            ':content' => $payload['content'],
            ':sent_by' => fdx_staff_actor_id($staff),
            ':provider_response' => json_encode([
                'method' => 'message_repairdone_link',
                'url' => $payload['url'],
                'confirmed_manually' => true,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $pdo->prepare("UPDATE fdx_orders SET status = 'notified' WHERE id = :id")
            ->execute([':id' => $orderId]);
        fdx_event($orderId, 'staff', fdx_staff_actor_id($staff), 'pickup_sms_notice', 'ready_for_pickup', 'notified', [
            'sms_log_id' => (int) $pdo->lastInsertId(),
        ]);
        $pdo->commit();
        fdx_json(true, null, '已确认发送，订单进入待取机');
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function fdx_route_orders_pickup(): never
{
    fdx_require_method('POST');
    $staff = fdx_require_staff(['admin', 'duty', 'service']);
    $input = fdx_input();
    $orderId = (int) ($input['order_id'] ?? 0);
    $signature = trim((string) ($input['customer_signature'] ?? ''));
    if ($orderId <= 0) {
        fdx_json(false, null, '订单 ID 不正确', 422);
    }
    if ($signature === '') {
        fdx_json(false, null, '机主取机签字不能为空', 422);
    }

    $pdo = fdx_db();
    $pdo->beginTransaction();
    try {
        $order = fdx_find_order_for_update($pdo, $orderId);
        if (!in_array($order['status'], ['ready_for_pickup', 'notified'], true)) {
            throw new RuntimeException('只有待通知或待取机订单可以办理取机');
        }

        $pdo->prepare("
            INSERT INTO fdx_pickup_records (order_id, handled_by, pickup_time, customer_signature, notes)
            VALUES (:order_id, :handled_by, NOW(), :customer_signature, :notes)
        ")->execute([
            ':order_id' => $orderId,
            ':handled_by' => fdx_staff_actor_id($staff),
            ':customer_signature' => $signature,
            ':notes' => $input['notes'] ?? null,
        ]);

        $pdo->prepare("UPDATE fdx_orders SET status = 'completed', completed_at = NOW() WHERE id = :id")
            ->execute([':id' => $orderId]);
        $pdo->prepare("
            UPDATE fdx_order_assignments
            SET status = 'closed', released_at = NOW()
            WHERE order_id = :order_id AND status = 'active'
        ")->execute([':order_id' => $orderId]);
        fdx_event($orderId, 'staff', fdx_staff_actor_id($staff), 'order_picked_up', $order['status'], 'completed');
        $pdo->commit();
        fdx_json(true, null, '取机完成');
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function fdx_route_dashboard_stats(): never
{
    fdx_require_staff(['admin', 'duty', 'intake', 'dispatcher', 'service', 'viewer']);
    $activityStmt = fdx_db()->query("
        SELECT id, name, activity_date, location, status
        FROM fdx_activities
        WHERE is_current = 1
        ORDER BY id DESC
        LIMIT 1
    ");
    $activity = $activityStmt->fetch() ?: null;

    $stmt = fdx_db()->query("
        SELECT o.status, COUNT(*) AS count
        FROM fdx_orders o
        INNER JOIN fdx_activities a ON a.id = o.activity_id
        WHERE a.is_current = 1
        GROUP BY o.status
    ");
    $stats = [
        'waiting_assignment' => 0,
        'assigned' => 0,
        'repairing' => 0,
        'ready_for_pickup' => 0,
        'notified' => 0,
        'completed' => 0,
        'cancelled' => 0,
    ];
    foreach ($stmt as $row) {
        $stats[$row['status']] = (int) $row['count'];
    }
    fdx_json(true, [
        'activity' => $activity,
        'stats' => $stats,
        'active_total' => $stats['waiting_assignment'] + $stats['assigned'] + $stats['repairing'] + $stats['ready_for_pickup'] + $stats['notified'],
    ]);
}
