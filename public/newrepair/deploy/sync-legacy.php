<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/domain.php';

$dryRun = in_array('--dry-run', $argv, true);
$pdo = fdx_db();
$pdo->exec("SET NAMES utf8mb4");

$summary = [
    'dry_run' => $dryRun,
    'activities_read' => 0,
    'activities_inserted' => 0,
    'activities_updated' => 0,
    'technicians_read' => 0,
    'technicians_inserted' => 0,
    'technicians_matched' => 0,
    'orders_read' => 0,
    'orders_inserted' => 0,
    'orders_updated' => 0,
    'assignments_synced' => 0,
    'repair_records_synced' => 0,
    'pickup_records_synced' => 0,
    'order_events_synced' => 0,
    'sms_logs_synced' => 0,
    'sequences_synced' => 0,
    'warnings' => [],
];

function legacy_text(mixed $value, int $limit, string $default = ''): string
{
    $text = trim((string) ($value ?? ''));
    if ($text === '') {
        $text = $default;
    }
    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, $limit, 'UTF-8');
    }
    return substr($text, 0, $limit);
}

function legacy_nullable_text(mixed $value, int $limit): ?string
{
    $text = trim((string) ($value ?? ''));
    if ($text === '') {
        return null;
    }
    return legacy_text($text, $limit);
}

function legacy_datetime(mixed $value, ?string $fallback = null): ?string
{
    $text = trim((string) ($value ?? ''));
    if ($text === '' || str_starts_with($text, '0000-00-00')) {
        return $fallback;
    }
    return str_replace('T', ' ', $text);
}

function legacy_phone(mixed $value): ?string
{
    $raw = trim((string) ($value ?? ''));
    if ($raw === '') {
        return null;
    }
    $digits = preg_replace('/\D+/', '', $raw);
    return $digits !== '' ? legacy_text($digits, 30) : legacy_text($raw, 30);
}

function legacy_json_services(mixed $value): ?string
{
    $raw = trim((string) ($value ?? ''));
    if ($raw === '') {
        return null;
    }
    $items = array_values(array_filter(array_map('trim', preg_split('/[,，]/u', $raw) ?: [])));
    return $items ? json_encode($items, JSON_UNESCAPED_UNICODE) : null;
}

function legacy_status(?string $status): string
{
    return match ($status) {
        'pending' => 'waiting_assignment',
        'processing' => 'repairing',
        'ready', 'sms_sent' => 'ready_for_pickup',
        'completed' => 'completed',
        default => 'waiting_assignment',
    };
}

function legacy_find_technician_by_phone(PDO $pdo, ?string $phone): ?int
{
    if ($phone === null || $phone === '') {
        return null;
    }
    $stmt = $pdo->prepare("
        SELECT id
        FROM fdx_technicians
        WHERE phone = :phone
        ORDER BY status = 'active' DESC, id
        LIMIT 1
    ");
    $stmt->execute([':phone' => $phone]);
    $id = $stmt->fetchColumn();
    return $id ? (int) $id : null;
}

function legacy_find_technician_by_name(PDO $pdo, string $name): ?int
{
    if ($name === '') {
        return null;
    }
    $stmt = $pdo->prepare("
        SELECT id
        FROM fdx_technicians
        WHERE display_name = :name
        ORDER BY status = 'active' DESC, id
        LIMIT 1
    ");
    $stmt->execute([':name' => $name]);
    $id = $stmt->fetchColumn();
    return $id ? (int) $id : null;
}

function legacy_find_or_create_technician(PDO $pdo, array $legacy, array &$summary): int
{
    $legacyId = (int) $legacy['id'];
    $fyUserId = (int) ($legacy['fy_userid'] ?? 0);
    $name = legacy_text($legacy['name'] ?? '', 80, '旧系统技术员');
    $phone = legacy_phone($legacy['phone'] ?? null);

    $checks = [];
    $checks[] = ['source_table' => 'fyd_technicians', 'source_user_id' => $legacyId];
    if ($fyUserId > 0) {
        $checks[] = ['source_table' => 'fy_users', 'source_user_id' => $fyUserId];
    }

    foreach ($checks as $check) {
        $stmt = $pdo->prepare("
            SELECT id
            FROM fdx_technicians
            WHERE source_table = :source_table AND source_user_id = :source_user_id
            LIMIT 1
        ");
        $stmt->execute($check);
        $id = $stmt->fetchColumn();
        if ($id) {
            $summary['technicians_matched']++;
            return (int) $id;
        }
    }

    $matchedId = legacy_find_technician_by_phone($pdo, $phone) ?? legacy_find_technician_by_name($pdo, $name);
    if ($matchedId !== null) {
        $summary['technicians_matched']++;
        return $matchedId;
    }

    $status = ($legacy['status'] ?? '') === 'offline' ? 'inactive' : 'active';
    $skillTags = legacy_nullable_text($legacy['specialty'] ?? null, 255);
    $stmt = $pdo->prepare("
        INSERT INTO fdx_technicians (
            source_table, source_user_id, display_name, phone, skill_tags, status, created_at, updated_at
        ) VALUES (
            'fyd_technicians', :source_user_id, :display_name, :phone, :skill_tags, :status, :created_at, :updated_at
        )
    ");
    $stmt->execute([
        ':source_user_id' => $legacyId,
        ':display_name' => $name,
        ':phone' => $phone,
        ':skill_tags' => $skillTags ? json_encode([$skillTags], JSON_UNESCAPED_UNICODE) : null,
        ':status' => $status,
        ':created_at' => legacy_datetime($legacy['created_at'] ?? null) ?? date('Y-m-d H:i:s'),
        ':updated_at' => legacy_datetime($legacy['updated_at'] ?? null) ?? date('Y-m-d H:i:s'),
    ]);
    $summary['technicians_inserted']++;
    return (int) $pdo->lastInsertId();
}

function legacy_find_or_create_order_technician(PDO $pdo, array $order, array &$summary): int
{
    $name = legacy_text($order['technician1_name'] ?? '', 80, '旧系统未匹配技术员');
    $phone = legacy_phone($order['technician1_phone'] ?? $order['technician1_contact'] ?? null);
    $matchedId = legacy_find_technician_by_phone($pdo, $phone) ?? legacy_find_technician_by_name($pdo, $name);
    if ($matchedId !== null) {
        return $matchedId;
    }

    $stmt = $pdo->prepare("
        INSERT INTO fdx_technicians (
            source_table, source_user_id, display_name, phone, status, created_at, updated_at
        ) VALUES (
            'fyd_order_technician', :source_user_id, :display_name, :phone, 'inactive', NOW(), NOW()
        )
        ON DUPLICATE KEY UPDATE
            display_name = VALUES(display_name),
            phone = VALUES(phone),
            updated_at = NOW()
    ");
    $stmt->execute([
        ':source_user_id' => (int) $order['id'],
        ':display_name' => $name,
        ':phone' => $phone,
    ]);
    $id = (int) $pdo->lastInsertId();
    if ($id === 0) {
        $stmt = $pdo->prepare("
            SELECT id
            FROM fdx_technicians
            WHERE source_table = 'fyd_order_technician' AND source_user_id = :source_user_id
            LIMIT 1
        ");
        $stmt->execute([':source_user_id' => (int) $order['id']]);
        $id = (int) $stmt->fetchColumn();
    } else {
        $summary['technicians_inserted']++;
    }
    return $id;
}

function legacy_fetch_new_order_id(PDO $pdo, int $activityId, string $orderNo): int
{
    $stmt = $pdo->prepare("
        SELECT id
        FROM fdx_orders
        WHERE activity_id = :activity_id AND order_no = :order_no
        LIMIT 1
    ");
    $stmt->execute([
        ':activity_id' => $activityId,
        ':order_no' => $orderNo,
    ]);
    return (int) $stmt->fetchColumn();
}

$pdo->beginTransaction();

try {
    $activities = $pdo->query("SELECT * FROM fyd_activities ORDER BY id")->fetchAll();
    $summary['activities_read'] = count($activities);
    $activityMap = [];
    $currentActivityIds = [];

    foreach ($activities as $activity) {
        $name = legacy_text($activity['activity_name'] ?? '', 120, '旧系统活动');
        $date = (string) ($activity['activity_date'] ?? date('Y-m-d'));
        $status = (int) ($activity['is_current'] ?? 0) === 1 ? 'active' : 'closed';

        $stmt = $pdo->prepare("
            SELECT id
            FROM fdx_activities
            WHERE name = :name AND activity_date = :activity_date
            ORDER BY id
            LIMIT 1
        ");
        $stmt->execute([
            ':name' => $name,
            ':activity_date' => $date,
        ]);
        $newId = (int) $stmt->fetchColumn();

        if ($newId > 0) {
            $stmt = $pdo->prepare("
                UPDATE fdx_activities
                SET notes = :notes,
                    status = :status,
                    is_current = :is_current,
                    updated_at = :updated_at
                WHERE id = :id
            ");
            $stmt->execute([
                ':notes' => legacy_nullable_text($activity['description'] ?? null, 65535),
                ':status' => $status,
                ':is_current' => (int) ($activity['is_current'] ?? 0),
                ':updated_at' => legacy_datetime($activity['updated_at'] ?? null) ?? date('Y-m-d H:i:s'),
                ':id' => $newId,
            ]);
            $summary['activities_updated']++;
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO fdx_activities (
                    name, activity_date, status, is_current, notes, created_at, updated_at
                ) VALUES (
                    :name, :activity_date, :status, :is_current, :notes, :created_at, :updated_at
                )
            ");
            $stmt->execute([
                ':name' => $name,
                ':activity_date' => $date,
                ':status' => $status,
                ':is_current' => (int) ($activity['is_current'] ?? 0),
                ':notes' => legacy_nullable_text($activity['description'] ?? null, 65535),
                ':created_at' => legacy_datetime($activity['created_at'] ?? null) ?? date('Y-m-d H:i:s'),
                ':updated_at' => legacy_datetime($activity['updated_at'] ?? null) ?? date('Y-m-d H:i:s'),
            ]);
            $newId = (int) $pdo->lastInsertId();
            $summary['activities_inserted']++;
        }

        $activityMap[(int) $activity['id']] = $newId;
        if ((int) ($activity['is_current'] ?? 0) === 1) {
            $currentActivityIds[] = $newId;
        }
    }

    if ($currentActivityIds) {
        $pdo->exec("UPDATE fdx_activities SET is_current = 0");
        $stmt = $pdo->prepare("UPDATE fdx_activities SET is_current = 1, status = 'active' WHERE id = :id");
        foreach ($currentActivityIds as $id) {
            $stmt->execute([':id' => $id]);
        }
    }

    $legacyTechnicians = $pdo->query("SELECT * FROM fyd_technicians ORDER BY id")->fetchAll();
    $summary['technicians_read'] = count($legacyTechnicians);
    $technicianMap = [];
    foreach ($legacyTechnicians as $legacyTechnician) {
        $technicianMap[(int) $legacyTechnician['id']] = legacy_find_or_create_technician($pdo, $legacyTechnician, $summary);
    }

    $orders = $pdo->query("SELECT * FROM fyd_orders ORDER BY activity_id, CAST(order_number AS UNSIGNED), id")->fetchAll();
    $summary['orders_read'] = count($orders);
    $orderMap = [];

    foreach ($orders as $order) {
        $legacyActivityId = (int) $order['activity_id'];
        if (!isset($activityMap[$legacyActivityId])) {
            $summary['warnings'][] = "订单 {$order['id']} 找不到旧活动 {$legacyActivityId}";
            continue;
        }

        $activityId = $activityMap[$legacyActivityId];
        $orderNo = legacy_text($order['order_number'] ?? '', 20, (string) $order['id']);
        $existingId = legacy_fetch_new_order_id($pdo, $activityId, $orderNo);
        $phone = legacy_phone($order['customer_phone'] ?? null) ?? legacy_text($order['customer_phone'] ?? '', 30, '未填写');
        $stationNo = (string) ((int) ($order['position'] ?? 1) === 1 ? '1' : '4');
        $status = legacy_status($order['status'] ?? null);
        $completedAt = $status === 'completed'
            ? legacy_datetime($order['completion_time'] ?? null, legacy_datetime($order['updated_at'] ?? null))
            : null;

        $stmt = $pdo->prepare("
            INSERT INTO fdx_orders (
                activity_id, order_no, station_no, status,
                customer_name, customer_phone, customer_phone_mask, backup_phone, customer_qq,
                gender, college, dormitory, student_id,
                device_type, device_model, boot_password_cipher,
                accessories, existing_damage, confirmed_out_of_warranty, service_types,
                allow_reinstall, allow_format, important_data,
                problem_description, repair_notes, notes, intake_notes,
                created_at, updated_at, completed_at
            ) VALUES (
                :activity_id, :order_no, :station_no, :status,
                :customer_name, :customer_phone, :customer_phone_mask, :backup_phone, :customer_qq,
                :gender, :college, :dormitory, :student_id,
                :device_type, :device_model, :boot_password_cipher,
                :accessories, :existing_damage, :confirmed_out_of_warranty, :service_types,
                :allow_reinstall, :allow_format, :important_data,
                :problem_description, :repair_notes, :notes, :intake_notes,
                :created_at, :updated_at, :completed_at
            )
            ON DUPLICATE KEY UPDATE
                station_no = VALUES(station_no),
                status = VALUES(status),
                customer_name = VALUES(customer_name),
                customer_phone = VALUES(customer_phone),
                customer_phone_mask = VALUES(customer_phone_mask),
                backup_phone = VALUES(backup_phone),
                customer_qq = VALUES(customer_qq),
                gender = VALUES(gender),
                college = VALUES(college),
                dormitory = VALUES(dormitory),
                student_id = VALUES(student_id),
                device_type = VALUES(device_type),
                device_model = VALUES(device_model),
                boot_password_cipher = VALUES(boot_password_cipher),
                accessories = VALUES(accessories),
                existing_damage = VALUES(existing_damage),
                confirmed_out_of_warranty = VALUES(confirmed_out_of_warranty),
                service_types = VALUES(service_types),
                allow_reinstall = VALUES(allow_reinstall),
                allow_format = VALUES(allow_format),
                important_data = VALUES(important_data),
                problem_description = VALUES(problem_description),
                repair_notes = VALUES(repair_notes),
                notes = VALUES(notes),
                intake_notes = VALUES(intake_notes),
                created_at = VALUES(created_at),
                updated_at = VALUES(updated_at),
                completed_at = VALUES(completed_at)
        ");
        $stmt->execute([
            ':activity_id' => $activityId,
            ':order_no' => $orderNo,
            ':station_no' => $stationNo,
            ':status' => $status,
            ':customer_name' => legacy_text($order['customer_name'] ?? '', 80, '未填写'),
            ':customer_phone' => $phone,
            ':customer_phone_mask' => fdx_mask_phone($phone),
            ':backup_phone' => legacy_phone($order['backup_phone'] ?? null),
            ':customer_qq' => legacy_nullable_text($order['customer_qq'] ?? null, 30),
            ':gender' => legacy_nullable_text($order['gender'] ?? null, 20),
            ':college' => legacy_nullable_text($order['college'] ?? null, 120),
            ':dormitory' => legacy_nullable_text($order['dormitory'] ?? null, 120),
            ':student_id' => legacy_nullable_text($order['student_id'] ?? null, 60),
            ':device_type' => legacy_text($order['device_type'] ?? '', 60, '未填写'),
            ':device_model' => legacy_nullable_text($order['device_model'] ?? null, 120),
            ':boot_password_cipher' => fdx_encrypt_secret(legacy_nullable_text($order['login_password'] ?? null, 100)),
            ':accessories' => legacy_nullable_text($order['accessories'] ?? null, 65535),
            ':existing_damage' => legacy_nullable_text($order['existing_damage'] ?? null, 65535),
            ':confirmed_out_of_warranty' => 1,
            ':service_types' => legacy_json_services($order['service_type'] ?? null),
            ':allow_reinstall' => fdx_json_bool($order['allow_system_reinstall'] ?? null),
            ':allow_format' => fdx_json_bool($order['allow_disk_format'] ?? null),
            ':important_data' => legacy_nullable_text($order['important_data'] ?? null, 65535),
            ':problem_description' => legacy_text($order['problem_description'] ?? '', 65535, '未填写'),
            ':repair_notes' => legacy_nullable_text($order['repair_notes'] ?? null, 65535),
            ':notes' => legacy_nullable_text($order['notes'] ?? null, 65535),
            ':intake_notes' => legacy_nullable_text($order['notes'] ?? null, 65535),
            ':created_at' => legacy_datetime($order['created_at'] ?? null) ?? date('Y-m-d H:i:s'),
            ':updated_at' => legacy_datetime($order['updated_at'] ?? null) ?? date('Y-m-d H:i:s'),
            ':completed_at' => $completedAt,
        ]);

        $newOrderId = legacy_fetch_new_order_id($pdo, $activityId, $orderNo);
        $orderMap[(int) $order['id']] = $newOrderId;
        if ($existingId > 0) {
            $summary['orders_updated']++;
        } else {
            $summary['orders_inserted']++;
        }

        $pdo->prepare("DELETE FROM fdx_order_assignments WHERE order_id = :order_id AND notes = '旧系统同步'")
            ->execute([':order_id' => $newOrderId]);
        $pdo->prepare("DELETE FROM fdx_repair_records WHERE order_id = :order_id AND notes = '旧系统同步'")
            ->execute([':order_id' => $newOrderId]);

        $legacyTechnicianId = (int) ($order['technician_id'] ?? 0);
        $technicianId = $legacyTechnicianId > 0 && isset($technicianMap[$legacyTechnicianId])
            ? $technicianMap[$legacyTechnicianId]
            : null;
        if ($technicianId === null && trim((string) ($order['technician1_name'] ?? '')) !== '') {
            $technicianId = legacy_find_or_create_order_technician($pdo, $order, $summary);
        }

        if ($technicianId !== null) {
            $assignmentStatus = in_array($status, ['completed', 'cancelled'], true) ? 'closed' : 'active';
            $assignedAt = legacy_datetime($order['technician1_time'] ?? null)
                ?? legacy_datetime($order['updated_at'] ?? null)
                ?? legacy_datetime($order['created_at'] ?? null)
                ?? date('Y-m-d H:i:s');
            $pdo->prepare("
                INSERT INTO fdx_order_assignments (
                    order_id, technician_id, assigned_by, status, assigned_at, accepted_at, released_at, notes
                ) VALUES (
                    :order_id, :technician_id, NULL, :status, :assigned_at, :accepted_at, :released_at, '旧系统同步'
                )
            ")->execute([
                ':order_id' => $newOrderId,
                ':technician_id' => $technicianId,
                ':status' => $assignmentStatus,
                ':assigned_at' => $assignedAt,
                ':accepted_at' => $status === 'waiting_assignment' ? null : $assignedAt,
                ':released_at' => $assignmentStatus === 'closed' ? ($completedAt ?? legacy_datetime($order['updated_at'] ?? null)) : null,
            ]);
            $summary['assignments_synced']++;
        }

        $diagnosis = legacy_nullable_text($order['diagnosis'] ?? null, 65535);
        $solution = legacy_nullable_text($order['solution'] ?? null, 65535);
        if (($diagnosis !== null || $solution !== null) && $technicianId === null) {
            $technicianId = legacy_find_or_create_order_technician($pdo, $order, $summary);
        }
        if (($diagnosis !== null || $solution !== null) && $technicianId !== null) {
            $submittedAt = legacy_datetime($order['technician1_time'] ?? null)
                ?? legacy_datetime($order['updated_at'] ?? null)
                ?? legacy_datetime($order['created_at'] ?? null)
                ?? date('Y-m-d H:i:s');
            $pdo->prepare("
                INSERT INTO fdx_repair_records (
                    order_id, technician_id, diagnosis, solution, result, notes, technician_signature, submitted_at
                ) VALUES (
                    :order_id, :technician_id, :diagnosis, :solution, 'fixed', '旧系统同步', :technician_signature, :submitted_at
                )
            ")->execute([
                ':order_id' => $newOrderId,
                ':technician_id' => $technicianId,
                ':diagnosis' => $diagnosis ?? '旧系统未填写',
                ':solution' => $solution ?? '旧系统未填写',
                ':technician_signature' => legacy_nullable_text($order['technician_signature'] ?? null, 65535),
                ':submitted_at' => $submittedAt,
            ]);
            $summary['repair_records_synced']++;
        }

        $pickupTime = legacy_datetime($order['pickup_time'] ?? null, $completedAt);
        $customerSignature = legacy_nullable_text($order['customer_signature'] ?? null, 65535);
        if (($status === 'completed' || $pickupTime !== null || $customerSignature !== null) && $pickupTime !== null) {
            $existingPickup = $pdo->prepare("SELECT id, notes FROM fdx_pickup_records WHERE order_id = :order_id LIMIT 1");
            $existingPickup->execute([':order_id' => $newOrderId]);
            $pickup = $existingPickup->fetch();
            if (!$pickup) {
                $pdo->prepare("
                    INSERT INTO fdx_pickup_records (order_id, handled_by, pickup_time, customer_signature, notes)
                    VALUES (:order_id, NULL, :pickup_time, :customer_signature, '旧系统同步')
                ")->execute([
                    ':order_id' => $newOrderId,
                    ':pickup_time' => $pickupTime,
                    ':customer_signature' => $customerSignature,
                ]);
                $summary['pickup_records_synced']++;
            } elseif (($pickup['notes'] ?? '') === '旧系统同步') {
                $pdo->prepare("
                    UPDATE fdx_pickup_records
                    SET pickup_time = :pickup_time,
                        customer_signature = :customer_signature
                    WHERE id = :id
                ")->execute([
                    ':pickup_time' => $pickupTime,
                    ':customer_signature' => $customerSignature,
                    ':id' => $pickup['id'],
                ]);
                $summary['pickup_records_synced']++;
            }
        }
    }

    foreach (array_unique($activityMap) as $activityId) {
        foreach (['1', '4'] as $stationNo) {
            $stmt = $pdo->prepare("
                SELECT MAX(CAST(order_no AS UNSIGNED))
                FROM fdx_orders
                WHERE activity_id = :activity_id AND station_no = :station_no
            ");
            $stmt->execute([
                ':activity_id' => $activityId,
                ':station_no' => $stationNo,
            ]);
            $maxNumber = (int) ($stmt->fetchColumn() ?: 0);
            $nextNumber = $maxNumber > 0 ? $maxNumber + 2 : ($stationNo === '1' ? 1 : 2);
            $pdo->prepare("
                INSERT INTO fdx_order_sequences (activity_id, station_no, next_number)
                VALUES (:activity_id, :station_no, :next_number)
                ON DUPLICATE KEY UPDATE next_number = VALUES(next_number)
            ")->execute([
                ':activity_id' => $activityId,
                ':station_no' => $stationNo,
                ':next_number' => $nextNumber,
            ]);
            $summary['sequences_synced']++;
        }
    }

    foreach ($orderMap as $newOrderId) {
        $pdo->prepare("
            DELETE FROM fdx_order_events
            WHERE order_id = :order_id
              AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.legacy_source')) = 'fyd_order_logs'
        ")->execute([':order_id' => $newOrderId]);
        $pdo->prepare("
            DELETE FROM fdx_sms_logs
            WHERE order_id = :order_id
              AND JSON_UNQUOTE(JSON_EXTRACT(provider_response, '$.legacy_source')) = 'fyd_sms_logs'
        ")->execute([':order_id' => $newOrderId]);
    }

    $logs = $pdo->query("SELECT * FROM fyd_order_logs ORDER BY id")->fetchAll();
    foreach ($logs as $log) {
        $oldOrderId = (int) $log['order_id'];
        if (!isset($orderMap[$oldOrderId])) {
            continue;
        }
        $oldTechnicianId = (int) ($log['technician_id'] ?? 0);
        $actorId = $oldTechnicianId > 0 && isset($technicianMap[$oldTechnicianId]) ? $technicianMap[$oldTechnicianId] : null;
        $pdo->prepare("
            INSERT INTO fdx_order_events (
                order_id, actor_type, actor_id, event_type, from_status, to_status, payload, created_at
            ) VALUES (
                :order_id, :actor_type, :actor_id, :event_type, :from_status, :to_status, :payload, :created_at
            )
        ")->execute([
            ':order_id' => $orderMap[$oldOrderId],
            ':actor_type' => $actorId ? 'technician' : 'system',
            ':actor_id' => $actorId,
            ':event_type' => legacy_text($log['action'] ?? 'legacy_log', 80, 'legacy_log'),
            ':from_status' => $log['old_status'] ? legacy_status((string) $log['old_status']) : null,
            ':to_status' => $log['new_status'] ? legacy_status((string) $log['new_status']) : null,
            ':payload' => json_encode([
                'legacy_source' => 'fyd_order_logs',
                'legacy_id' => (int) $log['id'],
                'legacy_action' => $log['action'] ?? null,
                'notes' => $log['notes'] ?? null,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':created_at' => legacy_datetime($log['created_at'] ?? null) ?? date('Y-m-d H:i:s'),
        ]);
        $summary['order_events_synced']++;
    }

    $smsLogs = $pdo->query("SELECT * FROM fyd_sms_logs ORDER BY id")->fetchAll();
    foreach ($smsLogs as $sms) {
        $oldOrderId = (int) $sms['order_id'];
        if (!isset($orderMap[$oldOrderId])) {
            continue;
        }
        $status = match ($sms['status'] ?? '') {
            'sent' => 'manual_sent',
            'failed' => 'failed',
            default => 'draft',
        };
        $pdo->prepare("
            INSERT INTO fdx_sms_logs (
                order_id, phone_mask, content, status, sent_at, provider_response, error_message, created_at
            ) VALUES (
                :order_id, :phone_mask, :content, :status, :sent_at, :provider_response, :error_message, :created_at
            )
        ")->execute([
            ':order_id' => $orderMap[$oldOrderId],
            ':phone_mask' => fdx_mask_phone((string) ($sms['phone'] ?? '')),
            ':content' => legacy_text($sms['content'] ?? '', 65535, '旧系统短信记录'),
            ':status' => $status,
            ':sent_at' => legacy_datetime($sms['sent_at'] ?? null),
            ':provider_response' => json_encode([
                'legacy_source' => 'fyd_sms_logs',
                'legacy_id' => (int) $sms['id'],
                'template_type' => $sms['template_type'] ?? null,
                'response_data' => $sms['response_data'] ?? null,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':error_message' => legacy_nullable_text($sms['error_message'] ?? null, 65535),
            ':created_at' => legacy_datetime($sms['created_at'] ?? null) ?? date('Y-m-d H:i:s'),
        ]);
        $summary['sms_logs_synced']++;
    }

    if ($dryRun) {
        $pdo->rollBack();
    } else {
        $pdo->commit();
    }

    echo json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
