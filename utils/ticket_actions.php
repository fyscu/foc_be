<?php
/**
 * Transactional ticket operations shared by mini-program, App and admin routes.
 * The caller supplies only the actor returned by verifyToken(), never request claims.
 * This module performs database work only; notification delivery belongs to the worker.
 */

class TicketActionError extends RuntimeException
{
    public int $httpStatus;
    public array $body;

    public function __construct(int $httpStatus, string $message, array $extra = [])
    {
        parent::__construct($message);
        $this->httpStatus = $httpStatus;
        $this->body = array_merge(
            ['success' => false, 'message' => $message, 'status' => $message, 'changedFields' => []],
            $extra
        );
    }
}

function ticketIdValue($value, string $field = 'ticket id'): string
{
    if (!is_int($value) && !is_string($value)) {
        throw new TicketActionError(400, 'Invalid ' . $field);
    }
    $id = (string)$value;
    if (!preg_match('/^[0-9]+$/D', $id)) {
        throw new TicketActionError(400, 'Invalid ' . $field);
    }
    $id = ltrim($id, '0');
    if ($id === '' || strlen($id) > 19
        || (strlen($id) === 19 && strcmp($id, '9223372036854775807') > 0)) {
        throw new TicketActionError(400, 'Invalid ' . $field);
    }
    return $id;
}

function ticketActorId(array $actor): int
{
    try {
        $id = ticketIdValue($actor['id'] ?? null, 'actor');
    } catch (TicketActionError $e) {
        throw new TicketActionError(401, 'Unauthorized');
    }
    if (strlen($id) > 10 || (int)$id > 2147483647) {
        throw new TicketActionError(401, 'Unauthorized');
    }
    return (int)$id;
}

function ticketTechnicianId($value): int
{
    $id = ticketIdValue($value, 'technician id');
    if (strlen($id) > 10 || (int)$id > 2147483647) {
        throw new TicketActionError(400, 'Invalid technician id');
    }
    return (int)$id;
}

function ticketIsAdmin(array $actor): bool
{
    return ($actor['is_admin'] ?? false) === true
        || ($actor['is_admin'] ?? null) === 1;
}

function ticketTransaction(PDO $pdo, callable $work): array
{
    if ($pdo->inTransaction()) {
        throw new LogicException('Ticket operations require an independent transaction.');
    }
    for ($attempt = 0; $attempt < 3; $attempt++) {
        try {
            // One transaction only: do not change the connection/session default.
            // A fresh read view after a user-row lock is needed for capacity counts.
            $pdo->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
            $pdo->beginTransaction();
            $result = $work();
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $mysqlCode = $e instanceof PDOException ? (int)($e->errorInfo[1] ?? 0) : 0;
            if ($attempt < 2 && in_array($mysqlCode, [1205, 1213], true)) {
                usleep(random_int(20000, 60000) * ($attempt + 1));
                continue;
            }
            throw $e;
        }
    }
    throw new LogicException('Unreachable transaction retry state.');
}

function ticketLock(PDO $pdo, string $id): array
{
    $stmt = $pdo->prepare('SELECT * FROM fy_workorders WHERE id = ? FOR UPDATE');
    $stmt->execute([$id]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ticket) {
        throw new TicketActionError(404, 'Ticket not found');
    }
    return $ticket;
}

/** All mutations lock the ticket first and all associated users in ascending ID order. */
function ticketLockUsers(PDO $pdo, array $ids): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($id) => $id > 0)));
    sort($ids, SORT_NUMERIC);
    $users = [];
    $stmt = $pdo->prepare(
        'SELECT id, openid, nickname, phone, email, role, available, max_concurrent '
        . 'FROM fy_users WHERE id = ? FOR UPDATE'
    );
    foreach ($ids as $id) {
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $users[$id] = $row;
        }
    }
    return $users;
}

function ticketRequireAccess(array $actor, array $ticket, array $users): void
{
    $actorId = ticketActorId($actor);
    if (!isset($users[$actorId])) {
        throw new TicketActionError(401, 'Unauthorized');
    }
    if (ticketIsAdmin($actor) || $actorId === (int)$ticket['user_id']) {
        return;
    }
    if (($users[$actorId]['role'] ?? '') === 'technician'
        && $actorId === (int)($ticket['assigned_technician_id'] ?? 0)) {
        return;
    }
    throw new TicketActionError(403, 'Permission denied');
}

function ticketNow(PDO $pdo): string
{
    return (string)$pdo->query('SELECT NOW()')->fetchColumn();
}

function ticketIsActive(string $status): bool
{
    return in_array($status, ['Pending', 'Repairing', 'UserConfirming', 'TechConfirming'], true);
}

function ticketRefreshCapacity(PDO $pdo, array $users, array $ids, bool $completed = false): void
{
    $ids = array_values(array_unique(array_map('intval', $ids)));
    sort($ids, SORT_NUMERIC);
    foreach ($ids as $id) {
        if ($id <= 0 || ($users[$id]['role'] ?? '') !== 'technician') {
            continue;
        }
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM fy_workorders WHERE assigned_technician_id = ? "
            . "AND repair_status IN ('Repairing', 'UserConfirming', 'TechConfirming')"
        );
        $stmt->execute([$id]);
        $count = (int)$stmt->fetchColumn();
        $capacity = max(1, (int)($users[$id]['max_concurrent'] ?? 1));
        $available = $count < $capacity ? 1 : 0;
        $stmt = $pdo->prepare(
            'UPDATE fy_users SET available = ?' . ($completed ? ', last_time = NOW()' : '') . ' WHERE id = ?'
        );
        $stmt->execute([$available, $id]);
    }
}

function ticketUpdateFields(PDO $pdo, array &$ticket, array $changes): void
{
    if (!$changes) {
        return;
    }
    // Keys are produced exclusively by the field validator or lifecycle functions below.
    $assignments = [];
    $values = [];
    foreach ($changes as $key => $value) {
        if (!preg_match('/^[a-zA-Z_]+$/D', $key)) {
            throw new LogicException('Invalid internal update key.');
        }
        $assignments[] = $key . ' = ?';
        $values[] = $value;
    }
    $values[] = (string)$ticket['id'];
    $stmt = $pdo->prepare('UPDATE fy_workorders SET ' . implode(', ', $assignments) . ' WHERE id = ?');
    $stmt->execute($values);
    $ticket = array_replace($ticket, $changes);
}

/** Each row is one delivery; successes are never retried with failing siblings. */
function ticketQueue(PDO $pdo, string $kind, array $ticket, array $users): void
{
    $owner = $users[(int)$ticket['user_id']] ?? [];
    $techId = (int)($ticket['assigned_technician_id'] ?? 0);
    $tech = $users[$techId] ?? [];
    $eventId = bin2hex(random_bytes(16));
    $jobs = match ($kind) {
        'assign', 'transfer' => [
            ['tech', 'sms'], ['tech', 'email'], ['tech', 'wechat'],
            ['user', 'sms'], ['user', 'email'], ['user', 'wechat'],
        ],
        'complete' => [['user', 'sms'], ['user', 'email']],
        'close' => [['tech', 'sms']],
        default => throw new LogicException('Unknown ticket notification kind.'),
    };
    $stmt = $pdo->prepare(
        'INSERT INTO fy_ticket_notification_outbox '
        . '(event_id, job_key, ticket_id, kind, channel, payload, status, attempts, available_at, created_at) '
        . "VALUES (?, ?, ?, ?, ?, ?, 'pending', 0, NOW(), NOW())"
    );
    foreach ($jobs as [$recipient, $channel]) {
        $contact = $recipient === 'tech' ? $tech : $owner;
        $address = match ($channel) {
            'sms' => $recipient === 'user'
                ? (string)(($ticket['user_phone'] ?? '') ?: ($owner['phone'] ?? ''))
                : (string)($contact['phone'] ?? ''),
            'email' => (string)($contact['email'] ?? ''),
            'wechat' => (string)($contact['openid'] ?? ''),
        };
        if (trim($address) === '') {
            continue;
        }
        $payload = [
            'recipient' => $recipient,
            'address' => $address,
            'ticket_id' => (string)$ticket['id'],
            'expected_tech' => $techId,
            'expected_status' => (string)$ticket['repair_status'],
            'expected_code' => (int)($ticket['transcode'] ?? 0),
            'owner_name' => (string)(($owner['nickname'] ?? '') ?: ($ticket['user_nick'] ?? '神秘用户')),
            'tech_name' => (string)($tech['nickname'] ?? ''),
            'tech_phone' => (string)($tech['phone'] ?? ''),
            'user_phone' => (string)(($ticket['user_phone'] ?? '') ?: ($owner['phone'] ?? '')),
            'fault_type' => (string)($ticket['fault_type'] ?? ''),
            'qq_number' => (string)($ticket['qq_number'] ?? ''),
            'create_time' => (string)($ticket['create_time'] ?? ''),
        ];
        $stmt->execute([
            $eventId, $recipient . ':' . $channel, (string)$ticket['id'], $kind, $channel,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);
    }
}

function ticketCredential(array $data): array
{
    // Supplying an invalid hash must never fall back to a valid numeric code.
    if (array_key_exists('order_hash', $data) && $data['order_hash'] !== null) {
        if (!is_string($data['order_hash']) || $data['order_hash'] === ''
            || strlen($data['order_hash']) > 255) {
            throw new TicketActionError(400, 'Order hash mismatch');
        }
        return ['hash', $data['order_hash']];
    }
    $code = $data['tvcode'] ?? null;
    if ((!is_int($code) && !is_string($code)) || !preg_match('/^[0-9]{6}$/D', (string)$code)) {
        throw new TicketActionError(400, 'Invalid code');
    }
    return ['code', (string)$code];
}

function ticketRequestId(array $data): ?string
{
    if (!array_key_exists('request_id', $data) || $data['request_id'] === null) {
        return null;
    }
    if (!is_string($data['request_id'])
        || !preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $data['request_id'])) {
        throw new TicketActionError(400, 'Invalid request_id');
    }
    return $data['request_id'];
}

/** The actor user row is locked, serializing this actor's keys across different tickets. */
function ticketRequestReplay(
    PDO $pdo, array $ticket, int $actorId, int $targetId, string $digest, string $requestId
): ?array {
    $stmt = $pdo->prepare(
        'SELECT ticket_id, request_digest, resulting_code, result_json FROM fy_ticket_action_receipts '
        . 'WHERE actor_id = ? AND request_id = ? LIMIT 1'
    );
    $stmt->execute([$actorId, $requestId]);
    $receipt = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$receipt) {
        return null;
    }
    if ((string)$receipt['ticket_id'] !== (string)$ticket['id']
        || !hash_equals((string)$receipt['request_digest'], $digest)) {
        throw new TicketActionError(409, 'Transfer request_id was already used with different parameters');
    }
    if ((int)($ticket['assigned_technician_id'] ?? 0) !== $targetId
        || (int)$ticket['transcode'] !== (int)$receipt['resulting_code']
        || !ticketIsActive((string)$ticket['repair_status'])
        || (int)($ticket['archived'] ?? 0) !== 0) {
        throw new TicketActionError(409, 'Transfer request is stale');
    }
    $result = json_decode($receipt['result_json'], true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($result) || ($result['success'] ?? false) !== true) {
        throw new LogicException('Invalid transfer receipt.');
    }
    unset($result['_previous_code']);
    return $result;
}

function ticketSaveTransferReceipt(
    PDO $pdo, array $ticket, int $actorId, string $digest, array $result, int $previousCode, ?string $requestId
): void {
    $receiptBody = $result;
    $receiptBody['_previous_code'] = $previousCode;
    $stmt = $pdo->prepare(
        'INSERT INTO fy_ticket_action_receipts '
        . '(ticket_id, actor_id, request_digest, resulting_code, result_json, created_at, request_id) '
        . 'VALUES (?, ?, ?, ?, ?, NOW(), ?)'
    );
    $stmt->execute([
        (string)$ticket['id'], $actorId, $digest, (int)$ticket['transcode'],
        json_encode($receiptBody, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $requestId,
    ]);
}

function ticketTransferDigest(string $ticketId, int $actorId, int $targetId, array $credential): string
{
    return hash('sha256', json_encode(
        ['give', $ticketId, $actorId, $targetId, $credential[0], $credential[1]],
        JSON_THROW_ON_ERROR
    ));
}

function ticketTransferReplay(PDO $pdo, array $ticket, int $actorId, int $targetId, string $digest): ?array
{
    if ((int)($ticket['assigned_technician_id'] ?? 0) !== $targetId) {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT resulting_code, result_json FROM fy_ticket_action_receipts '
        . 'WHERE ticket_id = ? AND actor_id = ? AND request_digest = ? '
        . 'AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([(string)$ticket['id'], $actorId, $digest]);
    $receipt = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$receipt || (int)$receipt['resulting_code'] !== (int)$ticket['transcode']) {
        return null;
    }
    $result = json_decode($receipt['result_json'], true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($result) || ($result['success'] ?? false) !== true) {
        return null;
    }
    unset($result['_previous_code']);
    return $result;
}

function ticketTransferResult(array $ticket, bool $wasAssigned): array
{
    $targetId = (int)$ticket['assigned_technician_id'];
    $time = $ticket['assigned_time'];
    // Keep both historic response shapes usable for all clients.
    return [
        'success' => true,
        'message' => $wasAssigned ? 'Order transferred successfully' : 'Order assigned successfully',
        'technician_id' => $targetId,
        'assigned_time' => $time,
        'new_technician_id' => $targetId,
        'new_assigned_time' => $time,
        'repair_status' => (string)$ticket['repair_status'],
    ];
}

/** A six-digit code must not recycle a recent capability or a queued job's version. */
function ticketFreshTransferCode(PDO $pdo, array $ticket): int
{
    $blocked = [(int)($ticket['transcode'] ?? 0) => true];
    $stmt = $pdo->prepare(
        "SELECT resulting_code, JSON_UNQUOTE(JSON_EXTRACT(result_json, '$._previous_code')) AS previous_code "
        . 'FROM fy_ticket_action_receipts WHERE ticket_id = ? '
        . 'AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)'
    );
    $stmt->execute([(string)$ticket['id']]);
    while ($receipt = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $blocked[(int)$receipt['resulting_code']] = true;
        $blocked[(int)($receipt['previous_code'] ?? 0)] = true;
    }
    $stmt = $pdo->prepare(
        "SELECT JSON_UNQUOTE(JSON_EXTRACT(payload, '$.expected_code')) FROM fy_ticket_notification_outbox "
        . "WHERE ticket_id = ? AND status IN ('pending', 'processing')"
    );
    $stmt->execute([(string)$ticket['id']]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $code) {
        $blocked[(int)$code] = true;
    }
    for ($attempt = 0; $attempt < 32; $attempt++) {
        $code = random_int(100000, 999999);
        if (!isset($blocked[$code])) {
            return $code;
        }
    }
    throw new TicketActionError(503, 'Transfer code generation is busy; please retry');
}

/** Called only with the ticket and all affected users already locked. */
function ticketTransferLocked(
    PDO $pdo, array &$ticket, array $users, int $targetId, int $actorId, string $requestDigest,
    ?string $requestId = null
): array
{
    if (!ticketIsActive((string)$ticket['repair_status']) || (int)($ticket['archived'] ?? 0) !== 0) {
        throw new TicketActionError(409, 'Order has closed');
    }
    if (($users[$targetId]['role'] ?? '') !== 'technician') {
        throw new TicketActionError(422, 'Target must be an existing technician');
    }
    if (!isset($users[(int)$ticket['user_id']])) {
        throw new TicketActionError(409, 'Ticket user no longer exists');
    }
    $oldTechId = (int)($ticket['assigned_technician_id'] ?? 0);
    if ($oldTechId === $targetId) {
        return ticketTransferResult($ticket, true);
    }
    $wasAssigned = $oldTechId > 0;
    $now = ticketNow($pdo);
    $previousCode = (int)($ticket['transcode'] ?? 0);
    $newCode = ticketFreshTransferCode($pdo, $ticket);
    ticketUpdateFields($pdo, $ticket, [
        'assigned_technician_id' => $targetId,
        'assigned_time' => $now,
        'repair_status' => 'Repairing',
        'transcode' => $newCode,
    ]);
    ticketRefreshCapacity($pdo, $users, [$oldTechId, $targetId]);
    $owner = $users[(int)$ticket['user_id']];
    $stmt = $pdo->prepare(
        'INSERT INTO fy_transfer_record (ticketid, time, type, fromuid, fromname, userid, username, tid, tname) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        (string)$ticket['id'], $now, $wasAssigned ? 'transfer' : 'assign',
        $wasAssigned ? $oldTechId : 100000,
        $wasAssigned ? (string)($users[$oldTechId]['nickname'] ?? '') : '系统',
        (int)$ticket['user_id'], (string)($owner['nickname'] ?? ''),
        $targetId, (string)$users[$targetId]['nickname'],
    ]);
    ticketQueue($pdo, $wasAssigned ? 'transfer' : 'assign', $ticket, $users);
    $result = ticketTransferResult($ticket, $wasAssigned);
    // Internal metadata is never returned to the HTTP caller.
    ticketSaveTransferReceipt($pdo, $ticket, $actorId, $requestDigest, $result, $previousCode, $requestId);
    return $result;
}

function ticketGive(PDO $pdo, array $actor, array $data): array
{
    $ticketId = ticketIdValue($data['order_id'] ?? null);
    $actorId = ticketActorId($actor);
    $targetId = array_key_exists('tid', $data) && $data['tid'] !== null && $data['tid'] !== ''
        ? ticketTechnicianId($data['tid']) : $actorId;
    if (!ticketIsAdmin($actor) && $targetId !== $actorId) {
        throw new TicketActionError(403, 'Only an administrator may assign another technician');
    }
    $credential = ticketCredential($data);
    $requestId = ticketRequestId($data);
    $digest = ticketTransferDigest($ticketId, $actorId, $targetId, $credential);

    return ticketTransaction($pdo, function () use ($pdo, $actor, $actorId, $targetId, $ticketId, $credential, $digest, $requestId) {
        $ticket = ticketLock($pdo, $ticketId);
        $users = ticketLockUsers($pdo, [
            $actorId, $targetId, $ticket['user_id'], $ticket['assigned_technician_id'] ?? 0,
        ]);
        if (!isset($users[$actorId])) {
            throw new TicketActionError(401, 'Unauthorized');
        }
        if (!ticketIsAdmin($actor) && ($users[$actorId]['role'] ?? '') !== 'technician') {
            throw new TicketActionError(403, 'Permission denied');
        }
        if ($requestId !== null) {
            $replayed = ticketRequestReplay($pdo, $ticket, $actorId, $targetId, $digest, $requestId);
            if ($replayed !== null) {
                return $replayed;
            }
        }
        if (!ticketIsActive((string)$ticket['repair_status']) || (int)($ticket['archived'] ?? 0) !== 0) {
            throw new TicketActionError(409, 'Order has closed');
        }
        if (($users[$targetId]['role'] ?? '') !== 'technician') {
            throw new TicketActionError(422, 'Target must be an existing technician');
        }
        $valid = $credential[0] === 'hash'
            ? hash_equals((string)($ticket['order_hash'] ?? ''), $credential[1])
            : hash_equals((string)($ticket['transcode'] ?? ''), $credential[1]);
        if (!$valid) {
            $replayed = ticketTransferReplay($pdo, $ticket, $actorId, $targetId, $digest);
            if ($replayed !== null) {
                if ($requestId !== null) {
                    ticketSaveTransferReceipt(
                        $pdo, $ticket, $actorId, $digest, $replayed,
                        $credential[0] === 'code' ? (int)$credential[1] : (int)$ticket['transcode'], $requestId
                    );
                }
                return $replayed;
            }
            throw new TicketActionError(
                400, $credential[0] === 'hash' ? 'Order hash mismatch' : 'Transfer vcode mismatch'
            );
        }
        if ((int)($ticket['assigned_technician_id'] ?? 0) === $targetId) {
            $result = ticketTransferResult($ticket, true);
            if ($requestId !== null) {
                ticketSaveTransferReceipt(
                    $pdo, $ticket, $actorId, $digest, $result, (int)$ticket['transcode'], $requestId
                );
            }
            return $result;
        }
        return ticketTransferLocked($pdo, $ticket, $users, $targetId, $actorId, $digest, $requestId);
    });
}

function ticketTextValue($value, string $field, int $maximum, bool $byteLimit = false): string
{
    if (!is_string($value) && !is_int($value)) {
        throw new TicketActionError(422, 'Invalid field: ' . $field);
    }
    $text = (string)$value;
    if (strlen($text) > ($byteLimit ? $maximum : $maximum * 4)
        || preg_match('//u', $text) !== 1) {
        throw new TicketActionError(422, 'Invalid or oversized field: ' . $field);
    }
    if (!$byteLimit && preg_match_all('/./us', $text) > $maximum) {
        throw new TicketActionError(422, 'Field too long: ' . $field);
    }
    return $text;
}

function ticketBasicChanges(array $ticket, array $actor, array $data): array
{
    $limits = [
        'user_phone' => 20, 'qq_number' => 20, 'device_type' => 50, 'model' => 255,
        'computer_brand' => 50, 'fault_type' => 50, 'campus' => 50,
        'repair_description' => 65535, 'repair_image_url' => 255,
        'user_nick' => 255, 'complete_image_url' => 255,
    ];
    $changes = [];
    foreach ($data as $key => $value) {
        if ($value === null || $key === 'tid' || $key === 'repair_status') {
            continue;
        }
        if ($key === 'assigned_technician_id') {
            if (!ticketIsAdmin($actor)
                && ticketTechnicianId($value) !== (int)($ticket['assigned_technician_id'] ?? 0)) {
                throw new TicketActionError(403, 'Only an administrator may change the assigned technician');
            }
            continue;
        }
        if (isset($limits[$key])) {
            $value = ticketTextValue($value, $key, $limits[$key], $key === 'repair_description');
        } elseif ($key === 'warranty_status') {
            if (!is_string($value) || !in_array($value, ['under', 'expired', 'unknown'], true)) {
                throw new TicketActionError(422, 'Invalid field: warranty_status');
            }
        } elseif ($key === 'DuoCampus') {
            if (!in_array($value, [0, 1, '0', '1', false, true], true)) {
                throw new TicketActionError(422, 'Invalid field: DuoCampus');
            }
            $value = (int)$value;
        } elseif ($key === 'machine_purchase_date') {
            if (!is_string($value) || !preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', $value, $parts)
                || !checkdate((int)$parts[2], (int)$parts[3], (int)$parts[1]) || (int)$parts[1] < 1000) {
                throw new TicketActionError(422, 'Invalid field: machine_purchase_date');
            }
        } elseif ($key === 'refused_times' && ticketIsAdmin($actor)) {
            if ((!is_int($value) && !is_string($value)) || !preg_match('/^[0-9]{1,10}$/D', (string)$value)
                || (int)$value > 2147483647) {
                throw new TicketActionError(422, 'Invalid field: refused_times');
            }
            $value = (int)$value;
        } else {
            // Older callers can submit a whole object. Ignore unchanged identity
            // fields, but never report success for attempted protected-field changes.
            if (array_key_exists($key, $ticket) && is_scalar($value)
                && (string)$ticket[$key] === (string)$value) {
                continue;
            }
            throw new TicketActionError(422, 'Field cannot be modified: ' . (string)$key);
        }
        if (!array_key_exists($key, $ticket)) {
            throw new TicketActionError(422, 'Unsupported field: ' . (string)$key);
        }
        if ($ticket[$key] === null || (string)$ticket[$key] !== (string)$value) {
            $changes[$key] = $value;
        }
    }
    return $changes;
}

function ticketRequestedStatus(array $data): ?string
{
    if (!array_key_exists('repair_status', $data) || $data['repair_status'] === null) {
        return null;
    }
    $status = $data['repair_status'];
    if (!is_string($status) || !in_array(
        $status, ['Pending', 'Repairing', 'UserConfirming', 'TechConfirming', 'Done', 'Canceled', 'Closed'], true
    )) {
        throw new TicketActionError(422, 'Invalid repair status');
    }
    return $status;
}

function ticketNextStatus(array $ticket, array $actor, array $users, ?string $requested): string
{
    $current = (string)$ticket['repair_status'];
    if ($requested === null) {
        return $current;
    }
    $actorId = ticketActorId($actor);
    if (in_array($requested, ['UserConfirming', 'TechConfirming'], true)) {
        if (!ticketIsAdmin($actor)) {
            $isTech = $actorId === (int)($ticket['assigned_technician_id'] ?? 0)
                && ($users[$actorId]['role'] ?? '') === 'technician';
            // Existing clients name states for the person who still needs to confirm.
            $expected = $isTech ? 'UserConfirming' : 'TechConfirming';
            if ($requested !== $expected) {
                throw new TicketActionError(403, 'Invalid confirmation role');
            }
        }
        if ($current === 'Done') {
            return 'Done';
        }
    }
    if ($requested === $current) {
        return $current;
    }
    if (in_array($current, ['Canceled', 'Closed'], true)
        && in_array($requested, ['Canceled', 'Closed'], true)) {
        return $current;
    }
    if (!ticketIsActive($current)) {
        throw new TicketActionError(409, 'Order has closed');
    }
    if (in_array($requested, ['Canceled', 'Closed'], true)) {
        return $requested;
    }
    if ($requested === 'Done') {
        if ($current === 'Pending') {
            throw new TicketActionError(409, 'An unassigned order cannot be completed');
        }
        return 'Done';
    }
    if (in_array($requested, ['UserConfirming', 'TechConfirming'], true)) {
        if ($current === 'Pending') {
            throw new TicketActionError(409, 'An unassigned order cannot be confirmed');
        }
        if (($current === 'UserConfirming' && $requested === 'TechConfirming')
            || ($current === 'TechConfirming' && $requested === 'UserConfirming')) {
            return 'Done';
        }
        return $requested;
    }
    throw new TicketActionError(409, 'Invalid status transition');
}

function ticketSet(PDO $pdo, array $actor, array $data, int $weeklyLimit = 5): array
{
    $ticketId = ticketIdValue($data['tid'] ?? null);
    $actorId = ticketActorId($actor);
    $requested = ticketRequestedStatus($data);
    $assignmentRequested = array_key_exists('assigned_technician_id', $data)
        && $data['assigned_technician_id'] !== null;
    $targetId = $assignmentRequested ? ticketTechnicianId($data['assigned_technician_id']) : null;
    $weeklyLimit = max(1, $weeklyLimit);

    return ticketTransaction($pdo, function () use (
        $pdo, $actor, $actorId, $data, $ticketId, $requested, $assignmentRequested, $targetId, $weeklyLimit
    ) {
        $ticket = ticketLock($pdo, $ticketId);
        $users = ticketLockUsers($pdo, [
            $actorId, $ticket['user_id'], $ticket['assigned_technician_id'] ?? 0, $targetId ?? 0,
        ]);
        ticketRequireAccess($actor, $ticket, $users);
        $changes = ticketBasicChanges($ticket, $actor, $data);
        $oldTechId = (int)($ticket['assigned_technician_id'] ?? 0);
        $oldStatus = (string)$ticket['repair_status'];

        if ((int)($ticket['archived'] ?? 0) !== 0 && ($changes || $requested !== null || $assignmentRequested)) {
            throw new TicketActionError(409, 'Archived orders cannot be modified through this endpoint');
        }
        if ($assignmentRequested && $targetId !== $oldTechId) {
            if ($requested !== null && $requested !== 'Repairing') {
                throw new TicketActionError(422, 'Reassignment cannot be combined with another status transition');
            }
            ticketUpdateFields($pdo, $ticket, $changes);
            $digest = hash('sha256', json_encode(['admin-set', $ticketId, $actorId, $targetId], JSON_THROW_ON_ERROR));
            $result = ticketTransferLocked($pdo, $ticket, $users, $targetId, $actorId, $digest);
            $changes['assigned_technician_id'] = $targetId;
            $changes['assigned_time'] = $ticket['assigned_time'];
            $changes['repair_status'] = $ticket['repair_status'];
            return array_merge($result, ['changedFields' => $changes, 'changed' => true]);
        }
        if ($assignmentRequested && ($users[$targetId]['role'] ?? '') !== 'technician') {
            throw new TicketActionError(422, 'Target must be an existing technician');
        }
        $newStatus = ticketNextStatus($ticket, $actor, $users, $requested);
        if ($newStatus !== $oldStatus) {
            $changes['repair_status'] = $newStatus;
            if ($newStatus === 'Done') {
                $changes['completion_time'] = ticketNow($pdo);
            }
        }
        $changed = count($changes) > 0;
        ticketUpdateFields($pdo, $ticket, $changes);
        if ($newStatus !== $oldStatus && in_array($newStatus, ['Canceled', 'Closed'], true)) {
            $stmt = $pdo->prepare(
                'UPDATE fy_users SET available = LEAST(GREATEST(COALESCE(available, 0), 0) + 1, ?) '
                . "WHERE id = ? AND role = 'user'"
            );
            $stmt->execute([$weeklyLimit, (int)$ticket['user_id']]);
            ticketRefreshCapacity($pdo, $users, [$oldTechId]);
            ticketQueue($pdo, 'close', $ticket, $users);
        } elseif ($newStatus !== $oldStatus && $newStatus === 'Done') {
            ticketRefreshCapacity($pdo, $users, [$oldTechId], true);
            ticketQueue($pdo, 'complete', $ticket, $users);
        }
        if ($requested !== null && $requested !== $newStatus) {
            $changes['repair_status'] = $newStatus;
        }
        return ['success' => true, 'changedFields' => $changes, 'repair_status' => $newStatus, 'changed' => $changed];
    });
}

function ticketComplete(PDO $pdo, array $actor, array $data): array
{
    $ticketId = ticketIdValue($data['order_id'] ?? null);
    $actorId = ticketActorId($actor);
    return ticketTransaction($pdo, function () use ($pdo, $actor, $actorId, $ticketId) {
        $ticket = ticketLock($pdo, $ticketId);
        $techId = (int)($ticket['assigned_technician_id'] ?? 0);
        $users = ticketLockUsers($pdo, [$actorId, $ticket['user_id'], $techId]);
        ticketRequireAccess($actor, $ticket, $users);
        if ((int)($ticket['archived'] ?? 0) !== 0) {
            throw new TicketActionError(409, 'Archived orders cannot be completed');
        }
        $status = (string)$ticket['repair_status'];
        if ($status === 'Done') {
            return ['success' => true, 'status' => 'ticket completed', 'repair_status' => 'Done'];
        }
        if (!in_array($status, ['Repairing', 'UserConfirming', 'TechConfirming'], true)) {
            throw new TicketActionError(409, 'Only an assigned active order can be completed');
        }
        ticketUpdateFields($pdo, $ticket, ['repair_status' => 'Done', 'completion_time' => ticketNow($pdo)]);
        ticketRefreshCapacity($pdo, $users, [$techId], true);
        ticketQueue($pdo, 'complete', $ticket, $users);
        return ['success' => true, 'status' => 'ticket completed', 'repair_status' => 'Done'];
    });
}
