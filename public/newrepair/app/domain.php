<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function fdx_event(
    int $orderId,
    string $actorType,
    ?int $actorId,
    string $eventType,
    ?string $fromStatus = null,
    ?string $toStatus = null,
    array $payload = []
): void {
    $stmt = fdx_db()->prepare("
        INSERT INTO fdx_order_events (
            order_id, actor_type, actor_id, event_type,
            from_status, to_status, payload, created_at
        ) VALUES (
            :order_id, :actor_type, :actor_id, :event_type,
            :from_status, :to_status, :payload, NOW()
        )
    ");
    $stmt->execute([
        ':order_id' => $orderId,
        ':actor_type' => $actorType,
        ':actor_id' => $actorId,
        ':event_type' => $eventType,
        ':from_status' => $fromStatus,
        ':to_status' => $toStatus,
        ':payload' => $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
    ]);
}

function fdx_active_activity(PDO $pdo): ?array
{
    $stmt = $pdo->query("
        SELECT *
        FROM fdx_activities
        WHERE is_current = 1 AND status = 'active'
        ORDER BY activity_date DESC, id DESC
        LIMIT 1
    ");
    $activity = $stmt->fetch();
    return $activity ?: null;
}

function fdx_station_from_input(string|int|null $station): string
{
    $value = (string) $station;
    if ($value === '1') {
        return '1';
    }
    if ($value === '4' || $value === '2') {
        return '4';
    }
    fdx_json(false, null, '录入位置只能是 1 号位或 4 号位', 422);
}

function fdx_next_order_no(PDO $pdo, int $activityId, string $stationNo): string
{
    $initial = $stationNo === '1' ? 1 : 2;

    $insert = $pdo->prepare("
        INSERT IGNORE INTO fdx_order_sequences (activity_id, station_no, next_number)
        VALUES (:activity_id, :station_no, :next_number)
    ");
    $insert->execute([
        ':activity_id' => $activityId,
        ':station_no' => $stationNo,
        ':next_number' => $initial,
    ]);

    $stmt = $pdo->prepare("
        SELECT next_number
        FROM fdx_order_sequences
        WHERE activity_id = :activity_id AND station_no = :station_no
        FOR UPDATE
    ");
    $stmt->execute([
        ':activity_id' => $activityId,
        ':station_no' => $stationNo,
    ]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('无法生成订单编号');
    }

    $number = (int) $row['next_number'];
    $pdo->prepare("
        UPDATE fdx_order_sequences
        SET next_number = :next_number
        WHERE activity_id = :activity_id AND station_no = :station_no
    ")->execute([
        ':next_number' => $number + 2,
        ':activity_id' => $activityId,
        ':station_no' => $stationNo,
    ]);

    return str_pad((string) $number, 4, '0', STR_PAD_LEFT);
}

function fdx_json_bool(mixed $value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }
    if (in_array($value, [true, 1, '1', 'true', '是', 'yes'], true)) {
        return 1;
    }
    if (in_array($value, [false, 0, '0', 'false', '否', 'no'], true)) {
        return 0;
    }
    return null;
}

function fdx_order_select_sql(): string
{
    return "
        SELECT
            o.*,
            act.name AS activity_name,
            act.activity_date,
            act.location AS activity_location,
            ta.technician_id,
            tech.display_name AS technician_name
        FROM fdx_orders o
        INNER JOIN fdx_activities act ON act.id = o.activity_id
        LEFT JOIN fdx_order_assignments ta ON ta.id = (
            SELECT a.id
            FROM fdx_order_assignments a
            WHERE a.order_id = o.id
            ORDER BY (a.status = 'active') DESC, a.assigned_at DESC, a.id DESC
            LIMIT 1
        )
        LEFT JOIN fdx_technicians tech ON tech.id = ta.technician_id
    ";
}

function fdx_find_order_for_update(PDO $pdo, int $orderId): array
{
    $stmt = $pdo->prepare("SELECT * FROM fdx_orders WHERE id = :id FOR UPDATE");
    $stmt->execute([':id' => $orderId]);
    $order = $stmt->fetch();
    if (!$order) {
        throw new RuntimeException('订单不存在');
    }
    return $order;
}

function fdx_technician_owns_order(PDO $pdo, int $technicianId, int $orderId): bool
{
    $stmt = $pdo->prepare("
        SELECT 1
        FROM fdx_order_assignments
        WHERE order_id = :order_id
          AND technician_id = :technician_id
          AND status = 'active'
        LIMIT 1
    ");
    $stmt->execute([
        ':order_id' => $orderId,
        ':technician_id' => $technicianId,
    ]);
    return (bool) $stmt->fetchColumn();
}
