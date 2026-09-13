<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', 'stderr');
date_default_timezone_set('Asia/Shanghai');

function db(): PDO {
    $dsn = getenv('FOC_TEST_DSN') ?: '';
    if (!preg_match('/(?:^|;)dbname=(foc_reliability_test(?:_[a-z0-9_]+)?)(?:;|$)/', $dsn, $m)) {
        throw new RuntimeException('Refusing writes: FOC_TEST_DSN must select foc_reliability_test[_suffix].');
    }
    if (getenv('FOC_TEST_ALLOW_SCHEMA_RESET') !== 'yes') {
        throw new RuntimeException('FOC_TEST_ALLOW_SCHEMA_RESET=yes must explicitly allow disposable schema setup.');
    }
    $pdo = new PDO($dsn, getenv('FOC_TEST_DB_USER') ?: 'root', getenv('FOC_TEST_DB_PASSWORD') ?: '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== $m[1]) {
        throw new RuntimeException('Connected database did not match the isolated test schema.');
    }
    $pdo->exec('SET SESSION innodb_lock_wait_timeout=10');
    return $pdo;
}

function loadCore(): void {
    $path = getenv('FOC_TEST_CORE');
    if (!$path || !is_file($path)) {
        throw new RuntimeException('FOC_TEST_CORE must point to the actual candidate utils/ticket_actions.php.');
    }
    require_once $path;
}

function ok(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
function eq($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' expected=' . json_encode($expected) . ' actual=' . json_encode($actual));
    }
}
function accepted(array $result, string $message): void {
    ok(($result['success'] ?? false) === true, $message . ': ' . json_encode($result));
}
function rejected(array $result, string $message): void {
    ok(($result['success'] ?? null) === false, $message . ': ' . json_encode($result));
}

function row(PDO $pdo, string $table, int $id): array {
    if (!in_array($table, ['fy_workorders', 'fy_users'], true)) throw new RuntimeException('Invalid test table.');
    $s = $pdo->prepare('SELECT * FROM ' . $table . ' WHERE id=?');
    $s->execute([$id]);
    return $s->fetch() ?: [];
}
function actor(PDO $pdo, int $id): array {
    $a = row($pdo, 'fy_users', $id);
    $a['is_admin'] = $id === 40;
    $a['is_lucky_admin'] = $id === 40;
    return $a;
}
function auditCount(PDO $pdo, int $id): int {
    $s = $pdo->prepare('SELECT COUNT(*) FROM fy_transfer_record WHERE ticketid=?');
    $s->execute([$id]);
    return (int)$s->fetchColumn();
}
function outboxCount(PDO $pdo): int {
    $table = getenv('FOC_TEST_OUTBOX_TABLE') ?: 'fy_ticket_notification_outbox';
    if (!preg_match('/^fy_[a-z0-9_]+$/', $table)) throw new RuntimeException('Invalid outbox table.');
    return (int)$pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
}
function testTables(PDO $pdo): array {
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        if (!preg_match('/^fy_[a-z0-9_]+$/', $table)) {
            throw new RuntimeException('Unexpected table in disposable schema; refusing reset: ' . $table);
        }
    }
    return $tables;
}
function fixture(PDO $pdo): void {
    // The db() gate has already verified the explicit test-only database name.
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach (testTables($pdo) as $table) $pdo->exec('TRUNCATE TABLE ' . $table);
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    $users = [
        [10,'user',2], [11,'user',2], [20,'technician',1],
        [30,'technician',1], [40,'admin',1], [50,'user',2],
    ];
    $s = $pdo->prepare("INSERT INTO fy_users (id,openid,access_token,token_expiry,nickname,role,available,phone,email,campus,max_concurrent,status) VALUES (?,?,?,DATE_ADD(NOW(), INTERVAL 1 DAY),?,?,?,'10000000000','fixture@example.invalid', 'fixture',1,'normal')");
    foreach ($users as [$id,$role,$available]) {
        $s->execute([$id,'fixture-openid-'.$id,hash('sha256','fixture-legacy-'.$id),'Fixture '.$id,$role,$available]);
    }
    $pdo->exec("INSERT INTO fy_admins (id,role,username,password,openid) VALUES (1,'super','fixture-admin','unused','fixture-openid-40')");
    $s = $pdo->prepare("INSERT INTO fy_admin_tokens (admin_id,token,expires_at,created_at,ip) VALUES (1,?,DATE_ADD(NOW(), INTERVAL 1 DAY),NOW(),'127.0.0.1')");
    $s->execute([hash('sha256','fixture-admin-40')]);
    $s = $pdo->prepare("INSERT INTO fy_app_tokens (user_id,token,expires_at,created_at) VALUES (30,?,DATE_ADD(NOW(), INTERVAL 1 DAY),NOW())");
    $s->execute([hash('sha256','fixture-app-30')]);
}
function ticket(PDO $pdo, int $id, string $status = 'Repairing', ?int $tech = 20, int $owner = 10, array $extra = []): void {
    $data = array_merge([
        'id'=>$id,'user_id'=>$owner,'user_nick'=>'Fixture customer',
        'create_time'=>'2026-09-13 12:00:00','machine_purchase_date'=>'2020-01-01',
        'user_phone'=>'','device_type'=>'computer','model'=>'Fixture model',
        'warranty_status'=>'unknown','computer_brand'=>'Fixture brand',
        'repair_description'=>'Synthetic integration test only','repair_status'=>$status,
        'fault_type'=>'fixture','campus'=>'test-campus','qq_number'=>'',
        'assigned_technician_id'=>$tech,'assigned_time'=>$tech ? '2026-09-13 12:01:00' : null,
        'order_hash'=>hash('sha256','fixture-order-'.$id),'transcode'=>123456,'refused_times'=>0
    ], $extra);
    $cols = implode(',', array_keys($data));
    $s = $pdo->prepare('INSERT INTO fy_workorders (' . $cols . ') VALUES (' . implode(',', array_fill(0,count($data),'?')) . ')');
    $s->execute(array_values($data));
    if ($tech) {
        $s = $pdo->prepare('UPDATE fy_users SET available=0 WHERE id=?');
        $s->execute([$tech]);
    }
}
function invoke(PDO $pdo, string $action, int $actorId, array $data): array {
    $a = actor($pdo, $actorId);
    try {
        switch ($action) {
            case 'give': return ticketGive($pdo, $a, $data);
            case 'set': return ticketSet($pdo, $a, $data, 5);
            case 'complete': return ticketComplete($pdo, $a, $data);
            default: throw new RuntimeException('Unknown action');
        }
    } catch (TicketActionError $e) {
        return array_merge($e->body, ['success'=>false, '_httpStatus'=>$e->httpStatus]);
    }
}
function runTest(string $name, callable $body): bool {
    try {
        $body();
        echo "PASS " . $name . PHP_EOL;
        return true;
    } catch (Throwable $e) {
        fwrite(STDERR, "FAIL " . $name . ": " . get_class($e) . ': ' . $e->getMessage() . PHP_EOL);
        return false;
    }
}
